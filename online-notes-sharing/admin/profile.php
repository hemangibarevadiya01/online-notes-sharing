<?php
$admin_title = 'Administrator Profile & Security';
require_once __DIR__ . '/includes/header.php';

$errors = [];
$name = $admin_user['name'];
$email = $admin_user['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Security token invalid.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $old_pwd = $_POST['old_password'] ?? '';
    $new_pwd = $_POST['new_password'] ?? '';
    $confirm_pwd = $_POST['confirm_password'] ?? '';

    if (empty($name)) $errors[] = 'Name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

    $update_pwd = false;
    if (!empty($new_pwd)) {
        if (empty($old_pwd)) {
            $errors[] = 'Current password is required to change password.';
        } elseif (strlen($new_pwd) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($new_pwd !== $confirm_pwd) {
            $errors[] = 'New passwords do not match.';
        } else {
            $stmt_chk = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt_chk->execute([$admin_user['id']]);
            $current_hash = $stmt_chk->fetchColumn();

            if (!password_verify($old_pwd, $current_hash)) {
                $errors[] = 'Current password is incorrect.';
            } else {
                $update_pwd = true;
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($update_pwd) {
                $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
                $stmt_up = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
                $stmt_up->execute([$name, $email, $new_hash, $admin_user['id']]);
            } else {
                $stmt_up = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt_up->execute([$name, $email, $admin_user['id']]);
            }

            $_SESSION['user_name'] = $name;
            flash('success', 'Admin profile settings updated successfully.');
            header('Location: profile.php');
            exit;
        } catch (PDOException $e) {
            error_log("Admin profile update error: " . $e->getMessage());
            $errors[] = 'Database error while saving changes.';
        }
    }
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mx-auto" style="max-width: 600px;">
    <h4 class="fw-bold mb-3 pb-2 border-bottom"><i class="bi bi-shield-lock-fill text-purple me-2"></i>Admin Profile & Security</h4>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="profile.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="mb-3">
            <label class="form-label">Administrator Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email Address <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
        </div>

        <hr class="my-4">
        <h6 class="fw-bold mb-2">Update Password</h6>
        <p class="text-muted small mb-3">Leave blank if you don't wish to modify your login password.</p>

        <div class="mb-3">
            <label class="form-label small">Current Password</label>
            <input type="password" name="old_password" class="form-control form-control-sm" placeholder="Current admin password">
        </div>

        <div class="mb-3">
            <label class="form-label small">New Password</label>
            <input type="password" name="new_password" class="form-control form-control-sm" placeholder="At least 6 characters">
        </div>

        <div class="mb-4">
            <label class="form-label small">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control form-control-sm" placeholder="Repeat new password">
        </div>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm px-4">Update Profile</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
