<?php

class CreateProductsTable
{
    public function up(): string
    {
        return "
            CREATE TABLE products (
                id SERIAL PRIMARY KEY,
                sku VARCHAR(12) UNIQUE NOT NULL,
                name VARCHAR(100) NOT NULL,
                brand VARCHAR(50) NOT NULL,
                type VARCHAR(20) NOT NULL CHECK (type IN ('shoe', 'clothing')),
                
                -- Common attributes
                price DECIMAL(10,2) NOT NULL CHECK (price >= 0),
                safety_stock INT NOT NULL DEFAULT 0 CHECK (safety_stock >= 0),
                
                -- Shoe specific
                shoe_type VARCHAR(20) CHECK (shoe_type IN ('running', 'casual', 'formal', 'boot', 'sandal', 'slipper')),
                size_eu INT CHECK (size_eu BETWEEN 35 AND 48),
                material VARCHAR(50),
                
                -- Clothing specific
                clothing_type VARCHAR(20) CHECK (clothing_type IN ('shirt', 'pants', 'jacket', 'dress', 'skirt', 'sweater')),
                size_category VARCHAR(5) CHECK (size_category IN ('XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL')),
                fabric_type VARCHAR(50),
                fit_code VARCHAR(3) CHECK (fit_code IN ('REG', 'SLM', 'OVS', 'TAP')),
                
                -- Audit columns
                version INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
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
                )
            );
            
            -- Create indexes for better performance
            CREATE INDEX idx_products_sku ON products(sku);
            CREATE INDEX idx_products_type ON products(type);
            CREATE INDEX idx_products_brand ON products(brand);
            CREATE INDEX idx_products_price ON products(price);
            CREATE INDEX idx_products_created ON products(created_at);
            
            -- Create trigger for updated_at
            CREATE OR REPLACE FUNCTION update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ language 'plpgsql';
            
            CREATE TRIGGER update_products_updated_at
                BEFORE UPDATE ON products
                FOR EACH ROW
                EXECUTE FUNCTION update_updated_at_column();
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