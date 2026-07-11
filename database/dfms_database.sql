-- =========================================================
-- DAIRY FARM MANAGEMENT SYSTEM (DFMS)
-- ENHANCED FINAL DATABASE SCRIPT (DEFENCE SAFE)
-- =========================================================
-- 1️⃣ Drop existing database completely
DROP DATABASE IF EXISTS dfms_db;

-- 2️⃣ Create a new fresh database
CREATE DATABASE dfms_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

-- 3️⃣ Use the newly created database
USE dfms_db;
-- Disable foreign key checks for clean slate
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- 1️⃣ ROLE TABLE
-- =========================================================
CREATE TABLE role (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

-- =========================================================
-- 2️⃣ USER TABLE (EMAIL LOGIN)
-- =========================================================
CREATE TABLE user (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    status ENUM('Active','Inactive') DEFAULT 'Active',
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES role(role_id)
);

-- =========================================================
-- 3️⃣ CATTLE TYPE
-- =========================================================
CREATE TABLE cattle_type (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(50) NOT NULL UNIQUE
);

-- =========================================================
-- 4️⃣ BREED
-- =========================================================
CREATE TABLE breed (
    breed_id INT AUTO_INCREMENT PRIMARY KEY,
    breed_name VARCHAR(100) NOT NULL,
    type_id INT NOT NULL,
    FOREIGN KEY (type_id) REFERENCES cattle_type(type_id),
    UNIQUE (breed_name, type_id)
);

-- =========================================================
-- 5️⃣ CATTLE
-- =========================================================
CREATE TABLE cattle (
    cattle_id INT AUTO_INCREMENT PRIMARY KEY,
    tag_id VARCHAR(50) NOT NULL UNIQUE,
    gender ENUM('Male','Female') NOT NULL,
    dob DATE NOT NULL,
    breed_id INT NOT NULL,
    type_id INT NOT NULL,
    status ENUM('Alive','Pregnant','Sold','Dead') DEFAULT 'Alive',
    parent_id INT NULL,
    notes TEXT,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (breed_id) REFERENCES breed(breed_id),
    FOREIGN KEY (type_id) REFERENCES cattle_type(type_id),
    FOREIGN KEY (parent_id) REFERENCES cattle(cattle_id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES user(user_id)
);

-- =========================================================
-- 6️⃣ MILK COLLECTION
-- =========================================================
CREATE TABLE milk_collection (
    milk_id INT AUTO_INCREMENT PRIMARY KEY,
    collection_date DATE NOT NULL,
    shift ENUM('Morning','Evening') NOT NULL,
    quantity DECIMAL(10,2) NOT NULL CHECK (quantity >= 0),
    cattle_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cattle_id) REFERENCES cattle(cattle_id),
    FOREIGN KEY (user_id) REFERENCES user(user_id),
    UNIQUE (cattle_id, collection_date, shift)
);

-- =========================================================
-- 7️⃣ CUSTOMER
-- =========================================================
CREATE TABLE customer (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    advance_balance DECIMAL(10,2) DEFAULT 0,
    due_balance DECIMAL(10,2) DEFAULT 0,
    status ENUM('Active','Inactive') DEFAULT 'Active'
);

-- =========================================================
-- 8️⃣ SALES
-- =========================================================
CREATE TABLE sales (
    sales_id INT AUTO_INCREMENT PRIMARY KEY,
    sales_date DATE NOT NULL,
    total_quantity DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    sales_type ENUM('Retail','Wholesale','Dairy') NOT NULL,
    sales_status ENUM('Paid','Partial','Due') DEFAULT 'Due',
    remarks TEXT,
    customer_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customer(customer_id),
    FOREIGN KEY (user_id) REFERENCES user(user_id)
);

-- =========================================================
-- 9️⃣ SALE_MILK (M:N RESOLUTION)
-- =========================================================
CREATE TABLE sale_milk (
    sale_milk_id INT AUTO_INCREMENT PRIMARY KEY,
    sales_id INT NOT NULL,
    milk_id INT NOT NULL,
    quantity_sold DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sales_id) REFERENCES sales(sales_id) ON DELETE CASCADE,
    FOREIGN KEY (milk_id) REFERENCES milk_collection(milk_id),
    UNIQUE (sales_id, milk_id)
);

