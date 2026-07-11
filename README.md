

<!-- admin login credentials -->
Role: Admin
Email: admin@dfms.com
Password: password

<!-- folder structure we have -->
dfms/
│
├── 📁 assets/
│   ├── 📁 css/
│   │   └── style.css                          # All CSS styles (Pure CSS only)
│   │
│   ├── 📁 js/
│   │   └── script.js                          # All JavaScript (Vanilla JS only)
│   │
│   └── 📁 images/
│       ├── logo.png                           # Logo
│       ├── default-avatar.png                 # Default user image
│       └── favicon.ico                        # Website icon
│
├── 📁 database/
│   └── dfms_database.sql                      # Enhanced database schema
│
├── 📁 includes/
│   ├── config.php                             # Database connection
│   ├── auth.php                               # ✅ NEW - Session & role validation
│   ├── header.php                             # Common header with navigation
│   ├── footer.php                             # Common footer
│   ├── functions.php                          # Helper functions
│   ├── validation.php                         # ✅ NEW - Form validation
│   └── database-helpers.php                   # ✅ NEW - Common queries
│
├── 📁 api/                                    # ✅ NEW - AJAX endpoints (Pure PHP)
│   ├── get-breeds.php                         # Get breeds by cattle type
│   ├── get-available-milk.php                 # Get unsold milk for sales
│   ├── check-stock.php                        # Check inventory before transaction
│   ├── get-customer-balance.php               # Get customer balance
│   ├── get-cattle-details.php                 # Get cattle information
│   └── validate-email.php                     # Check email availability
│
├── 📁 admin/
│   ├── dashboard.php                          # Admin dashboard with statistics
│   │
│   ├── 📁 cattle-setup/                       # ✅ NEW - Types & Breeds management
│   │   ├── types-list.php                     # View all cattle types
│   │   ├── type-add.php                       # Add new cattle type
│   │   ├── type-edit.php                      # Edit cattle type
│   │   ├── type-delete.php                    # Delete cattle type
│   │   ├── breeds-list.php                    # View all breeds
│   │   ├── breed-add.php                      # Add new breed
│   │   ├── breed-edit.php                     # Edit breed
│   │   └── breed-delete.php                   # Delete breed
│   │
│   ├── 📁 cattle/
│   │   ├── cattle-list.php                    # View all cattle
│   │   ├── cattle-add.php                     # Add new cattle
│   │   ├── cattle-edit.php                    # Edit cattle
│   │   ├── cattle-delete.php                  # Delete cattle
│   │   └── cattle-view.php                    # ✅ NEW - View single cattle details
│   │
│   ├── 📁 milk/
│   │   ├── milk-list.php                      # View milk records
│   │   ├── milk-add.php                       # Add milk record
│   │   ├── milk-edit.php                      # Edit milk record
│   │   ├── milk-delete.php                    # Delete milk record
│   │   └── milk-view.php                      # ✅ NEW - View single milk record
│   │
│   ├── 📁 customers/
│   │   ├── customer-list.php                  # View all customers
│   │   ├── customer-add.php                   # Add new customer
│   │   ├── customer-edit.php                  # Edit customer
│   │   ├── customer-delete.php                # Delete customer
│   │   ├── customer-view.php                  # ✅ NEW - View customer details
│   │   └── customer-ledger.php                # ✅ NEW - Customer transaction history
│   │
│   ├── 📁 sales/
│   │   ├── sales-list.php                     # View all sales
│   │   ├── sales-add.php                      # Record new sale
│   │   ├── sales-edit.php                     # Edit sale
│   │   ├── sales-delete.php                   # Delete sale
│   │   ├── sales-view.php                     # ✅ NEW - View sale details (includes sale_milk)
│   │   ├── payment-add.php                    # Add payment
│   │   ├── payment-list.php                   # ✅ NEW - View all payments
│   │   └── payment-history.php                # ✅ NEW - Payment history per sale
│   │
│   ├── 📁 inventory/
│   │   ├── inventory-list.php                 # View inventory
│   │   ├── inventory-add.php                  # Add item
│   │   ├── inventory-edit.php                 # Edit item
│   │   ├── inventory-delete.php               # Delete item
│   │   ├── stock-in.php                       # Add stock (IN transaction)
│   │   ├── stock-out.php                      # Remove stock (OUT transaction)
│   │   ├── stock-adjustment.php               # ✅ NEW - Adjust stock
│   │   └── transaction-history.php            # ✅ NEW - View all transactions
│   │
│   ├── 📁 reports/                            # 🔄 RESTRUCTURED
│   │   ├── reports-dashboard.php              # Reports home page
│   │   │
│   │   ├── 📁 milk/                           # ✅ NEW - Milk reports
│   │   │   ├── daily-production.php           # Daily milk production (uses view)
│   │   │   ├── top-producers.php              # Top milk producing cattle (uses view)
│   │   │   ├── available-milk.php             # Available milk for sale (uses view)
│   │   │   └── collection-summary.php         # Overall milk collection summary
│   │   │
│   │   ├── 📁 sales/                          # ✅ NEW - Sales reports
│   │   │   ├── monthly-sales.php              # Monthly sales report (uses view)
│   │   │   ├── customer-balance.php           # Customer balance summary (uses view)
│   │   │   ├── sales-analysis.php             # Sales analysis by type/status
│   │   │   └── revenue-report.php             # Revenue & profit analysis
│   │   │
│   │   ├── 📁 inventory/                      # ✅ NEW - Inventory reports
│   │   │   ├── low-stock-alert.php            # Low stock alerts (uses view)
│   │   │   ├── transaction-summary.php        # Transaction summary (uses view)
│   │   │   ├── stock-movement.php             # Stock movement report
│   │   │   └── inventory-valuation.php        # Inventory value report
│   │   │
│   │   ├── 📁 cattle/                         # ✅ NEW - Cattle reports
│   │   │   ├── cattle-summary.php             # Cattle inventory summary (uses view)
│   │   │   ├── breeding-report.php            # Breeding & offspring report
│   │   │   ├── age-distribution.php           # Age distribution of cattle
│   │   │   └── health-status.php              # Cattle health status report
│   │   │
│   │   └── 📁 financial/                      # ✅ NEW - Financial reports
│   │       ├── income-statement.php           # Income vs expenses
│   │       ├── profit-loss.php                # Profit & loss statement
│   │       └── cash-flow.php                  # Cash flow report
│   │
│   └── 📁 users/
│       ├── user-list.php                      # View all users
│       ├── user-add.php                       # Add new user
│       ├── user-edit.php                      # Edit user
│       ├── user-delete.php                    # Delete user
│       ├── user-view.php                      # ✅ NEW - View user profile
│       ├── role-management.php                # ✅ NEW - Manage roles & permissions
│       └── activity-log.php                   # ✅ NEW - User activity logs
│
├──📁 employee/
|   ├── dashboard.php                    ✅ Already planned
|   │
|   ├── 📁 cattle/
|   │   ├── cattle-list.php             ✅ View only
|   │   └── cattle-view.php             ✅ View details
|   │
|   ├── 📁 milk/
|   │   ├── milk-list.php               ✅ View own records
|   │   ├── milk-add.php                ✅ Add milk
|   │   └── my-collections.php          ✅ Personal history
|   │
|   ├── 📁 customers/                    ✨ NEW
|   │   ├── customer-list.php           ✅ View all customers
|   │   ├── customer-add.php            ✅ Add new customer (walk-ins)
|   │   └── customer-view.php           ✅ View customer details (readonly)
|   │
|   ├── 📁 sales/                        ✨ NEW
|   │   ├── sales-add.php               ✅ Create new sale
|   │   ├── sales-list.php              ✅ View own sales (readonly)
|   │   └── payment-add.php             ✅ Record payment
|   │
|   ├── 📁 inventory/
|   │   ├── inventory-list.php          ✅ View inventory
|   │   └── inventory-usage.php         ✅ Record usage
|   │
|   └── profile.php                      ✅ Employee profile
│
├── 📄 index.php                               # Landing page (redirects to login)
├── 📄 login.php                               # Login page (email-based)
├── 📄 logout.php                              # Logout handler
├── 📄 403.php                                 # ✅ NEW - Access denied page
├── 📄 404.php                                 # ✅ NEW - Page not found
└── 📄 README.md                               # Project documentation

<!-- To count pregnant as alive -->
ALTER TABLE cattle
DROP COLUMN status,
ADD COLUMN life_status ENUM('Alive','Sold','Dead') NOT NULL DEFAULT 'Alive',
ADD COLUMN is_pregnant TINYINT(1) NOT NULL DEFAULT 0;


