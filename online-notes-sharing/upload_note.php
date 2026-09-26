<?php
$page_title = 'Upload Study Note';
require_once __DIR__ . '/includes/auth.php';

// Authentication guard
require_login();
$user = current_user();

// Fetch categories for select menu
$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to load categories: " . $e->getMessage());
}

$errors = [];
$title = '';
$subject = '';
$category_id = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    $title = trim($_POST['title'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    // Form field validation
    if (empty($title)) {
        $errors[] = 'Note title is required.';
    } elseif (strlen($title) < 3) {
        $errors[] = 'Note title must be at least 3 characters.';
    }

    if (empty($subject)) {
        $errors[] = 'Subject name is required.';
    }

    if ($category_id <= 0) {
        $errors[] = 'Please select a valid academic category.';
    }

    // File validation
    if (!isset($_FILES['note_file']) || $_FILES['note_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose a valid file to upload.';
    } else {
        $file = $_FILES['note_file'];
        $original_name = basename($file['name']);
        $file_size = (int)$file['size'];
        $tmp_path = $file['tmp_name'];

        $max_size = 25 * 1024 * 1024; // 25 MB
        if ($file_size > $max_size) {
            $errors[] = 'File size exceeds maximum permitted limit of 25MB.';
        }

        // Extension check
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip'];

        if (!in_array($ext, $allowed_extensions, true)) {
            $errors[] = 'Invalid file format (' . htmlspecialchars($ext) . '). Allowed: PDF, DOC, DOCX, PPT, PPTX, ZIP.';
        }

        // Prohibited dangerous extensions
        $dangerous_extensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'cgi', 'pl', 'exe', 'sh', 'bat', 'cmd', 'js', 'jar'];
        if (in_array($ext, $dangerous_extensions, true)) {
            $errors[] = 'Security exception: Executable or script files are strictly prohibited.';
        }

        // MIME type inspection using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected_mime = finfo_file($finfo, $tmp_path);
        finfo_close($finfo);

        $valid_mimes = [
            'pdf'  => ['application/pdf', 'application/x-pdf', 'application/acrobat'],
            'doc'  => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
            'ppt'  => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
            'zip'  => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/octet-stream']
        ];

        if (!isset($valid_mimes[$ext]) || !in_array($detected_mime, $valid_mimes[$ext], true)) {
            // For docx and pptx, if MIME is zip or octet-stream it's standard; otherwise block
            if (!in_array($detected_mime, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'])) {
                $errors[] = 'File validation failed: MIME type (' . htmlspecialchars($detected_mime) . ') does not match allowed types.';
            }
        }
    }

    // Process file move and database insertion
    if (empty($errors)) {
        $upload_dir = __DIR__ . '/uploads/notes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate unique safe file name
        $unique_name = 'note_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
        $destination = $upload_dir . $unique_name;
        $relative_path = 'uploads/notes/' . $unique_name;

        if (move_uploaded_file($tmp_path, $destination)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO notes 
                    (user_id, category_id, title, description, subject, file_name, file_path, file_type, file_size, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
                
                $stmt->execute([
                    $user['id'],
                    $category_id,
                    $title,
                    $description,
                    $subject,
                    $original_name,
                    $relative_path,
                    $detected_mime,
                    $file_size
                ]);

                $note_id = $pdo->lastInsertId();

                // Generate system notification
                create_notification(
                    (int)$user['id'],
                    'Note Uploaded Successfully',
                    'Your study note "' . htmlspecialchars($title) . '" has been published and is now available.',
                    'note_upload',
                    (int)$note_id
                );

                flash('success', 'Study note "' . htmlspecialchars($title) . '" uploaded successfully!');
                header('Location: dashboard.php');
                exit;
            } catch (PDOException $e) {
                error_log("Note upload DB error: " . $e->getMessage());
                // Remove uploaded file if DB insert fails
                if (file_exists($destination)) {
                    unlink($destination);
                }
                $errors[] = 'A database error occurred while saving the note record.';
            }
        } else {
            $errors[] = 'Failed to move uploaded file into storage directory.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4" style="max-width: 800px;">
    <!-- Page Header Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Upload Note</li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
            <div class="brand-icon" style="width: 50px; height: 50px; font-size: 1.5rem;">
                <i class="bi bi-cloud-arrow-up-fill"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-0">Upload Study Material</h3>
                <p class="text-muted small mb-0">Share your verified academic summaries and exam notes with students</p>
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

        <form method="POST" action="upload_note.php" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Title -->
            <div class="mb-3">
                <label for="title" class="form-label">Note Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title" placeholder="e.g. Operating Systems & Process Scheduling Cheatsheet" value="<?= htmlspecialchars($title) ?>" required maxlength="255">
                <div class="form-text small">Give your note a concise, descriptive title.</div>
            </div>

            <!-- Subject & Category Grid -->
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="subject" class="form-label">Subject / Course Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="subject" name="subject" placeholder="e.g. Computer Science 301, Calculus III" value="<?= htmlspecialchars($subject) ?>" required maxlength="150">
                </div>
                <div class="col-md-6">
                    <label for="category_id" class="form-label">Academic Category <span class="text-danger">*</span></label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">-- Choose Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ((int)$category_id === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Description -->
            <div class="mb-3">
                <label for="description" class="form-label">Detailed Description</label>
                <textarea class="form-control" id="description" name="description" rows="4" placeholder="Summarize key topics covered, lecture references, textbook chapters, or exam tips..."><?= htmlspecialchars($description) ?></textarea>
            </div>

            <!-- File Upload -->
            <div class="mb-4">
                <label class="form-label">Attach Note Document <span class="text-danger">*</span></label>
                <div class="p-4 border border-2 border-dashed rounded-3 text-center bg-light">
                    <i class="bi bi-file-earmark-arrow-up fs-1 text-primary mb-2 d-block"></i>
                    <p class="mb-2 fw-semibold">Click to select or drag and drop your document</p>
                    <p class="small text-muted mb-3">Allowed extensions: <strong>PDF, DOC, DOCX, PPT, PPTX, ZIP</strong> (Max file size: 25MB)</p>
                    
                    <input type="file" class="form-control d-none" id="note_file" name="note_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip" required>
                    
                    <label for="note_file" class="btn btn-outline-primary btn-sm px-4">
                        <i class="bi bi-folder2-open me-1"></i> Browse Files
                    </label>
                    <div id="selected_file_name" class="mt-2 text-success small fw-bold"></div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-check2-circle me-1"></i> Publish Note
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
