<?php
/**
 * Online Notes Sharing Platform - Comprehensive Share Note Handler
 * Allows sharing via:
 * 1. Direct Email (with secure note link and message logged in note_shares)
 * 2. Study Group (broadcasts in-app notification to all group members)
 * 3. Shareable Link (public URL to note.php / download.php without exposing filesystem path)
 */
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

$note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : (isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0);

if ($note_id <= 0) {
    flash('danger', 'Invalid study note specified.');
    header('Location: notes.php');
    exit;
}

try {
    $stmt_note = $pdo->prepare("SELECT id, title, subject, user_id FROM notes WHERE id = ?");
    $stmt_note->execute([$note_id]);
    $note = $stmt_note->fetch();

    if (!$note) {
        flash('danger', 'Requested note not found.');
        header('Location: notes.php');
        exit;
    }

    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || isset($_POST['ajax']);

    // Construct secure platform URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $secure_note_url = "{$protocol}://{$host}{$base_dir}/note.php?id={$note_id}";
    $secure_download_url = "{$protocol}://{$host}{$base_dir}/download.php?id={$note_id}";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf($token)) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Invalid security token.']);
                exit;
            }
            flash('danger', 'Security validation failed.');
            header('Location: note.php?id=' . $note_id);
            exit;
        }

        $share_type = $_POST['share_type'] ?? 'email';
        $receiver_email = trim($_POST['receiver_email'] ?? '');
        $group_id = (int)($_POST['group_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        // 1. Email Sharing
        if ($share_type === 'email' || !empty($receiver_email)) {
            if (empty($receiver_email) || !filter_var($receiver_email, FILTER_VALIDATE_EMAIL)) {
                flash('warning', 'Please provide a valid recipient email address.');
                header('Location: note.php?id=' . $note_id);
                exit;
            }

            // Store in note_shares table
            $stmt_share = $pdo->prepare("INSERT INTO note_shares (note_id, sender_id, receiver_email, message, shared_at) 
                                         VALUES (?, ?, ?, ?, NOW())");
            $stmt_share->execute([$note_id, $user['id'], $receiver_email, $message]);

            // Notify recipient if user account exists
            $stmt_rec = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt_rec->execute([$receiver_email]);
            $recipient_user = $stmt_rec->fetch();

            if ($recipient_user) {
                $notif_msg = htmlspecialchars($user['name']) . ' shared the note "' . htmlspecialchars($note['title']) . '" with you. ' . ($message ? 'Message: "' . htmlspecialchars($message) . '"' : '');
                create_notification(
                    (int)$recipient_user['id'],
                    'Note Shared With You',
                    $notif_msg,
                    'note_share',
                    $note_id
                );
            }

            flash('success', 'Study note "' . htmlspecialchars($note['title']) . '" was shared with ' . htmlspecialchars($receiver_email) . '.');
            header('Location: note.php?id=' . $note_id);
            exit;
        }

        // 2. Study Group Sharing
        if ($share_type === 'group' || $group_id > 0) {
            $stmt_grp = $pdo->prepare("SELECT name FROM groups WHERE id = ?");
            $stmt_grp->execute([$group_id]);
            $group = $stmt_grp->fetch();

            if ($group) {
                // Fetch all group members except current sender
                $stmt_m = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ? AND user_id != ?");
                $stmt_m->execute([$group_id, $user['id']]);
                $members = $stmt_m->fetchAll();

                foreach ($members as $m) {
                    $notif_text = htmlspecialchars($user['name']) . ' shared a note in study group "' . htmlspecialchars($group['name']) . '": "' . htmlspecialchars($note['title']) . '".';
                    create_notification(
                        (int)$m['user_id'],
                        'New Group Note Shared',
                        $notif_text,
                        'group_share',
                        $note_id
                    );
                }

                flash('success', 'Study note shared with members of "' . htmlspecialchars($group['name']) . '".');
                header('Location: group.php?id=' . $group_id);
                exit;
            } else {
                flash('danger', 'Selected study group was not found.');
                header('Location: note.php?id=' . $note_id);
                exit;
            }
        }
    }

} catch (PDOException $e) {
    error_log("Share note error: " . $e->getMessage());
    flash('danger', 'Database error while sharing note.');
    header('Location: note.php?id=' . $note_id);
    exit;
}

header('Location: note.php?id=' . $note_id);
exit;
