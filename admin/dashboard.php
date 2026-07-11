<?php
/**
 * =========================================================
 * DAIRY FARM MANAGEMENT SYSTEM (DFMS)
 * Admin Dashboard - With Milk Freshness Stats
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check admin access
checkAuth();
checkRole(['Admin']);

$page_title = 'Admin Dashboard';
$user_name = get_user_name();

// Get current date and time
$current_date = date('l, F d, Y');
$current_time = date('h:i A');

// Get dashboard statistics
$stats = [];
$stats['total_cattle'] = $conn->query("SELECT COUNT(*) as count FROM cattle WHERE life_status = 'Alive'")->fetch_assoc()['count'];
$stats['today_milk'] = $conn->query("SELECT COALESCE(SUM(quantity), 0) as total FROM milk_collection WHERE collection_date = CURDATE()")->fetch_assoc()['total'];
$stats['total_customers'] = $conn->query("SELECT COUNT(*) as count FROM customer WHERE status = 'Active'")->fetch_assoc()['count'];
$stats['today_sales'] = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE sales_date = CURDATE()")->fetch_assoc()['total'];
$stats['pending_payments'] = $conn->query("SELECT COALESCE(SUM(total_amount), 0) - COALESCE((SELECT SUM(amount_paid) FROM payment p JOIN sales s2 ON p.sales_id = s2.sales_id WHERE s2.sales_status IN ('Due', 'Partial')), 0) as pending FROM sales WHERE sales_status IN ('Due', 'Partial')")->fetch_assoc()['pending'];
$stats['low_stock'] = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE current_quantity <= minimum_quantity")->fetch_assoc()['count'];

// 🆕 NEW: Get fresh milk statistics (< 24 hours)
$fresh_milk_query = $conn->query("SELECT COALESCE(SUM(available_quantity), 0) as total FROM available_milk");
$stats['available_milk'] = $fresh_milk_query->fetch_assoc()['total'];

// 🆕 NEW: Get expired milk statistics (>= 24 hours)
$expired_milk_query = $conn->query("SELECT COALESCE(SUM(wasted_quantity), 0) as total FROM milk_wastage");
$stats['expired_milk'] = $expired_milk_query->fetch_assoc()['total'];

// Recent milk collections
$recent_milk = $conn->query("SELECT mc.*, c.tag_id FROM milk_collection mc JOIN cattle c ON mc.cattle_id = c.cattle_id ORDER BY mc.created_at DESC LIMIT 5");

// Recent sales
$recent_sales = $conn->query("SELECT s.*, c.customer_name FROM sales s JOIN customer c ON s.customer_id = c.customer_id ORDER BY s.created_at DESC LIMIT 5");

// Low stock items
$low_stock_items = $conn->query("SELECT * FROM low_stock_inventory LIMIT 5");

include '../includes/header.php';
?>

<style>
/* Modern Dashboard Enhancements */
.dashboard-welcome {
    background: linear-gradient(135deg, var(--accent-blue), var(--accent-dark));
    color: white;
    padding: 2.5rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
}

.dashboard-welcome::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.welcome-content {
    position: relative;
    z-index: 1;
}

.welcome-greeting {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    animation: fadeInUp 0.6s ease;
}

.welcome-date {
    font-size: 1.1rem;
    opacity: 0.9;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: fadeInUp 0.6s ease 0.1s backwards;
}

.welcome-date span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Enhanced Stats Cards */
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.modern-stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.75rem;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: block;
}

.modern-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--accent-blue), var(--accent-light));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}

.modern-stat-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
    border-color: var(--accent-blue);
}

.modern-stat-card:hover::before {
    transform: scaleX(1);
}

