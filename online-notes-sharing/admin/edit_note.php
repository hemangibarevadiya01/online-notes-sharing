<?php
$admin_title = 'Edit Study Note';
require_once __DIR__ . '/includes/header.php';

$note_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($note_id <= 0) {
    flash('danger', 'Invalid note specified.');
    header('Location: notes.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ?");
    $stmt->execute([$note_id]);
    $note = $stmt->fetch();

    if (!$note) {
        flash('danger', 'Note not found.');
        header('Location: notes.php');
        exit;
    }

    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    flash('danger', 'Database error.');
    header('Location: notes.php');
    exit;
}

$errors = [];
$title = $note['title'];
$subject = $note['subject'];
$category_id = $note['category_id'];
$status = $note['status'];
$description = $note['description'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Security token invalid.';
    }

    $title = trim($_POST['title'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $status = $_POST['status'] ?? 'approved';
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) $errors[] = 'Title cannot be empty.';
    if (empty($subject)) $errors[] = 'Subject cannot be empty.';

    if (empty($errors)) {
        try {
            $stmt_up = $pdo->prepare("UPDATE notes SET title = ?, subject = ?, category_id = ?, status = ?, description = ? WHERE id = ?");
            $stmt_up->execute([$title, $subject, $category_id, $status, $description, $note_id]);

            flash('success', 'Note updated successfully.');
            header('Location: notes.php');
            exit;
        } catch (PDOException $e) {
            error_log("Admin update note error: " . $e->getMessage());
            $errors[] = 'Failed to update note.';
        }
    }
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mx-auto" style="max-width: 700px;">
    <h4 class="fw-bold mb-4 pb-2 border-bottom"><i class="bi bi-pencil-square text-primary me-2"></i>Modify Note Details</h4>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="edit_note.php?id=<?= $note_id ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="mb-3">
            <label class="form-label">Note Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($title) ?>" required>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Subject / Course <span class="text-danger">*</span></label>
                <input type="text" name="subject" class="form-control" value="<?= htmlspecialchars($subject) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-select">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ((int)$category_id === (int)$cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Moderation Status</label>
            <select name="status" class="form-select">
                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved (Publicly Visible)</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected (Hidden)</option>
            </select>
        </div>

        <div class="mb-4">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($description) ?></textarea>
        </div>

        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
            <a href="notes.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">Update Note</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
