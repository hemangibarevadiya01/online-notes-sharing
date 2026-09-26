<?php
/**
 * Logout handler
 * Securely destroys session and redirects to login
 */
require_once __DIR__ . '/includes/auth.php';

// Clear session array
$_SESSION = [];

// Delete session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start new temporary session to display logout feedback
session_start();
flash('info', 'You have been successfully signed out.');
header('Location: login.php');
exit;
