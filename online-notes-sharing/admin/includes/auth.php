<?php
/**
 * Admin Panel Authentication Guard
 * Ensures session is active, user is logged in as an administrator,
 * and verifies role directly against MySQL database.
 */

require_once __DIR__ . '/../../includes/auth.php';

$admin_id = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);

if ($admin_id <= 0 || !is_admin()) {
    flash('danger', 'Administrator login required to access this panel.');
    header('Location: login.php');
    exit;
}

// Strict Server-Side Database Role & Status Verification
try {
    $stmt_check = $pdo->prepare("SELECT id, name, email, role, status FROM users WHERE id = ? LIMIT 1");
    $stmt_check->execute([$admin_id]);
    $admin_user = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$admin_user || $admin_user['role'] !== 'admin' || $admin_user['status'] !== 'active') {
        // Clear invalid admin session
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_email'], $_SESSION['admin_role']);
        flash('danger', 'Access denied. You do not possess administrator permissions.');
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Admin auth guard error: " . $e->getMessage());
    header('Location: login.php');
    exit;
}

