<?php
$page_title = 'Create Study Group';
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

$errors = [];
$name = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Security token expired. Please try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $errors[] = 'Study group name is required.';
    } elseif (strlen($name) < 3) {
        $errors[] = 'Study group name must be at least 3 characters.';
    }

    if (empty($errors)) {
        try {
            // 1. Insert into groups
            $stmt = $pdo->prepare("INSERT INTO groups (name, description, created_by, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$name, $description, $user['id']]);
            $group_id = (int)$pdo->lastInsertId();

            // 2. Insert creator as first member
            $stmt_m = $pdo->prepare("INSERT INTO group_members (group_id, user_id, joined_at) VALUES (?, ?, NOW())");
            $stmt_m->execute([$group_id, $user['id']]);

            flash('success', 'Study Group "' . htmlspecialchars($name) . '" created successfully! You are now the group administrator.');
            header('Location: group.php?id=' . $group_id);
            exit;

        } catch (PDOException $e) {
            error_log("Create group error: " . $e->getMessage());
            $errors[] = 'Database error occurred while creating study group.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4" style="max-width: 650px;">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="groups.php">Study Groups</a></li>
            <li class="breadcrumb-item active" aria-current="page">Create Group</li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
            <div class="brand-icon" style="width: 48px; height: 48px; font-size: 1.5rem;">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-0">Start a Study Group</h3>
                <p class="text-muted small mb-0">Create an academic space for your classmates or study partners</p>
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

        <form method="POST" action="create_group.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="mb-3">
                <label for="groupName" class="form-label">Study Group Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="groupName" name="name" placeholder="e.g. Organic Chemistry Study Lounge, Calculus Exam Prep" value="<?= htmlspecialchars($name) ?>" required maxlength="150">
                <div class="form-text small">Give your circle a clear and recognizable academic title.</div>
            </div>

            <div class="mb-4">
                <label for="groupDesc" class="form-label">Group Focus / Objectives</label>
                <textarea class="form-control" id="groupDesc" name="description" rows="4" placeholder="Briefly describe the syllabus, subjects covered, meeting times, or collaboration goals..."><?= htmlspecialchars($description) ?></textarea>
            </div>

            <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                <a href="groups.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check2-circle me-1"></i> Create Group
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
