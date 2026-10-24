<?php
/**
 * NoteShare — Administrator Registration & Provisioning
 * Protected with server-side authorized invitation token verification.
 */
$page_title = 'Administrator Registration';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in as administrator
if (is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$name = '';
$email = '';

// Determine if any administrators currently exist in the database
$admin_count = 0;
$auth_passkey = 'ADMIN2026';

try {
    $stmt_c = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $admin_count = (int)$stmt_c->fetchColumn();

    $stmt_k = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'admin_passkey' LIMIT 1");
    $stmt_k->execute();
    $db_key = $stmt_k->fetchColumn();
    if (!empty($db_key)) {
        $auth_passkey = $db_key;
    }
} catch (PDOException $e) {
    error_log("Admin registration check error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid request security token. Please refresh and try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $setup_token = trim($_POST['setup_token'] ?? '');

    // 1. Name validation
    if (empty($name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($name) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    }

    // 2. Email validation
    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid administrator email address.';
    }

    // 3. Password validation (min 8 chars)
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    // 4. Password confirmation
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    // 5. Secure Setup Token Authorization Check
    // If administrators already exist, strictly require the secret authorized passkey
    if ($admin_count > 0) {
        if (empty($setup_token) || !hash_equals($auth_passkey, $setup_token)) {
            $errors[] = 'Invalid or unauthorized administrator setup token. Administrator registration requires authorized verification.';
        }
    } else {
        // Initial setup mode: verify token if provided or accept initial setup
        if (!empty($setup_token) && !hash_equals($auth_passkey, $setup_token)) {
            $errors[] = 'Invalid initial setup token. Please check your system configuration.';
        }
    }

    // 6. Duplicate email check
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email address already exists. Please choose a different email or sign in.';
            }
        } catch (PDOException $e) {
            error_log("Admin duplicate email check error: " . $e->getMessage());
            $errors[] = 'A database error occurred. Please try again later.';
        }
    }

    // 7. Insert Administrator
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')");
            $stmt->execute([$name, $email, $hashed_password]);

            flash('success', 'Administrator account created successfully! Please sign in with your credentials.');
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            error_log("Admin creation error: " . $e->getMessage());
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
    <title><?= htmlspecialchars($page_title) ?> | NoteShare Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-dark text-white d-flex align-items-center justify-content-center py-5" style="min-height: 100vh; background: radial-gradient(circle at top, #1e293b 0%, #0f172a 100%);">

<div class="container" style="max-width: 480px;">
    <div class="card shadow-lg border-0 bg-white text-dark rounded-4 p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="brand-icon mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.75rem; background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 14px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="bi bi-shield-lock-fill text-warning"></i>
            </div>
            <h3 class="fw-bold mb-1">Admin Provisioning</h3>
            <p class="text-muted small mb-2">Create an authorized platform administrator account</p>

            <?php if ($admin_count === 0): ?>
                <span class="badge bg-success-subtle text-success border px-3 py-1 rounded-pill small fw-semibold">
                    <i class="bi bi-shield-check me-1"></i> Initial Administrator Setup
                </span>
            <?php else: ?>
                <span class="badge bg-warning-subtle text-warning border px-3 py-1 rounded-pill small fw-semibold">
                    <i class="bi bi-key-fill me-1"></i> Authorized Invitation Required
                </span>
            <?php endif; ?>
        </div>

        <?php display_flash(); ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'register.php') ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Full Name -->
            <div class="mb-3">
                <label for="adminName" class="form-label fw-semibold small">Full Name</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control border-start-0" id="adminName" name="name" value="<?= htmlspecialchars($name) ?>" placeholder="e.g. Master Administrator" required autocomplete="name">
                </div>
            </div>

            <!-- Email Address -->
            <div class="mb-3">
                <label for="adminEmail" class="form-label fw-semibold small">Admin Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control border-start-0" id="adminEmail" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="admin@notes.com" required autocomplete="username">
                </div>
            </div>

            <!-- Password -->
            <div class="mb-3">
                <label for="adminPassword" class="form-label fw-semibold small">Password (Min. 8 characters)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control border-start-0 border-end-0" id="adminPassword" name="password" placeholder="At least 8 characters" required minlength="8" autocomplete="new-password">
                    <button class="btn btn-outline-secondary border-start-0 password-toggle-btn" type="button" data-target="adminPassword" aria-label="Toggle password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="mb-3">
                <label for="adminConfirmPassword" class="form-label fw-semibold small">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-shield-lock"></i></span>
                    <input type="password" class="form-control border-start-0 border-end-0" id="adminConfirmPassword" name="confirm_password" placeholder="Re-enter password" required autocomplete="new-password">
                    <button class="btn btn-outline-secondary border-start-0 password-toggle-btn" type="button" data-target="adminConfirmPassword" aria-label="Toggle password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Setup Authorization Token -->
            <div class="mb-4 p-3 bg-light rounded-3 border">
                <label for="setup_token" class="form-label fw-bold small text-dark d-flex justify-content-between mb-1">
                    <span><i class="bi bi-key-fill text-warning me-1"></i> Authorized Setup Token</span>
                    <span class="badge bg-secondary text-white" style="font-size: 0.65rem;">Private Key</span>
                </label>
                <input type="password" class="form-control form-control-sm bg-white" id="setup_token" name="setup_token" placeholder="Enter administrative authorization token" required autocomplete="off">
                <div class="form-text small" style="font-size: 0.72rem;">Required private security token to authorize administrative provisioning.</div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-dark w-100 py-2 mb-3 fw-bold shadow-sm">
                <i class="bi bi-shield-check me-1"></i> Create Administrator Account
            </button>

            <!-- Back to Login -->
            <div class="text-center small text-muted mb-2">
                Already registered as administrator? 
                <a href="login.php" class="fw-bold text-primary">Admin Sign In</a>
            </div>

            <!-- Return to Main Site -->
            <div class="text-center pt-3 border-top small text-muted">
                <a href="../index.php" class="text-muted text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i> Return to NoteShare Main Site
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.password-toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (input) {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        }
    });
});
</script>

</body>
</html>
