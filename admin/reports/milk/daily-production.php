<?php
// admin/reports/milk/daily-production.php
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Daily Milk Production Report";

// Date filters with validation
$errors = [];
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$cattle_filter = isset($_GET['cattle_id']) ? trim($_GET['cattle_id']) : '';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Validate dates if form is submitted
if (isset($_GET['date_from']) || isset($_GET['date_to'])) {
    $today = date('Y-m-d');
    
    // Check if dates are empty
    if (empty($date_from)) {
        $errors['date_from'] = 'From date is required';
    }
    if (empty($date_to)) {
        $errors['date_to'] = 'To date is required';
    }
    
    // Check if dates are valid format
    if (!empty($date_from) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $errors['date_from'] = 'Invalid date format';
    }
    if (!empty($date_to) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $errors['date_to'] = 'Invalid date format';
    }
    
    // Check if dates are in the future
    if (!empty($date_from) && $date_from > $today) {
        $errors['date_from'] = 'From date cannot be in the future';
    }
    if (!empty($date_to) && $date_to > $today) {
        $errors['date_to'] = 'To date cannot be in the future';
    }
    
    // Check if from date is after to date
    if (empty($errors) && !empty($date_from) && !empty($date_to) && $date_from > $date_to) {
        $errors['date_range'] = 'From date cannot be later than To date';
    }
    
    // Check if date range is too large
    if (empty($errors) && !empty($date_from) && !empty($date_to)) {
        $date1 = new DateTime($date_from);
        $date2 = new DateTime($date_to);
        $diff = $date1->diff($date2);
        if ($diff->days > 365) {
            $errors['date_range'] = 'Date range cannot exceed 365 days';
        }
    }
}

// Fetch all cattle for dropdown
$cattle_sql = "SELECT cattle_id, tag_id FROM cattle WHERE life_status IN ('Alive', 'Pregnant') AND gender = 'Female' ORDER BY tag_id";
$cattle_result = $conn->query($cattle_sql);

// Only fetch data if no validation errors
$result = null;
$totals = ['total_days' => 0, 'total_milk' => 0, 'avg_daily' => 0, 'total_collections' => 0];
$total_records = 0;

