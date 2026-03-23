<?php

namespace App\Repositories;

use App\Models\Product;
use PDO;

class ProductRepository
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Find a product by ID
     */
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
    
    /**
     * Find a product by barcode
     */
    public function findByBarcode(string $barcode): ?Product
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM products WHERE barcode = :barcode
        ");
        $stmt->execute(['barcode' => $barcode]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return $this->hydrateProduct($data);
    }
    
    /**
     * Find a product by SKU
     */
    public function findBySku(string $sku): ?Product
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM products WHERE sku = :sku
        ");
        $stmt->execute(['sku' => $sku]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return null;
        }
        
        return $this->hydrateProduct($data);
    }
    
    /**
     * Find all products
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM products WHERE is_active = TRUE ORDER BY name");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $products = [];
        foreach ($data as $row) {
            $products[] = $this->hydrateProduct($row);
        }
        
        return $products;
    }
    
    /**
     * Find products by category ID
     */
    public function findByCategoryId(int $categoryId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM products WHERE category_id = :category_id AND is_active = TRUE ORDER BY name
        ");
        $stmt->execute(['category_id' => $categoryId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $products = [];
        foreach ($data as $row) {
            $products[] = $this->hydrateProduct($row);
        }
        
        return $products;
    }
    
    /**
     * Find low stock products
     */
    public function findLowStock(?int $threshold = null): array
    {
        if ($threshold === null) {
            $stmt = $this->pdo->query("
                SELECT * FROM products 
                WHERE quantity <= reorder_level AND is_active = TRUE 
                ORDER BY quantity
            ");
        } else {
            $stmt = $this->pdo->prepare("
                SELECT * FROM products 
                WHERE quantity <= :threshold AND is_active = TRUE 
                ORDER BY quantity
            ");
            $stmt->execute(['threshold' => $threshold]);
        }
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $products = [];
        foreach ($data as $row) {
            $products[] = $this->hydrateProduct($row);
        }
        
        return $products;
    }
    
    /**
     * Save a product (insert or update)
     */
    public function save(Product $product): bool
    {
        if ($product->getId()) {
            $stmt = $this->pdo->prepare("
                UPDATE products 
                SET sku = :sku, 
                    barcode = :barcode,
                    name = :name,
                    description = :description,
                    category_id = :category_id,
                    unit_price = :unit_price,
                    cost_price = :cost_price,
                    quantity = :quantity,
                    reorder_level = :reorder_level,
                    is_active = :is_active,
                    updated_at = NOW()
                WHERE id = :id
            ");
            return $stmt->execute([
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'barcode' => $product->getBarcode(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'category_id' => $product->getCategoryId(),
                'unit_price' => $product->getUnitPrice(),
                'cost_price' => $product->getCostPrice(),
                'quantity' => $product->getQuantity(),
                'reorder_level' => $product->getReorderLevel(),
                'is_active' => $product->isActive() ? 1 : 0
            ]);
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO products 
            (sku, barcode, name, description, category_id, unit_price, cost_price, quantity, reorder_level, is_active, created_at, updated_at)
            VALUES (:sku, :barcode, :name, :description, :category_id, :unit_price, :cost_price, :quantity, :reorder_level, :is_active, NOW(), NOW())
        ");
        
        return $stmt->execute([
            'sku' => $product->getSku(),
            'barcode' => $product->getBarcode(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'category_id' => $product->getCategoryId(),
            'unit_price' => $product->getUnitPrice(),
            'cost_price' => $product->getCostPrice(),
            'quantity' => $product->getQuantity(),
            'reorder_level' => $product->getReorderLevel(),
            'is_active' => $product->isActive() ? 1 : 0
        ]);
    }
    
    /**
     * Update product quantity
     */
    public function updateQuantity(int $productId, int $newQuantity): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE products 
            SET quantity = :quantity, updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            'quantity' => $newQuantity,
            'id' => $productId
        ]);
    }
    
    /**
     * Adjust product quantity (increase or decrease)
     */
    public function adjustQuantity(int $productId, int $adjustment): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE products 
            SET quantity = quantity + :adjustment, updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            'adjustment' => $adjustment,
            'id' => $productId
        ]);
    }
    
    /**
     * Delete (deactivate) a product
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE products SET is_active = FALSE, updated_at = NOW() WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Count all active products
     */
    public function countAll(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM products WHERE is_active = TRUE");
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Count low stock products
     */
    public function countLowStock(): int
    {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM products WHERE quantity <= reorder_level AND is_active = TRUE
        ");
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Calculate total inventory value
     */
    public function calculateTotalValue(): ?float
    {
        $stmt = $this->pdo->query("
            SELECT SUM(quantity * unit_price) FROM products WHERE is_active = TRUE
        ");
        return (float)$stmt->fetchColumn();
    }
    
    /**
     * Find top selling products
     */
    public function findTopSelling(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.id,
                p.sku,
                p.name,
                p.quantity,
                p.unit_price,
                COALESCE(SUM(sl.quantity_changed), 0) as total_sold
            FROM products p
            LEFT JOIN stock_logs sl ON p.id = sl.product_id AND sl.action = 'sale'
            WHERE p.is_active = TRUE
            GROUP BY p.id, p.sku, p.name, p.quantity, p.unit_price
            ORDER BY total_sold DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Search products by name or SKU
     */
    public function search(string $searchTerm): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM products 
            WHERE (name LIKE :search OR sku LIKE :search OR barcode LIKE :search)
            AND is_active = TRUE 
            ORDER BY name
        ");
        $searchPattern = "%{$searchTerm}%";
        $stmt->execute(['search' => $searchPattern]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $products = [];
        foreach ($data as $row) {
            $products[] = $this->hydrateProduct($row);
        }
        
        return $products;
    }
    
    /**
     * Hydrate Product from database row
     */
    private function hydrateProduct(array $data): Product
    {
        $product = new Product();
        $product->setId($data['id'] ?? null)
                ->setSku($data['sku'] ?? '')
                ->setBarcode($data['barcode'] ?? '')
                ->setName($data['name'] ?? '')
                ->setDescription($data['description'] ?? null)
                ->setCategoryId($data['category_id'] ?? 0)
                ->setUnitPrice((float)($data['unit_price'] ?? 0))
                ->setCostPrice((float)($data['cost_price'] ?? 0))
                ->setQuantity((int)($data['quantity'] ?? 0))
                ->setReorderLevel((int)($data['reorder_level'] ?? 10))
                ->setIsActive($data['is_active'] ?? true)
                ->setCreatedAt(new \DateTime($data['created_at'] ?? 'now'))
                ->setUpdatedAt(new \DateTime($data['updated_at'] ?? 'now'));
        
        return $product;
    }
}
