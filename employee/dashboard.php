<?php
// employee/dashboard.php - REORGANIZED VERSION
session_start();
define('DFMS_EXEC', true);
require_once '../includes/config.php';
require_once '../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Employee Dashboard";
$user_id = get_user_id();
$user_name = get_user_name();

// Get today's date and yesterday (for 24hr shelf life)
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Get employee statistics
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM milk_collection WHERE user_id = ? AND collection_date = ?) as today_collections,
                (SELECT COALESCE(SUM(quantity), 0) FROM milk_collection WHERE user_id = ? AND collection_date = ?) as today_quantity,
                (SELECT COUNT(*) FROM milk_collection WHERE user_id = ?) as total_collections,
                (SELECT COALESCE(SUM(quantity), 0) FROM milk_collection WHERE user_id = ?) as total_quantity";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("isisii", $user_id, $today, $user_id, $today, $user_id, $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Get available milk for sale (24hr shelf life - today and yesterday only)
$available_milk_sql = "SELECT COALESCE(SUM(available_quantity), 0) as available_milk
                       FROM available_milk 
                       WHERE collection_date >= ? AND collection_date <= ?";
$available_milk_stmt = $conn->prepare($available_milk_sql);
$available_milk_stmt->bind_param("ss", $yesterday, $today);
$available_milk_stmt->execute();
$available_milk_result = $available_milk_stmt->get_result()->fetch_assoc();
$available_milk = $available_milk_result['available_milk'];
$available_milk_stmt->close();

// Get this month's statistics
$month_start = date('Y-m-01');
$month_sql = "SELECT 
                COUNT(*) as month_collections,
                COALESCE(SUM(quantity), 0) as month_quantity
              FROM milk_collection 
              WHERE user_id = ? AND collection_date >= ?";
$month_stmt = $conn->prepare($month_sql);
$month_stmt->bind_param("is", $user_id, $month_start);
$month_stmt->execute();
$month_stats = $month_stmt->get_result()->fetch_assoc();
$month_stmt->close();

// Get low stock items
$low_stock_sql = "SELECT * FROM low_stock_inventory ORDER BY shortage DESC LIMIT 10";
$low_stock_items = $conn->query($low_stock_sql);
$low_stock_count = $low_stock_items->num_rows;

// Get recent milk collections
$recent_sql = "SELECT mc.*, c.tag_id, ct.type_name, b.breed_name
               FROM milk_collection mc
               JOIN cattle c ON mc.cattle_id = c.cattle_id
               JOIN cattle_type ct ON c.type_id = ct.type_id
               JOIN breed b ON c.breed_id = b.breed_id
               WHERE mc.user_id = ?
               ORDER BY mc.collection_date DESC, mc.shift DESC
               LIMIT 5";
$recent_stmt = $conn->prepare($recent_sql);
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$recent_collections = $recent_stmt->get_result();
$recent_stmt->close();

// Get available milk for sale (24hr shelf life - today and yesterday only)
// This includes ALL records (admin + employee)
$available_milk_sql = "SELECT COALESCE(SUM(available_quantity), 0) as available_milk
                       FROM available_milk 
                       WHERE collection_date >= ? AND collection_date <= ?";
$available_milk_stmt = $conn->prepare($available_milk_sql);
$available_milk_stmt->bind_param("ss", $yesterday, $today);
$available_milk_stmt->execute();
$available_milk_result = $available_milk_stmt->get_result()->fetch_assoc();
$available_milk = $available_milk_result['available_milk'];
$available_milk_stmt->close();

// Get employee's today's sales statistics
$today_sales_sql = "SELECT 
                        COUNT(*) as today_sales_count,
                        COALESCE(SUM(total_amount), 0) as today_sales_amount
                    FROM sales 
                    WHERE user_id = ? AND sales_date = ?";
$today_sales_stmt = $conn->prepare($today_sales_sql);
$today_sales_stmt->bind_param("is", $user_id, $today);
$today_sales_stmt->execute();
$today_sales = $today_sales_stmt->get_result()->fetch_assoc();
$today_sales_stmt->close();

// Get employee's total outstanding dues (all time)
// $total_dues_sql = "SELECT 
//                       COALESCE(SUM(s.total_amount - COALESCE(paid.total_paid, 0)), 0) as total_outstanding
//                    FROM sales s
//                    LEFT JOIN (
//                        SELECT sales_id, SUM(amount_paid) as total_paid
//                        FROM payment
//                        GROUP BY sales_id
//                    ) paid ON s.sales_id = paid.sales_id
//                    WHERE s.user_id = ? AND s.sales_status IN ('Due', 'Partial')";
// $total_dues_stmt = $conn->prepare($total_dues_sql);
// $total_dues_stmt->bind_param("i", $user_id);
// $total_dues_stmt->execute();
// $total_dues = $total_dues_stmt->get_result()->fetch_assoc();
// $total_dues_stmt->close();