-- =========================================================
-- 🔟 PAYMENT
-- =========================================================
CREATE TABLE payment (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_date DATE NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash','Bank','Cheque','Digital') NOT NULL,
    sales_id INT NOT NULL,
    user_id INT NOT NULL,
    FOREIGN KEY (sales_id) REFERENCES sales(sales_id),
    FOREIGN KEY (user_id) REFERENCES user(user_id)
);

-- =========================================================
-- 1️⃣1️⃣ INVENTORY (Dung Included)
-- =========================================================
CREATE TABLE inventory (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL UNIQUE,
    category ENUM('Feed','Medicine','Fertilizer','Supplement','Equipment','Other') NOT NULL,
    unit ENUM('Kg','Bag','Litre','Piece','Box') NOT NULL,
    current_quantity DECIMAL(10,2) DEFAULT 0,
    minimum_quantity DECIMAL(10,2) DEFAULT 0
);

-- =========================================================
-- 1️⃣2️⃣ INVENTORY TRANSACTION
-- =========================================================
CREATE TABLE inventory_transaction (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    user_id INT NOT NULL,
    transaction_type ENUM('IN','OUT','ADJUSTMENT') NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    transaction_date DATE NOT NULL,
    remarks TEXT,
    FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id),
    FOREIGN KEY (user_id) REFERENCES user(user_id)
);

-- =========================================================
-- 🔥 PERFORMANCE INDEXES
-- =========================================================

-- User indexes
CREATE INDEX idx_user_email ON user(email);
CREATE INDEX idx_user_status ON user(status);
CREATE INDEX idx_user_role ON user(role_id);

-- Cattle indexes
CREATE INDEX idx_cattle_status ON cattle(status);
CREATE INDEX idx_cattle_tag ON cattle(tag_id);
CREATE INDEX idx_cattle_type ON cattle(type_id);
CREATE INDEX idx_cattle_breed ON cattle(breed_id);

-- Milk collection indexes
CREATE INDEX idx_milk_date ON milk_collection(collection_date);
CREATE INDEX idx_milk_cattle ON milk_collection(cattle_id);
CREATE INDEX idx_milk_shift ON milk_collection(shift);

-- Sales indexes
CREATE INDEX idx_sales_date ON sales(sales_date);
CREATE INDEX idx_sales_customer ON sales(customer_id);
CREATE INDEX idx_sales_status ON sales(sales_status);
CREATE INDEX idx_sales_type ON sales(sales_type);

-- Payment indexes
CREATE INDEX idx_payment_date ON payment(payment_date);
CREATE INDEX idx_payment_sales ON payment(sales_id);

-- Inventory indexes
CREATE INDEX idx_inventory_category ON inventory(category);
CREATE INDEX idx_inventory_low_stock ON inventory(current_quantity, minimum_quantity);

-- Transaction indexes
CREATE INDEX idx_transaction_date ON inventory_transaction(transaction_date);
CREATE INDEX idx_transaction_type ON inventory_transaction(transaction_type);
CREATE INDEX idx_transaction_inventory ON inventory_transaction(inventory_id);

-- =========================================================
-- ❌ PREVENT NEGATIVE STOCK (BEFORE TRIGGER)
-- =========================================================
DELIMITER //

CREATE TRIGGER prevent_negative_stock
BEFORE INSERT ON inventory_transaction
FOR EACH ROW
BEGIN
    DECLARE stock DECIMAL(10,2);
    SELECT current_quantity INTO stock
    FROM inventory
    WHERE inventory_id = NEW.inventory_id;

    IF NEW.transaction_type = 'OUT' AND NEW.quantity > stock THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Insufficient stock';
    END IF;
END//

DELIMITER ;

-- =========================================================
-- 🔄 UPDATE INVENTORY (AFTER TRIGGER)
-- =========================================================
DELIMITER //

