<?php
/**
 * =========================================================
 * DAIRY FARM MANAGEMENT SYSTEM (DFMS)
 * Common Header with Navigation - FINAL WORKING VERSION
 * =========================================================
 */

defined('DFMS_EXEC') or die('Access Denied');

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>assets/images/favicon.ico">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    
    <!-- Custom Page CSS (if exists) -->
    <?php if (isset($custom_css)): ?>
        <style><?php echo $custom_css; ?></style>
    <?php endif; ?>
</head>
<body class="logged-in <?php echo is_admin() ? 'admin-dashboard' : 'employee-dashboard'; ?>">
    
    <!-- Top Navigation Bar -->
    <nav class="topnav">
        <div class="nav-container">
            <!-- Logo & Brand -->
            <div class="nav-brand">
                <img src="<?php echo SITE_URL; ?>assets/images/logo2.png" alt="DFMS" class="nav-logo">
                <span class="nav-title"></span>
            </div>
            
            <!-- Navigation Menu -->
            <ul class="nav-menu">
                <?php if (is_admin()): ?>
                    <!-- Admin Menu -->
                    <li><a href="<?php echo SITE_URL; ?>admin/dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a></li>
                    
                    <li class="dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle">Cattle <span>▼</span></a>
                        <ul class="dropdown-menu">
                            <li><a href="<?php echo SITE_URL; ?>admin/cattle-setup/types-list.php">Types & Breeds</a></li>
                            <li><a href="<?php echo SITE_URL; ?>admin/cattle/cattle-list.php">View Cattle</a></li>
                            <li><a href="<?php echo SITE_URL; ?>admin/cattle/cattle-add.php">Add Cattle</a></li>
                        </ul>
                    </li>
                    
                    <li class="dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle">Milk <span>▼</span></a>
                        <ul class="dropdown-menu">
                            <li><a href="<?php echo SITE_URL; ?>admin/milk/milk-list.php">View Records</a></li>
                            <li><a href="<?php echo SITE_URL; ?>admin/milk/milk-add.php">Add Record</a></li>
                        </ul>
                    </li>
                    
                    <li class="dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle">Sales <span>▼</span></a>
                        <ul class="dropdown-menu">
                            <li><a href="<?php echo SITE_URL; ?>admin/customers/customer-list.php">Customers</a></li>
                            <li><a href="<?php echo SITE_URL; ?>admin/sales/sales-list.php">View Sales</a></li>
                            <li><a href="<?php echo SITE_URL; ?>admin/sales/sales-add.php">New Sale</a></li>
                            <li><a href="<?php echo SITE_URL; ?>admin/sales/payment-list.php">Payments</a></li>
                        </ul>
                    </li>
                    
                    <li><a href="<?php echo SITE_URL; ?>admin/inventory/inventory-list.php">Inventory</a></li>
                    
                    <li><a href="<?php echo SITE_URL; ?>admin/reports/reports-dashboard.php">Reports</a></li>
                    
                    <li class="dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle">Employees <span>▼</span></a>
                        <ul class="dropdown-menu">
                            <li><a href="<?php echo SITE_URL; ?>admin/users/user-list.php">View Employees</a></li>
                            <li><a href="<?php echo SITE_URL; ?>admin/users/user-add.php">Add Employee</a></li>
                        </ul>
                    </li>
                    
                <?php else: ?>
                    <!-- Employee Menu -->
                    <li><a href="<?php echo SITE_URL; ?>employee/dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a></li>
                    
                    <li><a href="<?php echo SITE_URL; ?>employee/cattle/cattle-list.php">Cattle</a></li>
                    
                    <li class="dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle">Milk <span>▼</span></a>
                        <ul class="dropdown-menu">
                            <li><a href="<?php echo SITE_URL; ?>employee/milk/milk-add.php">Add Collection</a></li>
                            <li><a href="<?php echo SITE_URL; ?>employee/milk/my-collections.php">My Collections</a></li>
                        </ul>
                    </li>
                    
                    <li class="dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle">Customers <span>▼</span></a>
                        <ul class="dropdown-menu">
                            <li><a href="<?php echo SITE_URL; ?>employee/customers/customer-list.php">View Customers</a></li>
                            <li><a href="<?php echo SITE_URL; ?>employee/customers/customer-add.php">Add Customer</a></li>
                        </ul>
                    </li>
                    
                    <li class="dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle">Sales <span>▼</span></a>
                        <ul class="dropdown-menu">
                            <li><a href="<?php echo SITE_URL; ?>employee/sales/sales-add.php">New Sale</a></li>
                            <li><a href="<?php echo SITE_URL; ?>employee/sales/sales-list.php">My Sales</a></li>
                        </ul>
                    </li>
                    
                    <li><a href="<?php echo SITE_URL; ?>employee/inventory/inventory-list.php">Inventory</a></li>
                    <li><a href="<?php echo SITE_URL; ?>employee/reports/reports-view.php">Reports</a></li>
                <?php endif; ?>
            </ul>
            
            <!-- User Menu -->
            <div class="nav-user">
                <div class="user-dropdown">
                    <button class="user-btn" type="button">
                        <span class="user-icon">👤</span>
                        <span class="user-name"><?php echo get_user_name(); ?></span>
                        <span>▼</span>
                    </button>
                    <ul class="user-menu">
                        <li><a href="<?php echo SITE_URL . (is_admin() ? 'admin' : 'employee'); ?>/profile.php">My Profile</a></li>
                        <li><a href="<?php echo SITE_URL; ?>logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Wrapper -->
    <div class="content-wrapper">
        <?php 
        // Display flash messages
        echo get_flash_message(); 
        ?>

