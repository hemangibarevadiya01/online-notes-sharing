<?php
/**
 * Online Notes Sharing Platform - Submit / Update Rating & Review
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
$rating = (int)($_POST['rating'] ?? 0);
$review = trim($_POST['review'] ?? '');

if ($note_id <= 0 || $rating < 1 || $rating > 5) {
    flash('danger', 'Please choose a valid rating between 1 and 5 stars.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'notes.php'));
    exit;
}

try {
    // Check note exists
    $stmt_note = $pdo->prepare("SELECT id, user_id, title FROM notes WHERE id = ?");
    $stmt_note->execute([$note_id]);
    $note = $stmt_note->fetch();

    if (!$note) {
        flash('danger', 'The note you are rating no longer exists.');
        header('Location: notes.php');
        exit;
    }

    // Insert or update rating
    $stmt = $pdo->prepare("INSERT INTO ratings (note_id, user_id, rating, review, created_at) 
                           VALUES (?, ?, ?, ?, NOW()) 
                           ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review)");
    $stmt->execute([$note_id, $user['id'], $rating, $review]);

    // Send notification to author if author is not current user
    if ((int)$note['user_id'] !== (int)$user['id']) {
        create_notification(
            (int)$note['user_id'],
            'New Rating Received',
            htmlspecialchars($user['name']) . ' rated your note "' . htmlspecialchars($note['title']) . '" ' . $rating . ' stars.',
            'rating',
            $note_id
        );
    }

    flash('success', 'Your rating of ' . $rating . ' star(s) has been recorded. Thank you for your feedback!');
    header('Location: note.php?id=' . $note_id);
    exit;

} catch (PDOException $e) {
    error_log("Rating error: " . $e->getMessage());
    flash('danger', 'Database error while saving rating.');
    header('Location: note.php?id=' . $note_id);
    exit;
}
