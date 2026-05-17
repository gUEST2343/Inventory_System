<?php

require_once __DIR__ . '/mail_helper.php';

if (!function_exists('ensureUsersRegistrationSchema')) {
    function ensureUsersRegistrationSchema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                phone VARCHAR(20),
                customer_group VARCHAR(50) NOT NULL DEFAULT 'regular',
                role VARCHAR(20) NOT NULL DEFAULT 'customer',
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                is_verified BOOLEAN NOT NULL DEFAULT FALSE,
                account_status VARCHAR(20) NOT NULL DEFAULT 'pending',
                verification_code VARCHAR(255),
                code_expiry TIMESTAMP NULL,
                verification_failed_attempts INT NOT NULL DEFAULT 0,
                verification_locked_until TIMESTAMP NULL,
                verification_resend_count INT NOT NULL DEFAULT 0,
                last_verification_sent_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS password VARCHAR(255)");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(100)");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS full_name VARCHAR(100)");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20)");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS customer_group VARCHAR(50) DEFAULT 'regular'");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'customer'");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified BOOLEAN DEFAULT FALSE");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS account_status VARCHAR(20) DEFAULT 'active'");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_code VARCHAR(255)");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS code_expiry TIMESTAMP NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_failed_attempts INT DEFAULT 0");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_locked_until TIMESTAMP NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_resend_count INT DEFAULT 0");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_verification_sent_at TIMESTAMP NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

        $pdo->exec("
            UPDATE users
            SET
                full_name = COALESCE(NULLIF(full_name, ''), username),
                role = COALESCE(NULLIF(role, ''), 'customer'),
                customer_group = COALESCE(NULLIF(customer_group, ''), 'regular'),
                is_active = COALESCE(is_active, TRUE),
                created_at = COALESCE(created_at, CURRENT_TIMESTAMP),
                updated_at = COALESCE(updated_at, CURRENT_TIMESTAMP),
                is_verified = COALESCE(is_verified, FALSE),
                account_status = COALESCE(NULLIF(account_status, ''), CASE WHEN COALESCE(is_verified, FALSE) = TRUE THEN 'active' ELSE 'pending' END),
                verification_failed_attempts = COALESCE(verification_failed_attempts, 0),
                verification_resend_count = COALESCE(verification_resend_count, 0)
        ");

        $pdo->exec("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        $pdo->exec("
            ALTER TABLE users
            ADD CONSTRAINT users_role_check
            CHECK (role IN ('admin', 'manager', 'staff', 'customer', 'supplier'))
        ");

        $pdo->exec("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_account_status_check");
        $pdo->exec("
            ALTER TABLE users
            ADD CONSTRAINT users_account_status_check
            CHECK (account_status IN ('pending', 'active', 'suspended'))
        ");

        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS users_email_unique_ci_idx ON users (LOWER(email))");
    }
}

if (!function_exists('buildAppUrl')) {
    function buildAppUrl(string $path = ''): string
    {
        $configured = trim((string) (getenv('APP_URL') ?: ''));
        if ($configured !== '') {
            return rtrim($configured, '/') . '/' . ltrim($path, '/');
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . '/' . ltrim($path, '/');
    }
}

if (!function_exists('generateEmailVerificationCode')) {
    function generateEmailVerificationCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('getVerificationExpiryDateTime')) {
    function getVerificationExpiryDateTime(): DateTimeImmutable
    {
        return (new DateTimeImmutable('now'))->modify('+15 minutes');
    }
}

if (!function_exists('isAccountVerified')) {
    function isAccountVerified(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 't', 'yes', 'y'], true);
    }
}

if (!function_exists('formatCountdownSeconds')) {
    function formatCountdownSeconds(int $seconds): int
    {
        return max(0, $seconds);
    }
}

if (!function_exists('setPendingVerificationSession')) {
    function setPendingVerificationSession(array $user): void
    {
        $_SESSION['pending_verification'] = [
            'user_id' => (int) ($user['id'] ?? 0),
            'email' => (string) ($user['email'] ?? ''),
            'full_name' => (string) ($user['full_name'] ?? ''),
        ];
    }
}

if (!function_exists('clearPendingVerificationSession')) {
    function clearPendingVerificationSession(): void
    {
        unset($_SESSION['pending_verification']);
    }
}