/* Specific card colors */
.modern-stat-card.success-card::before {
    background: linear-gradient(90deg, var(--success), #27ae60);
}

.modern-stat-card.success-card:hover {
    border-color: var(--success);
}

.modern-stat-card.danger-card::before {
    background: linear-gradient(90deg, var(--danger), #c0392b);
}

.modern-stat-card.danger-card:hover {
    border-color: var(--danger);
}

.stat-header-modern {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
}

.stat-icon-modern {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    background: linear-gradient(135deg, rgba(27, 126, 173, 0.1), rgba(27, 126, 173, 0.05));
}

.success-card .stat-icon-modern {
    background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(39, 174, 96, 0.05));
}

.danger-card .stat-icon-modern {
    background: linear-gradient(135deg, rgba(231, 76, 60, 0.1), rgba(231, 76, 60, 0.05));
}

.stat-value-modern {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 0.25rem;
    line-height: 1;
}

.stat-label-modern {
    color: var(--text-medium);
    font-size: 0.95rem;
    font-weight: 500;
}

.stat-trend {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.75rem;
    font-size: 0.85rem;
    color: var(--success);
    font-weight: 600;
}

.stat-trend.negative {
    color: var(--danger);
}

/* Modern Card Design */
.modern-card {
    background: white;
    border-radius: 16px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    transition: var(--transition);
}

.modern-card:hover {
    box-shadow: var(--shadow-md);
}

.modern-card-header {
    padding: 1.5rem;
    border-bottom: 2px solid var(--bg-tertiary);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modern-card-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Quick Actions Modern */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1.5rem;
}

.quick-action-btn {
    padding: 1.25rem;
    background: linear-gradient(135deg, var(--accent-blue), var(--accent-dark));
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.quick-action-btn:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-accent);
    color: var(--accent-orange);
}

.quick-action-btn .icon {
    font-size: 1.5rem;
}

