<?php
// modules/product_module.php

class ProductModule
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getAllProducts($limit = null, $category = null)
    {
        try {
            $sql = "SELECT * FROM products WHERE status = 'active'";
            $params = [];

            if ($category) {
                $sql .= " AND category = ?";
                $params[] = $category;
            }

            $sql .= " ORDER BY created_at DESC";

            if ($limit) {
                $sql .= " LIMIT ?";
                $params[] = $limit;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get products error: " . $e->getMessage());
            return [];
        }
    }

    public function getProduct($id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get product error: " . $e->getMessage());
            return null;
        }
    }

    public function createProduct($data)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO products (name, description, price, stock_quantity, category, image_url)
                VALUES (?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['price'],
                $data['stock_quantity'],
                $data['category'],
                $data['image_url'] ?? null,
            ]);

            return ['success' => true, 'id' => $stmt->fetchColumn()];
        } catch (PDOException $e) {
            error_log("Create product error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create product'];
        }
    }

    public function updateProduct($id, $data)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE products
                SET name = ?, description = ?, price = ?, stock_quantity = ?,
                    category = ?, image_url = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['price'],
                $data['stock_quantity'],
                $data['category'],
                $data['image_url'] ?? null,
                $id,
            ]);

            return ['success' => true, 'message' => 'Product updated'];
        } catch (PDOException $e) {
            error_log("Update product error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update product'];
        }
    }

    public function deleteProduct($id)
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE products SET status = 'deleted' WHERE id = ?");
            $stmt->execute([$id]);

            return ['success' => true, 'message' => 'Product deleted'];
        } catch (PDOException $e) {
            error_log("Delete product error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete product'];
        }
    }

    public function updateStock($productId, $quantity)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE products
                SET stock_quantity = stock_quantity + ?
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $productId]);

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("Update stock error: " . $e->getMessage());
            return ['success' => false];
        }
    }
}
