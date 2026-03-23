-- ============================================================
-- Inventory System Database Setup for PostgreSQL
-- Database: inventory_system
-- ============================================================

-- Note: Database creation is handled by setup.php

-- ============================================================
-- Users Table (for authentication)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    customer_group VARCHAR(50) DEFAULT 'regular',
    role VARCHAR(20) DEFAULT 'staff' CHECK (role IN ('admin', 'manager', 'staff', 'customer')),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- ============================================================
-- Categories Table
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id INTEGER NULL REFERENCES categories(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_parent (parent_id)
);

-- ============================================================
-- Products Table (Inventory Items)
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    sku VARCHAR(50) UNIQUE NOT NULL,
    barcode VARCHAR(50),
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
    unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    cost_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    quantity INTEGER NOT NULL DEFAULT 0,
    reorder_level INTEGER NOT NULL DEFAULT 10,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_sku (sku),
    INDEX idx_barcode (barcode),
    INDEX idx_category (category_id),
    INDEX idx_name (name),
    INDEX idx_quantity (quantity)
);

-- ============================================================
-- Stock Logs Table (Audit Table for Inventory Changes)
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_logs (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    action VARCHAR(20) NOT NULL CHECK (action IN ('add', 'remove', 'adjust', 'sale', 'return', 'transfer')),
    quantity_before INTEGER NOT NULL,
    quantity_after INTEGER NOT NULL,
    quantity_changed INTEGER NOT NULL,
    reference_number VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_product (product_id),
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
);

-- ============================================================
-- Create trigger for updated_at
-- ============================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers for all tables
CREATE TRIGGER update_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_categories_updated_at
    BEFORE UPDATE ON categories
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_products_updated_at
    BEFORE UPDATE ON products
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_stock_logs_updated_at
    BEFORE UPDATE ON stock_logs
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- ============================================================
-- Insert Default Admin User
-- Password: admin123 (hashed with bcrypt)
-- Default username: admin
-- Default password: admin123
-- ============================================================
INSERT INTO users (username, password, email, full_name, role) 
VALUES (
    'admin', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    'admin@inventorysystem.com', 
    'System Administrator', 
    'admin'
);

-- ============================================================
-- Insert Sample Categories
-- ============================================================
INSERT INTO categories (name, description, parent_id) VALUES
('Electronics', 'Electronic devices and accessories', NULL),
('Computers', 'Laptops, desktops, and computer accessories', 1),
('Mobile Devices', 'Smartphones and tablets', 1),
('Office Supplies', 'General office supplies and stationery', NULL),
('Furniture', 'Office and home furniture', NULL),
('Clothing', 'Apparel and fashion items', NULL);

-- ============================================================
-- Insert Sample Products
-- ============================================================
INSERT INTO products (sku, barcode, name, description, category_id, unit_price, cost_price, quantity, reorder_level) VALUES
('ELEC001', '1234567890123', 'Wireless Mouse', 'Ergonomic wireless mouse with USB receiver', 1, 25.99, 15.00, 100, 20),
('ELEC002', '1234567890124', 'USB Keyboard', 'Mechanical USB keyboard with RGB lighting', 1, 45.99, 28.00, 75, 15),
('COMP001', '1234567890125', 'Laptop Stand', 'Adjustable aluminum laptop stand', 2, 35.99, 20.00, 50, 10),
('COMP002', '1234567890126', 'External HDD 1TB', 'Portable external hard drive 1TB USB 3.0', 2, 59.99, 40.00, 30, 10),
('MOBI001', '1234567890127', 'Phone Charger', 'Fast charging USB-C charger 20W', 3, 19.99, 10.00, 200, 50),
('OFFI001', '1234567890128', 'Ballpoint Pens (Box)', 'Box of 50 blue ballpoint pens', 4, 12.99, 6.00, 150, 30),
('FURN001', '1234567890129', 'Office Chair', 'Ergonomic office chair with lumbar support', 5, 149.99, 90.00, 25, 5),
('CLTH001', '1234567890130', 'T-Shirt (Cotton)', '100% cotton regular fit t-shirt - Black M', 6, 15.99, 8.00, 100, 25);

-- ============================================================
-- Insert Sample Stock Logs
-- ============================================================
INSERT INTO stock_logs (product_id, user_id, action, quantity_before, quantity_after, quantity_changed, reference_number, notes) VALUES
(1, 1, 'add', 0, 100, 100, 'INIT001', 'Initial stock'),
(2, 1, 'add', 0, 75, 75, 'INIT002', 'Initial stock'),
(3, 1, 'add', 0, 50, 50, 'INIT003', 'Initial stock'),
(4, 1, 'add', 0, 30, 30, 'INIT004', 'Initial stock'),
(5, 1, 'add', 0, 200, 200, 'INIT005', 'Initial stock'),
(6, 1, 'add', 0, 150, 150, 'INIT006', 'Initial stock'),
(7, 1, 'add', 0, 25, 25, 'INIT007', 'Initial stock'),
(8, 1, 'add', 0, 100, 100, 'INIT008', 'Initial stock');

-- ============================================================
-- Create Views for Common Queries
-- ============================================================
CREATE OR REPLACE VIEW v_low_stock_products AS
SELECT 
    p.id,
    p.sku,
    p.name,
    p.quantity,
    p.reorder_level,
    c.name AS category_name
FROM products p
JOIN categories c ON p.category_id = c.id
WHERE p.quantity <= p.reorder_level AND p.is_active = true;

CREATE OR REPLACE VIEW v_product_stock_summary AS
SELECT 
    p.id,
    p.sku,
    p.name,
    p.quantity,
    p.unit_price,
    p.cost_price,
    (p.quantity * p.unit_price) AS total_value,
    c.name AS category_name
FROM products p
JOIN categories c ON p.category_id = c.id
WHERE p.is_active = true;

-- ============================================================
-- Grant Privileges (adjust as needed for your setup)
-- ============================================================
-- GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO postgres;
-- GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO postgres;
