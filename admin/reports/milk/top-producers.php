<?php
// admin/reports/milk/top-producers.php
session_start();
define('DFMS_EXEC', true);
require_once '../../../includes/config.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

require_admin();

$page_title = "Top Milk Producers";

// Date filters with validation
$errors = [];
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Validate dates if form is submitted
if (isset($_GET['date_from']) || isset($_GET['date_to'])) {
    $today = date('Y-m-d');
    
    // Both dates must be provided together
    if (!empty($date_from) && empty($date_to)) {
        $errors['date_to'] = 'To date is required when From date is specified';
    }
    if (!empty($date_to) && empty($date_from)) {
        $errors['date_from'] = 'From date is required when To date is specified';
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

// Only fetch data if no validation errors
$result = null;
$period_text = "All Time";

if (empty($errors)) {
    // Build SQL with optional date filter
    $sql = "
        SELECT 
            c.cattle_id,
            c.tag_id,
            ct.type_name,
            b.breed_name,
            COUNT(mc.milk_id) AS collection_count,
            SUM(mc.quantity) AS total_milk_produced,
            AVG(mc.quantity) AS avg_milk_per_collection,
            MAX(mc.collection_date) AS last_collection_date
        FROM cattle c
        INNER JOIN cattle_type ct ON c.type_id = ct.type_id
        INNER JOIN breed b ON c.breed_id = b.breed_id
        INNER JOIN milk_collection mc ON c.cattle_id = mc.cattle_id
        WHERE c.life_status IN ('Alive', 'Pregnant')
          AND c.gender = 'Female'";
    
    // Add date filter if both dates are provided
    if (!empty($date_from) && !empty($date_to)) {
        $sql .= " AND mc.collection_date BETWEEN ? AND ?";
        $period_text = date('d M Y', strtotime($date_from)) . " to " . date('d M Y', strtotime($date_to));
    }
    
    $sql .= " GROUP BY c.cattle_id, c.tag_id, ct.type_name, b.breed_name
              ORDER BY total_milk_produced DESC
              LIMIT 20";
    
    if (!empty($date_from) && !empty($date_to)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
}

include '../../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="header-content">
            <h1>🏆 Top Milk Producers</h1>
            <div class="breadcrumb">
                <a href="../../dashboard.php">Dashboard</a>
                <span>/</span>
                <a href="../reports-dashboard.php">Reports</a>
                <span>/</span>
                <span>Top Producers</span>
            </div>
        </div>
        <div class="header-actions">
            <!-- <button onclick="window.print()" class="btn btn-secondary no-print">🖨 Print</button> -->
            <a href="../reports-dashboard.php" class="btn btn-primary">← Back</a>
            <!-- <button onclick="exportPDF()" class="btn btn-info no-print">📄 Export PDF</button> -->
        </div>
    </div>

    <!-- Date Filter -->
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
                        <label class="form-label">From Date</label>
                        <input 
                            type="date" 
                            name="date_from" 
                            id="date_from"
                            class="form-control" 
                            value="<?php echo htmlspecialchars($date_from); ?>" 
                            max="<?php echo date('Y-m-d'); ?>"
                        >
                        <?php if (isset($errors['date_from'])): ?>
                            <span class="error-msg" style="color: #dc3545; font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                                <?php echo $errors['date_from']; ?>
                            </span>
                        <?php endif; ?>
                        <!-- <small style="color: var(--text-medium); display: block; margin-top: 0.25rem;">
                            Leave empty for all-time records
                        </small> -->
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input 
                            type="date" 
                            name="date_to" 
                            id="date_to"
                            class="form-control" 
                            value="<?php echo htmlspecialchars($date_to); ?>" 
                            max="<?php echo date('Y-m-d'); ?>"
                        >
                        <?php if (isset($errors['date_to'])): ?>
                            <span class="error-msg" style="color: #dc3545; font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                                <?php echo $errors['date_to']; ?>
                            </span>
                        <?php endif; ?>
                        <!-- <small style="color: var(--text-medium); display: block; margin-top: 0.25rem;">
                            Leave empty for all-time records
                        </small> -->
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <a href="top-producers.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>🐄 Top 20 Milk Producing Cattle</h3>
            <p style="color: var(--text-medium); margin-top: 0.5rem;">
                Ranking based on total milk production volume | Period: <strong><?php echo $period_text; ?></strong>
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
                                <th style="width: 80px;">Rank</th>
                                <th>Tag ID</th>
                                <th>Type</th>
                                <th>Breed</th>
                                <th style="text-align: center;">Collections</th>
                                <th style="text-align: right;">Total Produced (L)</th>
                                <th style="text-align: right;">Avg per Collection</th>
                                <th>Last Collection</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php 
                                $rank = 1;
                                while ($row = $result->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td style="text-align: center;">
                                            <?php if ($rank <= 3): ?>
                                                <span style="font-size: 1.8rem;">
                                                    <?php echo $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉'); ?>
                                                </span>
                                            <?php else: ?>
                                                <strong style="font-size: 1.1rem;">#<?php echo $rank; ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong style="color: var(--primary-color);">
                                                <?php echo htmlspecialchars($row['tag_id']); ?>
                                            </strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['type_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['breed_name']); ?></td>
                                        <td style="text-align: center;">
                                            <span class="badge badge-info">
                                                <?php echo number_format($row['collection_count']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <strong style="color: var(--success-color); font-size: 1.1rem;">
                                                <?php echo number_format($row['total_milk_produced'], 2); ?> L
                                            </strong>
                                        </td>
                                        <td style="text-align: right;">
                                            <?php echo number_format($row['avg_milk_per_collection'], 2); ?> L
                                        </td>
                                        <td><?php echo display_date($row['last_collection_date']); ?></td>
                                    </tr>
                                <?php 
                                $rank++;
                                endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state">
                                            <span class="empty-icon">🐄</span>
                                            <p>No production data available</p>
                                            <small>
                                                <?php if (!empty($date_from) && !empty($date_to)): ?>
                                                    No records found for the selected date range. Try a different period.
                                                <?php else: ?>
                                                    Start recording milk collections to see top producers
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- <?php if ($result && $result->num_rows > 0): ?>
                    <div style="margin-top: 2rem; padding: 1.5rem; background: var(--bg-light); border-radius: 8px; border-left: 4px solid var(--primary-color);">
                        <h4 style="margin: 0 0 1rem 0; color: var(--primary-color);">📊 Summary Statistics</h4>
                        <?php
                        // Calculate totals
                        $result->data_seek(0); // Reset pointer
                        $total_collections = 0;
                        $total_milk = 0;
                        $count = 0;
                        
                        while ($row = $result->fetch_assoc()) {
                            $total_collections += $row['collection_count'];
                            $total_milk += $row['total_milk_produced'];
                            $count++;
                        }
                        
                        $avg_per_cattle = $count > 0 ? $total_milk / $count : 0;
                        ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div>
                                <div style="font-size: 0.875rem; color: var(--text-medium);">Total Collections</div>
                                <div style="font-size: 1.5rem; font-weight: bold; color: var(--primary-color);">
                                    <?php echo number_format($total_collections); ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; color: var(--text-medium);">Total Milk Produced</div>
                                <div style="font-size: 1.5rem; font-weight: bold; color: var(--success-color);">
                                    <?php echo number_format($total_milk, 2); ?> L
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; color: var(--text-medium);">Average per Cattle</div>
                                <div style="font-size: 1.5rem; font-weight: bold; color: var(--info-color);">
                                    <?php echo number_format($avg_per_cattle, 2); ?> L
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; color: var(--text-medium);">Top Producers</div>
                                <div style="font-size: 1.5rem; font-weight: bold; color: var(--warning-color);">
                                    <?php echo $count; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?> -->
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
        if (errorSpan) {
            errorSpan.remove();
        }
        element.style.borderColor = '';
    }
    
    if (dateFrom) {
        dateFrom.addEventListener('change', function() {
            clearError(this);
            
            if (this.value && this.value > today) {
                showError(this, 'From date cannot be in the future');
            } else if (this.value && dateTo.value && this.value > dateTo.value) {
                showError(this, 'From date cannot be later than To date');
            } else if (this.value && !dateTo.value) {
                showError(dateTo, 'To date is required when From date is specified');
            }
        });
    }
    
    if (dateTo) {
        dateTo.addEventListener('change', function() {
            clearError(this);
            
            if (this.value && this.value > today) {
                showError(this, 'To date cannot be in the future');
            } else if (this.value && dateFrom.value && this.value < dateFrom.value) {
                showError(this, 'To date cannot be earlier than From date');
            } else if (this.value && !dateFrom.value) {
                showError(dateFrom, 'From date is required when To date is specified');
            }
        });
    }
    
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            document.querySelectorAll('.error-msg').forEach(el => el.remove());
            document.querySelectorAll('.form-control').forEach(el => el.style.borderColor = '');
            
            // Check if only one date is filled
            if (dateFrom.value && !dateTo.value) {
                showError(dateTo, 'To date is required when From date is specified');
                isValid = false;
            }
            if (dateTo.value && !dateFrom.value) {
                showError(dateFrom, 'From date is required when To date is specified');
                isValid = false;
            }
            
            // Validate dates if both are filled
            if (dateFrom.value && dateTo.value) {
                if (dateFrom.value > today) {
                    showError(dateFrom, 'From date cannot be in the future');
                    isValid = false;
                }
                
                if (dateTo.value > today) {
                    showError(dateTo, 'To date cannot be in the future');
                    isValid = false;
                }
                
                if (dateFrom.value > dateTo.value) {
                    showError(dateFrom, 'From date cannot be later than To date');
                    isValid = false;
                }
                
                // Check if range is too large (365 days)
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
<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    .page-header {
        margin-bottom: 2rem;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-icon {
    font-size: 4rem;
    display: block;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state p {
    font-size: 1.1rem;
    color: var(--text-medium);
    margin: 0.5rem 0;
}

.empty-state small {
    color: var(--text-light);
}
</style>

<?php
if (isset($stmt)) $stmt->close();
$conn->close();
include '../../../includes/footer.php';
?>