if (!function_exists('getPendingVerificationUserId')) {
    function getPendingVerificationUserId(): int
    {
        return (int) ($_SESSION['pending_verification']['user_id'] ?? 0);
    }
}

if (!function_exists('fetchVerificationUserById')) {
    function fetchVerificationUserById(PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT
                id,
                username,
                email,
                full_name,
                phone,
                role,
                is_active,
                is_verified,
                account_status,
                verification_code,
                code_expiry,
                verification_failed_attempts,
                verification_locked_until,
                verification_resend_count,
                last_verification_sent_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }
}

if (!function_exists('getVerificationResendSecondsRemaining')) {
    function getVerificationResendSecondsRemaining(array $user): int
    {
        if (empty($user['last_verification_sent_at'])) {
            return 0;
        }

        $lastSent = strtotime((string) $user['last_verification_sent_at']);
        if ($lastSent === false) {
            return 0;
        }

        return max(0, 60 - (time() - $lastSent));
    }
}

if (!function_exists('getVerificationLockSecondsRemaining')) {
    function getVerificationLockSecondsRemaining(array $user): int
    {
        if (empty($user['verification_locked_until'])) {
            return 0;
        }

        $lockedUntil = strtotime((string) $user['verification_locked_until']);
        if ($lockedUntil === false) {
            return 0;
        }

        return max(0, $lockedUntil - time());
    }
}

