<?php
// employee/reports/reports-view.php
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Employee']);

$page_title = "Reports Dashboard";
$user_id = get_user_id();

// Get quick stats for today
$today = date('Y-m-d');
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM milk_collection WHERE user_id = ? AND collection_date = ?) as today_collections,
                (SELECT COALESCE(SUM(quantity), 0) FROM milk_collection WHERE user_id = ? AND collection_date = ?) as today_milk,
                (SELECT COUNT(*) FROM sales WHERE user_id = ? AND sales_date = ?) as today_sales,
                (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE user_id = ? AND sales_date = ?) as today_revenue";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("isisisii", $user_id, $today, $user_id, $today, $user_id, $today, $user_id, $today);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Get wastage alert
$wastage_sql = "SELECT COUNT(*) as wastage_count, COALESCE(SUM(wasted_quantity), 0) as total_wasted 
                FROM milk_wastage WHERE collection_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
$wastage_result = $conn->query($wastage_sql);
$wastage_alert = $wastage_result->fetch_assoc();

include '../../includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>📊 Reports Dashboard</h1>
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a>
                <span>/</span>
                <span>Reports</span>
            </div>
        </div>
        <div class="header-actions">
            <a href="../dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    </div>

    <!-- Wastage Alert (if any) -->
    <!-- <?php if ($wastage_alert['wastage_count'] > 0): ?>
        <div class="alert alert-warning">
            <span class="alert-icon">⚠️</span>
            <div class="alert-message">
                <strong>Wastage Alert!</strong> 
                <?php echo $wastage_alert['wastage_count']; ?> milk record(s) expired in last 7 days. 
                Total wasted: <?php echo number_format($wastage_alert['total_wasted'], 2); ?> Liters. 
                <a href="milk-wastage.php" style="color: var(--warning); text-decoration: underline; font-weight: bold;">View Wastage Report →</a>
            </div>
        </div>
    <?php endif; ?> -->

    <!-- Today's Quick Stats -->
    <!-- <div class="card" style="background: linear-gradient(135deg, var(--accent-blue), var(--accent-dark)); color: white; margin-bottom: 2rem;">
        <div class="card-body" style="padding: 1.5rem;">
            <h3 style="margin-bottom: 1rem; color: white;">📅 Today's Summary (<?php echo date('d M Y'); ?>)</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                <div>
                    <div style="opacity: 0.9; font-size: 0.9rem;">Milk Collections</div>
                    <div style="font-size: 2rem; font-weight: bold; margin-top: 0.25rem;">
                        <?php echo $stats['today_collections']; ?>
                    </div>
                    <small style="opacity: 0.8;"><?php echo number_format($stats['today_milk'], 2); ?> L</small>
                </div>
                <div>
                    <div style="opacity: 0.9; font-size: 0.9rem;">Sales Recorded</div>
                    <div style="font-size: 2rem; font-weight: bold; margin-top: 0.25rem;">
                        <?php echo $stats['today_sales']; ?>
                    </div>
                    <small style="opacity: 0.8;">रू <?php echo number_format($stats['today_revenue'], 2); ?></small>
                </div>
                <div>
                    <div style="opacity: 0.9; font-size: 0.9rem;">Current Time</div>
                    <div style="font-size: 1.5rem; font-weight: bold; margin-top: 0.25rem;">
                        <?php echo date('h:i A'); ?>
                    </div>
                    <small style="opacity: 0.8;"><?php echo date('l'); ?></small>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Available Reports -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Available Reports</h3>
            <span class="badge badge-info">9 Reports</span>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                
                <!-- Daily Summary -->
                <a href="daily-summary.php" style="text-decoration: none; color: inherit;">
                    <div class="report-card" style="padding: 1.5rem; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 12px; box-shadow: var(--shadow-md); transition: var(--transition); cursor: pointer;" 
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-lg)'" 
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)'">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
                        <h4 style="margin-bottom: 0.5rem; color: white;">Daily Summary</h4>
                        <p style="opacity: 0.9; margin-bottom: 0; font-size: 0.9rem;">
                            Today's complete work summary and performance
                        </p>
                    </div>
                </a>

                <!-- My Milk Records -->
                <a href="my-milk-records.php" style="text-decoration: none; color: inherit;">
                    <div class="report-card" style="padding: 1.5rem; background: linear-gradient(135deg, #2ecc71, #27ae60); color: white; border-radius: 12px; box-shadow: var(--shadow-md); transition: var(--transition); cursor: pointer;" 
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-lg)'" 
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)'">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🥛</div>
                        <h4 style="margin-bottom: 0.5rem; color: white;">My Milk Records</h4>
                        <p style="opacity: 0.9; margin-bottom: 0; font-size: 0.9rem;">
                            Complete history with detailed statistics
                        </p>
                    </div>
                </a>

                <!-- Milk Wastage Report -->
                <a href="milk-wastage.php" style="text-decoration: none; color: inherit;">
                    <div class="report-card" style="padding: 1.5rem; background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; border-radius: 12px; box-shadow: var(--shadow-md); transition: var(--transition); cursor: pointer; position: relative;" 
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-lg)'" 
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)'">
                        <?php if ($wastage_alert['wastage_count'] > 0): ?>
                            <!-- <span style="position: absolute; top: 10px; right: 10px; background: #fff; color: #e74c3c; padding: 0.25rem 0.5rem; border-radius: 20px; font-size: 0.75rem; font-weight: bold;">
                                <?php echo $wastage_alert['wastage_count']; ?> New
                            </span> -->
                        <?php endif; ?>
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🗑️</div>
                        <h4 style="margin-bottom: 0.5rem; color: white;">Milk Wastage</h4>
                        <p style="opacity: 0.9; margin-bottom: 0; font-size: 0.9rem;">
                            Track expired milk and financial losses
                        </p>
                    </div>
                </a>

                <!-- My Sales Records -->
                <a href="my-sales-records.php" style="text-decoration: none; color: inherit;">
                    <div class="report-card" style="padding: 1.5rem; background: linear-gradient(135deg, #3498db, #2980b9); color: white; border-radius: 12px; box-shadow: var(--shadow-md); transition: var(--transition); cursor: pointer;" 
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-lg)'" 
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)'">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🛒</div>
                        <h4 style="margin-bottom: 0.5rem; color: white;">My Sales Records</h4>
                        <p style="opacity: 0.9; margin-bottom: 0; font-size: 0.9rem;">
                            All sales transactions and revenue details
                        </p>
                    </div>
                </a>

                <!-- my Added customers -->
                <a href="customer-analytics.php" style="text-decoration: none; color: inherit;">
                    <div class="report-card" style="padding: 1.5rem; background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; border-radius: 12px; box-shadow: var(--shadow-md); transition: var(--transition); cursor: pointer;" 
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-lg)'" 
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)'">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                        <h4 style="margin-bottom: 0.5rem; color: white;">My Added Customers</h4>
                        <p style="opacity: 0.9; margin-bottom: 0; font-size: 0.9rem;">
                            New walks-in Customer
                        </p>
                    </div>
                </a>

                <!-- Payment Collection -->
                <a href="payment-collection.php" style="text-decoration: none; color: inherit;">
                    <div class="report-card" style="padding: 1.5rem; background: linear-gradient(135deg, #f39c12, #e67e22); color: white; border-radius: 12px; box-shadow: var(--shadow-md); transition: var(--transition); cursor: pointer;" 
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-lg)'" 
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)'">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">💰</div>
                        <h4 style="margin-bottom: 0.5rem; color: white;">Payment Collection</h4>
                        <p style="opacity: 0.9; margin-bottom: 0; font-size: 0.9rem;">
                            All payments received and pending dues
                        </p>
                    </div>
                </a>

                <!-- Milk Shift Summary -->
                <a href="milk-shift-summary.php" style="text-decoration: none; color: inherit;">
                    <div class="report-card" style="padding: 1.5rem; background: linear-gradient(135deg, #1abc9c, #16a085); color: white; border-radius: 12px; box-shadow: var(--shadow-md); transition: var(--transition); cursor: pointer;" 
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-lg)'" 
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)'">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🌅</div>
                        <h4 style="margin-bottom: 0.5rem; color: white;">Shift-wise Report</h4>
                        <p style="opacity: 0.9; margin-bottom: 0; font-size: 0.9rem;">
                            Morning vs Evening shift comparison
                        </p>
                    </div>
                </a>

                <!-- Inventory Usage Report -->
                <a href="inventory-usage.php" style="text-decoration: none; color: inherit;">
                    <div class="report-card" style="padding: 1.5rem; background: linear-gradient(135deg, #34495e, #2c3e50); color: white; border-radius: 12px; box-shadow: var(--shadow-md); transition: var(--transition); cursor: pointer;" 
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-lg)'" 
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)'">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
                        <h4 style="margin-bottom: 0.5rem; color: white;">Inventory Usage</h4>
                        <p style="opacity: 0.9; margin-bottom: 0; font-size: 0.9rem;">
                            Items used and inventory transactions
                        </p>
                    </div>
                </a>

                <!-- My Activity Log -->
                <a href="my-activity-log.php" style="text-decoration: none; color: inherit;">
                    <div class="report-card" style="padding: 1.5rem; background: linear-gradient(135deg, #95a5a6, #7f8c8d); color: white; border-radius: 12px; box-shadow: var(--shadow-md); transition: var(--transition); cursor: pointer;" 
                         onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-lg)'" 
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow-md)'">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                        <h4 style="margin-bottom: 0.5rem; color: white;">My Activity Log</h4>
                        <p style="opacity: 0.9; margin-bottom: 0; font-size: 0.9rem;">
                            All activities and system interactions
                        </p>
                    </div>
                </a>

            </div>
        </div>
    </div>

    <!-- Performance Overview -->
    <!-- <div class="card">
        <div class="card-header">
            <h3>📊 Your Performance Overview</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <?php
            // Get overall statistics
            $month_start = date('Y-m-01');
            
            $overview_sql = "SELECT 
                            (SELECT COUNT(*) FROM milk_collection WHERE user_id = ?) as total_collections,
                            (SELECT COUNT(*) FROM milk_collection WHERE user_id = ? AND collection_date >= ?) as month_collections,
                            (SELECT COALESCE(SUM(quantity), 0) FROM milk_collection WHERE user_id = ?) as total_milk,
                            (SELECT COUNT(DISTINCT collection_date) FROM milk_collection WHERE user_id = ? AND collection_date >= ?) as days_worked_month,
                            (SELECT COUNT(*) FROM sales WHERE user_id = ?) as total_sales,
                            (SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE user_id = ?) as total_revenue,
                            (SELECT COUNT(*) FROM sales WHERE user_id = ? AND sales_date >= ?) as month_sales";
            $overview_stmt = $conn->prepare($overview_sql);
            $overview_stmt->bind_param("iisiiiiiis", $user_id, $user_id, $month_start, $user_id, $user_id, $month_start, $user_id, $user_id, $user_id, $month_start);
            $overview_stmt->execute();
            $overview = $overview_stmt->get_result()->fetch_assoc();
            $overview_stmt->close();
            ?>
            
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">🥛</div>
                    <div class="stat-details">
                        <span class="stat-label">Total Milk Collections</span>
                        <span class="stat-value"><?php echo $overview['total_collections']; ?></span>
                        <small style="color: var(--text-medium);"><?php echo number_format($overview['total_milk'], 2); ?> Liters</small>
                    </div>
                </div>
                
                <div class="stat-card stat-success">
                    <div class="stat-icon">📅</div>
                    <div class="stat-details">
                        <span class="stat-label">This Month Collections</span>
                        <span class="stat-value"><?php echo $overview['month_collections']; ?></span>
                        <small style="color: var(--text-medium);"><?php echo $overview['days_worked_month']; ?> days worked</small>
                    </div>
                </div>
                
                <div class="stat-card stat-info">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-details">
                        <span class="stat-label">Total Sales</span>
                        <span class="stat-value"><?php echo $overview['total_sales']; ?></span>
                        <small style="color: var(--text-medium);">रू <?php echo number_format($overview['total_revenue'], 2); ?></small>
                    </div>
                </div>
                
                <div class="stat-card stat-warning">
                    <div class="stat-icon">📈</div>
                    <div class="stat-details">
                        <span class="stat-label">This Month Sales</span>
                        <span class="stat-value"><?php echo $overview['month_sales']; ?></span>
                        <small style="color: var(--text-medium);">Transactions</small>
                    </div>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Quick Actions -->
    <!-- <div class="card">
        <div class="card-header">
            <h3>⚡ Quick Actions</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="../milk/milk-add.php" class="btn btn-success" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🥛</div>
                    <strong>Add Milk Collection</strong>
                </a>
                
                <a href="../sales/sales-add.php" class="btn btn-primary" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🛒</div>
                    <strong>Create Sale</strong>
                </a>
                
                <a href="../customers/customer-add.php" class="btn btn-info" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">👤</div>
                    <strong>Add Customer</strong>
                </a>
                
                <a href="../sales/sales-list.php" class="btn btn-warning" style="padding: 1rem; height: auto; text-decoration: none;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">💰</div>
                    <strong>View Sales</strong>
                </a>
            </div>
        </div>
    </div> -->

    <!-- Report Guidelines -->
    <!-- <div class="card">
        <div class="card-header">
            <h3>📝 Report Guidelines</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div class="info-box">
                <strong>📊 How to Use Reports:</strong>
                <ul>
                    <li><strong>Daily Summary:</strong> Quick overview of today's work and achievements</li>
                    <li><strong>My Milk Records:</strong> Detailed analysis of all milk collections with trends</li>
                    <li><strong>Milk Wastage:</strong> Track expired milk and identify improvement areas</li>
                    <li><strong>My Sales Records:</strong> Complete sales history with revenue breakdown</li>
                    <li><strong>Customer Analytics:</strong> Analyze customer behavior and payment patterns</li>
                    <li><strong>Payment Collection:</strong> Monitor payment status and outstanding dues</li>
                    <li><strong>Shift Summary:</strong> Compare morning vs evening performance</li>
                    <li><strong>Activity Log:</strong> Track all your system activities chronologically</li>
                    <li><strong>Date Filters:</strong> All reports support custom date ranges for analysis</li>
                    <li><strong>Print Options:</strong> Generate printable reports for your records</li>
                </ul>
            </div>
        </div>
    </div>
</div> -->

<?php
$conn->close();
include '../../includes/footer.php';
?>