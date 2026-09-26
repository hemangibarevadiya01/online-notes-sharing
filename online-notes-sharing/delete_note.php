<?php
/**
 * Online Notes Sharing Platform - Delete Note
 */
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

$note_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if ($note_id <= 0) {
    flash('danger', 'Invalid note specified.');
    header('Location: dashboard.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, user_id, title, file_path FROM notes WHERE id = ?");
    $stmt->execute([$note_id]);
    $note = $stmt->fetch();

    if (!$note) {
        flash('danger', 'Note not found.');
        header('Location: dashboard.php');
        exit;
    }

    // Permission check: author or admin
    if ((int)$note['user_id'] !== (int)$user['id'] && !is_admin()) {
        flash('danger', 'You do not have permission to delete this note.');
        header('Location: note.php?id=' . $note_id);
        exit;
    }

    // Remove file from disk
    $file_full_path = __DIR__ . '/' . $note['file_path'];
    if (file_exists($file_full_path) && is_file($file_full_path)) {
        @unlink($file_full_path);
    }

    // Delete DB record
    $stmt_del = $pdo->prepare("DELETE FROM notes WHERE id = ?");
    $stmt_del->execute([$note_id]);

    flash('success', 'Study note "' . htmlspecialchars($note['title']) . '" was successfully deleted.');
    
    // Redirect appropriately
    if (is_admin() && str_contains($_SERVER['HTTP_REFERER'] ?? '', 'admin')) {
        header('Location: admin/notes.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;

} catch (PDOException $e) {
    error_log("Delete note error: " . $e->getMessage());
    flash('danger', 'Database error while deleting note.');
    header('Location: dashboard.php');
    exit;
}
