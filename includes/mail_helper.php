<?php
// includes/mail_helper.php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$mailAutoloadPath = __DIR__ . '/../vendor/autoload.php';

// Load Composer dependencies only when the autoloader actually exists.
if (file_exists($mailAutoloadPath)) {
    require_once $mailAutoloadPath;
}

require_once __DIR__ . '/../config/mail.php';

class MailHelper
{
    private $mail = null;
    private $config = [];
    private $available = false;
    private $initializationError = '';

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/mail.php';

        // Fail gracefully when PHPMailer is not installed instead of causing a fatal error.
        if (!class_exists(PHPMailer::class)) {
            $this->initializationError = 'PHPMailer is not installed. Run composer install to enable email sending.';
            error_log($this->initializationError);
            return;
        }

        $this->mail = new PHPMailer(true);
        $this->setupMailer();
        $this->available = true;
    }

    private function setupMailer(): void
    {
        if (!$this->mail instanceof PHPMailer) {
            return;
        }

        $this->mail->SMTPDebug = SMTP::DEBUG_OFF;
        $this->mail->isSMTP();
        $this->mail->Host = $this->config['smtp_host'];
        $this->mail->SMTPAuth = $this->config['smtp_auth'];
        $this->mail->Username = $this->config['smtp_username'];
        $this->mail->Password = $this->config['smtp_password'];
        $this->mail->SMTPSecure = $this->config['smtp_secure'];
        $this->mail->Port = $this->config['smtp_port'];

        $this->mail->setFrom($this->config['from_email'], $this->config['from_name']);
        $this->mail->addReplyTo($this->config['reply_to'], $this->config['reply_to_name']);

        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getInitializationError(): string
    {
        return $this->initializationError;
    }

    public function sendEmail($to, $subject, $htmlBody, $altBody = '')
    {
        if (!$this->available || !$this->mail instanceof PHPMailer) {
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . $this->config['from_name'] . ' <' . $this->config['from_email'] . '>',
                'Reply-To: ' . $this->config['reply_to'],
            ];

            $sent = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
            if ($sent) {
                return ['success' => true, 'message' => 'Email sent successfully using the server mail function.'];
            }

            return [
                'success' => false,
                'message' => $this->initializationError !== '' ? $this->initializationError : 'Email service is not available on this server yet.',
            ];
        }

        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = $altBody ?: strip_tags($htmlBody);

            $this->mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $this->mail->ErrorInfo);
            return ['success' => false, 'message' => $this->mail->ErrorInfo];
        }
    }

    public function sendVerificationEmail($email, $username, $token)
    {
        $verificationLink = 'http://' . $_SERVER['HTTP_HOST'] . '/verify.php?token=' . $token;

        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 10px; text-align: center; }
                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    background: #4CAF50;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Email Verification</h2>
                </div>
                <p>Hello {$username},</p>
                <p>Thank you for registering! Please verify your email address by clicking the button below:</p>
                <p style='text-align: center;'>
                    <a href='{$verificationLink}' class='button'>Verify Email Address</a>
                </p>
                <p>If the button doesn't work, copy and paste this link:</p>
                <p>{$verificationLink}</p>
                <p>This link will expire in 24 hours.</p>
            </div>
        </body>
        </html>";

        return $this->sendEmail($email, 'Verify Your Email Address', $htmlBody);
    }

    public function sendRegistrationVerificationCode(string $email, string $fullName, string $code, DateTimeInterface $expiry, string $verifyUrl)
    {
        $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
        $expiryLabel = htmlspecialchars($expiry->format('M j, Y g:i A'), ENT_QUOTES, 'UTF-8');

        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937; background: #f8fafc; margin: 0; padding: 24px; }
                .card { max-width: 620px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 18px; overflow: hidden; }
                .header { background: linear-gradient(135deg, #c78b2a, #e3b766); color: #fff; padding: 24px 28px; }
                .content { padding: 28px; }
                .code { font-size: 34px; letter-spacing: 10px; font-weight: 700; text-align: center; color: #111827; background: #fff7e6; border: 1px dashed #e3b766; border-radius: 14px; padding: 18px; margin: 24px 0; }
                .button { display: inline-block; padding: 12px 18px; background: #111827; color: #fff !important; text-decoration: none; border-radius: 10px; font-weight: 600; }
                .footer { font-size: 13px; color: #6b7280; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='card'>
                <div class='header'>
                    <h2 style='margin:0;'>Verify your account</h2>
                </div>
                <div class='content'>
                    <p>Hello {$safeName},</p>
                    <p>Thanks for registering. Use the verification code below to activate your account.</p>
                    <div class='code'>{$safeCode}</div>
                    <p>This code expires at <strong>{$expiryLabel}</strong> and is valid for 15 minutes.</p>
                    <p><a href='{$safeUrl}' class='button'>Open verification page</a></p>
                    <p>If the button does not work, open this link in your browser:</p>
                    <p><a href='{$safeUrl}'>{$safeUrl}</a></p>
                    <div class='footer'>
                        If you did not create this account, you can safely ignore this email.
                    </div>
                </div>
            </div>
        </body>
        </html>";

        $altBody = "Hello {$fullName},\n\nYour verification code is {$code}.\nIt expires at {$expiry->format('Y-m-d H:i:s')}.\nVerify here: {$verifyUrl}\n";

        return $this->sendEmail($email, 'Your verification code', $htmlBody, $altBody);
    }

    public function sendOrderConfirmation($email, $username, $orderDetails)
    {
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .order-details { background: #f9f9f9; padding: 15px; border-radius: 5px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <h2>Order Confirmation</h2>
            <p>Thank you for your order, {$username}!</p>
            <div class='order-details'>
                <h3>Order #{$orderDetails['order_number']}</h3>
                <p><strong>Date:</strong> {$orderDetails['date']}</p>
                <p><strong>Total:</strong> \${$orderDetails['total']}</p>
                <p><strong>Status:</strong> {$orderDetails['status']}</p>
            </div>
            <p>We'll notify you when your order ships.</p>
        </body>
        </html>";

        return $this->sendEmail($email, 'Order Confirmation #' . $orderDetails['order_number'], $htmlBody);
    }
}

// Preserve the shared helper instance for any legacy includes that still expect it.
$mailHelper = new MailHelper();
