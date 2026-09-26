<?php
$page_title = 'Admin Portal Login';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in() && is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'Invalid security token.';
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($error)) {
        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['role'] !== 'admin') {
                        $error = 'Access restricted to administrators only.';
                    } elseif ($user['status'] === 'blocked') {
                        $error = 'Administrator account has been deactivated.';
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];

                        flash('success', 'Logged in to Admin Management Portal.');
                        header('Location: dashboard.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid administrative credentials.';
                }
            } catch (PDOException $e) {
                error_log("Admin login error: " . $e->getMessage());
                $error = 'A database error occurred.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal Sign In | Online Notes Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-dark text-white d-flex align-items-center justify-content-center" style="min-height: 100vh;">

<div class="container" style="max-width: 440px;">
    <div class="card shadow-lg border-0 bg-white text-dark rounded-4 p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="brand-icon mx-auto mb-3" style="width: 52px; height: 52px; font-size: 1.6rem; background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 12px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="bi bi-shield-lock-fill text-warning"></i>
            </div>
            <h3 class="fw-bold mb-1">Admin Portal</h3>
            <p class="text-muted small">Enter your administrative credentials to continue</p>
        </div>

        <?php display_flash(); ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small d-flex align-items-center mb-3">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'admin_login.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="mb-3">
                <label for="adminEmail" class="form-label">Admin Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control border-start-0" id="adminEmail" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="admin@notes.com" required>
                </div>
            </div>

            <div class="mb-4">
                <label for="adminPassword" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control border-start-0" id="adminPassword" name="password" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                <i class="bi bi-box-arrow-in-right me-1"></i> Access Admin Console
            </button>

            <div class="text-center pt-2 border-top">
                <p class="small text-muted mb-2">Need to create a new administrator account?</p>
                <a href="register_admin.php" class="btn btn-outline-secondary btn-sm w-100 mb-3">
                    <i class="bi bi-shield-plus me-1"></i> Register New Admin
                </a>
            </div>

            <div class="text-center small">
                <a href="../login.php" class="text-muted text-decoration-none">&larr; Return to Student Portal</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
