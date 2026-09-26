<?php
$admin_title = 'Edit Study Group';
require_once __DIR__ . '/includes/header.php';

$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($group_id <= 0) {
    flash('danger', 'Invalid group specified.');
    header('Location: groups.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();

    if (!$group) {
        flash('danger', 'Study group not found.');
        header('Location: groups.php');
        exit;
    }
} catch (PDOException $e) {
    flash('danger', 'Database error.');
    header('Location: groups.php');
    exit;
}

$errors = [];
$name = $group['name'];
$description = $group['description'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid security token.';
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $errors[] = 'Group name is required.';
    }

    if (empty($errors)) {
        try {
            $stmt_up = $pdo->prepare("UPDATE groups SET name = ?, description = ? WHERE id = ?");
            $stmt_up->execute([$name, $description, $group_id]);

            flash('success', 'Study group updated successfully.');
            header('Location: groups.php');
            exit;
        } catch (PDOException $e) {
            error_log("Update group error: " . $e->getMessage());
            $errors[] = 'Failed to update study group.';
        }
    }
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mx-auto" style="max-width: 650px;">
    <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
        <div class="brand-icon" style="width: 48px; height: 48px;">
            <i class="bi bi-pencil-square fs-4"></i>
        </div>
        <div>
            <h4 class="fw-bold mb-0">Modify Study Group</h4>
            <p class="text-muted small mb-0">Edit details for <?= htmlspecialchars($group['name']) ?></p>
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

    <form method="POST" action="edit_group.php?id=<?= $group_id ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="mb-3">
            <label class="form-label">Study Group Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" required>
        </div>

        <div class="mb-4">
            <label class="form-label">Description / Syllabus</label>
            <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
            <a href="groups.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">Update Group</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
