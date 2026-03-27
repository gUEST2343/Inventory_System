<?php
// config/mail.php
return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_auth' => true,
    'smtp_username' => 'your-email@gmail.com', // Replace with your email
    'smtp_password' => 'your-app-password', // Replace with Gmail App Password
    'from_email' => 'noreply@yourproject.com',
    'from_name' => 'Your Project Name',
    'reply_to' => 'support@yourproject.com',
    'reply_to_name' => 'Customer Support',
];
