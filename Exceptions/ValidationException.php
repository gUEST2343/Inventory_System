<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class ValidationException extends Exception
{
    private array $errors = [];
    private array $fieldErrors = [];
    private ?string $field;
    private mixed $value;
    
    public function __construct(
        string|array $message = "Validation failed",
        int $code = 0,
        Throwable $previous = null, 
        array $errors = [],
        string $field = null,
        mixed $value = null
    ) {
        if (is_array($message)) {
            $this->errors = $message;
            $message = 'Validation failed: ' . implode(', ', $message);
        } else {
            $this->errors = [$message];
        }
        
        parent::__construct($message, $code, $previous);
        
        $this->field = $field;
        $this->value = $value;
        
        if ($field) {
            $this->fieldErrors[$field] = is_array($message) ? $message : [$message];
        }
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }
    
    public function getField(): ?string
    {
        return $this->field;
    }
    
    public function getValue(): mixed
    {
        return $this->value;
    }
    
    public function addError(string $error, string $field = null): void
    {
        $this->errors[] = $error;
        
        if ($field) {
            if (!isset($this->fieldErrors[$field])) {
                $this->fieldErrors[$field] = [];
            }
            $this->fieldErrors[$field][] = $error;
        }
    }
    
    public function addFieldError(string $field, string $error): void
    {
        $this->addError($error, $field);
    }
    
    public function toArray(): array
    {
        return [
            'error' => 'VALIDATION_ERROR',
            'message' => $this->getMessage(),
            'errors' => $this->errors,
            'field_errors' => $this->fieldErrors,
            'field' => $this->field,
            'value' => $this->value,
        ];
    }
    
    /**
     * Check if a specific field has errors
     */
    public function hasFieldError(string $field): bool
    {
        return isset($this->fieldErrors[$field]) && !empty($this->fieldErrors[$field]);
    }
    
    /**
     * Get errors for a specific field
     */
    public function getFieldError(string $field): array
    {
        return $this->fieldErrors[$field] ?? [];
    }
    
    /**
     * Check if there are any errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
    
    /**
     * Get error count
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }
    
    /**
     * Merge another ValidationException into this one
     */
    public function merge(ValidationException $other): void
    {
        $this->errors = array_merge($this->errors, $other->getErrors());
        $this->fieldErrors = array_merge_recursive($this->fieldErrors, $other->getFieldErrors());
        
        // Update message
        $this->message = 'Validation failed: ' . implode(', ', $this->errors);
    }
    
    /**
     * Create a ValidationException from an array of errors
     */
    public static function fromArray(array $errors, string $field = null): self
    {
        return new self($errors, 0, null, $errors, $field);
    }
    
    /**
     * Create a field-specific ValidationException
     */
    public static function forField(string $field, string $error, mixed $value = null): self
    {
        return new self($error, 0, null, [$error], $field, $value);
    }
}