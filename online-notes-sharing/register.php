<?php
$page_title = 'Create Student Account';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in as a student
if (is_logged_in() && !is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$field_errors = [];
$name = '';
$email = '';
$agree_terms = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid request security token. Please refresh and try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $agree_terms = isset($_POST['agree_terms']) && $_POST['agree_terms'] === '1';

    // 1. Full name validation
    if (empty($name)) {
        $field_errors['name'] = 'Full name is required.';
        $errors[] = 'Full name is required.';
    } elseif (strlen($name) < 2) {
        $field_errors['name'] = 'Full name must be at least 2 characters.';
        $errors[] = 'Full name must be at least 2 characters.';
    }

    // 2. Email format validation
    if (empty($email)) {
        $field_errors['email'] = 'Email address is required.';
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $field_errors['email'] = 'Please provide a valid email address.';
        $errors[] = 'Please provide a valid email address.';
    }

    // 3. Password minimum 8 characters
    if (empty($password)) {
        $field_errors['password'] = 'Password is required.';
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif (strlen($password) < 8) {
        $field_errors['password'] = 'Password must be at least 8 characters long.';
        $errors[] = 'Password must be at least 8 characters long.';
    }

    // 4. Password confirmation
    if (empty($confirm_password)) {
        $field_errors['confirm_password'] = 'Please confirm your password.';
        $errors[] = 'Please confirm your password.';
    } elseif ($password !== $confirm_password) {
        $field_errors['confirm_password'] = 'Passwords do not match.';
        $errors[] = 'Passwords do not match.';
    }

    // 5. Terms and conditions agreement
    if (!$agree_terms) {
        $field_errors['agree_terms'] = 'You must agree to the Terms of Service and Academic Integrity Policy.';
        $errors[] = 'You must agree to the Terms of Service and Academic Integrity Policy.';
    }

    // 6. Duplicate email checking
    if (empty($field_errors['email'])) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $field_errors['email'] = 'An account with this email address already exists. Please log in.';
                $errors[] = 'An account with this email address already exists. Please log in.';
            }
        } catch (PDOException $e) {
            error_log("Student registration email check error: " . $e->getMessage());
            $errors[] = 'Database error occurred. Please try again later.';
        }
    }

    // 7. Insert new student user (ROLE IS ALWAYS 'user', NEVER ADMIN)
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user'; // Hardcoded server-side: visitors can NEVER become admin here
            
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$name, $email, $hashed_password, $role]);
            
            $new_user_id = (int)$pdo->lastInsertId();

            // Create initial welcome notification for the student
            create_notification(
                $new_user_id,
                'Welcome to NoteShare! 🎓',
                'Your student account has been created successfully. Browse study materials, upload lecture notes, and join peer study circles.',
                'welcome',
                'dashboard.php'
            );

            // Per workflow requirement: redirect to User Login page with success message
            flash('success', 'Registration successful! Please log in with your email and password to access your student dashboard.');
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            error_log("Student registration insertion error: " . $e->getMessage());
            $errors[] = 'Failed to register account. Please try again.';
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
            <h3 class="fw-bold mb-1">Create Student Account</h3>
            <p class="text-muted small">Join NoteShare to exchange study notes, track study missions, and collaborate</p>
        </div>

        <?php if (!empty($errors) && empty($field_errors)): ?>
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
                <label for="name" class="form-label fw-semibold small">Full Name</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-person"></i>
                    </span>
                    <input type="text" class="form-control border-start-0 <?= isset($field_errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" placeholder="e.g. Alex Johnson" value="<?= htmlspecialchars($name) ?>" required autocomplete="name">
                </div>
                <?php if (isset($field_errors['name'])): ?>
                    <div class="invalid-feedback d-block small"><?= htmlspecialchars($field_errors['name']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Email Address -->
            <div class="mb-3">
                <label for="email" class="form-label fw-semibold small">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-envelope"></i>
                    </span>
                    <input type="email" class="form-control border-start-0 <?= isset($field_errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" placeholder="name@student.edu" value="<?= htmlspecialchars($email) ?>" required autocomplete="username">
                </div>
                <?php if (isset($field_errors['email'])): ?>
                    <div class="invalid-feedback d-block small"><?= htmlspecialchars($field_errors['email']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="mb-3">
                <label for="password" class="form-label fw-semibold small">Password (Min. 8 characters)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" class="form-control border-start-0 border-end-0 <?= isset($field_errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" placeholder="At least 8 characters" required autocomplete="new-password" minlength="8">
                    <button class="btn btn-outline-secondary border-start-0 password-toggle-btn" type="button" data-target="password" aria-label="Toggle password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <?php if (isset($field_errors['password'])): ?>
                    <div class="invalid-feedback d-block small"><?= htmlspecialchars($field_errors['password']) ?></div>
                <?php else: ?>
                    <div class="form-text small" style="font-size: 0.75rem;">Must contain at least 8 characters.</div>
                <?php endif; ?>
            </div>

            <!-- Confirm Password -->
            <div class="mb-3">
                <label for="confirm_password" class="form-label fw-semibold small">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="bi bi-shield-lock"></i>
                    </span>
                    <input type="password" class="form-control border-start-0 border-end-0 <?= isset($field_errors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required autocomplete="new-password">
                    <button class="btn btn-outline-secondary border-start-0 password-toggle-btn" type="button" data-target="confirm_password" aria-label="Toggle password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <?php if (isset($field_errors['confirm_password'])): ?>
                    <div class="invalid-feedback d-block small"><?= htmlspecialchars($field_errors['confirm_password']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Agree to Terms and Conditions -->
            <div class="mb-4 form-check">
                <input type="checkbox" class="form-check-input <?= isset($field_errors['agree_terms']) ? 'is-invalid' : '' ?>" id="agree_terms" name="agree_terms" value="1" <?= $agree_terms ? 'checked' : '' ?> required>
                <label class="form-check-label small text-muted" for="agree_terms">
                    I agree to the <a href="about.php" target="_blank" class="text-primary text-decoration-underline">Terms of Service</a>, Privacy Policy, and Academic Integrity Guidelines.
                </label>
                <?php if (isset($field_errors['agree_terms'])): ?>
                    <div class="invalid-feedback d-block small"><?= htmlspecialchars($field_errors['agree_terms']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary w-100 py-2 mb-3 fw-bold">
                <i class="bi bi-person-plus-fill me-1"></i> Register Student Account
            </button>

            <!-- Already have an account -->
            <div class="text-center small text-muted">
                Already have an account? <a href="login.php" class="fw-bold text-primary">Sign in here</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
