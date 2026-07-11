<?php
// admin/reports/reports-dashboard.php - FIXED VERSION
session_start();
define('DFMS_EXEC', true);
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

checkAuth();
checkRole(['Admin']);

$page_title = "Reports Dashboard";

// =========================================================
// MILK STATISTICS
// =========================================================
$milk_stats_sql = "SELECT 
                    COUNT(DISTINCT cattle_id) as total_cattle,
                    COUNT(*) as total_collections,
                    COALESCE(SUM(quantity), 0) as total_milk,
                    COALESCE(AVG(quantity), 0) as avg_per_collection
                   FROM milk_collection";
$milk_stats = $conn->query($milk_stats_sql)->fetch_assoc();

// =========================================================
// SALES STATISTICS
// =========================================================
$sales_stats_sql = "SELECT 
                     COUNT(*) as total_sales,
                     COALESCE(SUM(total_amount), 0) as total_invoiced,
                     COALESCE(SUM(total_quantity), 0) as total_quantity,
                     COUNT(DISTINCT customer_id) as total_customers
                    FROM sales";
$sales_stats = $conn->query($sales_stats_sql)->fetch_assoc();

// =========================================================
// PAYMENT STATISTICS (Total Money Actually Received)
// =========================================================
$payment_stats_sql = "SELECT 
                       COUNT(*) as total_payments,
                       COALESCE(SUM(amount_paid), 0) as total_received
                      FROM payment";
$payment_stats = $conn->query($payment_stats_sql)->fetch_assoc();

// =========================================================
// OUTSTANDING DUES - CORRECT CALCULATION
// Outstanding = Total Invoiced - Total Paid
// =========================================================
$outstanding_balance = $sales_stats['total_invoiced'] - $payment_stats['total_received'];

// =========================================================
// COUNT UNPAID SALES (Status = 'Due' or 'Partial')
// =========================================================
$unpaid_sales_sql = "SELECT COUNT(*) as unpaid_count 
                     FROM sales 
                     WHERE sales_status IN ('Due', 'Partial')";
$unpaid_sales = $conn->query($unpaid_sales_sql)->fetch_assoc();

// =========================================================
// SALES BY STATUS BREAKDOWN
// =========================================================
$sales_by_status_sql = "SELECT 
                         sales_status,
                         COUNT(*) as count,
                         COALESCE(SUM(total_amount), 0) as amount
                        FROM sales
                        GROUP BY sales_status";
$sales_by_status_result = $conn->query($sales_by_status_sql);
$sales_by_status = [];
while ($row = $sales_by_status_result->fetch_assoc()) {
    $sales_by_status[$row['sales_status']] = $row;
}