CREATE TRIGGER update_inventory_after_transaction
AFTER INSERT ON inventory_transaction
FOR EACH ROW
BEGIN
    IF NEW.transaction_type = 'IN' THEN
        UPDATE inventory SET current_quantity = current_quantity + NEW.quantity
        WHERE inventory_id = NEW.inventory_id;
    ELSEIF NEW.transaction_type = 'OUT' THEN
        UPDATE inventory SET current_quantity = current_quantity - NEW.quantity
        WHERE inventory_id = NEW.inventory_id;
    ELSEIF NEW.transaction_type = 'ADJUSTMENT' THEN
        UPDATE inventory SET current_quantity = NEW.quantity
        WHERE inventory_id = NEW.inventory_id;
    END IF;
END//

DELIMITER ;

-- =========================================================
-- 📊 REPORTING VIEWS
-- =========================================================

-- =========================================================
-- VIEW 1: LOW STOCK INVENTORY (ALERT SUPPORT)
-- =========================================================
CREATE VIEW low_stock_inventory AS
SELECT 
    inventory_id, 
    item_name, 
    category, 
    unit,
    current_quantity, 
    minimum_quantity,
    (minimum_quantity - current_quantity) as shortage
FROM inventory
WHERE current_quantity <= minimum_quantity;

-- =========================================================
-- VIEW 2: AVAILABLE MILK FOR SALE
-- =========================================================
CREATE VIEW available_milk AS
SELECT 
    mc.milk_id,
    mc.collection_date,
    mc.shift,
    mc.quantity as total_quantity,
    COALESCE(SUM(sm.quantity_sold), 0) as sold_quantity,
    (mc.quantity - COALESCE(SUM(sm.quantity_sold), 0)) as available_quantity,
    c.tag_id,
    c.cattle_id,
    ct.type_name,
    b.breed_name
FROM milk_collection mc
LEFT JOIN sale_milk sm ON mc.milk_id = sm.milk_id
LEFT JOIN cattle c ON mc.cattle_id = c.cattle_id
LEFT JOIN cattle_type ct ON c.type_id = ct.type_id
LEFT JOIN breed b ON c.breed_id = b.breed_id
GROUP BY mc.milk_id
HAVING available_quantity > 0;

-- =========================================================
-- VIEW 3: CUSTOMER BALANCE SUMMARY
-- =========================================================
CREATE VIEW customer_balance_summary AS
SELECT 
    c.customer_id,
    c.customer_name,
    c.phone,
    c.address,
    c.advance_balance,
    c.due_balance,
    c.status,
    COUNT(DISTINCT s.sales_id) as total_sales,
    COALESCE(SUM(s.total_amount), 0) as total_sales_amount,
    COALESCE(SUM(p.amount_paid), 0) as total_paid,
    (COALESCE(SUM(s.total_amount), 0) - COALESCE(SUM(p.amount_paid), 0)) as outstanding_balance
FROM customer c
LEFT JOIN sales s ON c.customer_id = s.customer_id
LEFT JOIN payment p ON s.sales_id = p.sales_id
GROUP BY c.customer_id;

-- =========================================================
-- VIEW 4: DAILY MILK PRODUCTION REPORT
-- =========================================================
CREATE VIEW daily_milk_production AS
SELECT 
    mc.collection_date,
    mc.shift,
    COUNT(DISTINCT mc.cattle_id) as cattle_count,
    SUM(mc.quantity) as total_milk,
    AVG(mc.quantity) as avg_per_cattle,
    ct.type_name
FROM milk_collection mc
JOIN cattle c ON mc.cattle_id = c.cattle_id
JOIN cattle_type ct ON c.type_id = ct.type_id
GROUP BY mc.collection_date, mc.shift, ct.type_name
ORDER BY mc.collection_date DESC, mc.shift;

-- =========================================================
-- VIEW 5: MONTHLY SALES REPORT
-- =========================================================
CREATE VIEW monthly_sales_report AS
SELECT 
    DATE_FORMAT(sales_date, '%Y-%m') as month_year,
    sales_type,
    COUNT(sales_id) as total_transactions,
    SUM(total_quantity) as total_quantity_sold,
    SUM(total_amount) as total_revenue,
    AVG(total_amount) as avg_transaction_value,
    SUM(CASE WHEN sales_status = 'Paid' THEN total_amount ELSE 0 END) as paid_amount,
    SUM(CASE WHEN sales_status = 'Due' THEN total_amount ELSE 0 END) as due_amount,
    SUM(CASE WHEN sales_status = 'Partial' THEN total_amount ELSE 0 END) as partial_amount
