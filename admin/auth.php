<?php
// admin/auth.php

session_start();

class AdminAuth
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function login($username, $password)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, password_hash, email, role
                FROM admin_users
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['admin_logged_in'] = true;

                $stmt = $this->pdo->prepare("
                    UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?
                ");
                $stmt->execute([$admin['id']]);

                return ['success' => true];
            }

            return ['success' => false, 'message' => 'Invalid credentials'];
        } catch (PDOException $e) {
            error_log("Admin login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed'];
        }
    }

    public function isLoggedIn()
    {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    public function logout()
    {
        session_destroy();
        return true;
    }

    public function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            if ($isAjax) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Admin session expired. Please log in again.',
                    'redirect' => '/login.php',
                ]);
                exit();
            }

            header('Location: /login.php');
            exit();
        }
    }
}

require_once __DIR__ . '/../config/database.php';
$adminAuth = new AdminAuth($pdo);