// Get employee's today's outstanding dues only
$today_dues_sql = "SELECT 
                      COALESCE(SUM(s.total_amount - COALESCE(paid.total_paid, 0)), 0) as today_outstanding
                   FROM sales s
                   LEFT JOIN (
                       SELECT sales_id, SUM(amount_paid) as total_paid
                       FROM payment
                       GROUP BY sales_id
                   ) paid ON s.sales_id = paid.sales_id
                   WHERE s.user_id = ? 
                   AND s.sales_date = ? 
                   AND s.sales_status IN ('Due', 'Partial')";
$today_dues_stmt = $conn->prepare($today_dues_sql);
$today_dues_stmt->bind_param("is", $user_id, $today);
$today_dues_stmt->execute();
$today_dues = $today_dues_stmt->get_result()->fetch_assoc();
$today_dues_stmt->close();

// Get recent sales
$recent_sales_sql = "SELECT s.*, c.customer_name, c.customer_type,
                     (s.total_amount - COALESCE(SUM(p.amount_paid), 0)) as balance
                     FROM sales s
                     JOIN customer c ON s.customer_id = c.customer_id
                     LEFT JOIN payment p ON s.sales_id = p.sales_id
                     WHERE s.user_id = ?
                     GROUP BY s.sales_id
                     ORDER BY s.sales_date DESC, s.sales_id DESC
                     LIMIT 5";
$recent_sales_stmt = $conn->prepare($recent_sales_sql);
$recent_sales_stmt->bind_param("i", $user_id);
$recent_sales_stmt->execute();
$recent_sales = $recent_sales_stmt->get_result();
$recent_sales_stmt->close();

// Get recent inventory usage (check if table exists)
$table_check = $conn->query("SHOW TABLES LIKE 'inventory_usage'");
if ($table_check->num_rows > 0) {
    $recent_inventory_sql = "SELECT iu.*, i.item_name, u.full_name
                            FROM inventory_usage iu
                            JOIN inventory i ON iu.inventory_id = i.inventory_id
                            JOIN user u ON iu.user_id = u.user_id
                            WHERE iu.user_id = ?
                            ORDER BY iu.usage_date DESC, iu.usage_id DESC
                            LIMIT 5";
    $recent_inventory_stmt = $conn->prepare($recent_inventory_sql);
    $recent_inventory_stmt->bind_param("i", $user_id);
    $recent_inventory_stmt->execute();
    $recent_inventory = $recent_inventory_stmt->get_result();
    $recent_inventory_stmt->close();
} else {
    $recent_inventory = null;
}

include '../includes/header.php';
?>

