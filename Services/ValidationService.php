<?php

namespace App\Services;

use App\Exceptions\InvalidBarcodeException;
use App\Exceptions\ValidationException;
use App\Models\AbstractProduct;

class ValidationService
{
    private array $validationRules = [];
    private array $validationErrors = [];

    public function __construct()
    {
        $this->initializeValidationRules();
    }

    private function initializeValidationRules(): void
    {
        $this->validationRules = [
            'barcode' => [
                'pattern' => '/^[0-9]{12,14}$/',
                'message' => 'Barcode must be 12-14 numeric digits (EAN/UPC format)',
            ],
            'sku' => [
                'pattern' => '/^[A-Z0-9]{8,12}$/',
                'message' => 'SKU must be 8-12 uppercase alphanumeric characters',
            ],
            'quantity' => [
                'min' => 0,
                'max' => 10000,
                'message' => 'Quantity must be between 0 and 10,000',
            ],
            'price' => [
                'min' => 0,
                'max' => 10000,
                'message' => 'Price must be between 0 and 10,000 with 2 decimal places',
            ],
        ];
    }

    public function validateBarcode(string $barcode): bool
    {
        if (!preg_match($this->validationRules['barcode']['pattern'], $barcode)) {
            throw new InvalidBarcodeException(
                $this->validationRules['barcode']['message'],
                $barcode
            );
        }

        if (strlen($barcode) === 13 && !$this->validateEAN13CheckDigit($barcode)) {
            throw new InvalidBarcodeException('Invalid EAN-13 check digit', $barcode);
        }

        if (strlen($barcode) === 12 && !$this->validateUPCACheckDigit($barcode)) {
            throw new InvalidBarcodeException('Invalid UPC-A check digit', $barcode);
        }

        return true;
    }

    private function validateEAN13CheckDigit(string $barcode): bool
    {
        $checkDigit = (int) substr($barcode, -1);
        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $barcode[$i];
            $sum += ($i % 2 === 0) ? $digit : ($digit * 3);
        }

        $calculatedCheckDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit === $calculatedCheckDigit;
    }

    private function validateUPCACheckDigit(string $barcode): bool
    {
        return $this->validateEAN13CheckDigit('0' . $barcode);
    }

    public function validateProduct(AbstractProduct $product): bool
    {
        $this->validationErrors = [];

        try {
            $this->validateSku($product->getSku());
            $this->validatePrice($product->getPrice());
            $this->validateSafetyStock($product->getSafetyStock());
            $this->validateFieldLength($product->getName(), 'name', 2, 100);
            $this->validateFieldLength($product->getBrand(), 'brand', 2, 50);

            if (method_exists($product, 'getBarcode')) {
                $barcode = (string) $product->getBarcode();
                if ($barcode !== '') {
                    $this->validateBarcode($barcode);
                }
            }
        } catch (ValidationException | InvalidBarcodeException $e) {
            $this->validationErrors[] = $e->getMessage();
        }

        return empty($this->validationErrors);
    }

    private function validateSku(string $sku): void
    {
        if (!preg_match($this->validationRules['sku']['pattern'], $sku)) {
            throw new ValidationException($this->validationRules['sku']['message']);
        }
    }

    private function validatePrice(float $price): void
    {
        $rule = $this->validationRules['price'];
        if ($price < $rule['min'] || $price > $rule['max'] || round($price, 2) != $price) {
            throw new ValidationException($rule['message']);
        }
    }

    private function validateSafetyStock(int $quantity): void
    {
        $rule = $this->validationRules['quantity'];
        if ($quantity < $rule['min'] || $quantity > $rule['max']) {
            throw new ValidationException($rule['message']);
        }
    }

    private function validateFieldLength(?string $value, string $field, int $min, int $max): void
    {
        $length = strlen($value ?? '');
        if ($length < $min || $length > $max) {
            throw new ValidationException(
                ucfirst($field) . " must be between {$min} and {$max} characters"
            );
        }
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
