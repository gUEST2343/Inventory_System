<?php
return [
    'smtp_host'     => getenv('MAIL_HOST')         ?: 'smtp.gmail.com',
    'smtp_port'     => getenv('MAIL_PORT')         ?: 25,
    'smtp_secure'   => 'tls',
    'smtp_auth'     => true,
    'smtp_username' => getenv('MAIL_USERNAME')     ?: '',
    'smtp_password' => getenv('MAIL_PASSWORD')     ?: '',
    'from_email'    => getenv('MAIL_FROM_ADDRESS') ?: '',
    'from_name'     => getenv('MAIL_FROM_NAME')    ?: 'Inventory System',
    'reply_to'      => getenv('MAIL_FROM_ADDRESS') ?: '',
    'reply_to_name' => 'Support',
];
