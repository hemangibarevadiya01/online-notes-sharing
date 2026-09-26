<?php
$page_title = 'Edit Account Profile';
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

$errors = [];
$name = $user['name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Security verification failed.';
    }

    $name = trim($_POST['name'] ?? '');
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name)) {
        $errors[] = 'Your full name cannot be empty.';
    }

    // 1. Profile Image Upload Validation
    $profile_image_updated = false;
    $new_avatar_filename = $user['profile_image'] ?? 'default_avatar.svg';

    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar_file'];
        $orig_name = basename($file['name']);
        $file_size = (int)$file['size'];
        $tmp_name = $file['tmp_name'];

        $max_avatar_size = 2 * 1024 * 1024; // 2MB
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_exts, true)) {
            $errors[] = 'Invalid avatar image format. Allowed: JPG, PNG, WEBP.';
        } elseif ($file_size > $max_avatar_size) {
            $errors[] = 'Avatar image size exceeds 2MB limit.';
        } else {
            // MIME check
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_mime = finfo_file($finfo, $tmp_name);
            finfo_close($finfo);

            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($detected_mime, $allowed_mimes, true)) {
                $errors[] = 'Image security check failed: invalid image MIME type.';
            } else {
                $avatar_dir = __DIR__ . '/uploads/avatars/';
                if (!is_dir($avatar_dir)) mkdir($avatar_dir, 0755, true);

                $unique_avatar = 'avatar_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
                $dest_avatar = $avatar_dir . $unique_avatar;

                if (move_uploaded_file($tmp_name, $dest_avatar)) {
                    $profile_image_updated = true;
                    $new_avatar_filename = $unique_avatar;
                } else {
                    $errors[] = 'Failed to save uploaded avatar.';
                }
            }
        }
    }

    // 2. Password Change Logic
    $update_password = false;
    if (!empty($new_password)) {
        if (empty($old_password)) {
            $errors[] = 'Current password is required to change password.';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match.';
        } else {
            $stmt_pwd = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt_pwd->execute([$user['id']]);
            $current_hash = $stmt_pwd->fetchColumn();

            if (!password_verify($old_password, $current_hash)) {
                $errors[] = 'Current password entered is incorrect.';
            } else {
                $update_password = true;
            }
        }
    }

    // 3. Save updates to database
    if (empty($errors)) {
        try {
            $sql_parts = ["name = ?"];
            $params = [$name];

            if ($profile_image_updated) {
                $sql_parts[] = "profile_image = ?";
                $params[] = $new_avatar_filename;
            }

            if ($update_password) {
                $sql_parts[] = "password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }

            $params[] = $user['id'];
            $sql = "UPDATE users SET " . implode(', ', $sql_parts) . " WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $_SESSION['user_name'] = $name;
            flash('success', 'Profile and account preferences updated successfully.');
            header('Location: profile.php');
            exit;
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            $errors[] = 'Database error occurred while updating profile.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
$avatar_src = (!empty($user['profile_image']) && file_exists(__DIR__ . '/uploads/avatars/' . $user['profile_image'])) 
    ? 'uploads/avatars/' . htmlspecialchars($user['profile_image']) 
    : 'uploads/avatars/default_avatar.svg';
?>

<div class="container py-4" style="max-width: 650px;">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="profile.php">Profile</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Profile</li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
            <div class="brand-icon" style="width: 48px; height: 48px; font-size: 1.5rem;">
                <i class="bi bi-person-gear"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-0">Edit Profile</h3>
                <p class="text-muted small mb-0">Manage your avatar, name, and security password</p>
            </div>
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

        <form method="POST" action="edit_profile.php" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Current Avatar Preview & Upload -->
            <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-light rounded-3 border">
                <img src="<?= $avatar_src ?>" class="avatar-lg" style="width: 72px; height: 72px;" alt="Avatar">
                <div>
                    <label class="form-label fw-bold mb-1">Update Profile Picture</label>
                    <input type="file" name="avatar_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp">
                    <div class="form-text small">Max 2MB. Formats: JPG, PNG, WEBP.</div>
                </div>
            </div>

            <!-- Email (Readonly) -->
            <div class="mb-3">
                <label for="email_static" class="form-label">Email Address</label>
                <input type="email" class="form-control bg-light" id="email_static" value="<?= htmlspecialchars($user['email']) ?>" readonly>
            </div>

            <!-- Name -->
            <div class="mb-4">
                <label for="prof_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="prof_name" name="name" value="<?= htmlspecialchars($name) ?>" required>
            </div>

            <hr class="my-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock-fill text-purple me-1"></i> Change Password</h5>
            <p class="text-muted small mb-3">Leave blank if you do not want to change your password.</p>

            <div class="mb-3">
                <label for="old_pwd" class="form-label small">Current Password</label>
                <input type="password" class="form-control" id="old_pwd" name="old_password" placeholder="Enter current password">
            </div>

            <div class="mb-3">
                <label for="new_pwd" class="form-label small">New Password</label>
                <input type="password" class="form-control" id="new_pwd" name="new_password" placeholder="At least 6 characters">
            </div>

            <div class="mb-4">
                <label for="confirm_pwd" class="form-label small">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_pwd" name="confirm_password" placeholder="Repeat new password">
            </div>

            <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                <a href="profile.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check2 me-1"></i> Save Profile
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
