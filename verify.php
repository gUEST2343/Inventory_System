<?php
require_once __DIR__ . '/db_connect.php';

$result = ['success' => false, 'message' => 'Missing verification token.'];
$showLoginLink = false;
$token = trim($_GET['token'] ?? '');

if (!$pdo instanceof PDO) {
    $result = [
        'success' => false,
        'message' => $db_connection_error ?: 'Database connection is unavailable. Please try again later.',
    ];
} else {
    try {
        $verificationColumns = $pdo->query("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'users'
              AND column_name IN ('verification_token', 'email_verified')
        ")->fetchAll(PDO::FETCH_COLUMN);

        $hasVerificationToken = in_array('verification_token', $verificationColumns, true);
        $hasEmailVerified = in_array('email_verified', $verificationColumns, true);

        if (!$hasVerificationToken || !$hasEmailVerified) {
            $result = [
                'success' => true,
                'message' => 'Email verification is not enabled for this installation. You can sign in now.',
            ];
            $showLoginLink = true;
        } elseif ($token === '') {
            $result = ['success' => false, 'message' => 'Missing verification token.'];
        } else {
            $stmt = $pdo->prepare("
                UPDATE users
                SET email_verified = TRUE,
                    verification_token = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE verification_token = :token
                RETURNING id
            ");
            $stmt->execute(['token' => $token]);

            if ($stmt->fetchColumn()) {
                $result = [
                    'success' => true,
                    'message' => 'Email verified successfully. You can now log in.',
                ];
                $showLoginLink = true;
            } else {
                $result = ['success' => false, 'message' => 'Invalid or expired verification token.'];
            }
        }
    } catch (PDOException $e) {
        error_log('Email verification error: ' . $e->getMessage());
        $result = ['success' => false, 'message' => 'Unable to verify your email right now. Please try again later.'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="card mt-5 mx-auto" style="max-width: 520px;">
            <div class="card-body">
                <?php if ($result['success']): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8') ?></div>
                    <a href="<?= $showLoginLink ? 'user_login.php' : 'login.php' ?>" class="btn btn-primary w-100">Go to Login</a>
                <?php else: ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8') ?></div>
                    <a href="register.php" class="btn btn-secondary w-100">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
