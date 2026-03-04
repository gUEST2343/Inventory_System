<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class InvalidBarcodeException extends Exception
{
    private ?string $barcode;
    private ?string $barcodeType;
    private array $validationErrors = [];
    
    public function __construct(
        string $message = "Invalid barcode",
        int $code = 0,
        Throwable $previous = null,
        string $barcode = null,
        string $barcodeType = null,
        array $validationErrors = []
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->barcode = $barcode;
        $this->barcodeType = $barcodeType;
        $this->validationErrors = $validationErrors;
    }
    
    public function getBarcode(): ?string
    {
        return $this->barcode;
    }
    
    public function getBarcodeType(): ?string
    {
        return $this->barcodeType;
    }
    
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
    
    public function toArray(): array
    {
        return [
            'error' => 'INVALID_BARCODE',
            'message' => $this->getMessage(),
            'barcode' => $this->barcode,
            'barcode_type' => $this->barcodeType,
            'validation_errors' => $this->validationErrors,
            'suggestions' => $this->getSuggestions(),
        ];
    }
    
    /**
     * Get suggestions for fixing the barcode
     */
    public function getSuggestions(): array
    {
        $suggestions = [];
        
        if ($this->barcode) {
            $length = strlen($this->barcode);
            
            if ($length < 12) {
                $suggestions[] = 'Barcode is too short. EAN/UPC barcodes are typically 12-13 digits.';
            } elseif ($length > 14) {
                $suggestions[] = 'Barcode is too long. EAN/UPC barcodes are typically 12-13 digits.';
            }
            
            if (!ctype_digit($this->barcode)) {
                $suggestions[] = 'Barcode should contain only numeric digits (0-9).';
            }
            
            if ($length === 12) {
                $suggestions[] = 'For UPC-A barcodes, ensure the check digit is correct.';
            } elseif ($length === 13) {
                $suggestions[] = 'For EAN-13 barcodes, verify the check digit calculation.';
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Get possible barcode types for the given length
     */
    public function getPossibleTypes(): array
    {
        if (!$this->barcode) {
            return [];
        }
        
        $length = strlen($this->barcode);
        
        return match($length) {
            8 => ['EAN-8'],
            12 => ['UPC-A', 'UPC-E (expanded)'],
            13 => ['EAN-13'],
            14 => ['EAN-14', 'ITF-14'],
            default => ['Unknown format'],
        };
    }
    
    /**
     * Check if barcode contains non-numeric characters
     */
    public function hasNonNumericCharacters(): bool
    {
        return $this->barcode && !ctype_digit($this->barcode);
    }
    
    /**
     * Check if barcode length is invalid
     */
    public function hasInvalidLength(): bool
    {
        if (!$this->barcode) {
            return false;
        }
        
        $length = strlen($this->barcode);
        return $length < 8 || $length > 14;
    }
}