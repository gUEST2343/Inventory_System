<?php

$defaultConfig = [
    'environment' => 'sandbox',
    'consumer_key' => '',
    'consumer_secret' => '',
    'shortcode' => '',
    'passkey' => '',
    'callback_url' => 'https://luxestore.shop/api/mpesa/callback.php',
    'initiator_name' => '',
    'security_credential' => '',
    'timeout_seconds' => 30,
    'base_urls' => [
        'sandbox' => 'https://sandbox.safaricom.co.ke',
        'production' => 'https://api.safaricom.co.ke',
    ],
];

$envConfig = [
    'environment' => getenv('MPESA_ENV') ?: $defaultConfig['environment'],
    'consumer_key' => getenv('MPESA_CONSUMER_KEY') ?: $defaultConfig['consumer_key'],
    'consumer_secret' => getenv('MPESA_CONSUMER_SECRET') ?: $defaultConfig['consumer_secret'],
    'shortcode' => getenv('MPESA_SHORTCODE') ?: $defaultConfig['shortcode'],
    'passkey' => getenv('MPESA_PASSKEY') ?: $defaultConfig['passkey'],
    'callback_url' => getenv('MPESA_CALLBACK_URL') ?: $defaultConfig['callback_url'],
    'initiator_name' => getenv('MPESA_INITIATOR_NAME') ?: $defaultConfig['initiator_name'],
    'security_credential' => getenv('MPESA_SECURITY_CREDENTIAL') ?: $defaultConfig['security_credential'],
    'timeout_seconds' => (int) (getenv('MPESA_TIMEOUT_SECONDS') ?: $defaultConfig['timeout_seconds']),
];

$localConfig = [];
$localConfigPath = __DIR__ . '/mpesa.local.php';
if (is_file($localConfigPath)) {
    $loadedLocalConfig = require $localConfigPath;
    if (is_array($loadedLocalConfig)) {
        $localConfig = $loadedLocalConfig;
    }
}

return array_replace_recursive($defaultConfig, $envConfig, $localConfig);
