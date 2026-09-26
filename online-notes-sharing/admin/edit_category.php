<?php
$admin_title = 'Edit Category';
require_once __DIR__ . '/includes/header.php';

$cat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($cat_id <= 0) {
    flash('danger', 'Invalid category.');
    header('Location: categories.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$cat_id]);
    $cat = $stmt->fetch();

    if (!$cat) {
        flash('danger', 'Category not found.');
        header('Location: categories.php');
        exit;
    }
} catch (PDOException $e) {
    flash('danger', 'Database error.');
    header('Location: categories.php');
    exit;
}

$errors = [];
$name = $cat['name'];
$description = $cat['description'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid security token.';
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $errors[] = 'Category name cannot be empty.';
    }

    if (empty($errors)) {
        try {
            $stmt_check = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
            $stmt_check->execute([$name, $cat_id]);
            if ($stmt_check->fetch()) {
                $errors[] = 'Another category with this name already exists.';
            } else {
                $stmt_up = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt_up->execute([$name, $description, $cat_id]);

                flash('success', 'Category updated successfully.');
                header('Location: categories.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Edit category error: " . $e->getMessage());
            $errors[] = 'Database error while saving category.';
        }
    }
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mx-auto" style="max-width: 600px;">
    <h4 class="fw-bold mb-4 pb-2 border-bottom"><i class="bi bi-pencil-square text-primary me-2"></i>Modify Category</h4>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="edit_category.php?id=<?= $cat_id ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="mb-3">
            <label class="form-label">Category Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required>
        </div>

        <div class="mb-4">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
            <a href="categories.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">Update Category</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
