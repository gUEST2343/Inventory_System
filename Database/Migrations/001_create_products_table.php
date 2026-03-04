<?php

class CreateProductsTable
{
    public function up(): string
    {
        return "
            CREATE TABLE products (
                id INT PRIMARY KEY AUTO_INCREMENT,
                sku VARCHAR(12) UNIQUE NOT NULL,
                name VARCHAR(100) NOT NULL,
                brand VARCHAR(50) NOT NULL,
                type ENUM('shoe', 'clothing') NOT NULL,
                
                -- Common attributes
                price DECIMAL(10,2) NOT NULL CHECK (price >= 0),
                safety_stock INT NOT NULL DEFAULT 0 CHECK (safety_stock >= 0),
                
                -- Shoe specific
                shoe_type ENUM('running', 'casual', 'formal', 'boot', 'sandal', 'slipper') NULL,
                size_eu INT NULL CHECK (size_eu BETWEEN 35 AND 48),
                material VARCHAR(50) NULL,
                
                -- Clothing specific
                clothing_type ENUM('shirt', 'pants', 'jacket', 'dress', 'skirt', 'sweater') NULL,
                size_category ENUM('XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL') NULL,
                fabric_type VARCHAR(50) NULL,
                fit_code VARCHAR(3) NULL,
                
                -- Audit columns
                version INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                -- Check constraints for data integrity
                CONSTRAINT chk_product_type CHECK (
                    (type = 'shoe' AND shoe_type IS NOT NULL AND size_eu IS NOT NULL AND material IS NOT NULL) OR
                    (type = 'clothing' AND clothing_type IS NOT NULL AND size_category IS NOT NULL AND fabric_type IS NOT NULL)
                ),
                
                -- Additional constraints
                CONSTRAINT chk_shoe_size CHECK (
                    type != 'shoe' OR (size_eu BETWEEN 35 AND 48)
                ),
                CONSTRAINT chk_price_range CHECK (
                    price <= 10000
                ),
                CONSTRAINT chk_safety_stock CHECK (
                    safety_stock <= 1000
                ),
                CONSTRAINT chk_fit_code CHECK (
                    fit_code IS NULL OR fit_code IN ('REG', 'SLM', 'OVS', 'TAP')
                )
            );
            
            -- Create indexes for better performance
            CREATE INDEX idx_products_sku ON products(sku);
            CREATE INDEX idx_products_type ON products(type);
            CREATE INDEX idx_products_brand ON products(brand);
            CREATE INDEX idx_products_price ON products(price);
            CREATE INDEX idx_products_created ON products(created_at);
        ";
    }
    
    public function down(): string
    {
        return "
            DROP TABLE IF EXISTS products;
        ";
    }
    
    public function seed(): array
    {
        return [
            "INSERT INTO products (sku, name, brand, type, price, safety_stock, shoe_type, size_eu, material) VALUES
                ('RUN001', 'Running Pro', 'Nike', 'shoe', 129.99, 10, 'running', 42, 'Mesh'),
                ('CAS001', 'Casual Walk', 'Adidas', 'shoe', 89.99, 15, 'casual', 40, 'Leather'),
                ('FOR001', 'Formal Classic', 'Clarks', 'shoe', 149.99, 5, 'formal', 43, 'Leather');",
                
            "INSERT INTO products (sku, name, brand, type, price, safety_stock, clothing_type, size_category, fabric_type, fit_code) VALUES
                ('SHT001', 'Classic Shirt', 'Ralph Lauren', 'clothing', 59.99, 20, 'shirt', 'M', 'Cotton', 'REG'),
                ('PNT001', 'Denim Jeans', 'Levi''s', 'clothing', 79.99, 15, 'pants', 'L', 'Denim', 'SLM'),
                ('JKT001', 'Winter Jacket', 'North Face', 'clothing', 199.99, 8, 'jacket', 'XL', 'Polyester', 'REG');"
        ];
    }
}