FROM sales
GROUP BY DATE_FORMAT(sales_date, '%Y-%m'), sales_type
ORDER BY month_year DESC, sales_type;

-- =========================================================
-- VIEW 6: CATTLE INVENTORY SUMMARY
-- =========================================================
CREATE VIEW cattle_inventory_summary AS
SELECT 
    ct.type_name,
    b.breed_name,
    c.status,
    c.gender,
    COUNT(*) as count,
    AVG(TIMESTAMPDIFF(YEAR, c.dob, CURDATE())) as avg_age_years
FROM cattle c
JOIN cattle_type ct ON c.type_id = ct.type_id
JOIN breed b ON c.breed_id = b.breed_id
GROUP BY ct.type_name, b.breed_name, c.status, c.gender
ORDER BY ct.type_name, b.breed_name, c.status;

-- =========================================================
-- VIEW 7: TOP PERFORMING CATTLE (MILK PRODUCTION)
-- =========================================================
CREATE VIEW top_milk_producers AS
SELECT 
    c.cattle_id,
    c.tag_id,
    ct.type_name,
    b.breed_name,
    COUNT(mc.milk_id) as collection_count,
    SUM(mc.quantity) as total_milk_produced,
    AVG(mc.quantity) as avg_milk_per_collection,
    MAX(mc.collection_date) as last_collection_date
FROM cattle c
JOIN cattle_type ct ON c.type_id = ct.type_id
JOIN breed b ON c.breed_id = b.breed_id
LEFT JOIN milk_collection mc ON c.cattle_id = mc.cattle_id
WHERE c.status = 'Alive' AND c.gender = 'Female'
GROUP BY c.cattle_id
HAVING total_milk_produced > 0
ORDER BY total_milk_produced DESC;

-- =========================================================
-- VIEW 8: INVENTORY TRANSACTION SUMMARY
-- =========================================================
CREATE VIEW inventory_transaction_summary AS
SELECT 
    i.item_name,
    i.category,
    i.unit,
    i.current_quantity,
    SUM(CASE WHEN it.transaction_type = 'IN' THEN it.quantity ELSE 0 END) as total_in,
    SUM(CASE WHEN it.transaction_type = 'OUT' THEN it.quantity ELSE 0 END) as total_out,
    COUNT(it.transaction_id) as transaction_count,
    MAX(it.transaction_date) as last_transaction_date
FROM inventory i
LEFT JOIN inventory_transaction it ON i.inventory_id = it.inventory_id
GROUP BY i.inventory_id
ORDER BY i.category, i.item_name;

-- =========================================================
-- 🔰 DEFAULT DATA
-- =========================================================
INSERT INTO role (role_name) VALUES ('Admin'), ('Employee');

INSERT INTO cattle_type (type_name) VALUES ('Cow'), ('Buffalo'), ('Goat');

INSERT INTO breed (breed_name, type_id) VALUES
('Holstein', 1),
('Jersey', 1),
('Murrah', 2),
('Jaffarabadi', 2),
('Saanen', 3),
('Boer', 3);

INSERT INTO inventory (item_name, category, unit, minimum_quantity) VALUES
('Cattle Feed','Feed','Kg',100),
('Mineral Mix','Supplement','Kg',20),
('Vaccination','Medicine','Piece',10),
('Dung','Fertilizer','Kg',0),
('Hay','Feed','Kg',50),
('Antibiotics','Medicine','Piece',5),
('Deworming Tablets','Medicine','Piece',15);

SET FOREIGN_KEY_CHECKS = 1;


<!-- To count pregnant as alive -->
ALTER TABLE cattle
DROP COLUMN status,
ADD COLUMN life_status ENUM('Alive','Sold','Dead') NOT NULL DEFAULT 'Alive',
ADD COLUMN is_pregnant TINYINT(1) NOT NULL DEFAULT 0;


-- =========================================================
-- ADD CUSTOMER TYPE COLUMN
-- Run this SQL in your database
-- =========================================================

