<?php

if (!class_exists('ShimPHPMailerException')) {
    class ShimPHPMailerException extends \Exception {}
    class_alias('ShimPHPMailerException', 'PHPMailer\\PHPMailer\\Exception');
}

if (!class_exists('ShimPHPMailerSMTP')) {
    class ShimPHPMailerSMTP
    {
        const DEBUG_OFF = 0;
    }
    class_alias('ShimPHPMailerSMTP', 'PHPMailer\\PHPMailer\\SMTP');
}

if (!class_exists('ShimPHPMailer')) {
    class ShimPHPMailer
    {
        public $SMTPDebug = 0;
        public $Host;
        public $SMTPAuth;
        public $Username;
        public $Password;
        public $SMTPSecure;
        public $Port;
        public $From;
        public $FromName;
        public $CharSet = 'UTF-8';
        public $Subject;
        public $Body;
        public $AltBody;
        public $ErrorInfo = '';

        private $to = [];

        public function __construct($exceptions = null)
        {
        }

        public function isSMTP() {}

        public function setFrom($email, $name = '')
        {
            $this->From = $email;
            $this->FromName = $name;
        }

        public function addReplyTo($address, $name = '') {}

        public function isHTML($bool) {}

        public function clearAddresses()
        {
            $this->to = [];
        }

        public function addAddress($address)
        {
            $this->to[] = $address;
        }

        public function send()
        {
            $to = implode(',', $this->to);
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset={$this->CharSet}\r\n";
            if (!empty($this->From)) {
                $headers .= 'From: ' . ($this->FromName ?: $this->From) . " <{$this->From}>\r\n";
            }

            $sent = @mail($to, $this->Subject ?? '', $this->Body ?? '', $headers);
            if (!$sent) {
                $this->ErrorInfo = 'PHP mail() failed in PHPMailer shim';
                throw new ShimPHPMailerException($this->ErrorInfo);
            }

            return true;
        }
    }
    class_alias('ShimPHPMailer', 'PHPMailer\\PHPMailer\\PHPMailer');
}
