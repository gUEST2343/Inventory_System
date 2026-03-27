<?php
/**
 * M-Pesa Payment Integration Class
 * Handles M-Pesa STK Push, B2C, and C2B transactions
 */

class Mpesa {
    private $environment;
    private $consumerKey;
    private $consumerSecret;
    private $shortcode;
    private $passkey;
    private $callbackUrl;
    private $confirmationUrl;
    private $validationUrl;
    private $lastError;
    
    // M-Pesa Endpoints
    private $endpoints = [
        'sandbox' => [
            'oauth' => 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
            'stk_push' => 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
            'stk_status' => 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query',
            'b2c' => 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest',
            'c2b_register' => 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl',
            'c2b_simulate' => 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/simulate',
            'balance' => 'https://sandbox.safaricom.co.ke/mpesa/accountbalance/v1/query',
            'transaction_status' => 'https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query'
        ],
        'production' => [
            'oauth' => 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
            'stk_push' => 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
            'stk_status' => 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query',
            'b2c' => 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest',
            'c2b_register' => 'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl',
            'c2b_simulate' => 'https://api.safaricom.co.ke/mpesa/c2b/v1/simulate',
            'balance' => 'https://api.safaricom.co.ke/mpesa/accountbalance/v1/query',
            'transaction_status' => 'https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query'
        ]
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->environment = $this->getConfigValue('MPESA_ENV', defined('MPESA_ENVIRONMENT') ? MPESA_ENVIRONMENT : 'sandbox');
        $this->consumerKey = $this->getConfigValue('MPESA_CONSUMER_KEY', defined('MPESA_CONSUMER_KEY') ? MPESA_CONSUMER_KEY : 'your_consumer_key');
        $this->consumerSecret = $this->getConfigValue('MPESA_CONSUMER_SECRET', defined('MPESA_CONSUMER_SECRET') ? MPESA_CONSUMER_SECRET : 'your_consumer_secret');
        $this->shortcode = $this->getConfigValue('MPESA_SHORTCODE', defined('MPESA_SHORTCODE') ? MPESA_SHORTCODE : '174379');
        $this->passkey = $this->getConfigValue('MPESA_PASSKEY', defined('MPESA_PASSKEY') ? MPESA_PASSKEY : 'your_passkey');
        $this->callbackUrl = $this->getConfigValue('MPESA_CALLBACK_URL', defined('MPESA_CALLBACK_URL') ? MPESA_CALLBACK_URL : 'http://localhost/api/mpesa/callback.php');
        $this->confirmationUrl = $this->getConfigValue('MPESA_CONFIRMATION_URL', 'http://localhost/api/mpesa/confirmation.php');
        $this->validationUrl = $this->getConfigValue('MPESA_VALIDATION_URL', 'http://localhost/api/mpesa/validation.php');
        $this->lastError = '';
    }

