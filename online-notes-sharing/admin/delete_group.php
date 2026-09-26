<?php
/**
 * Admin Panel - Secure Study Group Deletion via POST
 */
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('danger', 'Method not allowed. Delete requires POST.');
    header('Location: groups.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    flash('danger', 'Security validation failed.');
    header('Location: groups.php');
    exit;
}

$group_id = (int)($_POST['id'] ?? 0);

if ($group_id <= 0) {
    flash('danger', 'Invalid group specified.');
    header('Location: groups.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
    $stmt->execute([$group_id]);
    flash('success', 'Study group deleted successfully.');
} catch (PDOException $e) {
    error_log("Delete group error: " . $e->getMessage());
    flash('danger', 'Failed to delete study group.');
}

header('Location: groups.php');
exit;
