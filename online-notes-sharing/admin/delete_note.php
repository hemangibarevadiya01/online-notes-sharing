<?php
/**
 * Admin Panel - Secure Note Deletion via POST
 */
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('danger', 'Method not allowed. Delete requires POST.');
    header('Location: notes.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) {
    flash('danger', 'Security validation failed.');
    header('Location: notes.php');
    exit;
}

$note_id = (int)($_POST['id'] ?? 0);

if ($note_id <= 0) {
    flash('danger', 'Invalid note specified.');
    header('Location: notes.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT file_path, title FROM notes WHERE id = ?");
    $stmt->execute([$note_id]);
    $note = $stmt->fetch();

    if ($note) {
        $p = __DIR__ . '/../' . $note['file_path'];
        if (file_exists($p) && is_file($p)) {
            @unlink($p);
        }

        $stmt_del = $pdo->prepare("DELETE FROM notes WHERE id = ?");
        $stmt_del->execute([$note_id]);

        flash('success', 'Study note "' . htmlspecialchars($note['title']) . '" and file deleted.');
    }
} catch (PDOException $e) {
    error_log("Admin delete note error: " . $e->getMessage());
    flash('danger', 'Error deleting note.');
}

header('Location: notes.php');
exit;
