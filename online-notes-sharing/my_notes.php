<?php
/**
 * NoteShare — My Notes Management
 * Allows students to view, filter, edit, and delete their own uploaded study notes.
 */
$page_title = 'My Uploaded Notes';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user_id = (int)$_SESSION['user_id'];
$status_filter = trim($_GET['status'] ?? 'all');
$valid_statuses = ['approved', 'pending', 'rejected'];

try {
    // Metrics
    $stmt_metrics = $pdo->prepare("
        SELECT 
            COUNT(*) as total_notes,
            COALESCE(SUM(downloads), 0) as total_downloads,
            COALESCE(SUM(views), 0) as total_views,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_notes,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_notes
        FROM notes
        WHERE user_id = ?
    ");
    $stmt_metrics->execute([$user_id]);
    $metrics = $stmt_metrics->fetch(PDO::FETCH_ASSOC);

    // Query notes with category
    $sql = "
        SELECT n.*, c.name as category_name,
               (SELECT COUNT(*) FROM likes WHERE note_id = n.id) as likes_count,
               (SELECT AVG(rating) FROM ratings WHERE note_id = n.id) as avg_rating
        FROM notes n
        LEFT JOIN categories c ON n.category_id = c.id
        WHERE n.user_id = ?
    ";
    $params = [$user_id];

    if (in_array($status_filter, $valid_statuses)) {
        $sql .= " AND n.status = ?";
        $params[] = $status_filter;
    }

    $sql .= " ORDER BY n.created_at DESC";
    $stmt_notes = $pdo->prepare($sql);
    $stmt_notes->execute($params);
    $notes = $stmt_notes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("My notes error: " . $e->getMessage());
    $notes = [];
    $metrics = ['total_notes' => 0, 'total_downloads' => 0, 'total_views' => 0, 'approved_notes' => 0, 'pending_notes' => 0];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Header Ribbon -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
                    <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">My Notes</li>
                </ol>
            </nav>
            <h2 class="fw-bold mb-1"><i class="bi bi-journal-album text-primary me-2"></i>My Uploaded Notes</h2>
            <p class="text-muted small mb-0">Manage, track download statistics, and update metadata for your study contributions</p>
        </div>
        <div class="d-flex gap-2">
            <a href="upload_note.php" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="bi bi-cloud-arrow-up-fill"></i> Upload New Note
            </a>
            <a href="dashboard.php" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Summary Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Total Uploads</span>
                    <div class="brand-icon" style="width: 38px; height: 38px; font-size: 1.1rem; background: rgba(79, 70, 229, 0.1); color: #4F46E5;">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark"><?= number_format($metrics['total_notes']) ?></h3>
                <small class="text-muted" style="font-size: 0.75rem;">Documents shared</small>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Total Downloads</span>
                    <div class="brand-icon" style="width: 38px; height: 38px; font-size: 1.1rem; background: rgba(16, 185, 129, 0.1); color: #10B981;">
                        <i class="bi bi-download"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark"><?= number_format($metrics['total_downloads']) ?></h3>
                <small class="text-success" style="font-size: 0.75rem;"><i class="bi bi-graph-up me-1"></i>Impact metric</small>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Total Views</span>
                    <div class="brand-icon" style="width: 38px; height: 38px; font-size: 1.1rem; background: rgba(6, 182, 212, 0.1); color: #06B6D4;">
                        <i class="bi bi-eye"></i>
                    </div>
                </div>
                <h3 class="fw-bold mb-0 text-dark"><?= number_format($metrics['total_views']) ?></h3>
                <small class="text-muted" style="font-size: 0.75rem;">Student impressions</small>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">Approval Status</span>
                    <div class="brand-icon" style="width: 38px; height: 38px; font-size: 1.1rem; background: rgba(124, 58, 237, 0.1); color: #7C3AED;">
                        <i class="bi bi-patch-check-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <h3 class="fw-bold mb-0 text-dark"><?= number_format($metrics['approved_notes']) ?></h3>
                    <?php if ($metrics['pending_notes'] > 0): ?>
                        <span class="badge bg-warning text-dark small"><?= $metrics['pending_notes'] ?> pending</span>
                    <?php endif; ?>
                </div>
                <small class="text-muted" style="font-size: 0.75rem;">Verified for sharing</small>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="my_notes.php" class="btn btn-sm <?= $status_filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">
            All Notes (<?= $metrics['total_notes'] ?>)
        </a>
        <a href="my_notes.php?status=approved" class="btn btn-sm <?= $status_filter === 'approved' ? 'btn-success' : 'btn-outline-secondary' ?>">
            <i class="bi bi-check-circle me-1"></i> Approved (<?= $metrics['approved_notes'] ?>)
        </a>
        <a href="my_notes.php?status=pending" class="btn btn-sm <?= $status_filter === 'pending' ? 'btn-warning text-dark' : 'btn-outline-secondary' ?>">
            <i class="bi bi-clock-history me-1"></i> Pending Review (<?= $metrics['pending_notes'] ?>)
        </a>
        <a href="my_notes.php?status=rejected" class="btn btn-sm <?= $status_filter === 'rejected' ? 'btn-danger' : 'btn-outline-secondary' ?>">
            <i class="bi bi-x-circle me-1"></i> Needs Revision
        </a>
    </div>

    <!-- Notes Table / Cards -->
    <?php if (empty($notes)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
            <div class="brand-icon mx-auto mb-3" style="width: 64px; height: 64px; font-size: 2rem; background: rgba(79, 70, 229, 0.1); color: #4F46E5;">
                <i class="bi bi-journal-plus"></i>
            </div>
            <h5 class="fw-bold mb-2">No notes found</h5>
            <p class="text-muted small mb-4" style="max-width: 420px; margin-inline: auto;">
                <?= $status_filter === 'all' ? "You haven't uploaded any study notes yet. Share your lecture notes, summaries, or assignments to start helping peers." : "No notes found matching the selected '{$status_filter}' filter." ?>
            </p>
            <div>
                <a href="upload_note.php" class="btn btn-primary px-4">
                    <i class="bi bi-cloud-arrow-up-fill me-1"></i> Upload Your First Note
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase text-muted">
                        <tr>
                            <th class="ps-4">Document Title</th>
                            <th>Subject & Category</th>
                            <th>Format</th>
                            <th>Downloads</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notes as $n): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="brand-icon flex-shrink-0" style="width: 40px; height: 40px; font-size: 1.2rem; background: rgba(79, 70, 229, 0.1); color: #4F46E5;">
                                            <i class="bi <?= match(strtolower($n['file_type'])) {
                                                'pdf' => 'bi-file-earmark-pdf-fill text-danger',
                                                'doc', 'docx' => 'bi-file-earmark-word-fill text-primary',
                                                'ppt', 'pptx' => 'bi-file-earmark-ppt-fill text-warning',
                                                'zip' => 'bi-file-earmark-zip-fill text-purple',
                                                default => 'bi-file-earmark-text-fill'
                                            } ?>"></i>
                                        </div>
                                        <div>
                                            <a href="note.php?id=<?= $n['id'] ?>" class="fw-bold text-dark text-decoration-none d-block text-truncate" style="max-width: 240px;">
                                                <?= htmlspecialchars($n['title']) ?>
                                            </a>
                                            <small class="text-muted"><?= format_bytes($n['file_size']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold small text-dark"><?= htmlspecialchars($n['subject']) ?></div>
                                    <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">
                                        <?= htmlspecialchars($n['category_name'] ?? 'General') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary text-uppercase" style="font-size: 0.72rem;">
                                        .<?= htmlspecialchars($n['file_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><i class="bi bi-download text-muted me-1"></i><?= number_format($n['downloads']) ?></span>
                                </td>
                                <td>
                                    <?php $avg = round((float)($n['avg_rating'] ?? 0), 1); ?>
                                    <div class="d-flex align-items-center gap-1 text-warning small">
                                        <i class="bi bi-star-fill"></i>
                                        <span class="fw-semibold text-dark"><?= $avg > 0 ? $avg : '—' ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($n['status'] === 'approved'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i> Approved
                                        </span>
                                    <?php elseif ($n['status'] === 'pending'): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                            <i class="bi bi-clock-fill me-1"></i> Pending
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                            <i class="bi bi-x-circle-fill me-1"></i> Rejected
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= date('M d, Y', strtotime($n['created_at'])) ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="note.php?id=<?= $n['id'] ?>" class="btn btn-outline-secondary" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit_note.php?id=<?= $n['id'] ?>" class="btn btn-outline-primary" title="Edit Note">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" title="Delete Note" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $n['id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>

                                    <!-- Delete Confirmation Modal -->
                                    <div class="modal fade text-start" id="deleteModal<?= $n['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <div class="modal-body p-4 text-center">
                                                    <div class="brand-icon mx-auto mb-3" style="width: 52px; height: 52px; font-size: 1.5rem; background: rgba(239, 68, 68, 0.1); color: #EF4444;">
                                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                                    </div>
                                                    <h5 class="fw-bold mb-2">Delete Study Note?</h5>
                                                    <p class="text-muted small mb-4">
                                                        Are you sure you want to permanently delete <strong>"<?= htmlspecialchars($n['title']) ?>"</strong>? This action cannot be undone.
                                                    </p>
                                                    <form action="delete_note.php" method="POST" class="d-flex gap-2">
                                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                        <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                                                        <button type="button" class="btn btn-light w-50 py-2 border" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger w-50 py-2">
                                                            <i class="bi bi-trash-fill me-1"></i> Yes, Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
