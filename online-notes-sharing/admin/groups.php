<?php
$admin_title = 'Study Group Management';
require_once __DIR__ . '/includes/header.php';

$groups = [];
try {
    $stmt = $pdo->query("SELECT g.*, u.name AS creator_name, COUNT(DISTINCT gm.user_id) AS members_count 
                         FROM groups g 
                         JOIN users u ON g.created_by = u.id 
                         LEFT JOIN group_members gm ON g.id = gm.group_id 
                         GROUP BY g.id 
                         ORDER BY g.created_at DESC");
    $groups = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Admin groups error: " . $e->getMessage());
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h5 class="fw-bold mb-0">Registered Student Study Groups</h5>
            <small class="text-muted">Manage academic circles, group leaders, and member access</small>
        </div>
        <a href="../create_group.php" class="btn btn-primary btn-sm" target="_blank">
            <i class="bi bi-plus-circle me-1"></i> New Group
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Group Name</th>
                    <th>Description</th>
                    <th>Leader / Creator</th>
                    <th>Members</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($groups)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No study groups created yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($groups as $g): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($g['name']) ?></td>
                            <td class="small text-muted" style="max-width: 260px;"><?= htmlspecialchars($g['description'] ?: 'No description.') ?></td>
                            <td class="small"><?= htmlspecialchars($g['creator_name']) ?></td>
                            <td class="small fw-semibold">
                                <a href="group_members.php?id=<?= $g['id'] ?>" class="text-decoration-none" title="View Members">
                                    <span class="badge bg-purple-subtle text-purple border"><?= $g['members_count'] ?> members</span>
                                </a>
                            </td>
                            <td class="small text-muted"><?= date('M j, Y', strtotime($g['created_at'])) ?></td>
                            <td class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <a href="group_members.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="View Members">
                                        <i class="bi bi-people"></i>
                                    </a>
                                    <a href="edit_group.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit Group">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="delete_group.php" onsubmit="return confirm('Permanently remove study group \'<?= htmlspecialchars(addslashes($g['name'])) ?>\'?');" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Group">
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
