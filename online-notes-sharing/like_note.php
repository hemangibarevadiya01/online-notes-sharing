<?php
/**
 * Online Notes Sharing Platform - Like / Unlike Note Endpoint
 * Supports both AJAX requests and standard POST form submissions.
 */
require_once __DIR__ . '/includes/auth.php';

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
           || isset($_POST['ajax']) 
           || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

if (!is_logged_in()) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'unauthorized', 'message' => 'Please sign in to like notes.']);
        exit;
    }
    flash('warning', 'Please sign in to like study notes.');
    header('Location: login.php');
    exit;
}

$user = current_user();
$note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : (isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0);

if ($note_id <= 0) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid note specified.']);
        exit;
    }
    flash('danger', 'Invalid note specified.');
    header('Location: notes.php');
    exit;
}

try {
    // Check if note exists
    $stmt = $pdo->prepare("SELECT id, user_id, title FROM notes WHERE id = ?");
    $stmt->execute([$note_id]);
    $note = $stmt->fetch();

    if (!$note) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Note not found.']);
            exit;
        }
        flash('danger', 'Note not found.');
        header('Location: notes.php');
        exit;
    }

    // Check existing like
    $stmt_check = $pdo->prepare("SELECT id FROM likes WHERE note_id = ? AND user_id = ?");
    $stmt_check->execute([$note_id, $user['id']]);
    $existing = $stmt_check->fetch();

    if ($existing) {
        // Unlike: Remove like
        $stmt_del = $pdo->prepare("DELETE FROM likes WHERE note_id = ? AND user_id = ?");
        $stmt_del->execute([$note_id, $user['id']]);
        $action = 'unliked';
        $message = 'Note removed from your likes.';
    } else {
        // Like: Insert like (preventing duplicates)
        $stmt_ins = $pdo->prepare("INSERT INTO likes (note_id, user_id) VALUES (?, ?)");
        $stmt_ins->execute([$note_id, $user['id']]);
        $action = 'liked';
        $message = 'Note added to your likes!';

        // Notify author if not liking own note
        if ((int)$note['user_id'] !== (int)$user['id']) {
            create_notification(
                (int)$note['user_id'],
                'New Like on Your Note',
                htmlspecialchars($user['name']) . ' liked your note "' . htmlspecialchars($note['title']) . '".',
                'like',
                $note_id
            );
        }
    }

    // Get updated total likes count
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE note_id = ?");
    $stmt_count->execute([$note_id]);
    $total_likes = (int)$stmt_count->fetchColumn();

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'action' => $action,
            'message' => $message,
            'total_likes' => $total_likes
        ]);
        exit;
    }

    flash('success', $message);
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'note.php?id=' . $note_id));
    exit;

} catch (PDOException $e) {
    error_log("Like toggle error: " . $e->getMessage());
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
        exit;
    }
    flash('danger', 'Unable to update like status.');
    header('Location: note.php?id=' . $note_id);
    exit;
}
