<?php
$admin_title = 'Note Reports & Moderation';
require_once __DIR__ . '/includes/header.php';

// POST action handler for Report Management CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        flash('danger', 'Security validation failed.');
        header('Location: reports.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $report_id = (int)($_POST['report_id'] ?? 0);

    if ($report_id > 0) {
        if ($action === 'set_status') {
            $new_st = in_array($_POST['status'] ?? '', ['pending', 'reviewed', 'resolved']) ? $_POST['status'] : 'pending';
            try {
                $pdo->prepare("UPDATE reports SET status = ? WHERE id = ?")->execute([$new_st, $report_id]);
                flash('success', 'Report status updated to ' . ucfirst($new_st) . '.');
            } catch (PDOException $e) {}
        } elseif ($action === 'delete_report') {
            try {
                $pdo->prepare("DELETE FROM reports WHERE id = ?")->execute([$report_id]);
                flash('success', 'Report log entry deleted.');
            } catch (PDOException $e) {}
        } elseif ($action === 'takedown_note' && isset($_POST['note_id'])) {
            $note_id = (int)$_POST['note_id'];
            try {
                // Delete file from disk
                $stmt_f = $pdo->prepare("SELECT file_path FROM notes WHERE id = ?");
                $stmt_f->execute([$note_id]);
                $f = $stmt_f->fetch();
                if ($f) {
                    $p = __DIR__ . '/../' . $f['file_path'];
                    if (file_exists($p)) @unlink($p);
                }
                $pdo->prepare("DELETE FROM notes WHERE id = ?")->execute([$note_id]);
                $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?")->execute([$report_id]);
                flash('success', 'Infringing document removed and report marked as resolved.');
            } catch (PDOException $e) {
                error_log("Takedown note error: " . $e->getMessage());
            }
        }
    }
    header('Location: reports.php');
    exit;
}

$reports = [];
try {
    $stmt = $pdo->query("SELECT r.*, n.title AS note_title, n.file_name, u.name AS reporter_name, u.email AS reporter_email 
                         FROM reports r 
                         LEFT JOIN notes n ON r.note_id = n.id 
                         JOIN users u ON r.user_id = u.id 
                         ORDER BY r.status = 'pending' DESC, r.created_at DESC");
    $reports = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Reports error: " . $e->getMessage());
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h5 class="fw-bold mb-0"><i class="bi bi-flag-fill text-danger me-2"></i>Reported Documents & Infringement Claims</h5>
            <small class="text-muted">Review community flags, update review stages, or take down infringing documents</small>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Reported Document</th>
                    <th>Reason & Details</th>
                    <th>Reported By</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No reports submitted.</td></tr>
                <?php else: ?>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td>
                                <?php if (!empty($r['note_title'])): ?>
                                    <a href="../note.php?id=<?= $r['note_id'] ?>" target="_blank" class="fw-bold text-dark text-decoration-none">
                                        <?= htmlspecialchars($r['note_title']) ?>
                                    </a>
                                    <div class="small text-muted"><?= htmlspecialchars($r['file_name']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">Note has been removed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold text-danger small"><?= htmlspecialchars($r['reason']) ?></div>
                                <div class="small text-muted" style="max-width: 280px;"><?= htmlspecialchars($r['description']) ?></div>
                            </td>
                            <td class="small">
                                <div class="fw-bold"><?= htmlspecialchars($r['reporter_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($r['reporter_email']) ?></small>
                            </td>
                            <td>
                                <div class="dropdown d-inline-block">
                                    <button class="badge bg-<?= $r['status'] === 'pending' ? 'danger' : ($r['status'] === 'reviewed' ? 'warning' : 'success') ?>-subtle text-<?= $r['status'] === 'pending' ? 'danger' : ($r['status'] === 'reviewed' ? 'warning' : 'success') ?> border dropdown-toggle border-0" type="button" data-bs-toggle="dropdown">
                                        <?= ucfirst($r['status']) ?>
                                    </button>
                                    <ul class="dropdown-menu shadow border-0 p-1 small">
                                        <li>
                                            <form method="POST" action="reports.php">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="action" value="set_status">
                                                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="status" value="pending">
                                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-clock me-1"></i> Mark as Pending</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" action="reports.php">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="action" value="set_status">
                                                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="status" value="reviewed">
                                                <button type="submit" class="dropdown-item text-warning"><i class="bi bi-eye me-1"></i> Mark as Reviewed</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" action="reports.php">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="action" value="set_status">
                                                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="status" value="resolved">
                                                <button type="submit" class="dropdown-item text-success"><i class="bi bi-check2-circle me-1"></i> Mark as Resolved</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                            <td class="small text-muted"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <?php if (!empty($r['note_id'])): ?>
                                        <a href="../note.php?id=<?= $r['note_id'] ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" target="_blank" title="View Reported Note">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($r['status'] !== 'resolved'): ?>
                                            <form method="POST" action="reports.php" onsubmit="return confirm('Take down note and delete file?');" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <input type="hidden" name="action" value="takedown_note">
                                                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                                <input type="hidden" name="note_id" value="<?= $r['note_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger py-1 px-2" title="Takedown Note">
                                                    <i class="bi bi-shield-x"></i> Takedown
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <!-- Delete Report -->
                                    <form method="POST" action="reports.php" onsubmit="return confirm('Delete this report record?');" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_report">
                                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Report Entry">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
