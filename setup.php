<?php
/**
 * Database Setup Script - Fixed Version
 * This script creates the database and imports the SQL file for PostgreSQL
 * 
 * Run this in your browser: http://localhost/inventory-system/setup.php
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Inventory System - Database Setup (PostgreSQL)</h1>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";

// Database configuration
$host = 'localhost';
$port = '5432';
$username = 'postgres';
$password = 'Root';
$dbname = 'Inventory_DB';

echo "Configuration:\n";
echo "  Host: $host\n";
echo "  Port: $port\n";
echo "  Database: $dbname\n";
echo "  Username: $username\n\n";

try {
    // Connect to PostgreSQL server (without database)
    echo "Step 1: Connecting to PostgreSQL server...\n";
    $dsn = "pgsql:host={$host};port={$port};dbname=postgres";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✓ Connected to PostgreSQL server successfully!\n\n";
    
    // Check if database exists, if not create it
    echo "Step 2: Checking database '$dbname'...\n";
    $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '$dbname'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $pdo->exec("CREATE DATABASE \"$dbname\" ENCODING 'UTF8'");
        echo "✓ Database '$dbname' created successfully!\n\n";
    } else {
        echo "✓ Database '$dbname' already exists!\n\n";
    }
    
    // Close connection and connect to the new database
    $pdo = null;
    echo "Step 3: Connecting to database '$dbname'...\n";
    $dsn = "pgsql:host={$host};port={$port};dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Set schema to public
    $pdo->exec("SET search_path TO public");
    echo "✓ Connected to database '$dbname'!\n\n";
    
    // Create tables manually with proper SQL
    echo "Step 4: Creating tables...\n\n";
    
    // Users table
    echo "Creating users table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ users table created\n";
    
    // Categories table
    echo "Creating categories table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        parent_id INTEGER NULL REFERENCES categories(id) ON DELETE SET NULL,
        is_active BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ categories table created\n";
    
    // Products table
    echo "Creating products table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ products table created\n";
    
    // Stock logs table
    echo "Creating stock_logs table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_logs (
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✓ stock_logs table created\n";
    
    $pdo->exec("ALTER TABLE stock_logs ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    
    // Create indexes
    echo "\nCreating indexes...\n";
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_username ON users(username)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_email ON users(email)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_role ON users(role)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_name ON categories(name)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_parent ON categories(parent_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sku ON products(sku)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_barcode ON products(barcode)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_category ON products(category_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_products_name ON products(name)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_quantity ON products(quantity)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_product ON stock_logs(product_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user ON stock_logs(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_action ON stock_logs(action)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_created ON stock_logs(created_at)");
    echo "✓ Indexes created\n";
    
    // Create trigger function
    echo "\nCreating trigger function...\n";
    $pdo->exec("CREATE OR REPLACE FUNCTION update_updated_at_column()
    RETURNS TRIGGER AS \$\$
    BEGIN
        NEW.updated_at = CURRENT_TIMESTAMP;
        RETURN NEW;
    END;
    \$\$ language 'plpgsql'");
    echo "✓ Trigger function created\n";
    
    // Recreate triggers so setup can be run multiple times safely
    echo "Creating triggers...\n";
    $pdo->exec("DROP TRIGGER IF EXISTS update_users_updated_at ON users");
    $pdo->exec("DROP TRIGGER IF EXISTS update_categories_updated_at ON categories");
    $pdo->exec("DROP TRIGGER IF EXISTS update_products_updated_at ON products");
    $pdo->exec("DROP TRIGGER IF EXISTS update_stock_logs_updated_at ON stock_logs");
    $pdo->exec("CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()");
    $pdo->exec("CREATE TRIGGER update_categories_updated_at BEFORE UPDATE ON categories FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()");
    $pdo->exec("CREATE TRIGGER update_products_updated_at BEFORE UPDATE ON products FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()");
    $pdo->exec("CREATE TRIGGER update_stock_logs_updated_at BEFORE UPDATE ON stock_logs FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()");
    echo "✓ Triggers created\n";
    
    // Insert admin user
    echo "\nStep 5: Inserting admin user...\n";
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (username, password, email, full_name, role) VALUES ('admin', '$password_hash', 'admin@inventorysystem.com', 'System Administrator', 'admin') ON CONFLICT (username) DO NOTHING");
    echo "✓ Admin user created (username: admin, password: admin123)\n";
    
    // Insert sample categories
    echo "\nStep 6: Inserting sample categories...\n";
    $pdo->exec("INSERT INTO categories (name, description, parent_id)
        SELECT data.name, data.description, data.parent_id
        FROM (
            VALUES
                ('Electronics', 'Electronic devices and accessories', NULL),
                ('Computers', 'Laptops, desktops, and computer accessories', 1),
                ('Mobile Devices', 'Smartphones and tablets', 1),
                ('Office Supplies', 'General office supplies and stationery', NULL),
                ('Furniture', 'Office and home furniture', NULL),
                ('Clothing', 'Apparel and fashion items', NULL)
        ) AS data(name, description, parent_id)
        WHERE NOT EXISTS (
            SELECT 1
            FROM categories c
            WHERE c.name = data.name
        )");
    echo "✓ Sample categories inserted\n";
    
    // Insert sample products
    echo "\nStep 7: Inserting sample products...\n";
    $pdo->exec("INSERT INTO products (sku, barcode, name, description, category_id, unit_price, cost_price, quantity, reorder_level) VALUES
        ('ELEC001', '1234567890123', 'Wireless Mouse', 'Ergonomic wireless mouse with USB receiver', 1, 25.99, 15.00, 100, 20),
        ('ELEC002', '1234567890124', 'USB Keyboard', 'Mechanical USB keyboard with RGB lighting', 1, 45.99, 28.00, 75, 15),
        ('COMP001', '1234567890125', 'Laptop Stand', 'Adjustable aluminum laptop stand', 2, 35.99, 20.00, 50, 10),
        ('COMP002', '1234567890126', 'External HDD 1TB', 'Portable external hard drive 1TB USB 3.0', 2, 59.99, 40.00, 30, 10),
        ('MOBI001', '1234567890127', 'Phone Charger', 'Fast charging USB-C charger 20W', 3, 19.99, 10.00, 200, 50),
        ('OFFI001', '1234567890128', 'Ballpoint Pens (Box)', 'Box of 50 blue ballpoint pens', 4, 12.99, 6.00, 150, 30),
        ('FURN001', '1234567890129', 'Office Chair', 'Ergonomic office chair with lumbar support', 5, 149.99, 90.00, 25, 5),
        ('CLTH001', '1234567890130', 'T-Shirt (Cotton)', '100% cotton regular fit t-shirt - Black M', 6, 15.99, 8.00, 100, 25)
    ON CONFLICT (sku) DO NOTHING");
    echo "✓ Sample products inserted\n";
    
    // Insert stock logs
    echo "\nStep 8: Inserting stock logs...\n";
    $pdo->exec("INSERT INTO stock_logs (
            product_id, user_id, action, quantity_before, quantity_after, quantity_changed, reference_number, notes
        )
        SELECT
            data.product_id,
            data.user_id,
            data.action,
            data.quantity_before,
            data.quantity_after,
            data.quantity_changed,
            data.reference_number,
            data.notes
        FROM (
            VALUES
                (1, 1, 'add', 0, 100, 100, 'INIT001', 'Initial stock'),
                (2, 1, 'add', 0, 75, 75, 'INIT002', 'Initial stock'),
                (3, 1, 'add', 0, 50, 50, 'INIT003', 'Initial stock'),
                (4, 1, 'add', 0, 30, 30, 'INIT004', 'Initial stock'),
                (5, 1, 'add', 0, 200, 200, 'INIT005', 'Initial stock'),
                (6, 1, 'add', 0, 150, 150, 'INIT006', 'Initial stock'),
                (7, 1, 'add', 0, 25, 25, 'INIT007', 'Initial stock'),
                (8, 1, 'add', 0, 100, 100, 'INIT008', 'Initial stock')
        ) AS data(product_id, user_id, action, quantity_before, quantity_after, quantity_changed, reference_number, notes)
        WHERE NOT EXISTS (
            SELECT 1
            FROM stock_logs s
            WHERE s.reference_number = data.reference_number
        )");
    echo "✓ Stock logs inserted\n";
    
    // Verify tables
    echo "\n========================================\n";
    echo "Verifying database tables...\n";
    $tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "  ✓ $table\n";
    }
    
    // Check admin user
    echo "\n========================================\n";
    echo "Checking admin user...\n";
    $stmt = $pdo->query("SELECT username, email, role FROM users WHERE username = 'admin'");
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "  ✓ Admin user: {$admin['username']} ({$admin['role']})\n";
        echo "  ✓ Email: {$admin['email']}\n";
        echo "  ✓ Password: admin123\n";
    }
    
    echo "\n========================================\n";
    echo "✓ Database setup completed successfully!\n";
    echo "========================================\n";
    echo "\nYou can now login at:\n";
    echo "  <a href='http://localhost/inventory-system/login.php' target='_blank'>http://localhost/inventory-system/login.php</a>\n";
    echo "\nLogin credentials:\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n";
    echo "\n";
    echo "<strong>IMPORTANT: Delete this file (setup.php) after setup for security!</strong>\n";
    
} catch (PDOException $e) {
    echo "✗ Database setup failed!\n";
    echo "========================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "  1. Make sure PostgreSQL is running\n";
    echo "  2. Check if username '$username' is correct\n";
    echo "  3. Check if password '$password' is correct\n";
    echo "  4. Verify PostgreSQL is accepting connections on port $port\n";
}

echo "</pre>";
?>
