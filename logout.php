<?php
/**
 * Logout Script
 * Destroys session and redirects to the homepage
 * 
 * @package InventorySystem
 */

session_start();

$wasLoggedIn = !empty($_SESSION['logged_in']) || !empty($_SESSION['user_id']);

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

session_start();
session_regenerate_id(true);
$_SESSION['flash_success'] = $wasLoggedIn
    ? 'You have been logged out successfully.'
    : 'You are already signed out.';

header('Location: index.php');
exit;
