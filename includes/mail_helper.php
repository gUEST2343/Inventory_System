<?php
// includes/mail_helper.php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$mailAutoloadPath = __DIR__ . '/../vendor/autoload.php';

if (file_exists($mailAutoloadPath)) {
    require_once $mailAutoloadPath;
}

if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer') && file_exists(__DIR__ . '/phpmailer_shim.php')) {
    require_once __DIR__ . '/phpmailer_shim.php';
}

require_once __DIR__ . '/../config/mail.php';

class MailHelper
{
    private ?PHPMailer $mail = null;
    private array $config = [];
    private bool $available = false;
    private string $initializationError = '';

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/mail.php';

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

    /**
     * Send an email using PHPMailer, SMTP socket fallback, or PHP mail().
     *
     * @param string $to
     * @param string $subject
     * @param string $htmlBody
     * @param string $altBody
     * @return array{success: bool, message: string}
     */
    public function sendEmail(string $to, string $subject, string $htmlBody, string $altBody = ''): array
    {
        if ($this->available && $this->mail instanceof PHPMailer) {
            try {
                $this->mail->clearAddresses();
                $this->mail->addAddress($to);
                $this->mail->Subject = $subject;
                $this->mail->Body = $htmlBody;
                $this->mail->AltBody = $altBody ?: strip_tags($htmlBody);
                $this->mail->send();

                return ['success' => true, 'message' => 'Email sent successfully'];
            } catch (Exception $e) {
                error_log('Mailer Error: ' . $this->mail->ErrorInfo . ' | ' . $e->getMessage());
                $this->initializationError = $this->mail->ErrorInfo ?: $e->getMessage();
            }
        }

        if ($this->sendEmailViaSmtpSocket($to, $subject, $htmlBody, $altBody)) {
            return ['success' => true, 'message' => 'Email sent successfully using SMTP fallback'];
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->config['from_name'] . ' <' . $this->config['from_email'] . '>',
            'Reply-To: ' . $this->config['reply_to'],
        ];

        $sent = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        if ($sent) {
            return ['success' => true, 'message' => 'Email sent successfully using the server mail() function.'];
        }

        return [
            'success' => false,
            'message' => $this->initializationError !== '' ? $this->initializationError : 'Email service is not available on this server yet.'
        ];
    }

    private function sendEmailViaSmtpSocket(string $to, string $subject, string $htmlBody, string $altBody = ''): bool
    {
        if (empty($this->config['smtp_host']) || empty($this->config['smtp_port'])) {
            return false;
        }

        $host = $this->config['smtp_host'];
        $port = (int)$this->config['smtp_port'];
        $security = strtolower($this->config['smtp_secure'] ?? '');
        $transportHost = $security === 'ssl' ? 'ssl://' . $host : $host;

        $socket = @fsockopen($transportHost, $port, $errno, $errstr, 30);
        if (!$socket) {
            error_log("SMTP fallback connect failed: {$errno} {$errstr}");
            return false;
        }

        stream_set_timeout($socket, 30);

        try {
            $this->expectSmtpResponse($socket, 220);

            $hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
            fwrite($socket, "EHLO {$hostName}\r\n");
            $this->expectSmtpResponse($socket, 250);

            if ($security === 'tls' || $security === 'starttls') {
                fwrite($socket, "STARTTLS\r\n");
                $this->expectSmtpResponse($socket, 220);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fwrite($socket, "EHLO {$hostName}\r\n");
                $this->expectSmtpResponse($socket, 250);
            }

            if (!empty($this->config['smtp_auth'])) {
                fwrite($socket, "AUTH LOGIN\r\n");
                $this->expectSmtpResponse($socket, 334);
                fwrite($socket, base64_encode($this->config['smtp_username']) . "\r\n");
                $this->expectSmtpResponse($socket, 334);
                fwrite($socket, base64_encode($this->config['smtp_password']) . "\r\n");
                $this->expectSmtpResponse($socket, 235);
            }

            fwrite($socket, "MAIL FROM:<{$this->config['from_email']}>\r\n");
            $this->expectSmtpResponse($socket, 250);

            fwrite($socket, "RCPT TO:<{$to}>\r\n");
            $this->expectSmtpResponse($socket, 250);

            fwrite($socket, "DATA\r\n");
            $this->expectSmtpResponse($socket, 354);

            $headers = [
                "From: {$this->config['from_name']} <{$this->config['from_email']}>",
                "Reply-To: {$this->config['reply_to']}",
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=UTF-8",
                "Subject: {$subject}",
                'Date: ' . date('r'),
            ];

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody;
            fwrite($socket, $message . "\r\n.\r\n");
            $this->expectSmtpResponse($socket, 250);

            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            return true;
        } catch (\RuntimeException $e) {
            error_log('SMTP fallback error: ' . $e->getMessage());
            fclose($socket);
            return false;
        }
    }

    /**
     * Read SMTP response lines from the socket and validate status code.
     *
     * @param resource $socket
     * @param int $expectedCode
     * @return void
     * @throws \RuntimeException
     */
    private function expectSmtpResponse($socket, int $expectedCode): void
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }

        if ((int)substr($response, 0, 3) !== $expectedCode) {
            throw new \RuntimeException('SMTP error: ' . trim($response));
        }
    }

    // ... existing sendVerificationEmail, sendRegistrationVerificationCode, sendOrderConfirmation ...
}