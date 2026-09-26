<?php
/**
 * Online Notes Sharing Platform - Saved / Bookmarked Notes
 * Handles both AJAX bookmark toggling and student saved notes catalog display.
 */
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

// Handle AJAX or POST toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
               || isset($_POST['ajax']);

    $note_id = (int)($_POST['note_id'] ?? 0);

    if ($note_id <= 0) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid note ID.']);
            exit;
        }
        flash('danger', 'Invalid note specified.');
        header('Location: saved_notes.php');
        exit;
    }

    try {
        // Check if already saved
        $stmt_check = $pdo->prepare("SELECT id FROM saved_notes WHERE note_id = ? AND user_id = ?");
        $stmt_check->execute([$note_id, $user['id']]);
        $existing = $stmt_check->fetch();

        if ($existing) {
            // Remove from saved notes
            $stmt_del = $pdo->prepare("DELETE FROM saved_notes WHERE note_id = ? AND user_id = ?");
            $stmt_del->execute([$note_id, $user['id']]);
            $action = 'unsaved';
            $msg = 'Note removed from your saved bookmarks.';
        } else {
            // Add to saved notes
            $stmt_ins = $pdo->prepare("INSERT INTO saved_notes (note_id, user_id) VALUES (?, ?)");
            $stmt_ins->execute([$note_id, $user['id']]);
            $action = 'saved';
            $msg = 'Note saved to your bookmarks!';
        }

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'action' => $action, 'message' => $msg]);
            exit;
        }

        flash('success', $msg);
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'saved_notes.php'));
        exit;

    } catch (PDOException $e) {
        error_log("Saved notes error: " . $e->getMessage());
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Database error occurred.']);
            exit;
        }
        flash('danger', 'Error updating bookmark.');
        header('Location: saved_notes.php');
        exit;
    }
}

// GET Request: Display all saved notes for this student
$page_title = 'My Saved Notes';
$saved_notes = [];

try {
    $stmt = $pdo->prepare("SELECT 
                                n.id, n.title, n.subject, n.description, n.file_name, n.file_size,
                                n.downloads, n.views, n.created_at,
                                c.name AS category_name,
                                u.name AS author_name,
                                sn.created_at AS bookmarked_at,
                                COUNT(DISTINCT l.id) AS likes_count,
                                COALESCE(AVG(r.rating), 0) AS avg_rating
                           FROM saved_notes sn
                           JOIN notes n ON sn.note_id = n.id
                           JOIN categories c ON n.category_id = c.id
                           JOIN users u ON n.user_id = u.id
                           LEFT JOIN likes l ON n.id = l.note_id
                           LEFT JOIN ratings r ON n.id = r.note_id
                           WHERE sn.user_id = ?
                           GROUP BY n.id
                           ORDER BY sn.created_at DESC");
    $stmt->execute([$user['id']]);
    $saved_notes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch saved notes: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-bookmark-fill text-warning me-2"></i>My Saved Notes</h2>
            <p class="text-muted small mb-0">Quick access to study materials and lecture guides you have bookmarked</p>
        </div>
        <div>
            <a href="notes.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-search me-1"></i> Browse More Notes
            </a>
        </div>
    </div>

    <!-- Notes Grid -->
    <div class="row g-4">
        <?php if (empty($saved_notes)): ?>
            <div class="col-12 text-center py-5">
                <div class="p-5 bg-white rounded-4 border shadow-sm" style="max-width: 500px; margin: 0 auto;">
                    <i class="bi bi-bookmark text-muted" style="font-size: 3.5rem;"></i>
                    <h4 class="fw-bold mt-3">No Saved Notes Yet</h4>
                    <p class="text-muted small">You haven't bookmarked any notes yet. Click the bookmark icon on any note card to save it here for fast revision.</p>
                    <a href="notes.php" class="btn btn-primary btn-sm px-4">Browse Study Notes</a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($saved_notes as $n): ?>
                <?php 
                    $ext = strtolower(pathinfo($n['file_name'], PATHINFO_EXTENSION));
                    $badge_class = 'file-badge-' . ($ext ?: 'txt');
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="edu-card">
                        <div class="edu-card-header">
                            <span class="file-badge <?= $badge_class ?>">
                                <i class="bi bi-file-earmark-text"></i> <?= strtoupper($ext) ?>
                            </span>
                            <span class="category-pill">
                                <i class="bi bi-tag-fill"></i> <?= htmlspecialchars($n['category_name']) ?>
                            </span>
                        </div>
                        <div class="edu-card-body">
                            <div class="text-muted small fw-semibold mb-1">
                                <i class="bi bi-book text-primary me-1"></i> <?= htmlspecialchars($n['subject']) ?>
                            </div>
                            <h5 class="fw-bold mb-2">
                                <a href="note.php?id=<?= $n['id'] ?>" class="text-dark text-decoration-none">
                                    <?= htmlspecialchars($n['title']) ?>
                                </a>
                            </h5>
                            <p class="text-muted small mb-3 text-truncate-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 42px;">
                                <?= htmlspecialchars($n['description'] ?: 'No description provided.') ?>
                            </p>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top small text-muted">
                                <span>Author: <strong><?= htmlspecialchars($n['author_name']) ?></strong></span>
                                <span>Saved <?= time_ago($n['bookmarked_at']) ?></span>
                            </div>
                        </div>
                        <div class="edu-card-footer">
                            <div class="d-flex align-items-center gap-2 small text-muted">
                                <span><i class="bi bi-download text-primary me-1"></i><?= $n['downloads'] ?></span>
                                <span><i class="bi bi-star-fill text-warning me-1"></i><?= number_format($n['avg_rating'], 1) ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <!-- Remove Bookmark Button -->
                                <form method="POST" action="saved_notes.php" class="d-inline">
                                    <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger p-1 px-2" title="Remove Bookmark">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <a href="note.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-primary p-1 px-2">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <a href="download.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-primary p-1 px-2">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