<div class="main-content">
    <!-- Welcome Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>👋 Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h1>
            <div class="breadcrumb">
                <span>Employee Dashboard</span>
                <span>/</span>
                <span><?php echo date('l, d F Y'); ?></span>
                <span>/</span>
                <span id="currentTime"><?php echo date('h:i:s A'); ?></span>
            </div>
        </div>
        <div class="header-actions">
            <a href="milk/milk-add.php" class="btn btn-primary">➕ Add Milk Record</a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card stat-primary">
        <div class="stat-icon">📊</div>
        <div class="stat-details">
            <span class="stat-label">Today's Collections</span>
            <span class="stat-value"><?php echo $stats['today_collections']; ?></span>
            <small style="color: var(--text-medium);"><?php echo number_format($stats['today_quantity'], 2); ?> Liters</small>
        </div>
    </div>
    
    <div class="stat-card stat-success">
        <div class="stat-icon">📅</div>
        <div class="stat-details">
            <span class="stat-label">This Month</span>
            <span class="stat-value"><?php echo $month_stats['month_collections']; ?></span>
            <small style="color: var(--text-medium);"><?php echo number_format($month_stats['month_quantity'], 2); ?> Liters</small>
        </div>
    </div>
    
    <div class="stat-card stat-info">
        <div class="stat-icon">🥛</div>
        <div class="stat-details">
            <span class="stat-label">Total Collections</span>
            <span class="stat-value"><?php echo $stats['total_collections']; ?></span>
            <small style="color: var(--text-medium);">All time</small>
        </div>
    </div>
    
    <div class="stat-card stat-warning">
        <div class="stat-icon">💧</div>
        <div class="stat-details">
            <span class="stat-label">Total Milk</span>
            <span class="stat-value"><?php echo number_format($stats['total_quantity'], 2); ?> L</span>
            <small style="color: var(--text-medium);">Collected by you</small>
        </div>
    </div>

    <!-- Today's Sales -->
    <div class="stat-card stat-success">
        <div class="stat-icon">🛒</div>
        <div class="stat-details">
            <span class="stat-label">Today's Sales</span>
            <span class="stat-value"><?php echo $today_sales['today_sales_count']; ?></span>
            <small style="color: var(--text-medium);">रू <?php echo number_format($today_sales['today_sales_amount'], 2); ?></small>
        </div>
    </div>

    <!-- CHANGED: Available Milk for Sale (All farm milk) -->
    <div class="stat-card stat-info">
        <div class="stat-icon">🥛</div>
        <div class="stat-details">
            <span class="stat-label">Available for Sale</span>
            <span class="stat-value"><?php echo number_format($available_milk, 2); ?> L</span>
            <small style="color: var(--text-medium);">Fresh milk in stock</small>
        </div>
    </div>

    <!-- Today's Dues -->
    <div class="stat-card stat-warning">
        <div class="stat-icon">📅</div>
        <div class="stat-details">
            <span class="stat-label">Today's Dues</span>
            <span class="stat-value">रू <?php echo number_format($today_dues['today_outstanding'], 2); ?></span>
            <small style="color: var(--text-medium);">Pending from today</small>
        </div>
    </div>

    <!-- Low Stock Card (Clickable) -->
    <a href="#low-stock-section" class="stat-card stat-danger" id="low-stock-card" style="text-decoration: none; color: inherit;">
        <div class="stat-icon">📦</div>
        <div class="stat-details">
            <span class="stat-label">Low Stock Items</span>
            <span class="stat-value"><?php echo $low_stock_count; ?></span>
            <small style="color: var(--text-medium);">
                <?php echo $low_stock_count > 0 ? 'Needs restock' : 'All good'; ?>
            </small>
        </div>
    </a>