// =========================================================
// INVENTORY STATISTICS
// =========================================================
$inventory_stats_sql = "SELECT 
                         COUNT(*) as total_items,
                         SUM(CASE WHEN current_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
                         SUM(CASE WHEN current_quantity <= minimum_quantity AND current_quantity > 0 THEN 1 ELSE 0 END) as low_stock
                        FROM inventory";
$inventory_stats = $conn->query($inventory_stats_sql)->fetch_assoc();

// =========================================================
// CUSTOMER STATISTICS
// =========================================================
$customer_stats_sql = "SELECT 
                        COUNT(*) as total_customers,
                        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_customers,
                        COALESCE(SUM(advance_balance), 0) as total_advance
                       FROM customer";
$customer_stats = $conn->query($customer_stats_sql)->fetch_assoc();

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
    </div>

    <!-- Quick Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">🥛</div>
            <div class="stat-details">
                <span class="stat-label">Total Milk Collected</span>
                <span class="stat-value"><?php echo number_format($milk_stats['total_milk'], 2); ?> L</span>
                <small style="color: var(--text-medium);">
                    <?php echo number_format($milk_stats['total_collections']); ?> collections from <?php echo $milk_stats['total_cattle']; ?> cattle
                </small>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">💰</div>
            <div class="stat-details">
                <span class="stat-label">Total Revenue (Received)</span>
                <span class="stat-value">रू <?php echo number_format($payment_stats['total_received'], 2); ?></span>
                <small style="color: var(--text-medium);">
                    From <?php echo number_format($payment_stats['total_payments']); ?> payments
                </small>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">⚠</div>
            <div class="stat-details">
                <span class="stat-label">Low/Out Stock Items</span>
                <span class="stat-value"><?php echo $inventory_stats['low_stock'] + $inventory_stats['out_of_stock']; ?></span>
                <small style="color: var(--text-medium);">
                    <?php echo $inventory_stats['out_of_stock']; ?> out of stock, <?php echo $inventory_stats['low_stock']; ?> low
                </small>
            </div>
        </div>
        
        <div class="stat-card stat-danger">
            <div class="stat-icon">⏳</div>
            <div class="stat-details">
                <span class="stat-label">Outstanding Dues</span>
                <span class="stat-value">रू <?php echo number_format($outstanding_balance, 2); ?></span>
                <small style="color: var(--text-medium);">
                    Unpaid from <?php echo $unpaid_sales['unpaid_count']; ?> sales
                </small>
            </div>
        </div>
    </div>

    <!-- Milk Reports Section -->
    <div class="card">
        <div class="card-header" style="background: var(--accent-blue); color: white;">
            <h3>🥛 Milk Production Reports</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                <a href="milk/daily-production.php" class="report-card">
                    <div class="report-icon" style="background: #e3f2fd; color: #1976d2;">📅</div>
                    <div class="report-info">
                        <h4>Daily Production</h4>
                        <p>View daily milk collection by shift and cattle type</p>
                    </div>
                </a>

                <a href="milk/top-producers.php" class="report-card">
                    <div class="report-icon" style="background: #f3e5f5; color: #7b1fa2;">🏆</div>
                    <div class="report-info">
                        <h4>Top Producers</h4>
                        <p>Cattle with highest milk production</p>
                    </div>
                </a>

                <a href="milk/available-milk.php" class="report-card">
                    <div class="report-icon" style="background: #e8f5e9; color: #388e3c;">✓</div>
                    <div class="report-info">
                        <h4>Available Milk</h4>
                        <p>Unsold milk available for sale</p>
                    </div>
                </a>

                <a href="milk/collection-summary.php" class="report-card">
                    <div class="report-icon" style="background: #fff3e0; color: #f57c00;">📊</div>
                    <div class="report-info">
                        <h4>Collection Summary</h4>
                        <p>Overall milk collection statistics</p>
                    </div>
                </a>

                <a href="milk/milk-wastage.php" class="report-card">
                    <div class="report-icon" style="background: #f3e5f5; color: #7b1fa2;">⚠️</div>
                    <div class="report-info">
                        <h4>Milk Wastage</h4>
                        <p>Expired and wasted milk records</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
            
    <!-- Sales Reports Section -->
    <div class="card">
        <div class="card-header" style="background: var(--success); color: white;">
            <h3>💰 Sales & Revenue Reports</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                <a href="sales/monthly-sales.php" class="report-card">
                    <div class="report-icon" style="background: #e8f5e9; color: #388e3c;">📈</div>
                    <div class="report-info">
                        <h4>Monthly Sales</h4>
                        <p>Sales performance by month and type</p>
                    </div>
                </a>

                <a href="sales/customer-balance.php" class="report-card">
                    <div class="report-icon" style="background: #fff3e0; color: #f57c00;">👥</div>
                    <div class="report-info">
                        <h4>Customer Balance</h4>
                        <p>Outstanding and advance balances</p>
                    </div>
                </a>

                <a href="sales/sales-analysis.php" class="report-card">
                    <div class="report-icon" style="background: #f3e5f5; color: #7b1fa2;">📊</div>
                    <div class="report-info">
                        <h4>Sales Analysis</h4>
                        <p>Analysis by type, status, and trends</p>
                    </div>
                </a>

                <a href="sales/revenue-report.php" class="report-card">
                    <div class="report-icon" style="background: #e3f2fd; color: #1976d2;">💵</div>
                    <div class="report-info">
                        <h4>Revenue Report</h4>
                        <p>Revenue and profit analysis</p>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Cattle Reports Section -->
    <div class="card">
        <div class="card-header" style="background: var(--info); color: white;">
            <h3>🐄 Cattle Reports</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                <a href="cattle/cattle-summary.php" class="report-card">
                    <div class="report-icon" style="background: #e3f2fd; color: #1976d2;">📊</div>
                    <div class="report-info">
                        <h4>Cattle Summary</h4>
                        <p>Cattle summary by type, breed, and status</p>
                    </div>
                </a>

                <a href="cattle/age-distribution.php" class="report-card">
                    <div class="report-icon" style="background: #f3e5f5; color: #7b1fa2;">📅</div>
                    <div class="report-info">
                        <h4>Age Distribution</h4>
                        <p>Cattle distribution by age groups</p>
                    </div>
                </a>

                <a href="cattle/breeding-report.php" class="report-card">
                    <div class="report-icon" style="background: #e8f5e9; color: #388e3c;">👶</div>
                    <div class="report-info">
                        <h4>Breeding Report</h4>
                        <p>Mother cattle and offspring tracking</p>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Inventory Reports Section -->
    <div class="card">
        <div class="card-header" style="background: var(--warning); color: white;">
            <h3>📦 Inventory Reports</h3>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                <a href="inventory/low-stock-alert.php" class="report-card">
                    <div class="report-icon" style="background: #ffebee; color: #c62828;">⚠</div>
                    <div class="report-info">
                        <h4>Low Stock Alert</h4>
                        <p>Items running low or out of stock</p>
                    </div>
                </a>

                <a href="inventory/transaction-summary.php" class="report-card">
                    <div class="report-icon" style="background: #e3f2fd; color: #1976d2;">📜</div>
                    <div class="report-info">
                        <h4>Transaction Summary</h4>
                        <p>Stock in/out transaction summary</p>
                    </div>
                </a>

                <a href="inventory/stock-movement.php" class="report-card">
                    <div class="report-icon" style="background: #f3e5f5; color: #7b1fa2;">📊</div>
                    <div class="report-info">
                        <h4>Stock Movement</h4>
                        <p>Detailed stock movement analysis</p>
                    </div>
                </a>

                <a href="inventory/inventory-valuation.php" class="report-card">
                    <div class="report-icon" style="background: #e8f5e9; color: #388e3c;">📋</div>
                    <div class="report-info">
                        <h4>Inventory Status</h4>
                        <p>Current inventory status report</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.report-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: white;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    text-decoration: none;
    transition: var(--transition);
    cursor: pointer;
}

.report-card:hover {
    border-color: var(--accent-blue);
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
}

.report-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    border-radius: 12px;
    flex-shrink: 0;
}

.report-info h4 {
    margin: 0 0 0.25rem 0;
    color: var(--text-dark);
    font-size: 1.1rem;
    font-weight: 600;
}

.report-info p {
    margin: 0;
    color: var(--text-medium);
    font-size: 0.9rem;
}
</style>

<?php
$conn->close();
include '../../includes/footer.php';
?>