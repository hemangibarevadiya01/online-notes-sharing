<?php
/**
 * Online Notes Sharing Platform - Secure Document Download
 * Verifies authentication, prevents directory traversal, records download history, and delivers file stream.
 */
require_once __DIR__ . '/includes/auth.php';

// Must be logged in to download notes
require_login();
$user = current_user();

$note_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($note_id <= 0) {
    flash('danger', 'Invalid note specified.');
    header('Location: notes.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, title, file_name, file_path, file_type, file_size, status FROM notes WHERE id = ?");
    $stmt->execute([$note_id]);
    $note = $stmt->fetch();

    if (!$note) {
        flash('danger', 'Requested note does not exist.');
        header('Location: notes.php');
        exit;
    }

    if ($note['status'] !== 'approved' && !is_admin()) {
        flash('warning', 'This note is currently pending review or has been archived.');
        header('Location: notes.php');
        exit;
    }

    // Secure path resolution & directory traversal defense
    $base_upload_dir = realpath(__DIR__ . '/uploads/notes');
    $target_file = realpath(__DIR__ . '/' . $note['file_path']);

    if (!$target_file || !file_exists($target_file) || !str_starts_with($target_file, $base_upload_dir)) {
        flash('danger', 'File not found on the server or storage path has expired.');
        header('Location: note.php?id=' . $note_id);
        exit;
    }

    // 1. Increment note download count
    $stmt_inc = $pdo->prepare("UPDATE notes SET downloads = downloads + 1 WHERE id = ?");
    $stmt_inc->execute([$note_id]);

    // 2. Insert download log record
    $stmt_dl = $pdo->prepare("INSERT INTO downloads (note_id, user_id, downloaded_at) VALUES (?, ?, NOW())");
    $stmt_dl->execute([$note_id, $user['id']]);

    // 3. Record study mission activity for download
    record_study_activity((int)$user['id'], 'download', $note_id);

    // Clean output buffer before file transmission
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Send HTTP download headers
    $download_name = $note['file_name'] ?: basename($target_file);
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $download_name) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($target_file));

    readfile($target_file);
    exit;

} catch (PDOException $e) {
    error_log("Download error: " . $e->getMessage());
    flash('danger', 'A database error occurred during download.');
    header('Location: note.php?id=' . $note_id);
    exit;
}
