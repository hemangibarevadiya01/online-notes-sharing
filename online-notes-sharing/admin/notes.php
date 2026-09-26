<?php
$admin_title = 'Note Moderation & Management';
require_once __DIR__ . '/includes/header.php';

// Handle status change via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_status') {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf($token)) {
        $note_id = (int)($_POST['note_id'] ?? 0);
        $new_st = in_array($_POST['new_status'] ?? '', ['approved', 'pending', 'rejected']) ? $_POST['new_status'] : 'approved';
        if ($note_id > 0) {
            try {
                $pdo->prepare("UPDATE notes SET status = ? WHERE id = ?")->execute([$new_st, $note_id]);
                flash('success', 'Note status changed to ' . ucfirst($new_st) . '.');
            } catch (PDOException $e) {}
        }
    }
    header('Location: notes.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$cat_id = (int)($_GET['category'] ?? 0);
$status = $_GET['status'] ?? '';

$conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $conditions[] = "(n.title LIKE :q1 OR n.subject LIKE :q2 OR u.name LIKE :q3)";
    $params[':q1'] = "%{$search}%";
    $params[':q2'] = "%{$search}%";
    $params[':q3'] = "%{$search}%";
}

if ($cat_id > 0) {
    $conditions[] = "n.category_id = :cat_id";
    $params[':cat_id'] = $cat_id;
}

if (!empty($status)) {
    $conditions[] = "n.status = :status";
    $params[':status'] = $status;
}

$where_sql = implode(' AND ', $conditions);

// Fetch categories for filter
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

$notes = [];
try {
    $sql = "SELECT n.*, c.name AS category_name, u.name AS author_name, 
                   (SELECT COUNT(*) FROM reports WHERE note_id = n.id AND status = 'pending') AS pending_reports
            FROM notes n
            JOIN categories c ON n.category_id = c.id
            JOIN users u ON n.user_id = u.id
            WHERE {$where_sql}
            ORDER BY n.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Admin notes query error: " . $e->getMessage());
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h5 class="fw-bold mb-0">Study Notes Moderation</h5>
            <small class="text-muted">Review, verify, edit, and moderate student uploaded materials</small>
        </div>
        <a href="../upload_note.php" class="btn btn-primary btn-sm" target="_blank">
            <i class="bi bi-cloud-arrow-up-fill me-1"></i> Upload Note
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" action="notes.php" class="row g-2 align-items-center mb-3">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search title, subject, or author..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $cat_id === (int)$cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            <?php if (!empty($search) || $cat_id > 0 || !empty($status)): ?>
                <a href="notes.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Title & Document</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Downloads</th>
                    <th>Status</th>
                    <th>Reports</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notes as $n): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($n['title']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($n['file_name']) ?> &bull; <?= format_bytes($n['file_size']) ?></small>
                        </td>
                        <td><span class="category-pill" style="font-size: 0.72rem;"><?= htmlspecialchars($n['category_name']) ?></span></td>
                        <td class="small"><?= htmlspecialchars($n['author_name']) ?></td>
                        <td class="small fw-semibold"><?= $n['downloads'] ?></td>
                        <td>
                            <div class="dropdown d-inline-block">
                                <button class="badge bg-<?= $n['status'] === 'approved' ? 'success' : ($n['status'] === 'pending' ? 'warning' : 'danger') ?>-subtle text-<?= $n['status'] === 'approved' ? 'success' : ($n['status'] === 'pending' ? 'warning' : 'danger') ?> border dropdown-toggle border-0" type="button" data-bs-toggle="dropdown">
                                    <?= ucfirst($n['status']) ?>
                                </button>
                                <ul class="dropdown-menu shadow border-0 p-1 small">
                                    <li>
                                        <form method="POST" action="notes.php">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                                            <input type="hidden" name="new_status" value="approved">
                                            <button type="submit" class="dropdown-item text-success"><i class="bi bi-check-circle me-1"></i> Approve</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="POST" action="notes.php">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                                            <input type="hidden" name="new_status" value="pending">
                                            <button type="submit" class="dropdown-item text-warning"><i class="bi bi-clock me-1"></i> Set Pending</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="POST" action="notes.php">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                                            <input type="hidden" name="new_status" value="rejected">
                                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-x-circle me-1"></i> Reject</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                        <td>
                            <?php if ($n['pending_reports'] > 0): ?>
                                <span class="badge bg-danger text-white"><?= $n['pending_reports'] ?> Flagged</span>
                            <?php else: ?>
                                <span class="text-muted small">None</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex align-items-center justify-content-end gap-1">
                                <a href="../note.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" target="_blank" title="Preview Note">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit_note.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit Note">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="delete_note.php" onsubmit="return confirm('Permanently delete note \'<?= htmlspecialchars(addslashes($n['title'])) ?>\' and remove file from storage?');" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Note">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