-- Add customer_type column to customer table
ALTER TABLE customer 
ADD COLUMN customer_type ENUM('Retail','Wholesale','Dairy') NOT NULL DEFAULT 'Retail' 
AFTER customer_name;

-- Update existing customers to Retail (default)
UPDATE customer SET customer_type = 'Retail' WHERE customer_type IS NULL;

-- =========================================================
-- VERIFICATION QUERY
-- =========================================================
-- Check if column was added successfully
DESCRIBE customer;



-- 24hrs fresh  milk available and  expired  milk record started
-- =========================================================
-- DAIRY FARM MANAGEMENT SYSTEM
-- Complete Milk Inventory Views
-- =========================================================

-- =========================================================
-- 1. DROP EXISTING VIEWS
-- =========================================================
DROP VIEW IF EXISTS available_milk;
DROP VIEW IF EXISTS all_milk_inventory;

-- =========================================================
-- 2. CREATE all_milk_inventory VIEW (Master View)
-- Shows ALL milk with freshness status
-- Used for: Reports, History, Tracking, Wastage Analysis
-- =========================================================
CREATE VIEW all_milk_inventory AS
SELECT 
    mc.milk_id,
    mc.collection_date,
    mc.shift,
    mc.quantity as total_quantity,
    COALESCE(SUM(sm.quantity_sold), 0) as sold_quantity,
    (mc.quantity - COALESCE(SUM(sm.quantity_sold), 0)) as available_quantity,
    c.tag_id,
    c.cattle_id,
    ct.type_name,
    b.breed_name,
    -- Calculate hours since collection
    TIMESTAMPDIFF(HOUR, 
        CONCAT(mc.collection_date, ' ', 
            CASE 
                WHEN mc.shift = 'Morning' THEN '06:00:00'
                WHEN mc.shift = 'Evening' THEN '18:00:00'
            END
        ), 
        NOW()
    ) as hours_since_collection,
    -- Freshness status
    CASE 
        WHEN TIMESTAMPDIFF(HOUR, 
            CONCAT(mc.collection_date, ' ', 
                CASE 
                    WHEN mc.shift = 'Morning' THEN '06:00:00'
                    WHEN mc.shift = 'Evening' THEN '18:00:00'
                END
            ), 
            NOW()
        ) <= 24 THEN 'Fresh'
        ELSE 'Expired'
    END as milk_status
FROM milk_collection mc
LEFT JOIN sale_milk sm ON mc.milk_id = sm.milk_id
LEFT JOIN cattle c ON mc.cattle_id = c.cattle_id
LEFT JOIN cattle_type ct ON c.type_id = ct.type_id
LEFT JOIN breed b ON c.breed_id = b.breed_id
GROUP BY mc.milk_id
ORDER BY mc.collection_date DESC, mc.shift DESC;

-- =========================================================
-- 3. CREATE available_milk VIEW (Sales View)
-- Shows ONLY fresh milk (<= 24 hours) with available quantity > 0
-- Used for: Sales transactions
-- =========================================================
CREATE VIEW available_milk AS
SELECT 
    milk_id,
    collection_date,
    shift,
    total_quantity,
    sold_quantity,
    available_quantity,
    tag_id,
    cattle_id,
    type_name,
    breed_name,
    hours_since_collection,
    milk_status
FROM all_milk_inventory
WHERE milk_status = 'Fresh'
  AND available_quantity > 0
ORDER BY collection_date DESC, shift DESC;

-- =========================================================
-- 4. WASTAGE TRACKING VIEW
-- Shows expired/wasted milk
-- =========================================================
CREATE VIEW milk_wastage AS
SELECT 
    milk_id,
    collection_date,
    shift,
    total_quantity,
    sold_quantity,
    available_quantity as wasted_quantity,
    (available_quantity * 80) as estimated_loss_retail,  -- Assuming Rs 80/L
    tag_id,
    cattle_id,
    type_name,
    breed_name,
    hours_since_collection,
    milk_status
FROM all_milk_inventory
WHERE milk_status = 'Expired'
  AND available_quantity > 0
ORDER BY collection_date DESC;

