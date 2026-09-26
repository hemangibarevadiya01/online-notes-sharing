<?php
$admin_title = 'Edit User Account';
require_once __DIR__ . '/includes/header.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    flash('danger', 'Invalid user specified.');
    header('Location: users.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $target_user = $stmt->fetch();

    if (!$target_user) {
        flash('danger', 'User not found.');
        header('Location: users.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Edit user error: " . $e->getMessage());
    flash('danger', 'Unable to retrieve user details.');
    header('Location: users.php');
    exit;
}

$errors = [];
$name = $target_user['name'];
$email = $target_user['email'];
$role = $target_user['role'];
$status = $target_user['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Security token invalid.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $status = $_POST['status'] ?? 'active';
    $new_password = $_POST['new_password'] ?? '';

    if (empty($name)) {
        $errors[] = 'User name is required.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }

    // Check duplicate email
    if (empty($errors)) {
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt_check->execute([$email, $user_id]);
        if ($stmt_check->fetch()) {
            $errors[] = 'Email is already assigned to another user.';
        }
    }

    if (empty($errors)) {
        try {
            if (!empty($new_password)) {
                $pwd_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_up = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, password = ? WHERE id = ?");
                $stmt_up->execute([$name, $email, $role, $status, $pwd_hash, $user_id]);
            } else {
                $stmt_up = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ? WHERE id = ?");
                $stmt_up->execute([$name, $email, $role, $status, $user_id]);
            }

            flash('success', 'User account updated successfully.');
            header('Location: users.php');
            exit;
        } catch (PDOException $e) {
            error_log("User update error: " . $e->getMessage());
            $errors[] = 'Database error while saving changes.';
        }
    }
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mx-auto" style="max-width: 650px;">
    <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
        <div class="brand-icon" style="width: 48px; height: 48px;">
            <i class="bi bi-person-gear fs-4"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0">Modify User Account</h4>
            <p class="text-muted small mb-0">Editing account for <?= htmlspecialchars($target_user['name']) ?></p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="edit_user.php?id=<?= $user_id ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="mb-3">
            <label class="form-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email Address <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">System Role</label>
                <select name="role" class="form-select">
                    <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Student / Regular User</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Account Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="blocked" <?= $status === 'blocked' ? 'selected' : '' ?>>Blocked / Deactivated</option>
                </select>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Set New Password (Leave blank to keep existing)</label>
            <input type="password" name="new_password" class="form-control" placeholder="New password">
        </div>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
            <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">Save Changes</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