    /**
     * Read config from getenv, $_ENV, $_SERVER, then fallback.
     */
    private function getConfigValue($key, $fallback = '') {
        $envValue = getenv($key);
        if ($envValue !== false && $envValue !== '') {
            return $envValue;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        return $fallback;
    }

    /**
     * Return last error message for debugging.
     */
    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Check if core M-Pesa credentials are configured.
     */
    public function isConfigured() {
        return
            !empty($this->consumerKey) &&
            !empty($this->consumerSecret) &&
            !empty($this->passkey) &&
            $this->consumerKey !== 'your_consumer_key' &&
            $this->consumerSecret !== 'your_consumer_secret' &&
            $this->passkey !== 'your_passkey';
    }

    /**
     * Send HTTP request using cURL (preferred) or stream fallback.
     */
    private function sendRequest($url, $headers = [], $payload = null) {
        // Prefer cURL when extension is available.
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($payload) ? $payload : json_encode($payload));
            }

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                return [
                    'success' => false,
                    'status_code' => $statusCode,
                    'body' => null,
                    'error' => $error ?: 'Unknown cURL error'
                ];
            }

            return [
                'success' => true,
                'status_code' => $statusCode,
                'body' => $response,
                'error' => null
            ];
        }

        // Fallback when cURL extension is not enabled.
        if (!function_exists('file_get_contents')) {
            return [
                'success' => false,
                'status_code' => 0,
                'body' => null,
                'error' => 'HTTP client unavailable. Enable PHP cURL extension.'
            ];
        }

        $method = $payload !== null ? 'POST' : 'GET';
        $headersString = implode("\r\n", $headers);

        $contextOptions = [
            'http' => [
                'method' => $method,
                'header' => $headersString,
                'ignore_errors' => true,
                'timeout' => 30
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];

        if ($payload !== null) {
            $contextOptions['http']['content'] = is_string($payload) ? $payload : json_encode($payload);
        }

        $context = stream_context_create($contextOptions);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $lastError = error_get_last();
            return [
                'success' => false,
                'status_code' => 0,
                'body' => null,
                'error' => $lastError['message'] ?? 'HTTP request failed'
            ];
        }

        return [
            'success' => true,
            'status_code' => 200,
            'body' => $response,
            'error' => null
        ];
    }
    
    /**
     * Get access token
     */
    public function getAccessToken() {
        // Guard against placeholder/default credentials.
        if (
            !$this->consumerKey || !$this->consumerSecret ||
            $this->consumerKey === 'your_consumer_key' ||
            $this->consumerSecret === 'your_consumer_secret'
        ) {
            $this->lastError = 'M-Pesa credentials are not configured. Set MPESA_CONSUMER_KEY and MPESA_CONSUMER_SECRET.';
            error_log($this->lastError);
            return null;
        }

        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $httpResult = $this->sendRequest(
            $this->endpoints[$this->environment]['oauth'],
            ['Authorization: Basic ' . $credentials]
        );

        if (!$httpResult['success']) {
            $this->lastError = 'M-Pesa OAuth request failed: ' . $httpResult['error'];
            error_log($this->lastError);
            return null;
        }

        $result = json_decode($httpResult['body'], true);
        
        if (isset($result['access_token'])) {
            $this->lastError = '';
            return $result['access_token'];
        }

        $apiError = $result['errorMessage'] ?? $result['error_description'] ?? $result['error'] ?? 'Unknown OAuth error';
        $this->lastError = 'M-Pesa OAuth token not returned: ' . $apiError;
        error_log($this->lastError);
        
        return null;
    }
    
    /**
     * Generate password for STK Push
     */
    private function generatePassword() {
        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);
        return $password;
    }
    
    /**
     * Initiate STK Push
     */
    public function stkPush($phoneNumber, $amount, $accountReference, $transactionDescription = '') {
        // Local/sandbox fallback for development when credentials are not set.
        if (!$this->isConfigured()) {
            if ($this->environment === 'sandbox') {
                $mockCheckoutRequestId = 'MOCK-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
                return [
                    'success' => true,
                    'message' => 'Sandbox mock payment accepted (credentials not configured).',
                    'checkout_request_id' => $mockCheckoutRequestId,
                    'merchant_request_id' => 'MOCK-MERCHANT-' . mt_rand(1000, 9999),
                    'mock' => true
                ];
            }

            return [
                'success' => false,
                'message' => 'M-Pesa credentials are not configured. Set MPESA_CONSUMER_KEY and MPESA_CONSUMER_SECRET.'
            ];
        }

        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => $this->lastError ?: 'Failed to get access token'
            ];
        }
        
        $phone = $this->formatPhoneNumber($phoneNumber);
        $timestamp = date('YmdHis');
        $password = $this->generatePassword();
        
        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerBuyGoodsOnline',
            'Amount' => round($amount),
            'PartyA' => $phone,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDescription ?: 'Payment for order ' . $accountReference
        ];

        $httpResult = $this->sendRequest(
            $this->endpoints[$this->environment]['stk_push'],
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            $payload
        );

        if (!$httpResult['success']) {
            return [
                'success' => false,
                'message' => 'Payment gateway request failed: ' . $httpResult['error']
            ];
        }

        $result = json_decode($httpResult['body'], true);
        
        if (isset($result['ResponseCode']) && $result['ResponseCode'] == 0) {
            // Store the checkout request ID for status check
            $_SESSION['mpesa_checkout_request_id'] = $result['CheckoutRequestID'];
            
            return [
                'success' => true,
                'message' => 'STK Push initiated successfully',
                'checkout_request_id' => $result['CheckoutRequestID'],
                'merchant_request_id' => $result['MerchantRequestID']
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['ResponseDescription'] ?? 'Failed to initiate STK Push',
            'error_code' => $result['ResponseCode'] ?? null
        ];
    }
    
    /**
     * Check STK Push status
     */
    public function stkStatus($checkoutRequestId) {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => $this->lastError ?: 'Failed to get access token'
            ];
        }
        
        $timestamp = date('YmdHis');
        $password = $this->generatePassword();
        
        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId
        ];

        $httpResult = $this->sendRequest(
            $this->endpoints[$this->environment]['stk_status'],
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            $payload
        );

        if (!$httpResult['success']) {
            return [
                'success' => false,
                'message' => 'Failed to check STK status: ' . $httpResult['error']
            ];
        }

        $result = json_decode($httpResult['body'], true);
        
        if (isset($result['ResponseCode'])) {
            return [
                'success' => true,
                'status' => $result,
                'is_complete' => $result['ResponseCode'] == 0 && isset($result['ResultCode']),
                'is_success' => isset($result['ResultCode']) && $result['ResultCode'] == 0
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to check STK status'
        ];
    }
    
    /**
     * B2C Payment (Business to Customer)
     */
    public function b2c($phoneNumber, $amount, $commandId, $occasion = '', $remarks = '') {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return ['success' => false, 'message' => $this->lastError ?: 'Failed to get access token'];
        }
        
        $phone = $this->formatPhoneNumber($phoneNumber);
        
        $payload = [
            'InitiatorName' => getenv('MPESA_INITIATOR_NAME') ?: 'testapi',
            'SecurityCredential' => getenv('MPESA_SECURITY_CREDENTIAL') ?: 'your_security_credential',
            'CommandID' => $commandId, // SalaryPayment, BusinessPayment, PromotionPayment
            'Amount' => round($amount),
            'PartyA' => $this->shortcode,
            'PartyB' => $phone,
            'Remarks' => $remarks ?: 'B2C Payment',
            'QueueTimeOutURL' => $this->callbackUrl,
            'ResultURL' => $this->callbackUrl,
            'Occasion' => $occasion
        ];

        $httpResult = $this->sendRequest(
            $this->endpoints[$this->environment]['b2c'],
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            $payload
        );

        if (!$httpResult['success']) {
            return ['success' => false, 'message' => 'B2C request failed: ' . $httpResult['error']];
        }

        $result = json_decode($httpResult['body'], true);
        
        if (isset($result['ResponseCode']) && $result['ResponseCode'] == 0) {
            return [
                'success' => true,
                'message' => 'B2C payment initiated',
                'conversation_id' => $result['ConversationID'],
                'originator_conversation_id' => $result['OriginatorConversationID']
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['ResponseDescription'] ?? 'Failed to initiate B2C'
        ];
    }
    
    /**
     * Register C2B URLs
     */
    public function registerUrls() {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return ['success' => false, 'message' => $this->lastError ?: 'Failed to get access token'];
        }
        
        $payload = [
            'ShortCode' => $this->shortcode,
            'ResponseType' => 'Completed',
            'ConfirmationURL' => $this->confirmationUrl,
            'ValidationURL' => $this->validationUrl
        ];

        $httpResult = $this->sendRequest(
            $this->endpoints[$this->environment]['c2b_register'],
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            $payload
        );

        if (!$httpResult['success']) {
            return ['success' => false, 'message' => 'Failed to register C2B URLs: ' . $httpResult['error']];
        }

        $result = json_decode($httpResult['body'], true);
        
        if (isset($result['ResponseCode']) && $result['ResponseCode'] == 0) {
            return ['success' => true, 'message' => 'C2B URLs registered successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to register C2B URLs'];
    }
    
    /**
     * Simulate C2B
     */
    public function simulateC2B($phoneNumber, $amount, $commandId = 'CustomerPayBillOnline') {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return ['success' => false, 'message' => $this->lastError ?: 'Failed to get access token'];
        }
        
        $phone = $this->formatPhoneNumber($phoneNumber);
        
        $payload = [
            'ShortCode' => $this->shortcode,
            'CommandID' => $commandId,
            'Amount' => round($amount),
            'Msisdn' => $phone,
            'BillRefNumber' => 'TEST',
            'InvoiceNumber' => '',
            'CallBackMetaData' => [
                'BusinessShortCode' => $this->shortcode,
                'BillRefNumber' => 'TEST',
                'InvoiceNumber' => '',
                'Amount' => round($amount)
            ]
        ];

        $httpResult = $this->sendRequest(
            $this->endpoints[$this->environment]['c2b_simulate'],
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            $payload
        );

        if (!$httpResult['success']) {
            return ['success' => false, 'message' => 'Failed to simulate C2B: ' . $httpResult['error']];
        }

        $result = json_decode($httpResult['body'], true);
        
        if (isset($result['ResponseCode']) && $result['ResponseCode'] == 0) {
            return ['success' => true, 'message' => 'C2B simulation successful'];
        }
        
        return ['success' => false, 'message' => 'Failed to simulate C2B'];
    }
    
    /**
     * Format phone number to M-Pesa format
     */
    private function formatPhoneNumber($phone) {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If starts with 0, replace with 254
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
        // If doesn't start with 254, add it
        if (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Validate phone number
     */
    public function validatePhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Valid formats: 07XXXXXXXX (10 digits), 254XXXXXXXXX (12 digits)
        if (preg_match('/^(07|01)/', $phone) && strlen($phone) === 10) {
            return true;
        }
        
        if (preg_match('/^254/', $phone) && strlen($phone) === 12) {
            return true;
        }
        
        return false;
    }
}

/**
 * Helper function to get M-Pesa instance
 */
function mpesa() {
    return new Mpesa();
}