-- =========================================================
-- 5. FRESHNESS SUMMARY VIEW
-- Quick dashboard view
-- =========================================================
CREATE VIEW milk_freshness_summary AS
SELECT 
    milk_status,
    COUNT(*) as record_count,
    SUM(total_quantity) as total_collected,
    SUM(sold_quantity) as total_sold,
    SUM(available_quantity) as total_available,
    CASE 
        WHEN milk_status = 'Expired' THEN SUM(available_quantity * 80)
        ELSE 0
    END as estimated_loss
FROM all_milk_inventory
GROUP BY milk_status;

-- =========================================================
-- VERIFICATION QUERIES
-- =========================================================

-- Test 1: View all milk inventory
SELECT 
    milk_id,
    collection_date,
    shift,
    total_quantity,
    sold_quantity,
    available_quantity,
    hours_since_collection,
    milk_status,
    CASE 
        WHEN hours_since_collection <= 12 THEN '🟢 Very Fresh'
        WHEN hours_since_collection <= 18 THEN '🟡 Fresh'
        WHEN hours_since_collection <= 24 THEN '🟠 Still Good'
        ELSE '🔴 Expired'
    END as freshness_indicator
FROM all_milk_inventory
LIMIT 20;

-- Test 2: View only fresh milk (for sales)
SELECT * FROM available_milk LIMIT 10;

-- Test 3: View wastage
SELECT 
    collection_date,
    shift,
    tag_id,
    wasted_quantity,
    estimated_loss_retail,
    hours_since_collection
FROM milk_wastage
ORDER BY collection_date DESC
LIMIT 10;

-- Test 4: Freshness summary
SELECT * FROM milk_freshness_summary;




-- for inventory transaction timestamp
ALTER TABLE inventory_transaction 
ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER transaction_date;

-- =========================================================
-- SAMPLE DATA FOR TESTING (Optional)
-- =========================================================

/*
-- Insert fresh milk (today morning)
INSERT INTO milk_collection (collection_date, shift, quantity, cattle_id, user_id)
VALUES (CURDATE(), 'Morning', 15.5, 1, 1);

-- Insert fresh milk (today evening)
INSERT INTO milk_collection (collection_date, shift, quantity, cattle_id, user_id)
VALUES (CURDATE(), 'Evening', 12.3, 1, 1);

-- Insert old milk (2 days ago - will be expired)
INSERT INTO milk_collection (collection_date, shift, quantity, cattle_id, user_id)
VALUES (DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Morning', 10.0, 1, 1);

-- Insert old milk (yesterday - might be fresh/expired depending on time)
INSERT INTO milk_collection (collection_date, shift, quantity, cattle_id, user_id)
VALUES (DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Evening', 8.5, 1, 1);
*/

-- =========================================================
-- INDEXES FOR PERFORMANCE (Optional but Recommended)
-- =========================================================

-- Index on collection_date for faster date filtering
CREATE INDEX IF NOT EXISTS idx_milk_collection_date 
ON milk_collection(collection_date);

-- Index on milk_id in sale_milk for faster joins
CREATE INDEX IF NOT EXISTS idx_sale_milk_milk_id 
ON sale_milk(milk_id);

-- Composite index for common queries
CREATE INDEX IF NOT EXISTS idx_milk_date_shift 
ON milk_collection(collection_date, shift);

-- for advance method 
ALTER TABLE payment 
MODIFY COLUMN payment_method ENUM('Cash', 'Bank', 'Cheque', 'Digital', 'Advance') NOT NULL;

-- to show advance payment
UPDATE payment 
SET payment_method = 'Advance' 
WHERE payment_method IS NULL OR payment_method = '';

-- =========================================================
-- END OF MILK INVENTORY VIEWS
-- =========================================================


-- end  fresh milk available.

-- =========================================================
-- ✅ END OF ENHANCED DFMS DATABASE
-- =========================================================
-- 
-- FEATURES INCLUDED:
-- ✅ Email-based authentication
-- ✅ Performance indexes for faster queries
-- ✅ Prevent negative stock trigger
-- ✅ Auto-update inventory trigger
-- ✅ 8 comprehensive reporting views
-- ✅ Default data for quick start
-- ✅ Defence presentation ready
-- =========================================================