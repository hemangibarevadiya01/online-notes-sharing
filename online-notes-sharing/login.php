<?php
$page_title = 'Sign In to Your Account';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    if (is_admin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$error = '';
$email = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = trim($_POST['redirect'] ?? '');

    if (empty($error)) {
        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email and password.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] === 'blocked') {
                        $error = 'Your account has been deactivated. Please contact support.';
                    } else {
                        // Successful login: Establish session with regenerated ID
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];

                        flash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');

                        // Redirect based on role or requested target
                        if (!empty($redirect) && !str_starts_with($redirect, 'http://') && !str_starts_with($redirect, 'https://')) {
                            header('Location: ' . $redirect);
                        } else {
                            if ($user['role'] === 'admin') {
                                header('Location: admin/dashboard.php');
                            } else {
                                header('Location: dashboard.php');
                            }
                        }
                        exit;
                    }
                } else {
                    $error = 'Invalid email or password. Please try again.';
                }
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'A database error occurred. Please try again later.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="text-center mb-4">
            <div class="brand-icon mx-auto mb-3" style="width: 48px; height: 48px; font-size: 1.5rem;">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <h3 class="fw-bold mb-1">Welcome Back</h3>
            <p class="text-muted small">Sign in to access study notes, upload materials & collaborate</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4 d-flex align-items-center">
                <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <!-- Email Field -->
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-envelope"></i>
                    </span>
                    <input type="email" class="form-control border-start-0" id="email" name="email" placeholder="name@student.edu" value="<?= htmlspecialchars($email) ?>" required autocomplete="username">
                </div>
            </div>

            <!-- Password Field -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label mb-0">Password</label>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                    <button class="btn btn-outline-secondary border-start-0 password-toggle-btn" type="button" data-target="password" aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Remember me & Forgot -->
            <div class="d-flex justify-content-between align-items-center mb-4 small">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="rememberMe">
                    <label class="form-check-label text-muted" for="rememberMe">Remember me</label>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>

            <!-- Register Link -->
            <div class="text-center small text-muted mb-4">
                Don't have an account? <a href="register.php" class="fw-bold text-primary">Create one now</a>
            </div>

            <!-- Test Credentials Callout -->
            <div class="p-3 bg-light rounded-3 border small">
                <div class="fw-bold text-dark mb-1"><i class="bi bi-key-fill text-warning me-1"></i> Demo Credentials</div>
                <div class="text-muted d-flex flex-column gap-1">
                    <div><strong>Admin:</strong> <code>admin@notes.com</code> / <code>admin123</code></div>
                    <div><strong>Student:</strong> <code>alex@student.com</code> / <code>student123</code></div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
