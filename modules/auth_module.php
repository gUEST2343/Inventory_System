<?php
// modules/auth_module.php

class AuthModule
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function register($username, $email, $password, $phone = '', $role = 'customer')
    {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $verificationToken = bin2hex(random_bytes(32));

            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, phone, verification_token, role)
                VALUES (?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            $stmt->execute([$username, $email, $passwordHash, $phone, $verificationToken, $role]);
            $userId = $stmt->fetchColumn();

            return [
                'success' => true,
                'message' => 'Registration successful',
                'user_id' => $userId,
                'verification_token' => $verificationToken,
            ];
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed'];
        }
    }

    public function login($username, $password)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, password_hash, email_verified
                FROM users
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                if (!$user['email_verified']) {
                    return ['success' => false, 'message' => 'Please verify your email first'];
                }

                $stmt = $this->pdo->prepare("UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$user['id']]);

                unset($user['password_hash']);
                return ['success' => true, 'user' => $user];
            }

            return ['success' => false, 'message' => 'Invalid credentials'];
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed'];
        }
    }

    public function verifyEmail($token)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users
                SET email_verified = TRUE, verification_token = NULL
                WHERE verification_token = ?
                RETURNING id, email, username
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                return ['success' => true, 'user' => $user];
            }
            return ['success' => false, 'message' => 'Invalid or expired token'];
        } catch (PDOException $e) {
            error_log("Verification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed'];
        }
    }
}
