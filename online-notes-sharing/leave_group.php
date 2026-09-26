<?php
/**
 * Online Notes Sharing Platform - Leave Study Group
 */
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: groups.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    flash('danger', 'Security validation error.');
    header('Location: groups.php');
    exit;
}

$group_id = (int)($_POST['group_id'] ?? 0);
if ($group_id <= 0) {
    flash('danger', 'Invalid study group specified.');
    header('Location: groups.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT name FROM groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();

    $stmt_del = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt_del->execute([$group_id, $user['id']]);

    $group_name = $group ? htmlspecialchars($group['name']) : 'the study group';
    flash('info', 'You have left ' . $group_name . '.');
    header('Location: groups.php');
    exit;

} catch (PDOException $e) {
    error_log("Leave group error: " . $e->getMessage());
    flash('danger', 'Unable to leave study group.');
    header('Location: group.php?id=' . $group_id);
    exit;
}
