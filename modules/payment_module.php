<?php
// modules/payment_module.php

class PaymentModule
{
    private $pdo;
    private $driver;
    private $logFile;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->driver = $this->pdo instanceof PDO ? (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) : '';
        $this->logFile = dirname(__DIR__) . '/logs/payment_sync.log';
    }

    public function processPayment($orderId, $paymentMethod, $paymentDetails, array $options = [])
    {
        try {
            $order = $this->getOrder($orderId);
            if (!$order) {
                throw new Exception('Order not found');
            }

            $paymentResult = $options['payment_result'] ?? $this->simulatePaymentGateway($paymentMethod, $order, $paymentDetails);
            $transactionStatus = $this->normalizeTransactionStatus($paymentResult['status'] ?? 'pending');
            $transactionId = $options['transaction_id']
                ?? ($paymentResult['transaction_id'] ?? null)
                ?? $this->generateTransactionId();

            $syncResult = $this->syncPaymentStatus($orderId, $transactionStatus, [
                'transaction_id' => $transactionId,
                'payment_gateway' => $paymentMethod,
                'payment_method' => $paymentMethod,
                'amount' => $order['total_amount'] ?? 0,
                'checkout_request_id' => $options['checkout_request_id'] ?? null,
                'reference_number' => $options['reference_number'] ?? ($paymentResult['response']['id'] ?? null),
                'gateway_response' => $paymentResult['response'] ?? $paymentDetails,
                'message' => $paymentResult['message'] ?? null,
                'source' => $options['source'] ?? 'processPayment',
            ], $options);

            if (!$syncResult['success']) {
                return $syncResult;
            }

            return [
                'success' => true,
                'status' => $transactionStatus,
                'payment_status' => $syncResult['payment_status'],
                'transaction_id' => $syncResult['transaction_id'],
                'message' => $paymentResult['message'] ?? 'Payment processed successfully.',
            ];
        } catch (Exception $e) {
            $this->logSyncError('Payment processing error', [
                'order_id' => $orderId,
                'payment_method' => $paymentMethod,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Payment failed'];
        }
    }

    public function initializePaymentRecord($orderId, $paymentMethod, array $paymentDetails = [], array $options = [])
    {
        $manageTransaction = $options['manage_transaction'] ?? !$this->pdo->inTransaction();

        try {
            if ($manageTransaction) {
                $this->pdo->beginTransaction();
            }

            $order = $this->getOrder($orderId, true);
            if (!$order) {
                throw new Exception('Order not found');
            }

            $existingTransaction = $this->findTransactionForUpdate($orderId, $options, true);
            $normalizedStatus = $this->normalizeTransactionStatus($options['status'] ?? 'pending');
            $gatewayResponse = $this->encodePayload($paymentDetails);
            $amount = $options['amount'] ?? ($order['total_amount'] ?? 0);

            if ($existingTransaction && empty($options['force_new'])) {
                $stmt = $this->pdo->prepare("
                    UPDATE payment_transactions
                    SET payment_gateway = COALESCE(?, payment_gateway),
                        amount = COALESCE(?, amount),
                        status = ?,
                        gateway_response = COALESCE(?, gateway_response),
                        checkout_request_id = COALESCE(?, checkout_request_id),
                        reference_number = COALESCE(?, reference_number),
                        updated_at = " . $this->currentTimestampSql() . "
                    WHERE id = ?
                ");
                $stmt->execute([
                    $paymentMethod ?: null,
                    $amount,
                    $normalizedStatus,
                    $gatewayResponse,
                    $options['checkout_request_id'] ?? null,
                    $options['reference_number'] ?? null,
                    $existingTransaction['id'],
                ]);

                $transactionId = $existingTransaction['transaction_id'] ?? null;
            } else {
                $transactionId = $options['transaction_id'] ?? null;
                $stmt = $this->pdo->prepare("
                    INSERT INTO payment_transactions (
                        order_id,
                        transaction_id,
                        payment_gateway,
                        amount,
                        status,
                        gateway_response,
                        checkout_request_id,
                        reference_number
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $orderId,
                    $transactionId,
                    $paymentMethod ?: 'manual',
                    $amount,
                    $normalizedStatus,
                    $gatewayResponse,
                    $options['checkout_request_id'] ?? null,
                    $options['reference_number'] ?? null,
                ]);
            }

            $this->updateOrderPaymentSnapshot(
                $orderId,
                $this->normalizeOrderPaymentStatus($normalizedStatus),
                [
                    'transaction_id' => $transactionId,
                    'payment_method' => $paymentMethod,
                    'order_status' => $options['order_status'] ?? null,
                    'current_order_status' => $order['status'] ?? 'pending',
                ],
                true
            );

            if ($manageTransaction) {
                $this->pdo->commit();
            }

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'payment_status' => $this->normalizeOrderPaymentStatus($normalizedStatus),
            ];
        } catch (Exception $e) {
            if ($manageTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logSyncError('Payment initialization error', [
                'order_id' => $orderId,
                'payment_method' => $paymentMethod,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Unable to initialize payment record.'];
        }
    }

    public function syncPaymentStatus($orderId, $transactionStatus, array $details = [], array $options = [])
    {
        $manageTransaction = $options['manage_transaction'] ?? !$this->pdo->inTransaction();

        try {
            if ($manageTransaction) {
                $this->pdo->beginTransaction();
            }

            $order = $this->getOrder($orderId, true);
            if (!$order) {
                throw new Exception('Order not found');
            }

            $normalizedTransactionStatus = $this->normalizeTransactionStatus($transactionStatus);
            $normalizedOrderPaymentStatus = $this->normalizeOrderPaymentStatus($normalizedTransactionStatus);
            $transaction = $this->findTransactionForUpdate($orderId, $details, true);
            $transactionId = $details['transaction_id'] ?? ($transaction['transaction_id'] ?? null);
            $paymentGateway = $details['payment_gateway'] ?? ($transaction['payment_gateway'] ?? null) ?? 'manual';
            $amount = $details['amount'] ?? ($transaction['amount'] ?? null) ?? ($order['total_amount'] ?? 0);
            $gatewayResponse = array_key_exists('gateway_response', $details)
                ? $this->encodePayload($details['gateway_response'])
                : ($transaction['gateway_response'] ?? null);
            $checkoutRequestId = $details['checkout_request_id'] ?? ($transaction['checkout_request_id'] ?? null);
            $referenceNumber = $details['reference_number'] ?? ($transaction['reference_number'] ?? null);
            if ($transaction) {
                $stmt = $this->pdo->prepare("
                    UPDATE payment_transactions
                    SET transaction_id = COALESCE(?, transaction_id),
                        payment_gateway = COALESCE(?, payment_gateway),
                        amount = COALESCE(?, amount),
                        status = ?,
                        gateway_response = COALESCE(?, gateway_response),
                        checkout_request_id = COALESCE(?, checkout_request_id),
                        reference_number = COALESCE(?, reference_number),
                        updated_at = " . $this->currentTimestampSql() . "
                    WHERE id = ?
                ");
                $stmt->execute([
                    $transactionId,
                    $paymentGateway,
                    $amount,
                    $normalizedTransactionStatus,
                    $gatewayResponse,
                    $checkoutRequestId,
                    $referenceNumber,
                    $transaction['id'],
                ]);
            } else {
                if ($transactionId === null && $normalizedTransactionStatus === 'completed') {
                    $transactionId = $this->generateTransactionId();
                }

                $stmt = $this->pdo->prepare("
                    INSERT INTO payment_transactions (
                        order_id,
                        transaction_id,
                        payment_gateway,
                        amount,
                        status,
                        gateway_response,
                        checkout_request_id,
                        reference_number
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $orderId,
                    $transactionId,
                    $paymentGateway,
                    $amount,
                    $normalizedTransactionStatus,
                    $gatewayResponse,
                    $checkoutRequestId,
                    $referenceNumber,
                ]);
            }

            $orderUpdate = $this->updateOrderPaymentSnapshot(
                $orderId,
                $normalizedOrderPaymentStatus,
                [
                    'transaction_id' => $transactionId,
                    'payment_method' => $details['payment_method'] ?? $paymentGateway,
                    'order_status' => $details['order_status'] ?? null,
                    'current_order_status' => $order['status'] ?? 'pending',
                    'transaction_status' => $normalizedTransactionStatus,
                ],
                true
            );

            if ($manageTransaction) {
                $this->pdo->commit();
            }

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'payment_status' => $normalizedOrderPaymentStatus,
                'order_status' => $orderUpdate['order_status'],
            ];
        } catch (Exception $e) {
            if ($manageTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logSyncError('Payment sync error', [
                'order_id' => $orderId,
                'transaction_status' => $transactionStatus,
                'details' => $details,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Failed to synchronize payment status.'];
        }
    }

    public function updatePaymentStatusFromAdmin($orderId, $paymentStatus, array $details = [])
    {
        $transactionStatus = $this->normalizeTransactionStatus($paymentStatus);

        return $this->syncPaymentStatus($orderId, $transactionStatus, [
            'transaction_id' => $details['transaction_id'] ?? ($details['generate_transaction_id'] ?? false ? $this->generateTransactionId() : null),
            'payment_gateway' => $details['payment_gateway'] ?? 'admin',
            'payment_method' => $details['payment_method'] ?? ($details['payment_gateway'] ?? 'admin'),
            'amount' => $details['amount'] ?? null,
            'checkout_request_id' => $details['checkout_request_id'] ?? null,
            'reference_number' => $details['reference_number'] ?? null,
            'gateway_response' => $details['gateway_response'] ?? [
                'updated_by' => 'admin',
                'notes' => $details['notes'] ?? '',
                'source' => $details['source'] ?? 'admin',
            ],
            'order_status' => $details['order_status'] ?? null,
        ], [
            'manage_transaction' => $details['manage_transaction'] ?? true,
        ]);
    }

    public function registerGatewayRequest($orderReference, $gateway, array $details = [])
    {
        $orderId = $this->resolveOrderIdFromReference($orderReference);
        if (!$orderId) {
            $this->logSyncError('Gateway request order not found', [
                'order_reference' => $orderReference,
                'gateway' => $gateway,
            ]);

            return ['success' => false, 'message' => 'Order not found for payment request.'];
        }

        return $this->syncPaymentStatus($orderId, $details['status'] ?? 'pending', [
            'transaction_id' => $details['transaction_id'] ?? null,
            'payment_gateway' => $gateway,
            'payment_method' => $gateway,
            'amount' => $details['amount'] ?? null,
            'checkout_request_id' => $details['checkout_request_id'] ?? null,
            'reference_number' => $details['reference_number'] ?? (string)$orderReference,
            'gateway_response' => $details['gateway_response'] ?? $details,
            'order_status' => $details['order_status'] ?? null,
        ], [
            'manage_transaction' => $details['manage_transaction'] ?? true,
        ]);
    }

    public function handleWebhook($gateway, $payload)
    {
        try {
            $orderId = $this->resolveOrderIdFromPayload($payload);
            if (!$orderId) {
                throw new Exception('Unable to resolve order from webhook payload');
            }

            $transactionStatus = $this->extractWebhookStatus($payload);

            return $this->syncPaymentStatus($orderId, $transactionStatus, [
                'transaction_id' => $this->extractPayloadValue($payload, ['transaction_id', 'reference_number', 'receipt_number']),
                'payment_gateway' => $gateway,
                'payment_method' => $gateway,
                'amount' => $this->extractPayloadValue($payload, ['amount']),
                'checkout_request_id' => $this->extractPayloadValue($payload, ['checkout_request_id']),
                'reference_number' => $this->extractPayloadValue($payload, ['reference_number', 'receipt_number', 'account_reference']),
                'gateway_response' => $payload,
                'source' => 'webhook',
            ]);
        } catch (Exception $e) {
            $this->logSyncError('Webhook error', [
                'gateway' => $gateway,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Webhook processing failed.'];
        }
    }

    public function synchronizeExistingPayments(array $options = [])
    {
        $dryRun = !empty($options['dry_run']);
        $targetOrderId = isset($options['order_id']) ? (int)$options['order_id'] : 0;
        $report = [
            'success' => true,
            'dry_run' => $dryRun,
            'checked_orders' => 0,
            'created_transactions' => 0,
            'updated_orders' => 0,
            'updated_transactions' => 0,
            'issues' => [],
        ];

        try {
            $sql = "
                SELECT
                    o.*,
                    pt.id AS payment_transaction_pk,
                    pt.status AS transaction_status,
                    pt.transaction_id AS latest_transaction_id,
                    pt.payment_gateway AS latest_payment_gateway,
                    pt.amount AS latest_payment_amount
                FROM orders o
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

            if ($targetOrderId > 0) {
                $sql .= " WHERE o.id = ?";
                $params[] = $targetOrderId;
            }

            $sql .= " ORDER BY o.id ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($orders as $order) {
                $report['checked_orders']++;
                $inferredTransactionStatus = $this->inferTransactionStatusFromOrder($order);
                $currentOrderPaymentStatus = $this->normalizeOrderPaymentStatus($order['payment_status'] ?? 'pending');
                $currentTransactionStatus = $this->normalizeTransactionStatus($order['transaction_status'] ?? 'pending');
                $expectedOrderPaymentStatus = $this->normalizeOrderPaymentStatus($inferredTransactionStatus);
                $needsTransaction = empty($order['payment_transaction_pk']);
                $needsPromotion = in_array(strtolower((string)($order['status'] ?? 'pending')), ['shipped', 'delivered', 'completed'], true)
                    && $currentTransactionStatus === 'pending';
                $needsOrderSync = $currentOrderPaymentStatus !== $expectedOrderPaymentStatus;

                if (!$needsTransaction && !$needsPromotion && !$needsOrderSync) {
                    continue;
                }

                $report['issues'][] = [
                    'order_id' => (int)$order['id'],
                    'order_number' => $order['order_number'] ?? null,
                    'order_status' => $order['status'] ?? null,
                    'order_payment_status' => $currentOrderPaymentStatus,
                    'transaction_status' => $needsTransaction ? null : $currentTransactionStatus,
                    'action' => $needsTransaction ? 'create_transaction' : ($needsPromotion ? 'promote_pending_to_paid' : 'sync_order_status'),
                ];

                if ($dryRun) {
                    continue;
                }

                $syncResult = $this->syncPaymentStatus((int)$order['id'], $inferredTransactionStatus, [
                    'transaction_id' => $order['latest_transaction_id'] ?? ($expectedOrderPaymentStatus === 'paid' ? $this->generateTransactionId() : null),
                    'payment_gateway' => $order['latest_payment_gateway'] ?? ($order['payment_method'] ?? 'manual'),
                    'payment_method' => $order['payment_method'] ?? ($order['latest_payment_gateway'] ?? 'manual'),
                    'amount' => $order['latest_payment_amount'] ?? ($order['total_amount'] ?? 0),
                    'reference_number' => $order['order_number'] ?? null,
                    'gateway_response' => [
                        'source' => 'payment_sync_backfill',
                        'order_status' => $order['status'] ?? 'pending',
                        'original_payment_status' => $order['payment_status'] ?? 'pending',
                    ],
                    'order_status' => $order['status'] ?? null,
                ]);

                if (!$syncResult['success']) {
                    $report['success'] = false;
                    $report['issues'][] = [
                        'order_id' => (int)$order['id'],
                        'action' => 'error',
                        'message' => $syncResult['message'] ?? 'Unknown synchronization error',
                    ];
                    continue;
                }

                if ($needsTransaction) {
                    $report['created_transactions']++;
                } else {
                    $report['updated_transactions']++;
                }

                $report['updated_orders']++;
            }

            return $report;
        } catch (Exception $e) {
            $this->logSyncError('Backfill synchronization error', [
                'options' => $options,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to synchronize existing payment data.',
                'issues' => [
                    ['action' => 'error', 'message' => $e->getMessage()],
                ],
            ];
        }
    }

    public function getIntegrityReport($limit = 25)
    {
        $limit = max(1, (int)$limit);

        try {
            $orphanOrdersStmt = $this->pdo->prepare("
                SELECT o.id, o.order_number, o.status, o.payment_status, o.total_amount, o.created_at
                FROM orders o
                LEFT JOIN payment_transactions pt ON pt.order_id = o.id
                WHERE pt.id IS NULL
                ORDER BY COALESCE(o.updated_at, o.created_at) DESC
                LIMIT {$limit}
            ");
            $orphanOrdersStmt->execute();
            $orphanOrders = $orphanOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

            $mismatchesStmt = $this->pdo->prepare("
                SELECT
                    o.id,
                    o.order_number,
                    o.status AS order_status,
                    o.payment_status AS order_payment_status,
                    pt.status AS transaction_status,
                    pt.transaction_id,
                    pt.payment_gateway,
                    pt.created_at AS transaction_created_at
                FROM orders o
                INNER JOIN payment_transactions pt
                    ON pt.id = (
                        SELECT pt2.id
                        FROM payment_transactions pt2
                        WHERE pt2.order_id = o.id
                        ORDER BY pt2.created_at DESC, pt2.id DESC
                        LIMIT 1
                    )
                ORDER BY COALESCE(o.updated_at, o.created_at) DESC
            ");
            $mismatchesStmt->execute();
            $allMismatches = $mismatchesStmt->fetchAll(PDO::FETCH_ASSOC);
            $mismatches = [];

            foreach ($allMismatches as $row) {
                $expected = $this->normalizeOrderPaymentStatus($row['transaction_status'] ?? 'pending');
                $current = $this->normalizeOrderPaymentStatus($row['order_payment_status'] ?? 'pending');

                if ($expected !== $current) {
                    $row['expected_payment_status'] = $expected;
                    $mismatches[] = $row;
                }

                if (count($mismatches) >= $limit) {
                    break;
                }
            }

            $orphanTransactionsStmt = $this->pdo->prepare("
                SELECT pt.*
                FROM payment_transactions pt
                LEFT JOIN orders o ON o.id = pt.order_id
                WHERE o.id IS NULL
                ORDER BY pt.created_at DESC
                LIMIT {$limit}
            ");
            $orphanTransactionsStmt->execute();
            $orphanTransactions = $orphanTransactionsStmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'summary' => [
                    'orphan_orders' => count($orphanOrders),
                    'mismatches' => count($mismatches),
                    'orphan_transactions' => count($orphanTransactions),
                ],
                'orphan_orders' => $orphanOrders,
                'mismatches' => $mismatches,
                'orphan_transactions' => $orphanTransactions,
            ];
        } catch (Exception $e) {
            $this->logSyncError('Integrity report error', ['error' => $e->getMessage()]);

            return [
                'summary' => [
                    'orphan_orders' => 0,
                    'mismatches' => 0,
                    'orphan_transactions' => 0,
                ],
                'orphan_orders' => [],
                'mismatches' => [],
                'orphan_transactions' => [],
            ];
        }
    }

    public function resolveOrderIdFromReference($reference)
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (is_numeric($reference)) {
            return (int)$reference;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM orders WHERE order_number = ? LIMIT 1");
        $stmt->execute([$reference]);
        $orderId = $stmt->fetchColumn();
        if ($orderId) {
            return (int)$orderId;
        }

        if (preg_match('/(\d+)$/', (string)$reference, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    private function getOrder($orderId, $forUpdate = false)
    {
        $sql = "SELECT * FROM orders WHERE id = ?";

        if ($forUpdate && $this->driver === 'pgsql') {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$orderId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function findTransactionForUpdate($orderId, array $details = [], $forUpdate = false)
    {
        $clauses = [];
        $params = [];

        if (!empty($details['payment_transaction_id'])) {
            $clauses[] = 'id = ?';
            $params[] = $details['payment_transaction_id'];
        }

        if (!empty($details['transaction_id'])) {
            $clauses[] = 'transaction_id = ?';
            $params[] = $details['transaction_id'];
        }

        if (!empty($details['checkout_request_id'])) {
            $clauses[] = 'checkout_request_id = ?';
            $params[] = $details['checkout_request_id'];
        }

        if (!empty($details['reference_number'])) {
            $clauses[] = 'reference_number = ?';
            $params[] = $details['reference_number'];
        }

        $sql = "SELECT * FROM payment_transactions WHERE order_id = ?";
        $sqlParams = [$orderId];

        if (!empty($clauses)) {
            $sql .= " AND (" . implode(' OR ', $clauses) . ")";
            $sqlParams = array_merge($sqlParams, $params);
        }

        $sql .= " ORDER BY created_at DESC, id DESC LIMIT 1";

        if ($forUpdate && $this->driver === 'pgsql') {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($sqlParams);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($transaction) {
            return $transaction;
        }

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM payment_transactions
            WHERE order_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$orderId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function updateOrderPaymentSnapshot($orderId, $paymentStatus, array $details = [], $withinTransaction = false)
    {
        $shouldManageTransaction = !$withinTransaction && !$this->pdo->inTransaction();

        try {
            if ($shouldManageTransaction) {
                $this->pdo->beginTransaction();
            }

            $order = $this->getOrder($orderId, true);
            if (!$order) {
                throw new Exception('Order not found');
            }

            $orderStatus = $details['order_status'] ?? $this->determineOrderStatus(
                $details['current_order_status'] ?? ($order['status'] ?? 'pending'),
                $paymentStatus,
                $details['transaction_status'] ?? null
            );

            $stmt = $this->pdo->prepare("
                UPDATE orders
                SET payment_status = ?,
                    transaction_id = COALESCE(?, transaction_id),
                    payment_method = COALESCE(?, payment_method),
                    status = ?,
                    updated_at = " . $this->currentTimestampSql() . "
                WHERE id = ?
            ");
            $stmt->execute([
                $paymentStatus,
                $details['transaction_id'] ?? null,
                $details['payment_method'] ?? null,
                $orderStatus,
                $orderId,
            ]);

            if ($shouldManageTransaction) {
                $this->pdo->commit();
            }

            return [
                'success' => true,
                'order_status' => $orderStatus,
            ];
        } catch (Exception $e) {
            if ($shouldManageTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function determineOrderStatus($currentOrderStatus, $paymentStatus, $transactionStatus = null)
    {
        $currentOrderStatus = strtolower((string)$currentOrderStatus);
        $paymentStatus = $this->normalizeOrderPaymentStatus($paymentStatus);
        $transactionStatus = $transactionStatus ? $this->normalizeTransactionStatus($transactionStatus) : null;

        if ($paymentStatus === 'paid') {
            if (in_array($currentOrderStatus, ['shipped', 'delivered', 'completed', 'cancelled'], true)) {
                return $currentOrderStatus;
            }

            return 'processing';
        }

        if ($paymentStatus === 'failed') {
            if (in_array($currentOrderStatus, ['shipped', 'delivered', 'completed'], true)) {
                return $currentOrderStatus;
            }

            return 'pending';
        }

        if ($paymentStatus === 'refunded') {
            if (in_array($currentOrderStatus, ['delivered', 'completed'], true)) {
                return $currentOrderStatus;
            }

            return 'cancelled';
        }

        if ($transactionStatus === 'pending' && $currentOrderStatus === 'processing') {
            return 'pending';
        }

        return $currentOrderStatus ?: 'pending';
    }

    private function inferTransactionStatusFromOrder(array $order)
    {
        $orderStatus = strtolower((string)($order['status'] ?? 'pending'));
        $paymentStatus = strtolower((string)($order['payment_status'] ?? 'pending'));
        $transactionStatus = strtolower((string)($order['transaction_status'] ?? ''));

        if ($transactionStatus !== '') {
            if (in_array($orderStatus, ['shipped', 'delivered', 'completed'], true) && $transactionStatus === 'pending') {
                return 'completed';
            }

            return $transactionStatus;
        }

        if (in_array($orderStatus, ['shipped', 'delivered', 'completed'], true)) {
            return 'completed';
        }

        if (in_array($paymentStatus, ['paid', 'completed'], true)) {
            return 'completed';
        }

        if ($paymentStatus === 'failed') {
            return 'failed';
        }

        if ($paymentStatus === 'refunded') {
            return 'refunded';
        }

        return 'pending';
    }

    private function normalizeTransactionStatus($status)
    {
        $status = strtolower(trim((string)$status));

        return match ($status) {
            'paid', 'approved', 'success', 'succeeded', 'complete' => 'completed',
            'declined', 'cancelled', 'canceled', 'error' => 'failed',
            'processing', 'authorized', 'authorised' => 'pending',
            'refunded' => 'refunded',
            'failed' => 'failed',
            'completed' => 'completed',
            default => 'pending',
        };
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

    private function generateTransactionId()
    {
        return 'TXN-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    private function resolveOrderIdFromPayload(array $payload)
    {
        $orderId = $this->extractPayloadValue($payload, ['order_id']);
        if ($orderId) {
            return (int)$orderId;
        }

        $referenceCandidates = [
            $this->extractPayloadValue($payload, ['account_reference', 'reference_number', 'bill_ref_number']),
            $this->extractMpesaAccountReference($payload),
        ];

        foreach ($referenceCandidates as $reference) {
            $resolvedOrderId = $this->resolveOrderIdFromReference($reference);
            if ($resolvedOrderId) {
                return $resolvedOrderId;
            }
        }

        $lookupFields = [
            'transaction_id' => $this->extractPayloadValue($payload, ['transaction_id', 'reference_number', 'receipt_number']),
            'checkout_request_id' => $this->extractPayloadValue($payload, ['checkout_request_id']),
        ];

        foreach ($lookupFields as $field => $value) {
            if (!$value) {
                continue;
            }

            $stmt = $this->pdo->prepare("SELECT order_id FROM payment_transactions WHERE {$field} = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$value]);
            $transactionOrderId = $stmt->fetchColumn();
            if ($transactionOrderId) {
                return (int)$transactionOrderId;
            }
        }

        if ($this->tableExists('payments')) {
            $checkoutRequestId = $this->extractPayloadValue($payload, ['checkout_request_id']);
            if ($checkoutRequestId) {
                $stmt = $this->pdo->prepare("SELECT order_id FROM payments WHERE checkout_request_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$checkoutRequestId]);
                $legacyOrderId = $stmt->fetchColumn();
                if ($legacyOrderId) {
                    return $this->resolveOrderIdFromReference($legacyOrderId);
                }
            }
        }

        return null;
    }

    private function extractWebhookStatus(array $payload)
    {
        $status = $this->extractPayloadValue($payload, ['status']);
        if ($status) {
            return $status;
        }

        if (isset($payload['mpesa_result_code'])) {
            return ((string)$payload['mpesa_result_code'] === '0') ? 'completed' : 'failed';
        }

        if (isset($payload['result_code'])) {
            return ((string)$payload['result_code'] === '0') ? 'completed' : 'failed';
        }

        return 'pending';
    }

    private function extractPayloadValue(array $payload, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '') {
                return $payload[$key];
            }
        }

        return null;
    }

    private function extractMpesaAccountReference(array $payload)
    {
        if (empty($payload['callback_items']) || !is_array($payload['callback_items'])) {
            return null;
        }

        foreach ($payload['callback_items'] as $item) {
            if (($item['Name'] ?? '') === 'AccountReference' && isset($item['Value'])) {
                return $item['Value'];
            }
        }

        return null;
    }

    private function encodePayload($payload)
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        if (is_string($payload)) {
            return $payload;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? json_encode(['raw' => (string)$payload]) : $encoded;
    }

    private function logSyncError($message, array $context = [])
    {
        $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        if (!empty($context)) {
            $entry .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        error_log($entry);

        $logDirectory = dirname($this->logFile);
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0777, true);
        }

        @file_put_contents($this->logFile, $entry . PHP_EOL, FILE_APPEND);
    }

    private function tableExists($tableName)
    {
        static $cache = [];

        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }

        if ($this->driver === 'mysql') {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");
        } else {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = current_schema()
                  AND table_name = ?
            ");
        }

        $stmt->execute([$tableName]);
        $cache[$tableName] = (bool)$stmt->fetchColumn();

        return $cache[$tableName];
    }

    private function currentTimestampSql()
    {
        return 'CURRENT_TIMESTAMP';
    }

    private function simulatePaymentGateway($method, $order, $details)
    {
        $success = true;

        if ($success) {
            return [
                'status' => 'completed',
                'message' => 'Payment successful',
                'response' => [
                    'id' => uniqid('gw_', true),
                    'amount' => $order['total_amount'],
                    'currency' => 'USD',
                    'status' => 'succeeded',
                    'payment_method' => $method,
                    'details' => $details,
                ],
            ];
        }

        return [
            'status' => 'failed',
            'message' => 'Payment failed',
            'response' => [
                'error' => 'Transaction declined',
                'code' => 'DECLINED',
            ],
        ];
    }
}
