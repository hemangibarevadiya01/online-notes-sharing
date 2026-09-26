<?php
/**
 * Admin Panel - Secure User Deletion via POST
 */
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('danger', 'Method not allowed. Delete operations require POST requests.');
    header('Location: users.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    flash('danger', 'Invalid security token.');
    header('Location: users.php');
    exit;
}

$user_id = (int)($_POST['id'] ?? 0);

if ($user_id <= 0) {
    flash('danger', 'Invalid user specified.');
    header('Location: users.php');
    exit;
}

if ($user_id === (int)$_SESSION['user_id']) {
    flash('danger', 'You cannot delete your own administrative account.');
    header('Location: users.php');
    exit;
}

try {
    // Delete files associated with user notes
    $stmt_notes = $pdo->prepare("SELECT file_path FROM notes WHERE user_id = ?");
    $stmt_notes->execute([$user_id]);
    $user_files = $stmt_notes->fetchAll();

    foreach ($user_files as $f) {
        $p = __DIR__ . '/../' . $f['file_path'];
        if (file_exists($p) && is_file($p)) @unlink($p);
    }

    $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt_del->execute([$user_id]);

    flash('success', 'User account and all associated documents deleted successfully.');
} catch (PDOException $e) {
    error_log("Delete user error: " . $e->getMessage());
    flash('danger', 'Failed to delete user account.');
}

header('Location: users.php');
exit;
