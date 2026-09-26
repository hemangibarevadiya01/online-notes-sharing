<?php
/**
 * Admin Panel Authentication Guard
 * Ensures session is started, user is logged in, and user possesses 'admin' role.
 */

require_once __DIR__ . '/../../includes/auth.php';

if (!is_logged_in()) {
    flash('danger', 'Administrator login required to access this panel.');
    header('Location: admin_login.php');
    exit;
}

if (!is_admin()) {
    flash('danger', 'Access denied. You do not have administrator permissions.');
    header('Location: ../dashboard.php');
    exit;
}
