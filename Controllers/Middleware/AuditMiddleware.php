<?php

namespace App\Middleware;

use App\Services\AuditService;
use App\Repositories\StockAdjustmentRepository;
use App\DTOs\ErrorResponse;
use PDO;

class AuditMiddleware
{
    private ?AuditService $auditService = null;
    private array $config;
    private string $logPath;

    public function __construct(?AuditService $auditService = null)
    {
        $this->auditService = $auditService;
        
        // Load config
        $this->config = require __DIR__ . '/../../config/app.php';
        
        // Set log path from config
        $this->logPath = $this->config['logging']['channels']['audit']['path'] ?? __DIR__ . '/../../storage/logs/audit.log';
        
        // Ensure log directory exists
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Process the request and log audit information
     *
     * @param array $request The request data
     * @param \Closure $next The next middleware in the chain
     * @return array The response from the next middleware
     */
    public function process(array $request, \Closure $next): array
    {
        $startTime = microtime(true);
        
        // Capture request details
        $method = strtoupper($request['method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = $request['path'] ?? $_SERVER['REQUEST_URI'] ?? '/';
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        
        // Get client IP
        $clientIp = $this->getClientIp();
        
        // Get user agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Get user ID if authenticated (from request or session)
        $userId = $request['user_id'] ?? $_SESSION['user_id'] ?? 'anonymous';
        $username = $request['username'] ?? $_SESSION['username'] ?? 'anonymous';
        
        // Get request ID if available
        $requestId = $request['request_id'] ?? $this->generateRequestId();
        
        // Build audit data
        $auditData = [
            'request_id' => $requestId,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            'method' => $method,
            'path' => $path,
            'query_string' => $queryString,
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'user_id' => $userId,
            'username' => $username,
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
            'accept' => $_SERVER['HTTP_ACCEPT'] ?? '',
        ];
        
        // Add request body if present and not empty
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $rawBody = $request['raw_body'] ?? file_get_contents('php://input');
            if (!empty($rawBody)) {
                // Don't log sensitive data like passwords
                $auditData['body'] = $this->sanitizeBody($rawBody);
                $auditData['body_size'] = strlen($rawBody);
            }
        }
        
        // Log the incoming request
        $this->logAuditEvent('request', $auditData);
        
        // Track if this is a stock adjustment request
        $isStockAdjustment = $this->isStockAdjustmentRequest($method, $path);
        
        try {
            // Process the request through the middleware chain
            $response = $next($request);
            
            // Calculate execution time
            $executionTime = microtime(true) - $startTime;
            
            // Add response details to audit data
            $auditData['status_code'] = $response['status_code'] ?? 200;
            $auditData['execution_time_ms'] = round($executionTime * 1000, 2);
            
            // Log the response
            $this->logAuditEvent('response', $auditData);
            
            // If this was a stock adjustment, log it to the database
            if ($isStockAdjustment && $this->auditService) {
                $this->logStockAdjustmentFromRequest($request, $auditData);
            }
            
            // Add request ID to response headers if not already present
            if (isset($response['headers'])) {
                $response['headers']['X-Request-ID'] = $requestId;
            } else {
                $response['headers'] = ['X-Request-ID' => $requestId];
            }
            
            return $response;
            
        } catch (\Exception $e) {
            // Log the exception
            $auditData['exception'] = $e->getMessage();
            $auditData['exception_class'] = get_class($e);
            $this->logAuditEvent('error', $auditData);
            
            throw $e;
        }
    }
    
    /**
     * Check if the request is a stock adjustment request
     */
    private function isStockAdjustmentRequest(string $method, string $path): bool
    {
        // Check for stock adjustment endpoints
        $stockEndpoints = [
            '/api/stock/adjust',
            '/api/stock/update',
            '/api/inventory/adjust',
            '/api/inventory/update',
        ];
        
        foreach ($stockEndpoints as $endpoint) {
            if (strpos($path, $endpoint) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Log stock adjustment from request data
     */
    private function logStockAdjustmentFromRequest(array $request, array $auditData): void
    {
        if (!$this->auditService) {
            return;
        }
        
        $body = $request['body'] ?? [];
        
        // Check if we have the required fields for stock adjustment
        if (empty($body['barcode']) || !isset($body['adjustment'])) {
            return;
        }
        
        try {
            // The actual logging would require the ProductVariant and other models
            // This is a simplified version that logs the intent
            $this->logAuditEvent('stock_adjustment', [
                'barcode' => $body['barcode'],
                'adjustment' => $body['adjustment'] ?? 0,
                'reason' => $body['reason'] ?? 'unknown',
                'notes' => $body['notes'] ?? '',
                'request_id' => $auditData['request_id'],
                'user_id' => $auditData['user_id'],
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the request
            error_log('Failed to log stock adjustment: ' . $e->getMessage());
        }
    }
    
    /**
     * Log an audit event to the audit log file
     */
    private function logAuditEvent(string $type, array $data): void
    {
        $logEntry = json_encode([
            'type' => $type,
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES) . "\n";
        
        // Append to audit log file
        file_put_contents($this->logPath, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also log to error_log for debugging in development
        if ($this->config['debug'] ?? false) {
            error_log('[AUDIT] ' . $type . ': ' . json_encode($data));
        }
    }
    
    /**
     * Sanitize request body to remove sensitive information
     */
    private function sanitizeBody(string $body): string
    {
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Not JSON, truncate to first 1000 chars
            return substr($body, 0, 1000);
        }
        
        // Remove sensitive fields
        $sensitiveFields = ['password', 'password_confirm', 'token', 'secret', 'api_key', 'credit_card'];
        
        array_walk_recursive($decoded, function (&$value, $key) use ($sensitiveFields) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $value = '***REDACTED***';
            }
        });
        
        return json_encode($decoded);
    }
    
    /**
     * Get the client IP address
     */
    private function getClientIp(): string
    {
        $ipSources = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];
        
        foreach ($ipSources as $source) {
            if (!empty($_SERVER[$source])) {
                $ip = $_SERVER[$source];
                
                // Handle comma-separated IPs (X-Forwarded-For can have multiple)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Generate a unique request ID
     */
    private function generateRequestId(): string
    {
        return sprintf(
            '%s-%s-%s',
            date('Ymd-His'),
            substr(md5(uniqid('', true)), 0, 8),
            substr(md5($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 4)
        );
    }
    
    /**
     * Create an error response
     */
    private function createErrorResponse(
        string $message,
        int $statusCode = 500,
        array $details = []
    ): array {
        return ErrorResponse::create(
            'AUDIT_ERROR',
            $message,
            $statusCode,
            $details
        )->toArray();
    }
}
