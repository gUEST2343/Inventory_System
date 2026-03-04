<?php
/**
 * Index Page - Entry point for the Inventory System
 * Redirects to login page or dashboard based on authentication status
 * 
 * @package InventorySystem
 */

// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Redirect to dashboard if already logged in
    header('Location: dashboard.php');
    exit;
}

// Redirect to login page
header('Location: login.php');
exit;
