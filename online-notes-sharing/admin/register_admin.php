<?php
$page_title = 'Register New Administrator';
require_once __DIR__ . '/../includes/auth.php';

// If already logged in as admin, redirect to admin dashboard
if (is_logged_in() && is_admin()) {
    header('Location: dashboard.php');
    exit;
}

// Master Admin Security Key required to register as an administrator
define('ADMIN_SECRET_KEY', 'ADMIN2026');

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $secret_key = trim($_POST['secret_key'] ?? '');

    if (empty($name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($name) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    }

    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    // Verify Admin Secret Passcode
    if (empty($secret_key)) {
        $errors[] = 'Admin Security Passkey is required to register an administrative account.';
    } elseif ($secret_key !== ADMIN_SECRET_KEY) {
        $errors[] = 'Invalid Admin Security Passkey. Please verify your administrative authorization key.';
    }

    // Check duplicate email
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email address already exists.';
            }
        } catch (PDOException $e) {
            error_log("Admin registration check error: " . $e->getMessage());
            $errors[] = 'Database error occurred. Please try again.';
        }
    }

    // Create Admin User
    if (empty($errors)) {
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, 'admin', 'active', NOW())");
            $stmt->execute([$name, $email, $hashed]);
            $new_admin_id = (int)$pdo->lastInsertId();

            // Establish admin session
            session_regenerate_id(true);
            $_SESSION['user_id'] = $new_admin_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['role'] = 'admin';

            flash('success', 'Administrator account created successfully! Welcome to the Admin Management Console.');
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            error_log("Admin registration insert error: " . $e->getMessage());
            $errors[] = 'Failed to create administrator account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Administrator Account | EduNotes Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-dark text-white d-flex align-items-center justify-content-center py-5" style="min-height: 100vh;">

<div class="container" style="max-width: 520px;">
    <div class="card shadow-lg border-0 bg-white text-dark rounded-4 p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="brand-icon mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.8rem; background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 14px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="bi bi-shield-lock-fill text-warning"></i>
            </div>
            <h3 class="fw-bold mb-1">Create Admin Account</h3>
            <p class="text-muted small mb-0">Register a new administrator with platform management privileges</p>
        </div>

        <?php display_flash(); ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 small mb-3">
                <div class="d-flex align-items-center mb-1">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Please correct the following:</strong>
                </div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Passkey Hint Box -->
        <div class="alert alert-info py-2 small d-flex align-items-start gap-2 mb-3">
            <i class="bi bi-info-circle-fill fs-6 mt-1 flex-shrink-0"></i>
            <div>
                <strong>Security Passkey:</strong> To prevent unauthorized registration, enter the security key:
                <code class="fw-bold bg-white px-2 py-1 rounded text-primary border ms-1">ADMIN2026</code>
            </div>
        </div>

        <form method="POST" action="register_admin.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Full Name -->
            <div class="mb-3">
                <label for="adminName" class="form-label fw-semibold small">Full Name</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control border-start-0" id="adminName" name="name" value="<?= htmlspecialchars($name) ?>" placeholder="e.g. John Doe" required>
                </div>
            </div>

            <!-- Email Address -->
            <div class="mb-3">
                <label for="adminEmail" class="form-label fw-semibold small">Admin Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control border-start-0" id="adminEmail" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="admin@domain.com" required>
                </div>
            </div>

            <!-- Password -->
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label for="adminPass" class="form-label fw-semibold small">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control border-start-0" id="adminPass" name="password" placeholder="Min 6 chars" required minlength="6">
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="adminConfirmPass" class="form-label fw-semibold small">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-shield-check"></i></span>
                        <input type="password" class="form-control border-start-0" id="adminConfirmPass" name="confirm_password" placeholder="Repeat password" required minlength="6">
                    </div>
                </div>
            </div>

            <!-- Admin Security Passkey -->
            <div class="mb-4">
                <label for="adminSecret" class="form-label fw-semibold small d-flex justify-content-between">
                    <span>Admin Security Passkey</span>
                    <span class="text-muted" style="font-size: 0.75rem;">Default: ADMIN2026</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-key-fill text-warning"></i></span>
                    <input type="text" class="form-control border-start-0" id="adminSecret" name="secret_key" placeholder="Enter ADMIN2026" required value="<?= isset($_POST['secret_key']) ? htmlspecialchars($_POST['secret_key']) : 'ADMIN2026' ?>">
                </div>
                <div class="form-text small text-muted">Required security passcode to authorize administrative registration.</div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                <i class="bi bi-shield-plus me-1"></i> Register as Administrator
            </button>

            <div class="d-flex justify-content-between align-items-center small text-muted pt-2 border-top">
                <a href="admin_login.php" class="text-decoration-none">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Already an Admin? Sign In
                </a>
                <a href="../index.php" class="text-muted text-decoration-none">&larr; Return to Main Site</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
