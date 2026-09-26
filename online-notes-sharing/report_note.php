<?php
/**
 * Online Notes Sharing Platform - Report Note Handler
 * Prevents duplicate reports by the same user for the same document while pending.
 */
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: notes.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    flash('danger', 'Security validation failed.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'notes.php'));
    exit;
}

$note_id = (int)($_POST['note_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$description = trim($_POST['description'] ?? '');

$allowed_reasons = [
    'Incorrect information',
    'Duplicate note',
    'Inappropriate content',
    'Copyright issue',
    'Other'
];

if ($note_id <= 0 || !in_array($reason, $allowed_reasons, true) || empty($description)) {
    flash('danger', 'Please choose a valid report category and provide a detailed explanation.');
    header('Location: note.php?id=' . $note_id);
    exit;
}

try {
    // 1. Verify note exists
    $stmt_note = $pdo->prepare("SELECT id, title FROM notes WHERE id = ?");
    $stmt_note->execute([$note_id]);
    $note = $stmt_note->fetch();

    if (!$note) {
        flash('danger', 'Study note does not exist.');
        header('Location: notes.php');
        exit;
    }

    // 2. Prevent duplicate pending reports from the same user
    $stmt_check = $pdo->prepare("SELECT id FROM reports WHERE note_id = ? AND user_id = ? AND status = 'pending'");
    $stmt_check->execute([$note_id, $user['id']]);
    if ($stmt_check->fetch()) {
        flash('warning', 'You already have a pending report under review for "' . htmlspecialchars($note['title']) . '". Our moderators will inspect it shortly.');
        header('Location: note.php?id=' . $note_id);
        exit;
    }

    // 3. Insert report
    $stmt_ins = $pdo->prepare("INSERT INTO reports (note_id, user_id, reason, description, status, created_at) 
                              VALUES (?, ?, ?, ?, 'pending', NOW())");
    $stmt_ins->execute([$note_id, $user['id'], $reason, $description]);

    flash('success', 'Thank you. Your report regarding "' . htmlspecialchars($note['title']) . '" has been submitted for administrative moderation.');
    header('Location: note.php?id=' . $note_id);
    exit;

} catch (PDOException $e) {
    error_log("Report note error: " . $e->getMessage());
    flash('danger', 'A database error occurred while filing your report.');
    header('Location: note.php?id=' . $note_id);
    exit;
}
