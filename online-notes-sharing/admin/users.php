<?php
$admin_title = 'User Management';
require_once __DIR__ . '/includes/header.php';

// Quick status toggle via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf($token)) {
        $uid = (int)($_POST['user_id'] ?? 0);
        $new_st = $_POST['new_status'] === 'active' ? 'active' : 'blocked';
        if ($uid > 0 && $uid !== (int)$admin_user['id']) {
            try {
                $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_st, $uid]);
                flash('success', 'User status updated to ' . $new_st . '.');
            } catch (PDOException $e) {}
        }
    }
    header('Location: users.php');
    exit;
}

// Filter & Search
$search = trim($_GET['q'] ?? '');
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $conditions[] = "(name LIKE :q1 OR email LIKE :q2)";
    $params[':q1'] = "%{$search}%";
    $params[':q2'] = "%{$search}%";
}

if (!empty($role_filter)) {
    $conditions[] = "role = :role";
    $params[':role'] = $role_filter;
}

if (!empty($status_filter)) {
    $conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}

$where_sql = implode(' AND ', $conditions);

$users = [];
try {
    $sql = "SELECT u.*, 
                   COUNT(DISTINCT n.id) AS notes_count, 
                   COUNT(DISTINCT gm.group_id) AS groups_count
            FROM users u
            LEFT JOIN notes n ON u.id = n.user_id
            LEFT JOIN group_members gm ON u.id = gm.user_id
            WHERE {$where_sql}
            GROUP BY u.id
            ORDER BY u.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Users query error: " . $e->getMessage());
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <!-- Header with Add Button -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4 pb-2 border-bottom">
        <div>
            <h5 class="fw-bold mb-0">Platform Users Directory</h5>
            <small class="text-muted">Manage student credentials, access permissions and moderation statuses</small>
        </div>
        <a href="add_user.php" class="btn btn-primary btn-sm">
            <i class="bi bi-person-plus-fill me-1"></i> Add New User
        </a>
    </div>

    <!-- Filter Toolbar -->
    <form method="GET" action="users.php" class="row g-2 align-items-center mb-3">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Roles</option>
                <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>Students / Users</option>
                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Administrators</option>
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Accounts</option>
                <option value="blocked" <?= $status_filter === 'blocked' ? 'selected' : '' ?>>Blocked Accounts</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            <?php if (!empty($search) || !empty($role_filter) || !empty($status_filter)): ?>
                <a href="users.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Notes</th>
                    <th>Groups</th>
                    <th>Registered</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="../uploads/avatars/default_avatar.svg" class="avatar-img" style="width: 36px; height: 36px;" alt="Avatar">
                                <div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($u['name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'primary' ?>-subtle text-<?= $u['role'] === 'admin' ? 'danger' : 'primary' ?> border text-uppercase" style="font-size: 0.7rem;">
                                <?= htmlspecialchars($u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ((int)$u['id'] !== (int)$admin_user['id']): ?>
                                <form method="POST" action="users.php" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $u['status'] === 'active' ? 'blocked' : 'active' ?>">
                                    <button type="submit" class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>-subtle text-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?> border border-0 cursor-pointer" title="Click to toggle status">
                                        <?= ucfirst($u['status']) ?> <i class="bi bi-arrow-repeat ms-1"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="small fw-semibold"><?= $u['notes_count'] ?></td>
                        <td class="small fw-semibold"><?= $u['groups_count'] ?></td>
                        <td class="small text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td class="text-end">
                            <div class="d-flex align-items-center justify-content-end gap-1">
                                <a href="edit_user.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit User">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <?php if ((int)$u['id'] !== (int)$admin_user['id']): ?>
                                    <form method="POST" action="delete_user.php" onsubmit="return confirm('Permanently delete user \'<?= htmlspecialchars(addslashes($u['name'])) ?>\' and all associated notes?');" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete User">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