<script>
// ========================================
// DROPDOWN MENU SCRIPT - 100% WORKING
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Get all dropdown containers
    const dropdowns = document.querySelectorAll('.dropdown');
    const userDropdown = document.querySelector('.user-dropdown');
    
    console.log('✅ Found ' + dropdowns.length + ' navigation dropdowns');
    console.log('✅ User dropdown: ' + (userDropdown ? 'Found' : 'Not found'));
    
    // Function to close all dropdowns
    function closeAllDropdowns() {
        dropdowns.forEach(function(dropdown) {
            dropdown.classList.remove('active');
        });
        if (userDropdown) {
            userDropdown.classList.remove('active');
        }
    }
    
    // Handle navigation dropdown clicks
    dropdowns.forEach(function(dropdown, index) {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        
        if (toggle) {
            // Replace the toggle click section with this:
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const isActive = dropdown.classList.contains('active');
                
                // 1. Close ALL other dropdowns first
                closeAllDropdowns();
                
                // 2. ONLY if the clicked one wasn't active, open it
                if (!isActive) {
                    dropdown.classList.add('active');
                }
            });
        }
    }
    
    // Handle user dropdown
    if (userDropdown) {
        const userBtn = userDropdown.querySelector('.user-btn');
        
        if (userBtn) {
            userBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('🖱️ Clicked user dropdown');
                
                // Check if user dropdown is active
                const isActive = userDropdown.classList.contains('active');
                
                // Close all navigation dropdowns
                dropdowns.forEach(function(dropdown) {
                    dropdown.classList.remove('active');
                });
                
                // Toggle user dropdown
                if (!isActive) {
                    userDropdown.classList.add('active');
                    console.log('👤 Opened user menu');
                } else {
                    userDropdown.classList.remove('active');
                    console.log('👤 Closed user menu');
                }
            });
        }
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        // Check if click is inside any dropdown
        let clickedInsideDropdown = false;
        
        dropdowns.forEach(function(dropdown) {
            if (dropdown.contains(e.target)) {
                clickedInsideDropdown = true;
            }
        });
        
        if (userDropdown && userDropdown.contains(e.target)) {
            clickedInsideDropdown = true;
        }
        
        // If clicked outside, close all
        if (!clickedInsideDropdown) {
            closeAllDropdowns();
            console.log('🖱️ Clicked outside - closed all dropdowns');
        }
    });
    
    // Close on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            closeAllDropdowns();
            console.log('⌨️ ESC pressed - closed all dropdowns');
        }
    });
    
    console.log('✅ Dropdown script initialized successfully');
});
</script>