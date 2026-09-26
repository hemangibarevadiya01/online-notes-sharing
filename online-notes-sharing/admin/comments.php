<?php
$admin_title = 'Comment Moderation';
require_once __DIR__ . '/includes/header.php';

// Deletion via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_comment') {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf($token)) {
        $cid = (int)($_POST['id'] ?? 0);
        if ($cid > 0) {
            try {
                $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$cid]);
                flash('success', 'Comment deleted successfully.');
            } catch (PDOException $e) {}
        }
    }
    header('Location: comments.php');
    exit;
}

$comments = [];
try {
    $stmt = $pdo->query("SELECT c.*, n.title AS note_title, u.name AS user_name, u.email AS user_email 
                         FROM comments c 
                         JOIN notes n ON c.note_id = n.id 
                         JOIN users u ON c.user_id = u.id 
                         ORDER BY c.created_at DESC");
    $comments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Admin comments error: " . $e->getMessage());
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <h5 class="fw-bold mb-3 pb-2 border-bottom"><i class="bi bi-chat-dots-fill text-purple me-2"></i>Community Comments & Discussions</h5>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Comment Content</th>
                    <th>Associated Note</th>
                    <th>Author</th>
                    <th>Date</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($comments)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">No comments found.</td></tr>
                <?php else: ?>
                    <?php foreach ($comments as $c): ?>
                        <tr>
                            <td class="small" style="max-width: 380px;"><?= nl2br(htmlspecialchars($c['comment'])) ?></td>
                            <td>
                                <a href="../note.php?id=<?= $c['note_id'] ?>" target="_blank" class="fw-semibold small text-primary text-decoration-none">
                                    <?= htmlspecialchars($c['note_title']) ?>
                                </a>
                            </td>
                            <td class="small">
                                <div class="fw-bold"><?= htmlspecialchars($c['user_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($c['user_email']) ?></small>
                            </td>
                            <td class="small text-muted"><?= date('M j, Y H:i', strtotime($c['created_at'])) ?></td>
                            <td class="text-end">
                                <form method="POST" action="comments.php" onsubmit="return confirm('Permanently remove this comment?');" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="delete_comment">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Comment">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
