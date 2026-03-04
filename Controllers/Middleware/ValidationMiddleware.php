<?php

namespace App\Middleware;

use App\DTOs\ErrorResponse;

class ValidationMiddleware
{
    public function process(array $request, \Closure $next): array
    {
        // Validate request method
        $method = strtoupper($request['method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        
        if (!in_array($method, $allowedMethods)) {
            return $this->createErrorResponse(
                'Invalid HTTP method. Allowed: ' . implode(', ', $allowedMethods),
                405
            );
        }
        
        // Skip validation for OPTIONS (preflight requests)
        if ($method === 'OPTIONS') {
            return $next($request);
        }
        
        // Validate content type for POST/PUT/PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
            if (!empty($contentType) && strpos($contentType, 'application/json') === false) {
                return $this->createErrorResponse(
                    'Content-Type must be application/json for POST/PUT/PATCH requests',
                    415
                );
            }
        }
        
        // Validate required headers
        $requiredHeaders = [
            'User-Agent' => 'User-Agent header is required',
            'Accept' => 'Accept header is required',
        ];
        
        foreach ($requiredHeaders as $header => $errorMessage) {
            $headerValue = $this->getHeader($header);
            if (empty($headerValue)) {
                return $this->createErrorResponse($errorMessage, 400, [
                    'missing_header' => $header,
                    'received_headers' => $this->getAllHeaders(),
                ]);
            }
        }
        
        // Validate API version header
        $apiVersion = $this->getHeader('X-API-Version');
        if ($apiVersion && !preg_match('/^v\d+(\.\d+)*$/', $apiVersion)) {
            return $this->createErrorResponse('Invalid API version format. Use format: v1, v1.2, etc.', 400);
        }
        
        // Validate request ID header (optional but recommended for tracking)
        $requestId = $this->getHeader('X-Request-ID');
        if (!$requestId) {
            // Generate a request ID if not provided
            $request['request_id'] = $this->generateRequestId();
        } else {
            $request['request_id'] = $requestId;
        }
        
        // Validate rate limiting
        $clientIp = $this->getClientIp();
        $rateLimitResult = $this->checkRateLimit($clientIp);
        
        if (!$rateLimitResult['allowed']) {
            return $this->createErrorResponse(
                'Rate limit exceeded. Please try again later.',
                429,
                [
                    'retry_after' => $rateLimitResult['retry_after'],
                    'limit' => $rateLimitResult['limit'],
                    'remaining' => $rateLimitResult['remaining'],
                    'reset' => $rateLimitResult['reset'],
                ]
            );
        }
        
        // Add rate limit info to request
        $request['rate_limit'] = $rateLimitResult;
        
        // Validate request body for JSON requests
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $body = file_get_contents('php://input');
            if (!empty($body)) {
                $decoded = json_decode($body, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errorMessage = match(json_last_error()) {
                        JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
                        JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
                        JSON_ERROR_CTRL_CHAR => 'Control character error',
                        JSON_ERROR_SYNTAX => 'Syntax error',
                        JSON_ERROR_UTF8 => 'Malformed UTF-8 characters',
                        default => 'Invalid JSON format',
                    };
                    
                    return $this->createErrorResponse(
                        'Invalid JSON in request body: ' . $errorMessage,
                        400,
                        ['json_error' => json_last_error_msg()]
                    );
                }
                
                // Store parsed body in request
                $request['body'] = $decoded;
                $request['raw_body'] = $body;
            }
        }
        
        // Validate query parameters
        if (!empty($_GET)) {
            $request['query'] = $_GET;
            
            // Validate numeric query parameters
            $numericParams = ['page', 'limit', 'offset', 'per_page'];
            foreach ($numericParams as $param) {
                if (isset($_GET[$param]) && !is_numeric($_GET[$param])) {
                    return $this->createErrorResponse(
                        "Query parameter '$param' must be numeric",
                        400
                    );
                }
            }
            
            // Validate boolean query parameters
            $booleanParams = ['active', 'enabled', 'published'];
            foreach ($booleanParams as $param) {
                if (isset($_GET[$param])) {
                    $value = strtolower($_GET[$param]);
                    if (!in_array($value, ['true', 'false', '1', '0', 'yes', 'no'])) {
                        return $this->createErrorResponse(
                            "Query parameter '$param' must be boolean (true/false)",
                            400
                        );
                    }
                }
            }
        }
        
        // Validate path parameters (if using routing)
        if (isset($request['path_params'])) {
            foreach ($request['path_params'] as $param => $value) {
                if (empty($value)) {
                    return $this->createErrorResponse(
                        "Path parameter '$param' cannot be empty",
                        400
                    );
                }
            }
        }
        
        // Add validation timestamp
        $request['validated_at'] = microtime(true);
        $request['validation_passed'] = true;
        
        try {
            return $next($request);
        } catch (\JsonException $e) {
            return $this->createErrorResponse(
                'Invalid JSON format: ' . $e->getMessage(),
                400,
                ['json_exception' => $e->getTrace()[0] ?? null]
            );
        } catch (\InvalidArgumentException $e) {
            return $this->createErrorResponse(
                'Invalid arguments: ' . $e->getMessage(),
                400
            );
        } catch (\Exception $e) {
            // Log unexpected errors
            error_log('Validation middleware error: ' . $e->getMessage());
            
            return $this->createErrorResponse(
                'Request validation failed',
                500,
                ['internal_error' => 'An unexpected validation error occurred']
            );
        }
    }
    
