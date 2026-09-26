<?php
$admin_title = 'Add New User';
require_once __DIR__ . '/includes/header.php';

$errors = [];
$name = '';
$email = '';
$role = 'user';
$status = 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid security token.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $status = $_POST['status'] ?? 'active';

    if (empty($name)) $errors[] = 'Full name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email address is required.';
    if (empty($password) || strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

    if (empty($errors)) {
        try {
            $stmt_chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt_chk->execute([$email]);
            if ($stmt_chk->fetch()) {
                $errors[] = 'An account with this email address already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt_ins = $pdo->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt_ins->execute([$name, $email, $hash, $role, $status]);

                flash('success', 'User account for "' . htmlspecialchars($name) . '" created successfully.');
                header('Location: users.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Add user error: " . $e->getMessage());
            $errors[] = 'Database error while creating user.';
        }
    }
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mx-auto" style="max-width: 650px;">
    <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
        <div class="brand-icon" style="width: 48px; height: 48px;">
            <i class="bi bi-person-plus-fill fs-4"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0">Add New User</h4>
            <p class="text-muted small mb-0">Create a new student or administrator account</p>
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

    <form method="POST" action="add_user.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="mb-3">
            <label class="form-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" value="<?= htmlspecialchars($name) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email Address <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="name@university.edu" value="<?= htmlspecialchars($email) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Account Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">System Role</label>
                <select name="role" class="form-select">
                    <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Student / User</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Account Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="blocked" <?= $status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                </select>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
            <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">Create User</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
