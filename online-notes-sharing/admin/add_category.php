<?php
$admin_title = 'Add New Category';
require_once __DIR__ . '/includes/header.php';

$errors = [];
$name = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid security token.';
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $errors[] = 'Category name is required.';
    }

    if (empty($errors)) {
        try {
            $stmt_check = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $stmt_check->execute([$name]);
            if ($stmt_check->fetch()) {
                $errors[] = 'A category with this name already exists.';
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO categories (name, description, created_at) VALUES (?, ?, NOW())");
                $stmt_ins->execute([$name, $description]);

                flash('success', 'Academic category "' . htmlspecialchars($name) . '" created successfully.');
                header('Location: categories.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Add category error: " . $e->getMessage());
            $errors[] = 'Database error while saving category.';
        }
    }
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mx-auto" style="max-width: 600px;">
    <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
        <div class="brand-icon" style="width: 48px; height: 48px;">
            <i class="bi bi-folder-plus fs-4"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0">Create Academic Category</h4>
            <p class="text-muted small mb-0">Add a subject classification for uploaded notes</p>
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

    <form method="POST" action="add_category.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="mb-3">
            <label class="form-label">Category Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Political Science, Data Science" value="<?= htmlspecialchars($name) ?>" required>
        </div>

        <div class="mb-4">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4" placeholder="Brief outline of disciplines covered..."><?= htmlspecialchars($description) ?></textarea>
        </div>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
            <a href="categories.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">Create Category</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