</div>
    <!-- Recent Collections -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Recent Collections</h3>
            <a href="milk/my-collections.php" class="btn btn-sm btn-primary">View All →</a>
        </div>
        <div class="card-body">
            <?php if ($recent_collections->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Shift</th>
                                <th>Cattle Tag</th>
                                <th>Type / Breed</th>
                                <th>Quantity (L)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recent_collections->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['collection_date'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $row['shift'] === 'Morning' ? 'badge-info' : 'badge-warning'; ?>">
                                            <?php echo $row['shift'] === 'Morning' ? '🌅' : '🌆'; ?> 
                                            <?php echo $row['shift']; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row['tag_id']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['type_name']); ?> / 
                                        <?php echo htmlspecialchars($row['breed_name']); ?>
                                    </td>
                                    <td><strong><?php echo number_format($row['quantity'], 2); ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">🥛</span>
                    <p>No milk collections recorded yet</p>
                    <a href="milk/milk-add.php" class="btn btn-primary" style="margin-top: 1rem;">
                        ➕ Add Your First Collection
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="card">
        <div class="card-header">
            <h3>🛒 Recent Sales</h3>
            <a href="sales/sales-list.php" class="btn btn-sm btn-primary">View All →</a>
        </div>
        <div class="card-body">
            <?php if ($recent_sales->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Quantity (L)</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recent_sales->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['sales_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                                    <td>
                                        <?php 
                                        $type_badges = [
                                            'Wholesale' => '<span class="badge badge-primary">🏪 Wholesale</span>',
                                            'Retail' => '<span class="badge badge-info">🛒 Retail</span>',
                                            'Regular' => '<span class="badge badge-success">⭐ Regular</span>'
                                        ];
                                        echo $type_badges[$row['sales_type']] ?? '<span class="badge badge-secondary">' . htmlspecialchars($row['sales_type']) . '</span>';
                                        ?>
                                    </td>
                                    <td><?php echo number_format($row['total_quantity'], 2); ?></td>
                                    <td>रू <?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td>
                                        <?php 
                                        $status_badges = [
                                            'Paid' => '<span class="badge badge-success">✓ Paid</span>',
                                            'Partial' => '<span class="badge badge-warning">⏳ Partial</span>',
                                            'Due' => '<span class="badge badge-danger">⚠ Due</span>'
                                        ];
                                        echo $status_badges[$row['sales_status']] ?? '<span class="badge badge-secondary">' . htmlspecialchars($row['sales_status']) . '</span>';
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">🛒</span>
                    <p>No sales recorded yet</p>
                    <a href="sales/sales-add.php" class="btn btn-primary" style="margin-top: 1rem;">
                        ➕ Create First Sale
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Inventory Usage -->
    <!-- <div class="card">
        <div class="card-header">
            <h3>📦 Recent Inventory Usage</h3>
            <a href="inventory/inventory-list.php" class="btn btn-sm btn-primary">View All →</a>
        </div>
        <div class="card-body">
            <?php if ($recent_inventory && $recent_inventory->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Quantity Used</th>
                                <th>Purpose</th>
                                <th>Used By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recent_inventory->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['usage_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['item_name']); ?></strong></td>
                                    <td><?php echo number_format($row['quantity_used'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['purpose'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">📦</span>
                    <p>No inventory usage recorded yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div> -->

    <!-- Low Stock Alert -->
    <?php if ($low_stock_count > 0): ?>
        <div class="card" id="low-stock-section" style="border: 2px solid var(--danger); scroll-margin-top: 20px;">
            <div class="card-header" style="background: linear-gradient(135deg, #fee, #fff); border-bottom: 2px solid var(--danger);">
                <h3 style="color: var(--danger); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span>⚠️</span> Low Stock Alert
                </h3>
                <a href="inventory/inventory-list.php" class="btn btn-danger btn-sm">Manage Inventory</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Minimum Required</th>
                                <th>Shortage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $low_stock_items->data_seek(0);
                            while ($item = $low_stock_items->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                    <td><span class="badge badge-secondary"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                    <td style="color: var(--danger); font-weight: bold;">
                                        <?php echo number_format($item['current_quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
                                    </td>
                                    <td><?php echo number_format($item['minimum_quantity'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td>
                                        <span class="badge badge-danger">
                                            ⚠ <?php echo number_format($item['shortage'], 2); ?> <?php echo htmlspecialchars($item['unit']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3>⚡ Quick Actions</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <!-- Milk Collection -->
                <a href="milk/milk-add.php" class="btn btn-success" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🥛</div>
                    <strong>Add Milk</strong><br>
                </a>
                
                <!-- Add Customer -->
                <a href="customers/customer-add.php" class="btn btn-primary" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">👤</div>
                    <strong>Add Customer</strong><br>
                </a>
                
                <!-- New Sale -->
                <a href="sales/sales-add.php" class="btn btn-warning" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🛒</div>
                    <strong>New Sale</strong><br>
                </a>
                
                <!-- Record Payment -->
                <a href="sales/sales-list.php" class="btn btn-success" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">💰</div>
                    <strong>Payment</strong><br>
                </a>
                
                <!-- My Collections -->
                <a href="milk/my-collections.php" class="btn btn-info" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📋</div>
                    <strong>My Records</strong><br>
                </a>
                
                <!-- View Cattle -->
                <a href="cattle/cattle-list.php" class="btn btn-secondary" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🐄</div>
                    <strong>View Cattle</strong><br>
                </a>
                
                <!-- View Customers -->
                <a href="customers/customer-list.php" class="btn btn-info" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">👥</div>
                    <strong>Customers</strong><br>
                </a>
                
                <!-- Inventory -->
                <a href="inventory/inventory-list.php" class="btn btn-secondary" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📦</div>
                    <strong>Inventory</strong><br>
                </a>
            </div>
        </div>
    </div> 
</div>

<style>
html {
    scroll-behavior: smooth;
}

#low-stock-section {
    scroll-margin-top: 20px;
}

/* Highlight animation when scrolled to */
@keyframes highlightPulse {
    0%, 100% { box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    50% { box-shadow: 0 8px 16px rgba(231, 76, 60, 0.3); }
}

#low-stock-section:target {
    animation: highlightPulse 2s ease-in-out;
}

.stat-card.stat-danger {
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.stat-card.stat-danger:hover {
    border-color: var(--danger);
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(231, 76, 60, 0.2);
}
</style>

<script>
// Update time every second with seconds
function updateTime() {
    const now = new Date();
    const hours = now.getHours();
    const minutes = now.getMinutes();
    const seconds = now.getSeconds();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const displayHours = hours % 12 || 12;
    
    const timeString = String(displayHours).padStart(2, '0') + ':' + 
                      String(minutes).padStart(2, '0') + ':' + 
                      String(seconds).padStart(2, '0') + ' ' + ampm;
    
    const currentTimeElement = document.getElementById('currentTime');
    
    if (currentTimeElement) {
        currentTimeElement.textContent = timeString;
    }
}

// Update time immediately and then every second
updateTime();
setInterval(updateTime, 1000);
</script>

<?php
$conn->close();
include '../includes/footer.php';
?>