<?php
/**
 * Admin Panel — Inspect & Moderate Note
 */
$admin_title = 'Inspect Note';
require_once __DIR__ . '/includes/header.php';

$note_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($note_id <= 0) {
    flash('danger', 'Invalid note specified.');
    header('Location: notes.php');
    exit;
}

// Handle status change POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf($token)) {
        $new_status = $_POST['status'] ?? 'pending';
        if (in_array($new_status, ['approved', 'pending', 'rejected'])) {
            $stmt_upd = $pdo->prepare("UPDATE notes SET status = ? WHERE id = ?");
            $stmt_upd->execute([$new_status, $note_id]);
            flash('success', "Note status updated to '" . ucfirst($new_status) . "'.");
            header("Location: view_note.php?id={$note_id}");
            exit;
        }
    } else {
        flash('danger', 'Invalid security token.');
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT n.*, c.name as category_name, u.name as author_name, u.email as author_email, u.status as author_status,
               (SELECT COUNT(*) FROM likes WHERE note_id = n.id) as likes_count,
               (SELECT COUNT(*) FROM saved_notes WHERE note_id = n.id) as saves_count,
               (SELECT AVG(rating) FROM ratings WHERE note_id = n.id) as avg_rating,
               (SELECT COUNT(*) FROM ratings WHERE note_id = n.id) as total_ratings,
               (SELECT COUNT(*) FROM comments WHERE note_id = n.id) as total_comments
        FROM notes n
        LEFT JOIN categories c ON n.category_id = c.id
        LEFT JOIN users u ON n.user_id = u.id
        WHERE n.id = ?
    ");
    $stmt->execute([$note_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        flash('danger', 'Study note not found.');
        header('Location: notes.php');
        exit;
    }

    // Reports on this note
    $stmt_rep = $pdo->prepare("
        SELECT r.*, u.name as reporter_name, u.email as reporter_email
        FROM reports r
        JOIN users u ON r.user_id = u.id
        WHERE r.note_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt_rep->execute([$note_id]);
    $reports = $stmt_rep->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Admin view note error: " . $e->getMessage());
    flash('danger', 'Database error loading note details.');
    header('Location: notes.php');
    exit;
}
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Admin</a></li>
                <li class="breadcrumb-item"><a href="notes.php" class="text-decoration-none">Notes Management</a></li>
                <li class="breadcrumb-item active" aria-current="page">Inspect Note #<?= $note['id'] ?></li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0">Note Moderation Inspection</h3>
    </div>
    <div class="d-flex gap-2">
        <a href="edit_note.php?id=<?= $note['id'] ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil-square me-1"></i> Edit Metadata
        </a>
        <a href="notes.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Notes
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Details & Preview -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <span class="badge bg-primary-subtle text-primary mb-2">
                        <?= htmlspecialchars($note['category_name'] ?? 'General') ?>
                    </span>
                    <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($note['title']) ?></h4>
                    <p class="text-muted small mb-0"><i class="bi bi-book me-1"></i>Subject: <strong><?= htmlspecialchars($note['subject']) ?></strong></p>
                </div>
                <div class="text-end">
                    <?php if ($note['status'] === 'approved'): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
                            <i class="bi bi-check-circle-fill me-1"></i> Approved
                        </span>
                    <?php elseif ($note['status'] === 'pending'): ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 fs-6">
                            <i class="bi bi-clock-fill me-1"></i> Pending Review
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fs-6">
                            <i class="bi bi-x-circle-fill me-1"></i> Rejected
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <div class="p-3 bg-light rounded-3 mb-3">
                <h6 class="fw-semibold text-dark small mb-1">Description</h6>
                <p class="text-muted small mb-0">
                    <?= nl2br(htmlspecialchars($note['description'] ?: 'No description provided by author.')) ?>
                </p>
            </div>

            <!-- Keywords -->
            <?php if (!empty($note['keywords'])): ?>
                <div class="mb-3">
                    <span class="small fw-semibold text-dark me-2">Keywords / Tags:</span>
                    <?php foreach (explode(',', $note['keywords']) as $kw): ?>
                        <span class="badge bg-light text-dark border me-1 small"><?= htmlspecialchars(trim($kw)) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- File Preview Area -->
            <?php if (strtolower($note['file_type']) === 'pdf'): ?>
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>In-Browser PDF Document Stream</h6>
                        <a href="../download.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="bi bi-download me-1"></i> Download File
                        </a>
                    </div>
                    <div class="ratio ratio-16x9 rounded-3 overflow-hidden border">
                        <iframe src="../preview.php?id=<?= $note['id'] ?>&mode=stream" allowfullscreen></iframe>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info py-3 d-flex align-items-center justify-content-between mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-arrow-down fs-4 text-primary"></i>
                        <div>
                            <div class="fw-semibold small">Uploaded Document (<?= strtoupper($note['file_type']) ?>)</div>
                            <small class="text-muted">File size: <?= format_bytes($note['file_size']) ?></small>
                        </div>
                    </div>
                    <a href="../download.php?id=<?= $note['id'] ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-download me-1"></i> Download for Review
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Community Reports on this Note -->
        <?php if (!empty($reports)): ?>
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white border-start border-danger border-4">
                <h5 class="fw-bold text-danger mb-3"><i class="bi bi-flag-fill me-2"></i>Community Reports (<?= count($reports) ?>)</h5>
                <div class="list-group list-group-flush">
                    <?php foreach ($reports as $r): ?>
                        <div class="list-group-item px-0 py-3">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="badge bg-danger-subtle text-danger text-uppercase" style="font-size: 0.7rem;">
                                    <?= htmlspecialchars($r['reason']) ?>
                                </span>
                                <small class="text-muted"><?= date('M d, Y H:i', strtotime($r['created_at'])) ?></small>
                            </div>
                            <p class="small text-dark mb-1"><strong>Report Details:</strong> <?= htmlspecialchars($r['description'] ?: 'No details provided.') ?></p>
                            <small class="text-muted">Reported by: <?= htmlspecialchars($r['reporter_name']) ?> (<?= htmlspecialchars($r['reporter_email']) ?>)</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Status Moderation & Metrics -->
    <div class="col-lg-4">
        <!-- Moderation Actions -->
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <h5 class="fw-bold mb-3 text-dark">Moderation Actions</h5>
            <form method="POST" action="view_note.php?id=<?= $note['id'] ?>" class="mb-4">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="update_status">
                
                <label for="statusSelect" class="form-label small fw-semibold">Change Document Status</label>
                <select class="form-select mb-3" id="statusSelect" name="status">
                    <option value="approved" <?= $note['status'] === 'approved' ? 'selected' : '' ?>>Approved (Publicly visible)</option>
                    <option value="pending" <?= $note['status'] === 'pending' ? 'selected' : '' ?>>Pending Review (Awaiting verification)</option>
                    <option value="rejected" <?= $note['status'] === 'rejected' ? 'selected' : '' ?>>Rejected (Hidden from search)</option>
                </select>
                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-arrow-repeat me-1"></i> Update Status
                </button>
            </form>

            <div class="pt-3 border-top">
                <form action="delete_note.php" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this note and its physical file from disk?');">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger w-100 py-2">
                        <i class="bi bi-trash-fill me-1"></i> Delete Note Permanently
                    </button>
                </form>
            </div>
        </div>

        <!-- Document Performance Metrics -->
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <h5 class="fw-bold mb-3 text-dark">Performance Metrics</h5>
            <div class="d-flex flex-column gap-3 small">
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted"><i class="bi bi-download me-2 text-primary"></i>Total Downloads</span>
                    <span class="fw-bold text-dark"><?= number_format($note['downloads']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted"><i class="bi bi-eye me-2 text-info"></i>Total Views</span>
                    <span class="fw-bold text-dark"><?= number_format($note['views']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted"><i class="bi bi-heart-fill me-2 text-danger"></i>Likes Count</span>
                    <span class="fw-bold text-dark"><?= number_format($note['likes_count']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted"><i class="bi bi-bookmark-fill me-2 text-warning"></i>Saved Bookmarks</span>
                    <span class="fw-bold text-dark"><?= number_format($note['saves_count']) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted"><i class="bi bi-star-fill me-2 text-warning"></i>Average Rating</span>
                    <span class="fw-bold text-dark"><?= round((float)$note['avg_rating'], 1) ?> / 5 (<?= $note['total_ratings'] ?> reviews)</span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted"><i class="bi bi-chat-text-fill me-2 text-purple"></i>Discussions</span>
                    <span class="fw-bold text-dark"><?= number_format($note['total_comments']) ?> comments</span>
                </div>
            </div>
        </div>

        <!-- Author Information -->
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h5 class="fw-bold mb-3 text-dark">Author Information</h5>
            <div class="d-flex align-items-center gap-3 mb-3">
                <img src="../uploads/avatars/default_avatar.svg" alt="Avatar" class="avatar-img" style="width: 44px; height: 44px;">
                <div>
                    <div class="fw-bold text-dark"><?= htmlspecialchars($note['author_name'] ?? 'Unknown') ?></div>
                    <small class="text-muted"><?= htmlspecialchars($note['author_email'] ?? '') ?></small>
                </div>
            </div>
            <a href="edit_user.php?id=<?= $note['user_id'] ?>" class="btn btn-outline-secondary btn-sm w-100">
                <i class="bi bi-person me-1"></i> View Author Account
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
