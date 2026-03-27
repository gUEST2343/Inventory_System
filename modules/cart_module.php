<?php
// modules/cart_module.php

class CartModule
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function addToCart($userId, $productId, $quantity = 1)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, stock_quantity FROM products
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                return ['success' => false, 'message' => 'Product not found'];
            }

            if ($product['stock_quantity'] < $quantity) {
                return ['success' => false, 'message' => 'Insufficient stock'];
            }

            $stmt = $this->pdo->prepare("
                SELECT id, quantity FROM cart
                WHERE user_id = ? AND product_id = ?
            ");
            $stmt->execute([$userId, $productId]);
            $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cartItem) {
                $newQuantity = $cartItem['quantity'] + $quantity;
                if ($newQuantity > $product['stock_quantity']) {
                    return ['success' => false, 'message' => 'Cannot add more than available stock'];
                }

                $stmt = $this->pdo->prepare("
                    UPDATE cart SET quantity = ? WHERE id = ?
                ");
                $stmt->execute([$newQuantity, $cartItem['id']]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)
                ");
                $stmt->execute([$userId, $productId, $quantity]);
            }

            return ['success' => true, 'message' => 'Added to cart'];
        } catch (PDOException $e) {
            error_log("Add to cart error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to add to cart'];
        }
    }

    public function getCart($userId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.*, p.name, p.price, p.image_url, p.stock_quantity
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ?
            ");
            $stmt->execute([$userId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total = 0;
            foreach ($items as &$item) {
                $item['subtotal'] = $item['price'] * $item['quantity'];
                $total += $item['subtotal'];
            }

            return [
                'items' => $items,
                'total' => $total,
                'item_count' => count($items),
            ];
        } catch (PDOException $e) {
            error_log("Get cart error: " . $e->getMessage());
            return ['items' => [], 'total' => 0, 'item_count' => 0];
        }
    }

    public function updateCartItem($cartId, $quantity)
    {
        try {
            if ($quantity <= 0) {
                return $this->removeFromCart($cartId);
            }

            $stmt = $this->pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->execute([$quantity, $cartId]);

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("Update cart error: " . $e->getMessage());
            return ['success' => false];
        }
    }

    public function removeFromCart($cartId)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM cart WHERE id = ?");
            $stmt->execute([$cartId]);

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("Remove from cart error: " . $e->getMessage());
            return ['success' => false];
        }
    }

    public function clearCart($userId)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$userId]);

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("Clear cart error: " . $e->getMessage());
            return ['success' => false];
        }
    }
}
