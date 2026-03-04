<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\ProductVariant;
use PDO;

class ProductRepository
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    public function findById(int $id): ?Product
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM products WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return $this->hydrateProduct($data);
    }
    
    public function findVariantByBarcode(string $barcode): ?ProductVariant
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM product_variants WHERE barcode = :barcode
        ");
        $stmt->execute(['barcode' => $barcode]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return $this->hydrateVariant($data);
    }
    
    public function findVariantsByProductId(int $productId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM product_variants WHERE product_id = :product_id
        ");
        $stmt->execute(['product_id' => $productId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $variants = [];
        foreach ($data as $row) {
            $variants[] = $this->hydrateVariant($row);
        }
        
        return $variants;
    }
    
    public function saveVariant(ProductVariant $variant): bool
    {
        if ($variant->getId()) {
            $stmt = $this->pdo->prepare("
                UPDATE product_variants 
                SET quantity = :quantity, 
                    reserved_quantity = :reserved_quantity,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            return $stmt->execute([
                'id' => $variant->getId(),
                'quantity' => $variant->getQuantity(),
                'reserved_quantity' => $variant->getReservedQuantity(),
                'updated_at' => $variant->getUpdatedAt()->format('Y-m-d H:i:s')
            ]);
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO product_variants 
            (product_id, barcode, color, size, quantity, reserved_quantity, created_at, updated_at)
            VALUES (:product_id, :barcode, :color, :size, :quantity, :reserved_quantity, :created_at, :updated_at)
        ");
        
        return $stmt->execute([
            'product_id' => $variant->getProductId(),
            'barcode' => $variant->getBarcode(),
            'color' => $variant->getColor()->value,
            'size' => $variant->getSize(),
            'quantity' => $variant->getQuantity(),
            'reserved_quantity' => $variant->getReservedQuantity() ?? 0,
            'created_at' => $variant->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $variant->getUpdatedAt()->format('Y-m-d H:i:s')
        ]);
    }
    
    public function save(Product $product): bool
    {
        if ($product->getId()) {
            $stmt = $this->pdo->prepare("
                UPDATE products 
                SET sku = :sku, 
                    name = :name,
                    type = :type,
                    price = :price,
                    safety_stock = :safety_stock,
                    description = :description,
                    category = :category,
                    brand = :brand,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            return $stmt->execute([
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'type' => $product->getType()->value,
                'price' => $product->getPrice(),
                'safety_stock' => $product->getSafetyStock(),
                'description' => $product->getDescription(),
                'category' => $product->getCategory(),
                'brand' => $product->getBrand(),
                'updated_at' => $product->getUpdatedAt()->format('Y-m-d H:i:s')
            ]);
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO products 
            (sku, name, type, price, safety_stock, description, category, brand, created_at, updated_at)
            VALUES (:sku, :name, :type, :price, :safety_stock, :description, :category, :brand, :created_at, :updated_at)
        ");
        
        return $stmt->execute([
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'type' => $product->getType()->value,
            'price' => $product->getPrice(),
            'safety_stock' => $product->getSafetyStock(),
            'description' => $product->getDescription(),
            'category' => $product->getCategory(),
            'brand' => $product->getBrand(),
            'created_at' => $product->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $product->getUpdatedAt()->format('Y-m-d H:i:s')
        ]);
    }
    
    public function findLowStockVariants(?int $threshold = null): array
    {
        $sql = "
            SELECT v.* 
            FROM product_variants v
            INNER JOIN products p ON v.product_id = p.id
            WHERE (v.quantity - COALESCE(v.reserved_quantity, 0)) <= 
                  COALESCE(:threshold, p.safety_stock, 0)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['threshold' => $threshold]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $variants = [];
        foreach ($data as $row) {
            $variants[] = $this->hydrateVariant($row);
        }
        
        return $variants;
    }
    
    public function countAllVariants(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM product_variants");
        return (int)$stmt->fetchColumn();
    }
    
    public function calculateTotalStockValue(): ?float
    {
        $stmt = $this->pdo->query("
            SELECT SUM(v.quantity * p.price) 
            FROM product_variants v
            INNER JOIN products p ON v.product_id = p.id
        ");
        return $stmt->fetchColumn();
    }
    
    public function countLowStockVariants(): int
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) 
            FROM product_variants v
            INNER JOIN products p ON v.product_id = p.id
            WHERE (v.quantity - COALESCE(v.reserved_quantity, 0)) <= p.safety_stock
        ");
        return (int)$stmt->fetchColumn();
    }
    
    public function countOutOfStockVariants(): int
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM product_variants WHERE quantity = 0
        ");
        return (int)$stmt->fetchColumn();
    }
    
    public function getAverageDailySales(int $productId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT AVG(quantity) 
            FROM stock_adjustments 
            WHERE variant_id IN (
                SELECT id FROM product_variants WHERE product_id = :product_id
            ) 
            AND reason = 'sold' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute(['product_id' => $productId]);
        return (float)$stmt->fetchColumn() ?? 0.0;
    }
    
    public function getTotalSalesLastMonth(): ?float
    {
        $stmt = $this->pdo->query("
            SELECT SUM(a.adjustment * p.price) 
            FROM stock_adjustments a
            INNER JOIN product_variants v ON a.variant_id = v.id
            INNER JOIN products p ON v.product_id = p.id
            WHERE a.reason = 'sold' 
            AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND a.adjustment < 0
        ");
        return $stmt->fetchColumn();
    }
    
    public function getAverageStockValue(): ?float
    {
        $stmt = $this->pdo->query("
            SELECT AVG(v.quantity * p.price) 
            FROM product_variants v
            INNER JOIN products p ON v.product_id = p.id
        ");
        return $stmt->fetchColumn();
    }
    
    public function findTopSellingProducts(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.id,
                p.sku,
                p.name,
                SUM(ABS(a.adjustment)) as total_sold
            FROM stock_adjustments a
            INNER JOIN product_variants v ON a.variant_id = v.id
            INNER JOIN products p ON v.product_id = p.id
            WHERE a.reason = 'sold'
            AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY p.id, p.sku, p.name
            ORDER BY total_sold DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getStockValueByCategory(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                p.category,
                SUM(v.quantity * p.price) as total_value
            FROM product_variants v
            INNER JOIN products p ON v.product_id = p.id
            WHERE p.category IS NOT NULL
            GROUP BY p.category
            ORDER BY total_value DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function hydrateProduct(array $data): Product
    {
        $product = new Product();
        $product->setId($data['id'] ?? null)
                ->setSku($data['sku'] ?? '')
                ->setName($data['name'] ?? '')
                ->setType(\App\Enums\ProductType::from($data['type'] ?? 'other'))
                ->setPrice((float)($data['price'] ?? 0))
                ->setSafetyStock($data['safety_stock'] ?? null)
                ->setDescription($data['description'] ?? null)
                ->setCategory($data['category'] ?? null)
                ->setBrand($data['brand'] ?? null)
                ->setCreatedAt(new \DateTime($data['created_at']))
                ->setUpdatedAt(new \DateTime($data['updated_at']));
        
        return $product;
    }
    
    private function hydrateVariant(array $data): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setId($data['id'] ?? null)
                ->setProductId($data['product_id'] ?? 0)
                ->setBarcode($data['barcode'] ?? '')
                ->setColor(\App\Enums\Color::from($data['color'] ?? 'black'))
                ->setSize($data['size'] ?? null)
                ->setQuantity((int)($data['quantity'] ?? 0))
                ->setReservedQuantity($data['reserved_quantity'] ?? 0)
                ->setCreatedAt(new \DateTime($data['created_at']))
                ->setUpdatedAt(new \DateTime($data['updated_at']));
        
        return $variant;
    }
}