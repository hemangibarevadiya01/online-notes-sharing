<?php
$page_title = 'Create Student Account';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid request security token. Please try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
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
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    // Duplicate email checking
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email address already exists. Please log in.';
            }
        } catch (PDOException $e) {
            error_log("Registration check error: " . $e->getMessage());
            $errors[] = 'Database error occurred. Please try again later.';
        }
    }

    $role = $_POST['role'] ?? 'user';
    $secret_key = trim($_POST['secret_key'] ?? '');

    if ($role === 'admin') {
        if ($secret_key !== 'ADMIN2026') {
            $errors[] = 'Invalid Admin Security Passkey. Enter ADMIN2026 to register as an administrator.';
        }
    } else {
        $role = 'user';
    }

    // Insert user
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $email, $hashed_password, $role]);
            
            $new_user_id = (int)$pdo->lastInsertId();

            // Establish session
            session_regenerate_id(true);
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['role'] = $role;

            if ($role === 'admin') {
                flash('success', 'Administrator account created successfully! Welcome to the Admin Management Console.');
                header('Location: admin/dashboard.php');
            } else {
                // Create welcome notification for student
                create_notification(
                    $new_user_id,
                    'Welcome to Online Notes Sharing!',
                    'Your account has been created successfully. Browse study materials, upload lecture notes, and join peer study groups.',
                    'welcome'
                );

                flash('success', 'Welcome to EduNotes, ' . htmlspecialchars($name) . '! Your account was created successfully.');
                header('Location: dashboard.php');
            }
            exit;
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $errors[] = 'Failed to register account. Please try again.';
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
            <h3 class="fw-bold mb-1">Create an Account</h3>
            <p class="text-muted small">Join our community of students and educators</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Full Name -->
            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-person"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" id="name" name="name" placeholder="e.g. Sarah Connor" value="<?= htmlspecialchars($name) ?>" required autocomplete="name">
                </div>
            </div>

            <!-- Email Address -->
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-envelope"></i>
                    </span>
                    <input type="email" class="form-control border-start-0" id="email" name="email" placeholder="name@student.edu" value="<?= htmlspecialchars($email) ?>" required autocomplete="username">
                </div>
            </div>

            <!-- Password -->
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" placeholder="At least 6 characters" required autocomplete="new-password" minlength="6">
                    <button class="btn btn-outline-secondary border-start-0 password-toggle-btn" type="button" data-target="password" aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="form-text small">Use 6 or more characters with numbers or letters.</div>
            </div>

            <!-- Confirm Password -->
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-shield-lock"></i>
                    </span>
                    <input type="password" class="form-control border-start-0 border-end-0" id="confirm_password" name="confirm_password" placeholder="Repeat your password" required autocomplete="new-password">
                    <button class="btn btn-outline-secondary border-start-0 password-toggle-btn" type="button" data-target="confirm_password" aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Account Type Selection (Student vs Administrator) -->
            <div class="mb-4 p-3 bg-light rounded-3 border">
                <label class="form-label fw-semibold small d-block mb-2 text-dark">
                    <i class="bi bi-person-badge text-primary me-1"></i> Account Type
                </label>
                <div class="d-flex gap-4 mb-1">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="role" id="roleUser" value="user" <?= ($role ?? 'user') !== 'admin' ? 'checked' : '' ?> onchange="toggleAdminKey(false)">
                        <label class="form-check-label small fw-semibold" for="roleUser">
                            <i class="bi bi-mortarboard text-primary me-1"></i> Student
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="role" id="roleAdmin" value="admin" <?= ($role ?? 'user') === 'admin' ? 'checked' : '' ?> onchange="toggleAdminKey(true)">
                        <label class="form-check-label small fw-semibold" for="roleAdmin">
                            <i class="bi bi-shield-lock text-warning me-1"></i> Administrator
                        </label>
                    </div>
                </div>

                <!-- Admin Security Key field (hidden by default unless Admin selected) -->
                <div id="adminKeyWrapper" class="<?= ($role ?? 'user') === 'admin' ? '' : 'd-none' ?> mt-3 pt-2 border-top">
                    <label for="secret_key" class="form-label small fw-semibold text-danger d-flex justify-content-between">
                        <span>Admin Security Passkey</span>
                        <span class="text-muted fw-normal" style="font-size: 0.75rem;">Default: <code>ADMIN2026</code></span>
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-key-fill text-warning"></i></span>
                        <input type="text" class="form-control border-start-0" id="secret_key" name="secret_key" placeholder="Enter ADMIN2026" value="<?= htmlspecialchars($secret_key ?? '') ?>">
                    </div>
                    <div class="form-text small" style="font-size: 0.72rem;">Required security authorization key to register an administrator account.</div>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                <i class="bi bi-person-check-fill me-1"></i> Register Account
            </button>

            <!-- Admin Dedicated Link -->
            <div class="text-center small text-muted mb-2">
                <i class="bi bi-shield-lock text-primary me-1"></i> Dedicated admin registration? 
                <a href="admin/register_admin.php" class="fw-semibold text-decoration-none">Admin Portal Sign Up &rarr;</a>
            </div>

            <!-- Login Link -->
            <div class="text-center small text-muted">
                Already have an account? <a href="login.php" class="fw-bold text-primary">Sign in here</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAdminKey(show) {
    const wrap = document.getElementById('adminKeyWrapper');
    if (wrap) {
        if (show) {
            wrap.classList.remove('d-none');
            const input = document.getElementById('secret_key');
            if (input && !input.value) input.value = 'ADMIN2026';
        } else {
            wrap.classList.add('d-none');
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