    private function checkRateLimit(string $clientIp): array
    {
        $limit = 100; // requests per minute
        $window = 60; // seconds
        
        // Use file-based rate limiting (for production, use Redis)
        $cacheDir = __DIR__ . '/../storage/cache/rate_limit/';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheFile = $cacheDir . md5($clientIp) . '.json';
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            $currentTime = time();
            
            // Check if window has expired
            if ($currentTime - $data['start_time'] >= $window) {
                // Reset counter
                $data = [
                    'count' => 1,
                    'start_time' => $currentTime,
                ];
            } else {
                // Increment counter
                $data['count']++;
            }
        } else {
            // First request
            $data = [
                'count' => 1,
                'start_time' => time(),
            ];
        }
        
        // Save data
        file_put_contents($cacheFile, json_encode($data));
        
        $remaining = max(0, $limit - $data['count']);
        $reset = $data['start_time'] + $window;
        $retryAfter = max(0, $reset - time());
        
        return [
            'allowed' => $data['count'] <= $limit,
            'limit' => $limit,
            'remaining' => $remaining,
            'reset' => $reset,
            'retry_after' => $retryAfter,
            'current' => $data['count'],
            'client_ip' => $clientIp,
        ];
    }
    
    private function createErrorResponse(
        string $message, 
        int $statusCode = 400, 
        array $details = []
    ): array {
        return ErrorResponse::create(
            'VALIDATION_ERROR',
            $message,
            $statusCode,
            $details
        )->toArray();
    }
    
    private function getHeader(string $name): ?string
    {
        $name = strtoupper(str_replace('-', '_', $name));
        
        // Try HTTP_ prefixed headers
        if (isset($_SERVER['HTTP_' . $name])) {
            return $_SERVER['HTTP_' . $name];
        }
        
        // Try without HTTP_ prefix
        if (isset($_SERVER[$name])) {
            return $_SERVER[$name];
        }
        
        // Try getallheaders() if available
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === strtolower($name)) {
                    return $value;
                }
            }
        }
        
        return null;
    }
    
    private function getAllHeaders(): array
    {
        $headers = [];
        
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $header = str_replace('_', '-', $key);
                $headers[$header] = $value;
            }
        }
        
        return $headers;
    }
    
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
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    private function generateRequestId(): string
    {
        return sprintf(
            '%s-%s-%s',
            date('Ymd-His'),
            substr(md5(uniqid('', true)), 0, 8),
            substr(md5($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 4)
        );
    }
}