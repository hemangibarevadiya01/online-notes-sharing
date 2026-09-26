<?php
$admin_title = 'Group Members';
require_once __DIR__ . '/includes/header.php';

$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($group_id <= 0) {
    flash('danger', 'Invalid group.');
    header('Location: groups.php');
    exit;
}

// Remove member via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_member') {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf($token)) {
        $uid = (int)$_POST['user_id'];
        try {
            $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?")->execute([$group_id, $uid]);
            flash('success', 'Member removed from group.');
        } catch (PDOException $e) {}
    }
    header('Location: group_members.php?id=' . $group_id);
    exit;
}

try {
    $stmt_g = $pdo->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt_g->execute([$group_id]);
    $group = $stmt_g->fetch();

    if (!$group) {
        flash('danger', 'Study group not found.');
        header('Location: groups.php');
        exit;
    }

    $stmt_m = $pdo->prepare("SELECT u.id, u.name, u.email, u.role, gm.joined_at 
                             FROM group_members gm 
                             JOIN users u ON gm.user_id = u.id 
                             WHERE gm.group_id = ? 
                             ORDER BY gm.joined_at ASC");
    $stmt_m->execute([$group_id]);
    $members = $stmt_m->fetchAll();
} catch (PDOException $e) {
    flash('danger', 'Database error.');
    header('Location: groups.php');
    exit;
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h5 class="fw-bold mb-0">Members of <?= htmlspecialchars($group['name']) ?></h5>
            <small class="text-muted">Total enrolled students: <?= count($members) ?></small>
        </div>
        <a href="groups.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Study Groups</a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Student Name</th>
                    <th>Email Address</th>
                    <th>Role in Group</th>
                    <th>Joined Date</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($m['name']) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($m['email']) ?></td>
                        <td>
                            <?php if ((int)$m['id'] === (int)$group['created_by']): ?>
                                <span class="badge bg-warning-subtle text-warning border">Creator / Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border">Member</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= date('M j, Y H:i', strtotime($m['joined_at'])) ?></td>
                        <td class="text-end">
                            <?php if ((int)$m['id'] !== (int)$group['created_by']): ?>
                                <form method="POST" action="group_members.php?id=<?= $group_id ?>" onsubmit="return confirm('Remove this student from the study group?');" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="remove_member">
                                    <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">Remove</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small">Creator</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
