<?php
/**
 * Admin Panel - Secure Category Deletion via POST
 */
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('danger', 'Invalid request method. Delete requires POST.');
    header('Location: categories.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    flash('danger', 'Security validation failed.');
    header('Location: categories.php');
    exit;
}

$cat_id = (int)($_POST['id'] ?? 0);

if ($cat_id <= 0) {
    flash('danger', 'Invalid category specified.');
    header('Location: categories.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$cat_id]);
    flash('success', 'Academic category removed successfully.');
} catch (PDOException $e) {
    error_log("Delete category error: " . $e->getMessage());
    flash('danger', 'Unable to delete category.');
}

header('Location: categories.php');
exit;
