<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class ProductNotFoundException extends Exception
{
    private ?string $identifier;
    private ?string $identifierType;
    private ?string $productType;
    private ?string $sku;
    private ?string $barcode;
    
    public function __construct(
        string $message = "Product not found",
        int $code = 0,
        Throwable $previous = null,
        string $identifier = null,
        string $identifierType = null,
        string $productType = null,
        string $sku = null,
        string $barcode = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->identifier = $identifier;
        $this->identifierType = $identifierType;
        $this->productType = $productType;
        $this->sku = $sku;
        $this->barcode = $barcode;
    }
    
    // Getter methods
    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }
    
    public function getIdentifierType(): ?string
    {
        return $this->identifierType;
    }
    
    public function getProductType(): ?string
    {
        return $this->productType;
    }
    
    public function getSku(): ?string
    {
        return $this->sku;
    }
    
    public function getBarcode(): ?string
    {
        return $this->barcode;
    }
    
    public function toArray(): array
    {
        return [
            'error' => 'PRODUCT_NOT_FOUND',
            'message' => $this->getMessage(),
            'identifier' => $this->identifier,
            'identifier_type' => $this->identifierType,
            'product_type' => $this->productType,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'suggestions' => $this->getSuggestions(),
            'alternative_search_terms' => $this->getAlternativeSearchTerms(),
        ];
    }
    
    /**
     * Get search suggestions
     */
    public function getSuggestions(): array
    {
        $suggestions = [];
        
        if ($this->identifier) {
            switch ($this->identifierType) {
                case 'barcode':
                    $suggestions[] = 'Check if the barcode was scanned correctly.';
                    $suggestions[] = 'Verify the barcode exists in the product database.';
                    $suggestions[] = 'Ensure the product has been added to inventory.';
                    break;
                    
                case 'sku':
                    $suggestions[] = 'Verify the SKU format (should be alphanumeric).';
                    $suggestions[] = 'Check if the product is active and not archived.';
                    $suggestions[] = 'Search for similar SKUs in the database.';
                    break;
                    
                case 'id':
                    $suggestions[] = 'Verify the product ID is correct.';
                    $suggestions[] = 'Check if the product has been deleted.';
                    break;
                    
                default:
                    $suggestions[] = 'Try searching by different criteria (SKU, barcode, name).';
                    $suggestions[] = 'Check if the product is in a different category.';
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Check if it's a barcode not found error
     */
    public function isBarcodeNotFound(): bool
    {
        return $this->identifierType === 'barcode';
    }
    
    /**
     * Check if it's a SKU not found error
     */
    public function isSkuNotFound(): bool
    {
        return $this->identifierType === 'sku';
    }
    
    /**
     * Check if it's an ID not found error
     */
    public function isIdNotFound(): bool
    {
        return $this->identifierType === 'id';
    }
    
    /**
     * Get alternative search suggestions
     */
    public function getAlternativeSearchTerms(): array
    {
        if (!$this->identifier) {
            return [];
        }
        
        $terms = [];
        
        // For barcode-like identifiers
        if (ctype_digit($this->identifier)) {
            // Try removing check digit
            if (strlen($this->identifier) === 13) {
                $terms[] = substr($this->identifier, 0, 12);
            } elseif (strlen($this->identifier) === 12) {
                $terms[] = substr($this->identifier, 0, 11);
            }
            
            // Try adding leading zero
            $terms[] = '0' . $this->identifier;
            
            // Try removing leading zeros
            $terms[] = ltrim($this->identifier, '0');
        }
        
        // For SKU-like identifiers
        if (preg_match('/^[A-Z0-9]+$/i', $this->identifier)) {
            // Try variations
            $terms[] = str_replace(['-', '_', ' ', '.'], '', $this->identifier);
            $terms[] = strtoupper($this->identifier);
            $terms[] = strtolower($this->identifier);
            
            // Try common SKU variations
            $terms[] = str_replace(' ', '-', $this->identifier);
            $terms[] = str_replace('-', '', $this->identifier);
        }
        
        return array_unique(array_filter($terms));
    }
    
    /**
     * Get user-friendly error message
     */
    public function getUserMessage(): string
    {
        if ($this->identifier && $this->identifierType) {
            switch ($this->identifierType) {
                case 'barcode':
                    return "No product found with barcode: {$this->identifier}";
                case 'sku':
                    return "No product found with SKU: {$this->identifier}";
                case 'id':
                    return "No product found with ID: {$this->identifier}";
                default:
                    return "Product not found: {$this->identifier}";
            }
        }
        
        return $this->getMessage();
    }
    
    /**
     * Create exception for barcode not found
     */
    public static function forBarcode(string $barcode, string $message = null): self
    {
        $message = $message ?? "Product with barcode '{$barcode}' not found";
        
        return new self(
            $message,
            0,
            null,
            $barcode,
            'barcode',
            null,
            null,
            $barcode
        );
    }
    
    /**
     * Create exception for SKU not found
     */
    public static function forSku(string $sku, string $message = null): self
    {
        $message = $message ?? "Product with SKU '{$sku}' not found";
        
        return new self(
            $message,
            0,
            null,
            $sku,
            'sku',
            null,
            $sku,
            null
        );
    }
    
    /**
     * Create exception for product ID not found
     */
    public static function forId(int $id, string $message = null): self
    {
        $message = $message ?? "Product with ID '{$id}' not found";
        
        return new self(
            $message,
            0,
            null,
            (string)$id,
            'id',
            null,
            null,
            null
        );
    }
    
    /**
     * Create exception for product name not found
     */
    public static function forName(string $name, string $message = null): self
    {
        $message = $message ?? "Product with name '{$name}' not found";
        
        return new self(
            $message,
            0,
            null,
            $name,
            'name',
            null,
            null,
            null
        );
    }
}