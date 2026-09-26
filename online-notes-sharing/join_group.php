<?php
/**
 * Online Notes Sharing Platform - Join Study Group
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
    flash('danger', 'Security validation failed.');
    header('Location: groups.php');
    exit;
}

$group_id = (int)($_POST['group_id'] ?? 0);
if ($group_id <= 0) {
    flash('danger', 'Invalid study group.');
    header('Location: groups.php');
    exit;
}

try {
    // Check group exists
    $stmt = $pdo->prepare("SELECT id, name, created_by FROM groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();

    if (!$group) {
        flash('danger', 'Study group not found.');
        header('Location: groups.php');
        exit;
    }

    // Check membership
    $stmt_check = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt_check->execute([$group_id, $user['id']]);
    if ($stmt_check->fetch()) {
        flash('info', 'You are already an enrolled member of "' . htmlspecialchars($group['name']) . '".');
        header('Location: group.php?id=' . $group_id);
        exit;
    }

    // Insert membership
    $stmt_ins = $pdo->prepare("INSERT INTO group_members (group_id, user_id, joined_at) VALUES (?, ?, NOW())");
    $stmt_ins->execute([$group_id, $user['id']]);

    // Notify group creator
    if ((int)$group['created_by'] !== (int)$user['id']) {
        create_notification(
            (int)$group['created_by'],
            'New Study Group Member',
            htmlspecialchars($user['name']) . ' joined your study group "' . htmlspecialchars($group['name']) . '".',
            'group_join'
        );
    }

    flash('success', 'You have successfully joined "' . htmlspecialchars($group['name']) . '"!');
    header('Location: group.php?id=' . $group_id);
    exit;

} catch (PDOException $e) {
    error_log("Join group error: " . $e->getMessage());
    flash('danger', 'Unable to join group at this time.');
    header('Location: group.php?id=' . $group_id);
    exit;
}
