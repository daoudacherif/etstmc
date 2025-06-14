<?php
require_once 'config.php';

function checkSession() {
    if (!isset($_SESSION['imsaid'])) {
        header('location:login.php');
        exit();
    }

    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        header('location:login.php?msg=timeout');
        exit();
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure session parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');

// Check session on every page load
checkSession();
?> 