<?php

namespace App\Exceptions;

use Throwable;

class GlobalExceptionHandler
{
    public static function handle(Throwable $exception): array
    {
        // Log error
        error_log('Exception: ' . $exception->getMessage());
        
        // Map to response
        if ($exception instanceof ValidationException) {
            return [
                'error' => 'VALIDATION_ERROR',
                'message' => $exception->getMessage(),
                'status' => 400,
                'timestamp' => date('c'),
            ];
        }
        
        if ($exception instanceof ProductNotFoundException) {
            return [
                'error' => 'PRODUCT_NOT_FOUND',
                'message' => $exception->getMessage(),
                'status' => 404,
                'timestamp' => date('c'),
            ];
        }
        
        if ($exception instanceof InsufficientStockException) {
            return [
                'error' => 'INSUFFICIENT_STOCK',
                'message' => $exception->getMessage(),
                'status' => 409,
                'timestamp' => date('c'),
            ];
        }
        
        // Default error
        return [
            'error' => 'INTERNAL_ERROR',
            'message' => 'An unexpected error occurred',
            'status' => 500,
            'timestamp' => date('c'),
        ];
    }
    
    public static function sendJsonResponse(Throwable $exception): void
    {
        $response = self::handle($exception);
        $statusCode = $response['status'] ?? 500;
        
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
}