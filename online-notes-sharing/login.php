<?php
$page_title = 'Student Sign In';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in as a student
if (is_logged_in() && !is_admin()) {
    header('Location: dashboard.php');
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
            $error = 'Please enter both your email address and password.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] === 'blocked') {
                        $error = 'Your student account has been deactivated. Please contact support.';
                    } elseif ($user['role'] !== 'user') {
                        // Allow only active student accounts through student login (no admin links/hints)
                        $error = 'Invalid email address or password. Please try again.';
                    } else {
                        // Successful student login
                        session_regenerate_id(true);
                        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_email'], $_SESSION['admin_role']);
                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['role'] = 'user';

                        flash('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');

                        // Redirect to requested protected page or User Dashboard
                        if (!empty($redirect) && !str_starts_with($redirect, 'http://') && !str_starts_with($redirect, 'https://')) {
                            header('Location: ' . $redirect);
                        } else {
                            header('Location: dashboard.php');
                        }
                        exit;
                    }
                } else {
                    $error = 'Invalid email address or password. Please try again.';
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

<div class="auth-wrapper py-5">
    <div class="auth-card mx-auto">
        <div class="text-center mb-4">
            <div class="brand-icon mx-auto mb-3" style="width: 52px; height: 52px; font-size: 1.6rem;">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <h3 class="fw-bold mb-1">Student Sign In</h3>
            <p class="text-muted small">Access your study dashboard, lecture notes & peer study groups</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4 d-flex align-items-center">
                <i class="bi bi-exclamation-octagon-fill me-2 fs-5 flex-shrink-0"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <?php if (!empty($redirect)): ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <?php endif; ?>

            <!-- Email Address -->
            <div class="mb-3">
                <label for="email" class="form-label fw-semibold small">Student Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-envelope"></i>
                    </span>
                    <input type="email" class="form-control border-start-0" id="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="alex@student.com" required autocomplete="username">
                </div>
            </div>

            <!-- Password -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label fw-semibold small mb-0">Password</label>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                    <button class="btn btn-outline-secondary border-start-0 password-toggle-btn" type="button" data-target="password" aria-label="Toggle password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Sign In Button -->
            <button type="submit" class="btn btn-primary w-100 py-2 mb-3 fw-bold">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Dashboard
            </button>

            <!-- Register Link -->
            <div class="text-center small text-muted">
                New to NoteShare? <a href="register.php" class="fw-bold text-primary">Create a free student account</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
