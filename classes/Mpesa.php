<?php

class Mpesa
{
    private string $environment;
    private string $consumerKey;
    private string $consumerSecret;
    private string $shortcode;
    private string $passkey;
    private string $callbackUrl;
    private string $baseUrl;
    private int $timeoutSeconds;

    public function __construct(array $overrides = [])
    {
        $config = [];
        $configPath = __DIR__ . '/../config/mpesa.php';
        if (is_file($configPath)) {
            $loaded = require $configPath;
            if (is_array($loaded)) {
                $config = $loaded;
            }
        }

        $settings = array_merge($config, $overrides);
        $baseUrls = $settings['base_urls'] ?? [];

        $this->environment = strtolower((string) ($settings['environment'] ?? 'sandbox'));
        $this->consumerKey = trim((string) ($settings['consumer_key'] ?? ''));
        $this->consumerSecret = trim((string) ($settings['consumer_secret'] ?? ''));
        $this->shortcode = trim((string) ($settings['shortcode'] ?? ''));
        $this->passkey = trim((string) ($settings['passkey'] ?? ''));
        $this->callbackUrl = trim((string) ($settings['callback_url'] ?? ''));
        $this->timeoutSeconds = max(10, (int) ($settings['timeout_seconds'] ?? 30));
        $this->baseUrl = rtrim((string) ($baseUrls[$this->environment] ?? $baseUrls['sandbox'] ?? 'https://sandbox.safaricom.co.ke'), '/');
    }

    public function validatePhoneNumber(string $phoneNumber): bool
    {
        return $this->formatPhoneNumber($phoneNumber) !== null;
    }

    public function formatPhoneNumber(string $phoneNumber): ?string
    {
        $normalized = preg_replace('/\D+/', '', $phoneNumber);
        if ($normalized === null || $normalized === '') {
            return null;
        }

        if (strpos($normalized, '0') === 0 && strlen($normalized) === 10) {
            $normalized = '254' . substr($normalized, 1);
        } elseif (strpos($normalized, '7') === 0 && strlen($normalized) === 9) {
            $normalized = '254' . $normalized;
        } elseif (strpos($normalized, '1') === 0 && strlen($normalized) === 9) {
            $normalized = '254' . $normalized;
        }

        return preg_match('/^254(1|7)\d{8}$/', $normalized) ? $normalized : null;
    }

    public function stkPush(string $phoneNumber, $amount, string $accountReference, string $transactionDesc = 'Order payment'): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'M-Pesa is not configured. Missing: ' . implode(', ', $this->getMissingConfigurationFields()) . '. Set Daraja credentials in environment variables or create config/mpesa.local.php from config/mpesa.local.example.php.',
            ];
        }

        $formattedPhone = $this->formatPhoneNumber($phoneNumber);
        if ($formattedPhone === null) {
            return [
                'success' => false,
                'message' => 'Invalid phone number. Use 2547XXXXXXXX or 2541XXXXXXXX.',
            ];
        }

        $amountValue = (float) $amount;
        if ($amountValue <= 0) {
            return [
                'success' => false,
                'message' => 'Amount must be greater than zero.',
            ];
        }

        $timestamp = date('YmdHis');
        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => base64_encode($this->shortcode . $this->passkey . $timestamp),
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) round($amountValue),
            'PartyA' => $formattedPhone,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $formattedPhone,
            'CallbackURL' => $this->callbackUrl,
            'AccountReference' => substr(trim($accountReference) !== '' ? trim($accountReference) : 'LuxeStore', 0, 20),
            'TransactionDesc' => substr(trim($transactionDesc) !== '' ? trim($transactionDesc) : 'LuxeStore payment', 0, 50),
        ];

        $response = $this->request('/mpesa/stkpush/v1/processrequest', $payload);
        if (!$response['success']) {
            return $response;
        }

        $body = $response['data'];
        $accepted = isset($body['ResponseCode']) && (string) $body['ResponseCode'] === '0';

        return [
            'success' => $accepted,
            'message' => $body['CustomerMessage'] ?? $body['errorMessage'] ?? ($accepted ? 'STK Push sent successfully.' : 'Failed to initiate STK Push.'),
            'checkout_request_id' => $body['CheckoutRequestID'] ?? null,
            'merchant_request_id' => $body['MerchantRequestID'] ?? null,
            'response_code' => $body['ResponseCode'] ?? null,
            'response_description' => $body['ResponseDescription'] ?? null,
            'customer_message' => $body['CustomerMessage'] ?? null,
            'raw_response' => $body,
        ];
    }

    public function queryStkStatus(string $checkoutRequestId): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'M-Pesa is not configured.',
            ];
        }

        $checkoutRequestId = trim($checkoutRequestId);
        if ($checkoutRequestId === '') {
            return [
                'success' => false,
                'message' => 'CheckoutRequestID is required.',
            ];
        }

        $timestamp = date('YmdHis');
        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => base64_encode($this->shortcode . $this->passkey . $timestamp),
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        return $this->request('/mpesa/stkpushquery/v1/query', $payload);
    }

    public function maskPhoneNumber(string $phoneNumber): string
    {
        $formatted = $this->formatPhoneNumber($phoneNumber);
        if ($formatted === null) {
            return $phoneNumber;
        }

        return substr($formatted, 0, 5) . '****' . substr($formatted, -3);
    }

    public function isConfigured(): bool
    {
        return count($this->getMissingConfigurationFields()) === 0;
    }

    public function getMissingConfigurationFields(): array
    {
        $missing = [];

        if ($this->consumerKey === '') {
            $missing[] = 'consumer_key';
        }
        if ($this->consumerSecret === '') {
            $missing[] = 'consumer_secret';
        }
        if ($this->shortcode === '') {
            $missing[] = 'shortcode';
        }
        if ($this->passkey === '') {
            $missing[] = 'passkey';
        }
        if ($this->callbackUrl === '') {
            $missing[] = 'callback_url';
        }

        return $missing;
    }

    private function getAccessToken(): string
    {
        $url = $this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->consumerKey . ':' . $this->consumerSecret,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Could not contact M-Pesa OAuth endpoint: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);
        if ($statusCode >= 400 || !is_array($decoded) || empty($decoded['access_token'])) {
            $message = is_array($decoded) ? ($decoded['errorMessage'] ?? $decoded['error_description'] ?? 'Could not get access token.') : 'Could not get access token.';
            throw new RuntimeException($message);
        }

        return (string) $decoded['access_token'];
    }

    private function request(string $path, array $payload): array
    {
        try {
            $token = $this->getAccessToken();
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'success' => false,
                'message' => 'Unable to reach the M-Pesa API: ' . $error,
            ];
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'message' => 'Invalid response from the M-Pesa API.',
                'status_code' => $statusCode,
                'raw_response' => $raw,
            ];
        }

        $success = $statusCode >= 200 && $statusCode < 300 && !isset($decoded['errorCode']);

        return [
            'success' => $success,
            'message' => $decoded['errorMessage'] ?? $decoded['ResponseDescription'] ?? ($success ? 'Request completed successfully.' : 'M-Pesa request failed.'),
            'status_code' => $statusCode,
            'data' => $decoded,
        ];
    }
}
