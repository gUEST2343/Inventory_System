<?php

namespace App\Database\Seeders;

use App\Models\Shoe;
use App\Models\Clothing;
use App\Models\ProductVariant;
use App\Enums\Color;
use App\Enums\ShoeType;
use App\Enums\ClothingType;
use App\Enums\SizeCategory;
use App\Database\DatabaseConnection;

class ProductSeeder
{
    private \PDO $db;
    
    public function __construct()
    {
        $this->db = DatabaseConnection::getInstance();
    }
    
    public function run(): void
    {
        $this->clearExistingData();
        $this->seedShoes();
        $this->seedClothing();
        $this->seedVariants();
        $this->seedInitialStock();
        
        echo "✅ Product seeding completed successfully.\n";
    }
    
    private function clearExistingData(): void
    {
        $tables = ['stock_adjustments', 'product_variants', 'products'];
        
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        
        foreach ($tables as $table) {
            try {
                $this->db->exec("TRUNCATE TABLE $table");
                echo "✓ Cleared table: $table\n";
            } catch (\PDOException $e) {
                echo "⚠️ Could not truncate $table: " . $e->getMessage() . "\n";
            }
        }
        
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
    
    private function seedShoes(): void
    {
        // Updated shoe types to match your enum
        $shoes = [
            [
                'sku' => 'RUN-001',
                'name' => 'AirMax Runner Pro',
                'brand' => 'Nike',
                'price' => 129.99,
                'type' => 'running',  // String instead of enum object
                'size_eu' => 42,
                'material' => 'Mesh',
                'safety_stock' => 10,
            ],
            [
                'sku' => 'CAS-001',
                'name' => 'Classic Leather Sneaker',
                'brand' => 'Adidas',
                'price' => 89.99,
                'type' => 'casual',  // Changed from SNEAKER to CASUAL
                'size_eu' => 43,
                'material' => 'Leather',
                'safety_stock' => 8,
            ],
            [
                'sku' => 'FRM-001',
                'name' => 'Oxford Dress Shoe',
                'brand' => 'Clarks',
                'price' => 159.99,
                'type' => 'formal',  // Changed from OXFORD to FORMAL
                'size_eu' => 44,
                'material' => 'Leather',
                'safety_stock' => 5,
            ],
            [
                'sku' => 'BOT-001',
                'name' => 'Timberland Boot',
                'brand' => 'Timberland',
                'price' => 199.99,
                'type' => 'boot',  // Changed from HIKING_BOOT to BOOT
                'size_eu' => 45,
                'material' => 'Leather',
                'safety_stock' => 6,
            ],
            [
                'sku' => 'SAN-001',
                'name' => 'Comfort Slides',
                'brand' => 'Birkenstock',
                'price' => 69.99,
                'type' => 'sandal',  // Changed from SLIDE to SANDAL
                'size_eu' => 41,
                'material' => 'Synthetic',
                'safety_stock' => 12,
            ],
        ];
        
        $stmt = $this->db->prepare("
            INSERT INTO products (sku, name, brand, price, type, shoe_type, size_eu, material, safety_stock, created_at)
            VALUES (?, ?, ?, ?, 'shoe', ?, ?, ?, ?, NOW())
        ");
        
        $shoeCount = 0;
        
        foreach ($shoes as $shoe) {
            try {
                $stmt->execute([
                    $shoe['sku'],
                    $shoe['name'],
                    $shoe['brand'],
                    $shoe['price'],
                    $shoe['type'],
                    $shoe['size_eu'],
                    $shoe['material'],
                    $shoe['safety_stock'],
                ]);
                
                $shoeCount++;
                echo "✓ Seeded shoe: {$shoe['sku']} - {$shoe['name']}\n";
            } catch (\PDOException $e) {
                echo "❌ Error seeding shoe {$shoe['sku']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "✅ Seeded $shoeCount shoes\n";
    }
    
    private function seedClothing(): void
    {
        $clothing = [
            [
                'sku' => 'SHI-001',
                'name' => 'Classic Oxford Shirt',
                'brand' => 'Ralph Lauren',
                'price' => 79.99,
                'type' => 'shirt',  // String instead of enum object
                'size' => 'M',  // String instead of enum object
                'fabric' => 'Cotton',
                'safety_stock' => 15,
            ],
            [
                'sku' => 'PNT-001',
                'name' => 'Slim Fit Jeans',
                'brand' => 'Levi\'s',
                'price' => 89.99,
                'type' => 'pants',  // String instead of enum object
                'size' => 'L',  // String instead of enum object
                'fabric' => 'Denim',
                'safety_stock' => 12,
            ],
            [
                'sku' => 'JKT-001',
                'name' => 'Bomber Jacket',
                'brand' => 'Alpha Industries',
                'price' => 129.99,
                'type' => 'jacket',  // String instead of enum object
                'size' => 'XL',  // String instead of enum object
                'fabric' => 'Nylon',
                'safety_stock' => 8,
            ],
            [
                'sku' => 'DRS-001',
                'name' => 'Summer Dress',
                'brand' => 'Zara',
                'price' => 59.99,
                'type' => 'dress',  // String instead of enum object
                'size' => 'S',  // String instead of enum object
                'fabric' => 'Linen',
                'safety_stock' => 10,
            ],
            [
                'sku' => 'SWT-001',
                'name' => 'Cashmere Sweater',
                'brand' => 'Banana Republic',
                'price' => 149.99,
                'type' => 'sweater',  // String instead of enum object
                'size' => 'M',  // String instead of enum object
                'fabric' => 'Cashmere',
                'safety_stock' => 7,
            ],
        ];
        
        $stmt = $this->db->prepare("
            INSERT INTO products (sku, name, brand, price, type, clothing_type, size_category, fabric_type, safety_stock, created_at)
            VALUES (?, ?, ?, ?, 'clothing', ?, ?, ?, ?, NOW())
        ");
        
        $clothingCount = 0;
        
        foreach ($clothing as $item) {
            try {
                $stmt->execute([
                    $item['sku'],
                    $item['name'],
                    $item['brand'],
                    $item['price'],
                    $item['type'],
                    $item['size'],
                    $item['fabric'],
                    $item['safety_stock'],
                ]);
                
                $clothingCount++;
                echo "✓ Seeded clothing: {$item['sku']} - {$item['name']}\n";
            } catch (\PDOException $e) {
                echo "❌ Error seeding clothing {$item['sku']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "✅ Seeded $clothingCount clothing items\n";
    }
    
    private function seedVariants(): void
    {
        $variantData = [
            // Shoes variants (different colors for each shoe)
            ['product_sku' => 'RUN-001', 'colors' => ['BLACK', 'WHITE', 'RED'], 'quantity' => 50],
            ['product_sku' => 'CAS-001', 'colors' => ['WHITE', 'NAVY', 'GRAY'], 'quantity' => 40],
            ['product_sku' => 'FRM-001', 'colors' => ['BLACK', 'BROWN'], 'quantity' => 25],
            ['product_sku' => 'BOT-001', 'colors' => ['BROWN', 'BLACK'], 'quantity' => 30],
            ['product_sku' => 'SAN-001', 'colors' => ['BLACK', 'BROWN', 'NAVY'], 'quantity' => 60],
            
            // Clothing variants
            ['product_sku' => 'SHI-001', 'colors' => ['WHITE', 'BLUE', 'PINK'], 'quantity' => 35],
            ['product_sku' => 'PNT-001', 'colors' => ['BLUE', 'BLACK', 'GRAY'], 'quantity' => 45],
            ['product_sku' => 'JKT-001', 'colors' => ['BLACK', 'GREEN', 'NAVY'], 'quantity' => 20],
            ['product_sku' => 'DRS-001', 'colors' => ['RED', 'BLUE', 'YELLOW'], 'quantity' => 30],
            ['product_sku' => 'SWT-001', 'colors' => ['GRAY', 'NAVY', 'BURGUNDY'], 'quantity' => 25],
        ];
        
        $productStmt = $this->db->prepare("SELECT id FROM products WHERE sku = ?");
        $variantStmt = $this->db->prepare("
            INSERT INTO product_variants 
            (product_id, barcode, color, quantity, reserved_quantity, created_at, version) 
            VALUES (?, ?, ?, ?, 0, NOW(), 0)
        ");
        
        $barcodeCounter = 8901234567890; // Start with valid EAN-13
        
        $totalVariants = 0;
        
        foreach ($variantData as $item) {
            $productStmt->execute([$item['product_sku']]);
            $product = $productStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$product) {
                echo "❌ Product not found: {$item['product_sku']}\n";
                continue;
            }
            
            $productId = $product['id'];
            
            foreach ($item['colors'] as $color) {
                try {
                    // Generate valid EAN-13 barcode
                    $barcode = $this->generateValidBarcode($barcodeCounter);
                    $barcodeCounter++;
                    
                    $variantStmt->execute([
                        $productId,
                        $barcode,
                        $color,
                        $item['quantity'],
                    ]);
                    
                    $totalVariants++;
                    echo "✓ Created variant: {$item['product_sku']} - {$color} (Barcode: {$barcode})\n";
                    
                } catch (\PDOException $e) {
                    echo "❌ Error creating variant for {$item['product_sku']} - {$color}: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "✅ Created $totalVariants product variants\n";
    }
    
    private function seedInitialStock(): void
    {
        // Create initial stock adjustments for all variants
        $variantStmt = $this->db->prepare("SELECT id, quantity FROM product_variants");
        $adjustmentStmt = $this->db->prepare("
            INSERT INTO stock_adjustments 
            (variant_id, previous_quantity, new_quantity, adjustment, reason, notes, adjusted_by, adjusted_at)
            VALUES (?, 0, ?, ?, 'initial_count', 'Initial stock seeding', 'seeder', NOW())
        ");
        
        $variants = $variantStmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (!$variants) {
            echo "⚠️ No variants found for stock adjustments\n";
            return;
        }
        
        $variantStmt->execute();
        $variants = $variantStmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $adjustmentCount = 0;
        
        foreach ($variants as $variant) {
            try {
                $adjustmentStmt->execute([
                    $variant['id'],
                    $variant['quantity'],
                    $variant['quantity'], // adjustment = new_quantity - previous_quantity (0)
                ]);
                
                $adjustmentCount++;
            } catch (\PDOException $e) {
                echo "❌ Error creating stock adjustment for variant {$variant['id']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "✅ Created $adjustmentCount initial stock adjustments\n";
    }
    
    private function generateValidBarcode(int $baseNumber): string
    {
        // Ensure 13 digits
        $barcode = str_pad((string)$baseNumber, 13, '0', STR_PAD_LEFT);
        
        // Calculate EAN-13 check digit
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int)$barcode[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        
        $checkDigit = (10 - ($sum % 10)) % 10;
        
        return substr($barcode, 0, 12) . $checkDigit;
    }
}