/* Alert Modern */
.modern-alert {
    background: linear-gradient(135deg, #fff5f5, #fff);
    border-left: 4px solid var(--danger);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    margin-top: 2rem;
    box-shadow: var(--shadow-sm);
}

/* Table Modern */
.modern-table {
    width: 100%;
}

.modern-table thead tr {
    background: var(--bg-tertiary);
}

.modern-table th {
    padding: 1rem;
    font-weight: 600;
    color: var(--text-dark);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modern-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.modern-table tbody tr {
    transition: var(--transition);
}

.modern-table tbody tr:hover {
    background: var(--bg-tertiary);
}

.modern-alert .modern-table th,
.modern-alert .modern-table td {
    text-align: center !important;
    vertical-align: middle;
}

/* smooth scroll css for anchor links i.e. low stock alert */
html {
    scroll-behavior: smooth;
}

#low-stock-section {
    scroll-margin-top: 45px;
}

/* Highlight animation when scrolled to */
@keyframes highlightPulse {
    0%, 100% { box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    50% { box-shadow: 0 8px 16px rgba(231, 76, 60, 0.3); }
}

#low-stock-section:target {
    animation: highlightPulse 2s ease-in-out;
}
</style>

<div class="main-content">
    <!-- Modern Welcome Header -->
    <div class="dashboard-welcome">
        <div class="welcome-content">
            <h1 class="welcome-greeting">👋 Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h1>
            <div class="welcome-date">
                <span>📅 <?php echo $current_date; ?></span>
                <span>•</span>
                <span>🕐 <?php echo $current_time; ?></span>
            </div>
        </div>
    </div>

    <!-- Modern Statistics Cards -->
    <div class="dashboard-stats">
        <!-- Total Cattle -->
        <div class="modern-stat-card">
            <div class="stat-header-modern">
                <div>
                    <div class="stat-value-modern"><?php echo $stats['total_cattle']; ?></div>
                    <div class="stat-label-modern">Total Cattle</div>
                    <div class="stat-trend">
                        <span></span> Active livestock
                    </div>
                </div>
                <div class="stat-icon-modern">🐄</div>
            </div>
        </div>

        <!-- Today's Milk -->
        <div class="modern-stat-card">
            <div class="stat-header-modern">
                <div>
                    <div class="stat-value-modern"><?php echo number_format($stats['today_milk'], 2); ?> L</div>
                    <div class="stat-label-modern">Today's Milk</div>
                    <div class="stat-trend">
                        <span></span> Liters collected
                    </div>
                </div>
                <div class="stat-icon-modern">🥛</div>
            </div>
        </div>

        <!-- 🆕 Available Fresh Milk -->
        <a href="sales/sales-add.php" class="modern-stat-card success-card">
            <div class="stat-header-modern">
                <div>
                    <div class="stat-value-modern"><?php echo number_format($stats['available_milk'], 2); ?> L</div>
                    <div class="stat-label-modern">Available for Sale</div>
                    <div class="stat-trend">
                        <span>🟢</span> Fresh milk (< 24h)
                    </div>
                </div>
                <div class="stat-icon-modern">✅</div>
            </div>
        </a>

        <!-- 🆕 Expired Milk (Wastage) -->
        <a href="reports/milk/milk-wastage.php" class="modern-stat-card danger-card">
            <div class="stat-header-modern">
                <div>
                    <div class="stat-value-modern"><?php echo number_format($stats['expired_milk'], 2); ?> L</div>
                    <div class="stat-label-modern">Expired Milk</div>
                    <div class="stat-trend negative">
                        <span>🔴</span> <?php echo $stats['expired_milk'] > 0 ? 'Wastage alert' : 'No wastage'; ?>
                    </div>
                </div>
                <div class="stat-icon-modern">⚠️</div>
            </div>
        </a>

        <!-- Active Customers -->
        <div class="modern-stat-card">
            <div class="stat-header-modern">
                <div>
                    <div class="stat-value-modern"><?php echo $stats['total_customers']; ?></div>
                    <div class="stat-label-modern">Active Customers</div>
                    <div class="stat-trend">
                        <span></span> Customer accounts
                    </div>
                </div>
                <div class="stat-icon-modern">👥</div>
            </div>
        </div>

        <!-- Today's Sales -->
        <div class="modern-stat-card">
            <div class="stat-header-modern">
                <div>
                    <div class="stat-value-modern">रू <?php echo number_format($stats['today_sales'], 2); ?></div>
                    <div class="stat-label-modern">Today's Sales</div>
                    <div class="stat-trend">
                        <span></span> Total revenue
                    </div>
                </div>
                <div class="stat-icon-modern">🪙</div>
            </div>
        </div>

        <!-- Pending Payments -->
        <a href="sales/sales-list.php" style="text-decoration: none; color: inherit;">
            <div class="modern-stat-card">
                <div class="stat-header-modern">
                    <div>
                        <div class="stat-value-modern">रू <?php echo number_format($stats['pending_payments'], 2); ?></div>
                        <div class="stat-label-modern">Pending Payments</div>
                        <div class="stat-trend negative">
                            <span>⚠</span> Outstanding dues
                        </div>
                    </div>
                    <div class="stat-icon-modern">⏳</div>
                </div>
            </div>
        </a>

        <!-- Low Stock Alert -->
        <!-- Low Stock Alert -->
        <a href="#low-stock-section" class="modern-stat-card <?php echo $stats['low_stock'] > 0 ? 'danger-card' : ''; ?>" id="low-stock-card">
            <div class="stat-header-modern">
                <div>
                    <div class="stat-value-modern"><?php echo $stats['low_stock']; ?></div>
                    <div class="stat-label-modern">Low Stock Items</div>
                    <div class="stat-trend <?php echo $stats['low_stock'] > 0 ? 'negative' : ''; ?>">
                        <span><?php echo $stats['low_stock'] > 0 ? '⚠' : '✓'; ?></span> 
                        <?php echo $stats['low_stock'] > 0 ? 'Needs restock' : 'All good'; ?>
                    </div>
                </div>
                <div class="stat-icon-modern">📦</div>
            </div>
        </a>
    </div>

    <!-- 🆕 Milk Freshness Alert (if wastage exists) -->
    <!-- <?php if ($stats['expired_milk'] > 0): ?>
        <div class="modern-alert">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="color: var(--danger); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span>🗑️</span> Milk Wastage Alert - Action Required!
                </h3>
                <a href="reports/milk/milk-wastage.php" class="btn btn-danger">View Wastage Report</a>
            </div>
            <p style="color: var(--text-medium); margin-bottom: 1rem;">
                <strong><?php echo number_format($stats['expired_milk'], 2); ?> liters</strong> of milk have expired (over 24 hours old). 
                Estimated loss: <strong>रू <?php echo number_format($stats['expired_milk'] * 80, 2); ?></strong> @ Retail price.
            </p>
            <div style="background: white; padding: 1rem; border-radius: 8px;">
                <strong style="color: var(--danger);">💡 Prevention Tips:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem; color: var(--text-dark);">
                    <li>Monitor available milk regularly throughout the day</li>
                    <li>Prioritize selling older milk (12+ hours) first</li>
                    <li>Adjust collection amounts based on daily sales trends</li>
                    <li>Consider discounts for milk nearing 24-hour mark</li>
                </ul>
            </div>
        </div>
    <?php endif; ?> -->

    <!-- Recent Activity Section -->
    <div class="customer-details">
        <!-- Recent Milk Collections -->
        <div class="modern-card">
            <div class="modern-card-header">
                <h3 class="modern-card-title">
                    <span>🥛</span> Recent Milk Collections
                </h3>
                <a href="milk/milk-list.php" class="btn btn-sm btn-primary">View All →</a>
            </div>
            <div class="card-body">
                <?php if ($recent_milk->num_rows > 0): ?>
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Cattle Tag</th>
                                <th>Shift</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($milk = $recent_milk->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($milk['collection_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($milk['tag_id']); ?></strong></td>
                                    <td><span class="badge badge-info"><?php echo $milk['shift']; ?></span></td>
                                    <td><strong><?php echo number_format($milk['quantity'], 2); ?> L</strong></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="empty-icon">🥛</span>
                        <p>No milk collections yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="modern-card">
            <div class="modern-card-header">
                <h3 class="modern-card-title">
                    <span>🛒</span> Recent Sales
                </h3>
                <a href="sales/sales-list.php" class="btn btn-sm btn-primary">View All →</a>
            </div>
            <div class="card-body">
                <?php if ($recent_sales->num_rows > 0): ?>
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($sale['sales_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($sale['customer_name']); ?></strong></td>
                                    <td>रू <?php echo number_format($sale['total_amount'], 2); ?></td>
                                    <td>
                                        <?php
                                        $status_class = ['Paid' => 'success', 'Partial' => 'warning', 'Due' => 'danger'];
                                        ?>
                                        <span class="badge badge-<?php echo $status_class[$sale['sales_status']]; ?>">
                                            <?php echo $sale['sales_status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="empty-icon">🛒</span>
                        <p>No sales yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <?php if ($stats['low_stock'] > 0 && $low_stock_items->num_rows > 0): ?>
        <div class="modern-alert" id="low-stock-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="color: var(--danger); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <span>⚠️</span> Low Stock Alert
                </h3>
                <a href="inventory/inventory-list.php" class="btn btn-danger">Manage Inventory</a>
            </div>
            <table class="modern-table">
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
                    <?php while ($item = $low_stock_items->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                            <td><span class="badge badge-secondary"><?php echo $item['category']; ?></span></td>
                            <td class="text-danger"><?php echo number_format($item['current_quantity'], 2); ?> <?php echo $item['unit']; ?></td>
                            <td><?php echo number_format($item['minimum_quantity'], 2); ?> <?php echo $item['unit']; ?></td>
                            <td><span class="badge badge-danger"><?php echo number_format($item['shortage'], 2); ?> <?php echo $item['unit']; ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="modern-card">
        <div class="modern-card-header">
            <h3 class="modern-card-title">
                <span>⚡</span> Quick Actions
            </h3>
        </div>
        <div class="quick-actions-grid">
            <a href="cattle/cattle-add.php" class="quick-action-btn">
                <span class="icon">🐄</span> Add Cattle
            </a>
            <a href="milk/milk-add.php" class="quick-action-btn">
                <span class="icon">🥛</span> Add Milk
            </a>
            <a href="sales/sales-add.php" class="quick-action-btn">
                <span class="icon">🛒</span> New Sale
            </a>
            <a href="customers/customer-add.php" class="quick-action-btn">
                <span class="icon">👥</span> Add Customer
            </a>
            <a href="inventory/inventory-list.php" class="quick-action-btn">
                <span class="icon">📦</span> Manage Stock
            </a>
            <a href="users/user-add.php" class="quick-action-btn">
                <span class="icon">👤</span> Add Employee
            </a>
        </div>
    </div>
</div>

<?php
$conn->close();
include '../includes/footer.php';
?>