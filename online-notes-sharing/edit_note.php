<?php
$page_title = 'Edit Study Note';
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

$note_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0);

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

    if ((int)$note['user_id'] !== (int)$user['id'] && !is_admin()) {
        flash('danger', 'You do not have permission to edit this note.');
        header('Location: note.php?id=' . $note_id);
        exit;
    }

    $stmt_cats = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt_cats->fetchAll();

} catch (PDOException $e) {
    error_log("Edit note fetch error: " . $e->getMessage());
    flash('danger', 'Unable to retrieve note.');
    header('Location: notes.php');
    exit;
}

$errors = [];
$title = $note['title'];
$subject = $note['subject'];
$category_id = $note['category_id'];
$description = $note['description'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Security token expired.';
    }

    $title = trim($_POST['title'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) {
        $errors[] = 'Title cannot be empty.';
    }

    if (empty($subject)) {
        $errors[] = 'Subject cannot be empty.';
    }

    if ($category_id <= 0) {
        $errors[] = 'Please select a valid academic category.';
    }

    // Optional replacement file
    $file_update = false;
    $new_file_name = $note['file_name'];
    $new_file_path = $note['file_path'];
    $new_file_type = $note['file_type'];
    $new_file_size = $note['file_size'];

    if (isset($_FILES['replacement_file']) && $_FILES['replacement_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['replacement_file'];
        $original_name = basename($file['name']);
        $file_size = (int)$file['size'];
        $tmp_path = $file['tmp_name'];

        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip'];

        if (!in_array($ext, $allowed, true)) {
            $errors[] = 'Invalid replacement file type.';
        } elseif ($file_size > 25 * 1024 * 1024) {
            $errors[] = 'Replacement file exceeds 25MB limit.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmp_path);
            finfo_close($finfo);

            $unique_name = 'note_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
            $dest = __DIR__ . '/uploads/notes/' . $unique_name;

            if (move_uploaded_file($tmp_path, $dest)) {
                $file_update = true;
                $new_file_name = $original_name;
                $new_file_path = 'uploads/notes/' . $unique_name;
                $new_file_type = $mime;
                $new_file_size = $file_size;

                // Delete old file if present
                $old_file = __DIR__ . '/' . $note['file_path'];
                if (file_exists($old_file)) {
                    @unlink($old_file);
                }
            } else {
                $errors[] = 'Failed to upload replacement file.';
            }
        }
    }

    if (empty($errors)) {
        try {
            $stmt_up = $pdo->prepare("UPDATE notes SET 
                                        title = ?, 
                                        subject = ?, 
                                        category_id = ?, 
                                        description = ?, 
                                        file_name = ?, 
                                        file_path = ?, 
                                        file_type = ?, 
                                        file_size = ?, 
                                        updated_at = NOW() 
                                      WHERE id = ?");
            $stmt_up->execute([
                $title, $subject, $category_id, $description,
                $new_file_name, $new_file_path, $new_file_type, $new_file_size,
                $note_id
            ]);

            flash('success', 'Note updated successfully.');
            header('Location: note.php?id=' . $note_id);
            exit;
        } catch (PDOException $e) {
            error_log("Note update error: " . $e->getMessage());
            $errors[] = 'Database error while saving note updates.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4" style="max-width: 750px;">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="note.php?id=<?= $note['id'] ?>"><?= htmlspecialchars($note['title']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Note</li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
            <div class="brand-icon" style="width: 48px; height: 48px; font-size: 1.5rem;">
                <i class="bi bi-pencil-square"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-0">Edit Note Details</h3>
                <p class="text-muted small mb-0">Modify information or replace the attached study material</p>
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

        <form method="POST" action="edit_note.php?id=<?= $note_id ?>" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="note_id" value="<?= $note_id ?>">

            <div class="mb-3">
                <label for="editTitle" class="form-label">Note Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="editTitle" name="title" value="<?= htmlspecialchars($title) ?>" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="editSubject" class="form-label">Subject / Course <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="editSubject" name="subject" value="<?= htmlspecialchars($subject) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="editCat" class="form-label">Category <span class="text-danger">*</span></label>
                    <select class="form-select" id="editCat" name="category_id" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ((int)$category_id === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="editDesc" class="form-label">Description</label>
                <textarea class="form-control" id="editDesc" name="description" rows="4"><?= htmlspecialchars($description) ?></textarea>
            </div>

            <div class="mb-4 p-3 bg-light rounded-3 border">
                <label class="form-label fw-bold">Current Document</label>
                <div class="d-flex align-items-center justify-content-between mb-2 small text-muted">
                    <span><i class="bi bi-file-earmark-check text-primary me-1"></i> <?= htmlspecialchars($note['file_name']) ?> (<?= format_bytes($note['file_size']) ?>)</span>
                </div>
                <label for="replacement_file" class="form-label small text-secondary">Upload Replacement File (Optional)</label>
                <input type="file" class="form-control form-control-sm" id="replacement_file" name="replacement_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip">
            </div>

            <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                <a href="note.php?id=<?= $note['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check2 me-1"></i> Update Note
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
