<?php
// modules/order_module.php

class OrderModule
{
    private $pdo;
    private $driver;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->driver = $this->pdo instanceof PDO ? (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) : '';
    }

    public function createOrder($userId, $cartItems, $shippingAddress, $billingAddress = null, array $options = [])
    {
        $manageTransaction = $options['manage_transaction'] ?? !$this->pdo->inTransaction();

        try {
            if ($manageTransaction) {
                $this->pdo->beginTransaction();
            }

            $total = 0;
            foreach ($cartItems as $item) {
                $unitPrice = (float)($item['price'] ?? $item['unit_price'] ?? 0);
                $total += $unitPrice * (int)($item['quantity'] ?? 0);
            }

            $orderNumber = 'ORD-' . time() . '-' . $userId;
            $shippingJson = json_encode($shippingAddress);
            $billingJson = json_encode($billingAddress ?: $shippingAddress);

            if ($this->driver === 'pgsql') {
                $stmt = $this->pdo->prepare("
                    INSERT INTO orders (
                        user_id, order_number, total_amount, status, payment_status,
                        shipping_address, billing_address
                    ) VALUES (?, ?, ?, 'pending', 'pending', ?, ?)
                    RETURNING id
                ");
                $stmt->execute([
                    $userId,
                    $orderNumber,
                    $total,
                    $shippingJson,
                    $billingJson,
                ]);
                $orderId = (int)$stmt->fetchColumn();
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO orders (
                        user_id, order_number, total_amount, status, payment_status,
                        shipping_address, billing_address
                    ) VALUES (?, ?, ?, 'pending', 'pending', ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $orderNumber,
                    $total,
                    $shippingJson,
                    $billingJson,
                ]);
                $orderId = (int)$this->pdo->lastInsertId();
            }

            foreach ($cartItems as $item) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO order_items (
                        order_id, product_id, product_name, quantity, unit_price, subtotal
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $unitPrice = (float)($item['price'] ?? $item['unit_price'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 0);
                $subtotal = $unitPrice * $quantity;
                $stmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'] ?? $item['product_name'] ?? ('Product #' . (int)$item['product_id']),
                    $quantity,
                    $unitPrice,
                    $subtotal,
                ]);
            }

            if ($manageTransaction) {
                $this->pdo->commit();
            }

            return [
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total' => $total,
            ];
        } catch (Exception $e) {
            if ($manageTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Create order error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create order'];
        }
    }

    public function updatePaymentStatus($orderId, $transactionId, $status)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE orders
                SET payment_status = ?, transaction_id = ?, status = ?
                WHERE id = ?
            ");
            $orderStatus = ($status == 'paid') ? 'processing' : 'pending';
            $stmt->execute([$status, $transactionId, $orderStatus, $orderId]);

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("Update payment status error: " . $e->getMessage());
            return ['success' => false];
        }
    }

    public function getOrders($userId = null, $status = null, $paymentStatus = null)
    {
        try {
            $sql = "
                SELECT
                    o.*,
                    u.username,
                    u.email,
                    COALESCE(NULLIF(TRIM(u.full_name), ''), NULLIF(TRIM(u.username), ''), NULLIF(TRIM(u.email), ''), 'Guest') AS customer_name,
                    COALESCE(o.order_date, o.created_at) AS display_date,
                    pt.status AS transaction_status,
                    pt.transaction_id AS latest_transaction_id,
                    pt.payment_gateway,
                    pt.checkout_request_id,
                    pt.reference_number,
                    pt.created_at AS payment_created_at,
                    (
                        SELECT COUNT(*)
                        FROM order_items oi
                        WHERE oi.order_id = o.id
                    ) AS item_count
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN payment_transactions pt
                    ON pt.id = (
                        SELECT pt2.id
                        FROM payment_transactions pt2
                        WHERE pt2.order_id = o.id
                        ORDER BY pt2.created_at DESC, pt2.id DESC
                        LIMIT 1
                    )
            ";
            $params = [];

            if ($userId !== null) {
                $sql .= " WHERE o.user_id = ?";
                $params[] = $userId;
            }

            if ($status) {
                $sql .= ($userId !== null) ? " AND" : " WHERE";
                $sql .= " o.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY COALESCE(o.order_date, o.created_at) DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($orders as &$order) {
                $storedPaymentStatus = $this->normalizeOrderPaymentStatus($order['payment_status'] ?? 'pending');
                $effectivePaymentStatus = $this->resolveEffectivePaymentStatus($storedPaymentStatus, $order['transaction_status'] ?? null);
                $order['stored_payment_status'] = $storedPaymentStatus;
                $order['payment_status'] = $effectivePaymentStatus;
                $order['payment_status_mismatch'] = !empty($order['transaction_status'])
                    && $storedPaymentStatus !== $effectivePaymentStatus;
            }
            unset($order);

            if ($paymentStatus) {
                $normalizedFilter = $this->normalizeOrderPaymentStatus($paymentStatus);
                $orders = array_values(array_filter($orders, static function ($order) use ($normalizedFilter) {
                    return ($order['payment_status'] ?? 'pending') === $normalizedFilter;
                }));
            }

            return $orders;
        } catch (PDOException $e) {
            error_log("Get orders error: " . $e->getMessage());
            return [];
        }
    }

    public function getOrderDetails($orderId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    o.*,
                    u.username,
                    u.email,
                    u.phone,
                    COALESCE(NULLIF(TRIM(u.full_name), ''), NULLIF(TRIM(u.username), ''), NULLIF(TRIM(u.email), ''), 'Guest') AS customer_name,
                    COALESCE(o.order_date, o.created_at) AS display_date
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                return null;
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    oi.*,
                    COALESCE(oi.product_name, p.name, CONCAT('Product #', oi.product_id)) AS product_name,
                    COALESCE(oi.price, oi.unit_price, 0) AS price,
                    COALESCE(oi.subtotal, COALESCE(oi.price, oi.unit_price, 0) * oi.quantity) AS subtotal
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
                ORDER BY oi.id ASC
            ");
            $stmt->execute([$orderId]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->pdo->prepare("
                SELECT * FROM payment_transactions WHERE order_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$orderId]);
            $order['transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $latestTransaction = $order['transactions'][0] ?? null;
            $storedPaymentStatus = $this->normalizeOrderPaymentStatus($order['payment_status'] ?? 'pending');
            $order['stored_payment_status'] = $storedPaymentStatus;
            $order['payment_status'] = $this->resolveEffectivePaymentStatus($storedPaymentStatus, $latestTransaction['status'] ?? null);
            $order['payment_status_mismatch'] = $latestTransaction
                ? $storedPaymentStatus !== $order['payment_status']
                : false;
            $order['latest_transaction'] = $latestTransaction;

            return $order;
        } catch (PDOException $e) {
            error_log("Get order details error: " . $e->getMessage());
            return null;
        }
    }

    public function updateOrderStatus($orderId, $status)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE orders
                SET status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$status, $orderId]);

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("Update order status error: " . $e->getMessage());
            return ['success' => false];
        }
    }

    private function normalizeOrderPaymentStatus($status)
    {
        $status = strtolower(trim((string)$status));

        return match ($status) {
            'completed', 'approved', 'success', 'succeeded' => 'paid',
            'failed', 'declined', 'cancelled', 'canceled', 'error' => 'failed',
            'refunded' => 'refunded',
            'paid' => 'paid',
            default => 'pending',
        };
    }

    private function resolveEffectivePaymentStatus($storedPaymentStatus, $transactionStatus)
    {
        $storedPaymentStatus = $this->normalizeOrderPaymentStatus($storedPaymentStatus);
        $transactionStatus = strtolower(trim((string)$transactionStatus));

        if ($transactionStatus === '') {
            return $storedPaymentStatus;
        }

        return match ($transactionStatus) {
            'completed', 'paid', 'approved', 'success', 'succeeded' => 'paid',
            'failed', 'declined', 'cancelled', 'canceled', 'error' => 'failed',
            'refunded' => 'refunded',
            default => $storedPaymentStatus,
        };
    }
}