if (!function_exists('sendAccountVerificationCode')) {
    function sendAccountVerificationCode(PDO $pdo, array $user, bool $isResend = false): array
    {
        $currentUser = fetchVerificationUserById($pdo, (int) ($user['id'] ?? 0));
        if (!$currentUser) {
            return ['success' => false, 'message' => 'Account not found.'];
        }

        if (isAccountVerified($currentUser['is_verified'])) {
            return ['success' => false, 'message' => 'This account is already verified.', 'already_verified' => true];
        }

        if (($currentUser['account_status'] ?? 'pending') === 'suspended') {
            return ['success' => false, 'message' => 'This account is suspended. Please contact support.'];
        }

        $lockSeconds = getVerificationLockSecondsRemaining($currentUser);
        if ($lockSeconds > 0) {
            return [
                'success' => false,
                'message' => 'Too many failed attempts. Try again in ' . ceil($lockSeconds / 60) . ' minutes.',
                'locked' => true,
                'lock_seconds' => $lockSeconds,
            ];
        }

        $resendSeconds = getVerificationResendSecondsRemaining($currentUser);
        if ($isResend && $resendSeconds > 0) {
            return [
                'success' => false,
                'message' => 'Please wait before requesting another code.',
                'retry_after' => $resendSeconds,
            ];
        }

        if ($isResend && (int) ($currentUser['verification_resend_count'] ?? 0) >= 3) {
            return [
                'success' => false,
                'message' => 'You have reached the resend limit. Please wait for the current code to expire or contact support.',
                'resend_limit_reached' => true,
            ];
        }

        $code = generateEmailVerificationCode();
        $hashedCode = password_hash($code, PASSWORD_DEFAULT);
        $expiry = getVerificationExpiryDateTime();
        $verifyUrl = buildAppUrl('verify_code.php');
        $mailHelper = new MailHelper();

        $pdo->beginTransaction();
        try {
            $updateStmt = $pdo->prepare("
                UPDATE users
                SET
                    verification_code = :verification_code,
                    code_expiry = :code_expiry,
                    verification_failed_attempts = 0,
                    verification_locked_until = NULL,
                    verification_resend_count = :verification_resend_count,
                    last_verification_sent_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $updateStmt->execute([
                'verification_code' => $hashedCode,
                'code_expiry' => $expiry->format('Y-m-d H:i:s'),
                'verification_resend_count' => $isResend ? ((int) ($currentUser['verification_resend_count'] ?? 0) + 1) : 0,
                'id' => $currentUser['id'],
            ]);

            $emailResult = $mailHelper->sendRegistrationVerificationCode(
                (string) $currentUser['email'],
                (string) $currentUser['full_name'],
                $code,
                $expiry,
                $verifyUrl
            );

            $pdo->commit();
            setPendingVerificationSession($currentUser);

            return [
                'success' => true,
                'message' => $emailResult['success']
                    ? 'We sent a verification code to your email address.'
                    : 'Your account was created, but the email could not be sent automatically. Use the resend option to try again.',
                'mail_success' => $emailResult['success'],
                'mail_message' => $emailResult['message'] ?? '',
                'expires_at' => $expiry->format('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Verification email error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'We could not generate a verification code right now. Please try again.',
            ];
        }
    }
}

if (!function_exists('attemptAccountVerification')) {
    function attemptAccountVerification(PDO $pdo, int $userId, string $code): array
    {
        $user = fetchVerificationUserById($pdo, $userId);
        if (!$user) {
            return ['success' => false, 'message' => 'We could not find your pending account. Please register again.'];
        }

        if (isAccountVerified($user['is_verified'])) {
            return ['success' => false, 'message' => 'This account is already verified.', 'already_verified' => true];
        }

        if (($user['account_status'] ?? 'pending') === 'suspended') {
            return ['success' => false, 'message' => 'This account is suspended. Please contact support.'];
        }

        $lockSeconds = getVerificationLockSecondsRemaining($user);
        if ($lockSeconds > 0) {
            return [
                'success' => false,
                'message' => 'Too many invalid attempts. Try again in ' . ceil($lockSeconds / 60) . ' minutes.',
                'locked' => true,
                'lock_seconds' => $lockSeconds,
            ];
        }

        if (trim($code) === '' || !preg_match('/^\d{6}$/', $code)) {
            return ['success' => false, 'message' => 'Enter the 6-digit verification code from your email.'];
        }

        if (empty($user['verification_code']) || empty($user['code_expiry'])) {
            return ['success' => false, 'message' => 'No active verification code was found. Please request a new code.', 'expired' => true];
        }

        $expiresAt = strtotime((string) $user['code_expiry']);
        if ($expiresAt === false || $expiresAt < time()) {
            return ['success' => false, 'message' => 'Your verification code has expired. Please request a new one.', 'expired' => true];
        }

        if (!password_verify($code, (string) $user['verification_code'])) {
            $failedAttempts = (int) ($user['verification_failed_attempts'] ?? 0) + 1;
            $lockUntil = $failedAttempts >= 5
                ? (new DateTimeImmutable('now'))->modify('+30 minutes')->format('Y-m-d H:i:s')
                : null;

            $updateStmt = $pdo->prepare("
                UPDATE users
                SET
                    verification_failed_attempts = :attempts,
                    verification_locked_until = :lock_until,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $updateStmt->execute([
                'attempts' => $failedAttempts,
                'lock_until' => $lockUntil,
                'id' => $user['id'],
            ]);

            $remainingAttempts = max(0, 5 - $failedAttempts);
            return [
                'success' => false,
                'message' => $remainingAttempts > 0
                    ? 'Invalid code. You have ' . $remainingAttempts . ' attempt' . ($remainingAttempts === 1 ? '' : 's') . ' remaining.'
                    : 'Too many invalid attempts. Your account is locked for 30 minutes.',
                'remaining_attempts' => $remainingAttempts,
                'locked' => $remainingAttempts === 0,
            ];
        }

        $pdo->beginTransaction();
        try {
            $verifyStmt = $pdo->prepare("
                UPDATE users
                SET
                    is_verified = TRUE,
                    account_status = 'active',
                    verification_code = NULL,
                    code_expiry = NULL,
                    verification_failed_attempts = 0,
                    verification_locked_until = NULL,
                    verification_resend_count = 0,
                    last_verification_sent_at = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $verifyStmt->execute(['id' => $user['id']]);
            $pdo->commit();

            return ['success' => true, 'message' => 'Your account has been verified successfully.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Account verification update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'We could not verify your account right now. Please try again later.'];
        }
    }
}

if (!function_exists('isAccountVerified')) {
    /**
     * Normalize various truthy/falsey representations for the `is_verified` field.
     *
     * @param mixed $value
     * @return bool
     */
    function isAccountVerified($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_null($value)) {
            return false;
        }

        // Handle numeric and string representations
        $val = (string) $value;
        $valLower = strtolower(trim($val));

        if ($valLower === '1' || $valLower === 'true' || $valLower === 't' || $valLower === 'yes' || $valLower === 'y') {
            return true;
        }

        return false;
    }
}