if (empty($errors)) {
    if (!empty($cattle_filter)) {
        // Individual cattle records from milk_collection table
        $where_conditions = ["mc.collection_date BETWEEN ? AND ?", "mc.cattle_id = ?"];
        $params = [$date_from, $date_to, $cattle_filter];
        $param_types = "ssi";
        
        $where_clause = implode(" AND ", $where_conditions);
        
        // Count total records
        $count_sql = "SELECT COUNT(*) as total 
                      FROM milk_collection mc 
                      WHERE $where_clause";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $records_per_page);
        
        // Fetch paginated data
        $sql = "SELECT 
                    mc.collection_date,
                    mc.shift,
                    mc.quantity as total_milk,
                    c.tag_id,
                    ct.type_name,
                    b.breed_name
                FROM milk_collection mc
                JOIN cattle c ON mc.cattle_id = c.cattle_id
                JOIN cattle_type ct ON c.type_id = ct.type_id
                JOIN breed b ON c.breed_id = b.breed_id
                WHERE $where_clause
                ORDER BY mc.collection_date DESC, mc.shift
                LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($sql);
        $params[] = $records_per_page;
        $params[] = $offset;
        $param_types .= "ii";
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        // Calculate totals
        $totals_sql = "SELECT 
                        COUNT(DISTINCT mc.collection_date) as total_days,
                        SUM(mc.quantity) as total_milk,
                        AVG(mc.quantity) as avg_daily,
                        COUNT(mc.milk_id) as total_collections
                       FROM milk_collection mc
                       WHERE $where_clause";
        
        $totals_stmt = $conn->prepare($totals_sql);
        $totals_params = array_slice($params, 0, -2);
        $totals_types = substr($param_types, 0, -2);
        $totals_stmt->bind_param($totals_types, ...$totals_params);
        $totals_stmt->execute();
        $totals = $totals_stmt->get_result()->fetch_assoc();
        
    } else {
        // Aggregated view data
        $where_conditions = ["collection_date BETWEEN ? AND ?"];
        $params = [$date_from, $date_to];
        $param_types = "ss";
        
        $where_clause = implode(" AND ", $where_conditions);
        
        // Count total records
        $count_sql = "SELECT COUNT(*) as total FROM daily_milk_production WHERE $where_clause";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $records_per_page);
        
        // Fetch paginated data
        $sql = "SELECT * FROM daily_milk_production 
                WHERE $where_clause
                ORDER BY collection_date DESC, shift, type_name
                LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($sql);
        $params[] = $records_per_page;
        $params[] = $offset;
        $param_types .= "ii";
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        // Calculate totals
        $totals_sql = "SELECT 
                        COUNT(DISTINCT collection_date) as total_days,
                        SUM(total_milk) as total_milk,
                        AVG(total_milk) as avg_daily,
                        SUM(cattle_count) as total_collections
                       FROM daily_milk_production
                       WHERE $where_clause";
        
        $totals_stmt = $conn->prepare($totals_sql);
        $totals_params = array_slice($params, 0, -2);
        $totals_types = substr($param_types, 0, -2);
        $totals_stmt->bind_param($totals_types, ...$totals_params);
        $totals_stmt->execute();
        $totals = $totals_stmt->get_result()->fetch_assoc();
    }
    
    // Default to 0 if null
    $totals['total_days'] = $totals['total_days'] ?? 0;
    $totals['total_milk'] = $totals['total_milk'] ?? 0;
    $totals['avg_daily'] = $totals['avg_daily'] ?? 0;
    $totals['total_collections'] = $totals['total_collections'] ?? 0;
}

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>📅 Daily Milk Production</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Daily Production</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="../reports-dashboard.php" class="btn btn-primary">← Back</a>
            <!-- <button onclick="exportPDF()" class="btn btn-info no-print">📄 Export PDF</button> -->
        </div>
    </div>

    <!-- Date & Cattle Filter -->
    <div class="card no-print">
        <div class="card-body">
            <?php if (!empty($errors['date_range'])): ?>
                <div class="alert alert-error" style="margin-bottom: 1rem;">
                    <span class="alert-icon">✕</span>
                    <span><?php echo $errors['date_range']; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="GET" class="filter-form" id="dateFilterForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date <span style="color: red;">*</span></label>
                        <input 
                            type="date" 
                            name="date_from" 
                            id="date_from"
                            class="form-control" 
                            value="<?php echo htmlspecialchars($date_from); ?>" 
                            max="<?php echo date('Y-m-d'); ?>"
                            required
                        >
                        <?php if (isset($errors['date_from'])): ?>
                            <span class="error-msg" style="color: #dc3545; font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                                <?php echo $errors['date_from']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date <span style="color: red;">*</span></label>
                        <input 
                            type="date" 
                            name="date_to" 
                            id="date_to"
                            class="form-control" 
                            value="<?php echo htmlspecialchars($date_to); ?>" 
                            max="<?php echo date('Y-m-d'); ?>"
                            required
                        >
                        <?php if (isset($errors['date_to'])): ?>
                            <span class="error-msg" style="color: #dc3545; font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                                <?php echo $errors['date_to']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Filter by Cattle</label>
                        <select name="cattle_id" class="form-control">
                            <option value="">All Cattle</option>
                            <?php while ($cattle = $cattle_result->fetch_assoc()): ?>
                                <option value="<?php echo $cattle['cattle_id']; ?>" 
                                    <?php echo $cattle_filter == $cattle['cattle_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cattle['tag_id']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <a href="daily-production.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">📅</div>
            <div class="stat-details">
                <span class="stat-label">Total Days</span>
                <span class="stat-value"><?php echo $totals['total_days']; ?></span>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Total Milk</span>
                <span class="stat-value"><?php echo number_format($totals['total_milk'], 2); ?> L</span>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">📊</div>
            <div class="stat-details">
                <span class="stat-label">Daily Average</span>
                <span class="stat-value"><?php echo number_format($totals['avg_daily'], 2); ?> L</span>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">🐄</div>
            <div class="stat-details">
                <span class="stat-label">Total Collections</span>
                <span class="stat-value"><?php echo $totals['total_collections']; ?></span>
            </div>
        </div>
    </div>

    <!-- Production Table -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Daily Production Records</h3>
            <p style="color: var(--text-medium); font-size: 0.9rem; margin-top: 0.5rem;">
                Showing <?php echo $total_records > 0 ? ($offset + 1) : 0; ?> - <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                from <?php echo date('d M Y', strtotime($date_from)); ?> to <?php echo date('d M Y', strtotime($date_to)); ?>
                <?php if (!empty($cattle_filter)): ?>
                    <?php
                    $selected_cattle_sql = "SELECT tag_id FROM cattle WHERE cattle_id = ?";
                    $selected_stmt = $conn->prepare($selected_cattle_sql);
                    $selected_stmt->bind_param("i", $cattle_filter);
                    $selected_stmt->execute();
                    $selected_tag = $selected_stmt->get_result()->fetch_assoc();
                    ?>
                    | Cattle: <strong><?php echo htmlspecialchars($selected_tag['tag_id']); ?></strong>
                <?php endif; ?>
            </p>
        </div>
        <div class="card-body">
            <?php if (!empty($errors) && empty($errors['date_range'])): ?>
                <div class="alert alert-warning">
                    <span class="alert-icon">⚠</span>
                    <span>Please correct the date validation errors above</span>
                </div>
            <?php elseif ($result): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Shift</th>
                                <?php if (empty($cattle_filter)): ?>
                                    <th>Cattle Type</th>
                                    <th>Cattle Count</th>
                                    <th>Total Milk (L)</th>
                                    <th>Avg per Cattle</th>
                                <?php else: ?>
                                    <th>Tag ID</th>
                                    <th>Type</th>
                                    <th>Breed</th>
                                    <th>Quantity (L)</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php 
                                $page_total = 0;
                                while ($row = $result->fetch_assoc()): 
                                    $page_total += empty($cattle_filter) ? $row['total_milk'] : $row['total_milk'];
                                ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($row['collection_date'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $row['shift'] === 'Morning' ? 'badge-info' : 'badge-warning'; ?>">
                                                <?php echo $row['shift']; ?>
                                            </span>
                                        </td>
                                        <?php if (empty($cattle_filter)): ?>
                                            <td><?php echo htmlspecialchars($row['type_name']); ?></td>
                                            <td><strong><?php echo $row['cattle_count']; ?></strong></td>
                                            <td><strong><?php echo number_format($row['total_milk'], 2); ?></strong></td>
                                            <td><?php echo number_format($row['avg_per_cattle'], 2); ?></td>
                                        <?php else: ?>
                                            <td>
                                                <strong style="color: var(--primary-color);">
                                                    <?php echo htmlspecialchars($row['tag_id']); ?>
                                                </strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['type_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['breed_name']); ?></td>
                                            <td><strong><?php echo number_format($row['total_milk'], 2); ?></strong></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endwhile; ?>
                                <tr style="background: var(--bg-tertiary); font-weight: bold;">
                                    <td colspan="<?php echo empty($cattle_filter) ? 4 : 3; ?>" style="text-align: right;">Page Total:</td>
                                    <td><?php echo number_format($page_total, 2); ?> L</td>
                                    <?php if (empty($cattle_filter)): ?>
                                        <td>-</td>
                                    <?php endif; ?>
                                </tr>
                                <tr style="background: var(--primary-color); color: white; font-weight: bold;">
                                    <td colspan="<?php echo empty($cattle_filter) ? 4 : 3; ?>" style="text-align: right;">Grand Total:</td>
                                    <td><?php echo number_format($totals['total_milk'], 2); ?> L</td>
                                    <?php if (empty($cattle_filter)): ?>
                                        <td>-</td>
                                    <?php endif; ?>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <span class="empty-icon">🥛</span>
                                            <p>No production records for selected period</p>
                                            <small style="color: var(--text-medium);">Try selecting a different date range or cattle</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div style="padding: 1rem; background: var(--bg-secondary); border-top: 1px solid var(--border-color); margin-top: 1rem;">
                        <div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <?php
                            $query_params = [];
                            $query_params['date_from'] = $date_from;
                            $query_params['date_to'] = $date_to;
                            if ($cattle_filter) $query_params['cattle_id'] = $cattle_filter;
                            
                            function build_pagination_url($page_num, $params) {
                                $params['page'] = $page_num;
                                return 'daily-production.php?' . http_build_query($params);
                            }
                            ?>
                            
                            <!-- First & Previous -->
                            <?php if ($page > 1): ?>
                                <a href="<?php echo build_pagination_url(1, $query_params); ?>" class="btn btn-secondary btn-sm" title="First Page">⏮️</a>
                                <a href="<?php echo build_pagination_url($page - 1, $query_params); ?>" class="btn btn-secondary btn-sm" title="Previous">◀️</a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm" disabled>⏮️</button>
                                <button class="btn btn-secondary btn-sm" disabled>◀️</button>
                            <?php endif; ?>
                            
                            <!-- Page Numbers -->
                            <?php
                            $range = 2;
                            $start = max(1, $page - $range);
                            $end = min($total_pages, $page + $range);
                            
                            if ($start > 1): ?>
                                <a href="<?php echo build_pagination_url(1, $query_params); ?>" class="btn btn-secondary btn-sm">1</a>
                                <?php if ($start > 2): ?>
                                    <span style="padding: 0.5rem; color: var(--text-medium);">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <button class="btn btn-primary btn-sm" style="min-width: 40px;"><?php echo $i; ?></button>
                                <?php else: ?>
                                    <a href="<?php echo build_pagination_url($i, $query_params); ?>" class="btn btn-secondary btn-sm" style="min-width: 40px;"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($end < $total_pages): ?>
                                <?php if ($end < $total_pages - 1): ?>
                                    <span style="padding: 0.5rem; color: var(--text-medium);">...</span>
                                <?php endif; ?>
                                <a href="<?php echo build_pagination_url($total_pages, $query_params); ?>" class="btn btn-secondary btn-sm"><?php echo $total_pages; ?></a>
                            <?php endif; ?>
                            
                            <!-- Next & Last -->
                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo build_pagination_url($page + 1, $query_params); ?>" class="btn btn-secondary btn-sm" title="Next">▶️</a>
                                <a href="<?php echo build_pagination_url($total_pages, $query_params); ?>" class="btn btn-secondary btn-sm" title="Last Page">⏭️</a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm" disabled>▶️</button>
                                <button class="btn btn-secondary btn-sm" disabled>⏭️</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('dateFilterForm');
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    const today = new Date().toISOString().split('T')[0];
    
    function showError(element, message) {
        const existingError = element.parentElement.querySelector('.error-msg');
        if (existingError) {
            existingError.remove();
        }
        
        const errorSpan = document.createElement('span');
        errorSpan.className = 'error-msg';
        errorSpan.style.color = '#dc3545';
        errorSpan.style.fontSize = '0.875rem';
        errorSpan.style.marginTop = '0.25rem';
        errorSpan.style.display = 'block';
        errorSpan.textContent = message;
        element.parentElement.appendChild(errorSpan);
        element.style.borderColor = '#dc3545';
    }
    
    function clearError(element) {
        const errorSpan = element.parentElement.querySelector('.error-msg');
        if (errorSpan && !errorSpan.textContent.includes('required')) {
            errorSpan.remove();
        }
        element.style.borderColor = '';
    }
    
    if (dateFrom) {
        dateFrom.addEventListener('change', function() {
            clearError(this);
            
            if (this.value > today) {
                showError(this, 'From date cannot be in the future');
            } else if (dateTo.value && this.value > dateTo.value) {
                showError(this, 'From date cannot be later than To date');
            }
        });
    }
    
    if (dateTo) {
        dateTo.addEventListener('change', function() {
            clearError(this);
            
            if (this.value > today) {
                showError(this, 'To date cannot be in the future');
            } else if (dateFrom.value && this.value < dateFrom.value) {
                showError(this, 'To date cannot be earlier than From date');
            }
        });
    }
    
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            document.querySelectorAll('.error-msg').forEach(el => {
                if (!el.textContent.includes('required')) {
                    el.remove();
                }
            });
            
            if (!dateFrom.value) {
                showError(dateFrom, 'From date is required');
                isValid = false;
            } else if (dateFrom.value > today) {
                showError(dateFrom, 'From date cannot be in the future');
                isValid = false;
            }
            
            if (!dateTo.value) {
                showError(dateTo, 'To date is required');
                isValid = false;
            } else if (dateTo.value > today) {
                showError(dateTo, 'To date cannot be in the future');
                isValid = false;
            }
            
            if (dateFrom.value && dateTo.value) {
                if (dateFrom.value > dateTo.value) {
                    showError(dateFrom, 'From date cannot be later than To date');
                    isValid = false;
                }
                
                const date1 = new Date(dateFrom.value);
                const date2 = new Date(dateTo.value);
                const diffTime = Math.abs(date2 - date1);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays > 365) {
                    showError(dateTo, 'Date range cannot exceed 365 days');
                    isValid = false;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                const firstError = document.querySelector('.error-msg');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }
});

if (!isValid) {
        e.preventDefault();
    }
return isValid;
</script>
<!-- Export to PDF function -->
<script>    
function exportPDF() {
    window.print();
}
</script>

<?php
if (isset($stmt)) $stmt->close();
if (isset($totals_stmt)) $totals_stmt->close();
if (isset($count_stmt)) $count_stmt->close();
if (isset($selected_stmt)) $selected_stmt->close();
$conn->close();
include '../../../includes/footer.php';
?>