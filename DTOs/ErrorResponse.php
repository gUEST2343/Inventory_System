<?php

namespace App\DTOs;

class ErrorResponse
{
    private string $errorCode;
    private string $message;
    private int $statusCode;
    private array $details;
    private string $timestamp;
    private ?string $requestId;
    private ?string $documentationUrl;
    
    /**
     * Private constructor - use static create() method
     */
    private function __construct(
        string $errorCode,
        string $message,
        int $statusCode = 500,
        array $details = [],
        ?string $requestId = null,
        ?string $documentationUrl = null
    ) {
        $this->errorCode = $errorCode;
        $this->message = $message;
        $this->statusCode = $statusCode;
        $this->details = $details;
        $this->timestamp = date('c'); // ISO 8601 format
        $this->requestId = $requestId ?? $this->generateRequestId();
        $this->documentationUrl = $documentationUrl;
    }
    
    /**
     * Create a new ErrorResponse instance
     */
    public static function create(
        string $errorCode,
        string $message,
        int $statusCode = 500,
        array $details = [],
        ?string $requestId = null,
        ?string $documentationUrl = null
    ): self {
        return new self($errorCode, $message, $statusCode, $details, $requestId, $documentationUrl);
    }
    
    /**
     * Create from an exception
     */
    public static function fromException(\Throwable $exception, bool $includeStackTrace = false): self
    {
        $details = [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
        
        if ($includeStackTrace) {
            $details['trace'] = $exception->getTraceAsString();
        }
        
        // Map exception types to error codes
        $errorCode = self::mapExceptionToErrorCode($exception);
        $statusCode = self::mapExceptionToStatusCode($exception);
        
        return new self(
            $errorCode,
            $exception->getMessage(),
            $statusCode,
            $details
        );
    }
    
    /**
     * Create validation error response
     */
    public static function validationError(array $errors, array $fieldErrors = []): self
    {
        $details = [];
        
        if (!empty($errors)) {
            $details['errors'] = $errors;
        }
        
        if (!empty($fieldErrors)) {
            $details['field_errors'] = $fieldErrors;
        }
        
        return new self(
            'VALIDATION_ERROR',
            'Validation failed',
            400,
            $details
        );
    }
    
    /**
     * Create not found error response
     */
    public static function notFound(string $resource, string $identifier = null): self
    {
        $message = $identifier 
            ? "$resource with identifier '$identifier' not found"
            : "$resource not found";
            
        $details = [
            'resource' => $resource,
            'identifier' => $identifier,
        ];
        
        return new self(
            'NOT_FOUND',
            $message,
            404,
            $details
        );
    }
    
    /**
     * Create unauthorized error response
     */
    public static function unauthorized(string $message = 'Authentication required'): self
    {
        return new self(
            'UNAUTHORIZED',
            $message,
            401
        );
    }
    
    /**
     * Create forbidden error response
     */
    public static function forbidden(string $message = 'Insufficient permissions'): self
    {
        return new self(
            'FORBIDDEN',
            $message,
            403
        );
    }
    
    /**
     * Create conflict error response
     */
    public static function conflict(string $message = 'Resource conflict'): self
    {
        return new self(
            'CONFLICT',
            $message,
            409
        );
    }
    
    /**
     * Create a success response (for consistency)
     */
    public static function success(string $message = 'Operation successful'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c'),
            'request_id' => self::generateRequestId(),
        ];
    }
    
    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        $response = [
            'success' => false,
            'error' => $this->errorCode,
            'message' => $this->message,
            'status' => $this->statusCode,
            'timestamp' => $this->timestamp,
            'request_id' => $this->requestId,
        ];
        
        if (!empty($this->details)) {
            $response['details'] = $this->details;
        }
        
        if ($this->documentationUrl) {
            $response['documentation'] = $this->documentationUrl;
        }
        
        // Only show details in development/staging
        if ($_ENV['APP_ENV'] === 'production') {
            unset($response['details']['trace'], $response['details']['file'], $response['details']['line']);
        }
        
        return $response;
    }
    
    /**
     * Convert to JSON string
     */
    public function toJson(int $options = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toArray(), $options);
    }
    
    /**
     * Send as HTTP response
     */
    public function sendResponse(): void
    {
        http_response_code($this->statusCode);
        header('Content-Type: application/json');
        echo $this->toJson();
        exit;
    }
    
    /**
     * Getters
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
    
    public function getMessage(): string
    {
        return $this->message;
    }
    
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    
    public function getDetails(): array
    {
        return $this->details;
    }
    
    public function getTimestamp(): string
    {
        return $this->timestamp;
    }
    
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
    
    /**
     * Generate unique request ID
     */
    private static function generateRequestId(): string
    {
        return sprintf(
            '%s-%s',
            date('Ymd-His'),
            substr(md5(uniqid('', true)), 0, 8)
        );
    }
    
    /**
     * Map exception class to error code
     */
    private static function mapExceptionToErrorCode(\Throwable $exception): string
    {
        $class = get_class($exception);
        
        $mapping = [
            'InvalidArgumentException' => 'INVALID_INPUT',
            'DomainException' => 'DOMAIN_ERROR',
            'RuntimeException' => 'RUNTIME_ERROR',
            'PDOException' => 'DATABASE_ERROR',
            'App\Exceptions\ValidationException' => 'VALIDATION_ERROR',
            'App\Exceptions\ProductNotFoundException' => 'PRODUCT_NOT_FOUND',
            'App\Exceptions\InsufficientStockException' => 'INSUFFICIENT_STOCK',
            'App\Exceptions\InvalidBarcodeException' => 'INVALID_BARCODE',
        ];
        
        foreach ($mapping as $exceptionClass => $errorCode) {
            if ($exception instanceof $exceptionClass || $class === $exceptionClass) {
                return $errorCode;
            }
        }
        
        return 'INTERNAL_ERROR';
    }
    
    /**
     * Map exception to HTTP status code
     */
    private static function mapExceptionToStatusCode(\Throwable $exception): int
    {
        $class = get_class($exception);
        
        $mapping = [
            'InvalidArgumentException' => 400,
            'App\Exceptions\ValidationException' => 400,
            'App\Exceptions\InvalidBarcodeException' => 400,
            'App\Exceptions\ProductNotFoundException' => 404,
            'App\Exceptions\InsufficientStockException' => 409,
            'PDOException' => 500,
        ];
        
        foreach ($mapping as $exceptionClass => $statusCode) {
            if ($exception instanceof $exceptionClass || $class === $exceptionClass) {
                return $statusCode;
            }
        }
        
        return 500;
    }
}