<?php
/**
 * Online Notes Sharing Platform - In-Browser Note Previewer
 */
require_once __DIR__ . '/includes/auth.php';

$note_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = $_GET['mode'] ?? 'page'; // 'page' (UI wrapper) or 'stream' (direct inline content)

if ($note_id <= 0) {
    flash('danger', 'Invalid note specified.');
    header('Location: notes.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT n.*, c.name AS category_name, u.name AS author_name 
                           FROM notes n 
                           JOIN categories c ON n.category_id = c.id 
                           JOIN users u ON n.user_id = u.id 
                           WHERE n.id = ?");
    $stmt->execute([$note_id]);
    $note = $stmt->fetch();

    if (!$note) {
        flash('danger', 'Note not found.');
        header('Location: notes.php');
        exit;
    }

    $base_upload_dir = realpath(__DIR__ . '/uploads/notes');
    $target_file = realpath(__DIR__ . '/' . $note['file_path']);

    if (!$target_file || !file_exists($target_file) || !str_starts_with($target_file, $base_upload_dir)) {
        flash('danger', 'Note document file is not accessible on the server.');
        header('Location: note.php?id=' . $note_id);
        exit;
    }

    $ext = strtolower(pathinfo($note['file_name'], PATHINFO_EXTENSION));

    // Direct stream mode (e.g. for iframe embedding)
    if ($mode === 'stream') {
        // Increment view count
        $stmt_v = $pdo->prepare("UPDATE notes SET views = views + 1 WHERE id = ?");
        $stmt_v->execute([$note_id]);

        if (ob_get_level()) ob_end_clean();

        if ($ext === 'pdf') {
            header('Content-Type: application/pdf');
        } else {
            header('Content-Type: text/plain; charset=utf-8');
        }
        header('Content-Disposition: inline; filename="' . basename($note['file_name']) . '"');
        header('Content-Length: ' . filesize($target_file));
        readfile($target_file);
        exit;
    }

} catch (PDOException $e) {
    error_log("Preview error: " . $e->getMessage());
    flash('danger', 'Unable to load preview.');
    header('Location: notes.php');
    exit;
}

$page_title = 'Preview: ' . htmlspecialchars($note['title']);
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-lg-5 py-4">
    <!-- Preview Header Control Bar -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3 bg-white p-3 rounded-4 shadow-sm border">
        <div class="d-flex align-items-center gap-3">
            <a href="note.php?id=<?= $note['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Back to Details">
                <i class="bi bi-arrow-left"></i> Note Details
            </a>
            <div>
                <h5 class="fw-bold mb-0 text-truncate" style="max-width: 500px;"><?= htmlspecialchars($note['title']) ?></h5>
                <span class="small text-muted">
                    Subject: <strong><?= htmlspecialchars($note['subject']) ?></strong> &bull; 
                    Author: <?= htmlspecialchars($note['author_name']) ?> &bull; 
                    Size: <?= format_bytes($note['file_size']) ?>
                </span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="preview.php?id=<?= $note['id'] ?>&mode=stream" target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-fullscreen me-1"></i> Fullscreen
            </a>
            <a href="download.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-download me-1"></i> Download File
            </a>
        </div>
    </div>

    <!-- Viewer Container -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-dark">
        <?php if ($ext === 'pdf'): ?>
            <div class="preview-container">
                <iframe src="preview.php?id=<?= $note['id'] ?>&mode=stream#toolbar=1" title="PDF Document Preview" style="width: 100%; height: 82vh; border: none;"></iframe>
            </div>
        <?php else: ?>
            <!-- Fallback for Non-PDF Office/Archive Files -->
            <div class="p-5 text-center text-white my-5">
                <i class="bi bi-file-earmark-text text-warning" style="font-size: 4rem;"></i>
                <h4 class="fw-bold mt-3">Document Preview Not Available</h4>
                <p class="text-white-50 small mb-4" style="max-width: 520px; margin: 0 auto;">
                    This document is saved as a <strong>.<?= strtoupper($ext) ?></strong> file. Direct browser rendering is optimized for PDF formats. You can download the file to inspect the contents.
                </p>
                <a href="download.php?id=<?= $note['id'] ?>" class="btn btn-primary btn-lg px-4">
                    <i class="bi bi-cloud-arrow-down-fill me-2"></i> Download <?= strtoupper($ext) ?> (<?= format_bytes($note['file_size']) ?>)
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
