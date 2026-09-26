<?php
/**
 * Online Notes Sharing Platform - Comments Management
 * Handles adding, editing, and deleting comments on study notes.
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
    flash('danger', 'Security token error. Please try again.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'notes.php'));
    exit;
}

$action = $_POST['action'] ?? 'add';
$note_id = (int)($_POST['note_id'] ?? 0);

if ($note_id <= 0) {
    flash('danger', 'Invalid note specified.');
    header('Location: notes.php');
    exit;
}

try {
    if ($action === 'add') {
        $comment_text = trim($_POST['comment'] ?? '');

        if (empty($comment_text)) {
            flash('warning', 'Comment cannot be empty.');
            header('Location: note.php?id=' . $note_id);
            exit;
        }

        // Fetch note to notify author
        $stmt_note = $pdo->prepare("SELECT user_id, title FROM notes WHERE id = ?");
        $stmt_note->execute([$note_id]);
        $note = $stmt_note->fetch();

        if (!$note) {
            flash('danger', 'Note not found.');
            header('Location: notes.php');
            exit;
        }

        // Insert comment
        $stmt = $pdo->prepare("INSERT INTO comments (note_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$note_id, $user['id'], $comment_text]);
        $comment_id = $pdo->lastInsertId();

        // Notify author if not commenting on own note
        if ((int)$note['user_id'] !== (int)$user['id']) {
            create_notification(
                (int)$note['user_id'],
                'New Discussion Comment',
                htmlspecialchars($user['name']) . ' commented on your study note "' . htmlspecialchars($note['title']) . '".',
                'comment',
                $note_id
            );
        }

        flash('success', 'Your comment has been posted.');
        header('Location: note.php?id=' . $note_id . '#comment-' . $comment_id);
        exit;

    } elseif ($action === 'delete') {
        $comment_id = (int)($_POST['comment_id'] ?? 0);

        if ($comment_id <= 0) {
            flash('danger', 'Invalid comment specified.');
            header('Location: note.php?id=' . $note_id);
            exit;
        }

        // Verify author or admin privilege
        $stmt_c = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt_c->execute([$comment_id]);
        $comment = $stmt_c->fetch();

        if (!$comment) {
            flash('danger', 'Comment not found.');
            header('Location: note.php?id=' . $note_id);
            exit;
        }

        if ((int)$comment['user_id'] !== (int)$user['id'] && !is_admin()) {
            flash('danger', 'You do not have permission to remove this comment.');
            header('Location: note.php?id=' . $note_id);
            exit;
        }

        $stmt_del = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        $stmt_del->execute([$comment_id]);

        flash('info', 'Comment removed.');
        header('Location: note.php?id=' . $note_id);
        exit;
    }

} catch (PDOException $e) {
    error_log("Comment error: " . $e->getMessage());
    flash('danger', 'A database error occurred.');
    header('Location: note.php?id=' . $note_id);
    exit;
}
