<?php
/**
 * Logout Script
 * Destroys session and redirects to login page
 * 
 * @package InventorySystem
 */

// Start session
session_start();

// Destroy all session data
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
