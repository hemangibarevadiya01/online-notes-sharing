<?php
$page_title = 'Note Details';
require_once __DIR__ . '/includes/auth.php';

$note_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($note_id <= 0) {
    flash('danger', 'Invalid note specified.');
    header('Location: notes.php');
    exit;
}

$current_user_id = is_logged_in() ? (int)$_SESSION['user_id'] : 0;

try {
    // 1. Increment view count
    $pdo->prepare("UPDATE notes SET views = views + 1 WHERE id = ?")->execute([$note_id]);

    // 2. Fetch note details
    $stmt = $pdo->prepare("SELECT 
                                n.*, 
                                c.name AS category_name,
                                u.id AS author_id,
                                u.name AS author_name,
                                u.email AS author_email,
                                u.profile_image AS author_image,
                                (SELECT COUNT(*) FROM notes WHERE user_id = u.id) AS author_notes_count
                           FROM notes n
                           JOIN categories c ON n.category_id = c.id
                           JOIN users u ON n.user_id = u.id
                           WHERE n.id = ?");
    $stmt->execute([$note_id]);
    $note = $stmt->fetch();

    if (!$note) {
        flash('danger', 'Study note not found.');
        header('Location: notes.php');
        exit;
    }

    // Check status permissions
    if ($note['status'] !== 'approved' && $note['user_id'] !== $current_user_id && !is_admin()) {
        flash('warning', 'This note is pending administrative approval.');
        header('Location: notes.php');
        exit;
    }

    // 3. Stats: Likes count & User liked status
    $stmt_likes = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE note_id = ?");
    $stmt_likes->execute([$note_id]);
    $total_likes = (int)$stmt_likes->fetchColumn();

    $user_liked = false;
    if ($current_user_id > 0) {
        $stmt_ul = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE note_id = ? AND user_id = ?");
        $stmt_ul->execute([$note_id, $current_user_id]);
        $user_liked = (bool)$stmt_ul->fetchColumn();
    }

    // 4. Stats: User saved status
    $user_saved = false;
    if ($current_user_id > 0) {
        $stmt_us = $pdo->prepare("SELECT COUNT(*) FROM saved_notes WHERE note_id = ? AND user_id = ?");
        $stmt_us->execute([$note_id, $current_user_id]);
        $user_saved = (bool)$stmt_us->fetchColumn();
    }

    // 5. Ratings statistics & User rating
    $stmt_ratings = $pdo->prepare("SELECT 
                                      COUNT(*) AS total_ratings,
                                      COALESCE(AVG(rating), 0) AS avg_score,
                                      SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS r5,
                                      SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS r4,
                                      SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS r3,
                                      SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS r2,
                                      SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS r1
                                   FROM ratings WHERE note_id = ?");
    $stmt_ratings->execute([$note_id]);
    $rating_stats = $stmt_ratings->fetch();

    $user_rating_val = 0;
    $user_review_val = '';
    if ($current_user_id > 0) {
        $stmt_ur = $pdo->prepare("SELECT rating, review FROM ratings WHERE note_id = ? AND user_id = ?");
        $stmt_ur->execute([$note_id, $current_user_id]);
        $existing_rating = $stmt_ur->fetch();
        if ($existing_rating) {
            $user_rating_val = (int)$existing_rating['rating'];
            $user_review_val = $existing_rating['review'] ?? '';
        }
    }

    // 6. Fetch Reviews & Comments
    $stmt_comments = $pdo->prepare("SELECT 
                                       c.*, 
                                       u.name AS user_name,
                                       u.profile_image AS user_avatar,
                                       u.role AS user_role
                                    FROM comments c
                                    JOIN users u ON c.user_id = u.id
                                    WHERE c.note_id = ?
                                    ORDER BY c.created_at DESC");
    $stmt_comments->execute([$note_id]);
    $comments = $stmt_comments->fetchAll();

    // 7. Fetch user study groups (for group share modal)
    $user_groups = [];
    if ($current_user_id > 0) {
        $stmt_ug = $pdo->prepare("SELECT g.id, g.name 
                                  FROM groups g 
                                  JOIN group_members gm ON g.id = gm.group_id 
                                  WHERE gm.user_id = ? 
                                  ORDER BY g.name ASC");
        $stmt_ug->execute([$current_user_id]);
        $user_groups = $stmt_ug->fetchAll();
    }

} catch (PDOException $e) {
    error_log("Note details error: " . $e->getMessage());
    flash('danger', 'Unable to retrieve note details.');
    header('Location: notes.php');
    exit;
}

$page_title = htmlspecialchars($note['title']);
$ext = strtolower(pathinfo($note['file_name'], PATHINFO_EXTENSION));
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="notes.php">Notes</a></li>
            <li class="breadcrumb-item"><a href="notes.php?category=<?= $note['category_id'] ?>"><?= htmlspecialchars($note['category_name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($note['title']) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Main Details Column -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4">
                <!-- Header Meta -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <span class="file-badge file-badge-<?= $ext ?: 'txt' ?> fs-6">
                        <i class="bi bi-file-earmark-text"></i> <?= strtoupper($ext) ?>
                    </span>
                    <span class="category-pill fs-6">
                        <i class="bi bi-tag-fill"></i> <?= htmlspecialchars($note['category_name']) ?>
                    </span>
                </div>

                <h2 class="fw-bold text-dark mb-2"><?= htmlspecialchars($note['title']) ?></h2>

                <div class="d-flex align-items-center gap-3 text-muted small mb-4 flex-wrap">
                    <span><i class="bi bi-book text-primary me-1"></i> Course: <strong><?= htmlspecialchars($note['subject']) ?></strong></span>
                    <span>&bull;</span>
                    <span><i class="bi bi-calendar3 me-1"></i> Uploaded <?= date('M d, Y', strtotime($note['created_at'])) ?></span>
                    <span>&bull;</span>
                    <span><i class="bi bi-eye text-info me-1"></i> <?= $note['views'] ?> views</span>
                    <span>&bull;</span>
                    <span><i class="bi bi-download text-success me-1"></i> <?= $note['downloads'] ?> downloads</span>
                </div>

                <!-- Description -->
                <h5 class="fw-bold mb-2">Description & Learning Highlights</h5>
                <div class="p-3 bg-light rounded-3 text-secondary mb-4" style="line-height: 1.7;">
                    <?= nl2br(htmlspecialchars($note['description'] ?: 'No detailed description provided by the author.')) ?>
                </div>

                <!-- Action Toolbar Buttons -->
                <div class="d-flex flex-wrap align-items-center gap-2 pt-3 border-top">
                    <!-- Preview Button -->
                    <a href="preview.php?id=<?= $note['id'] ?>" class="btn btn-purple">
                        <i class="bi bi-eye-fill me-1"></i> Preview Note
                    </a>

                    <!-- Download Button -->
                    <a href="download.php?id=<?= $note['id'] ?>" class="btn btn-primary">
                        <i class="bi bi-cloud-arrow-down-fill me-1"></i> Download (<?= format_bytes($note['file_size']) ?>)
                    </a>

                    <!-- Like Button (AJAX) -->
                    <button class="btn <?= $user_liked ? 'btn-danger' : 'btn-outline-danger' ?> btn-like-note" data-note-id="<?= $note['id'] ?>">
                        <i class="bi <?= $user_liked ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                        <span class="like-count"><?= $total_likes ?></span>
                    </button>

                    <!-- Save / Bookmark Button (AJAX) -->
                    <button class="btn <?= $user_saved ? 'btn-warning' : 'btn-outline-secondary' ?> btn-save-note" data-note-id="<?= $note['id'] ?>">
                        <i class="bi <?= $user_saved ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                        <span class="save-text"><?= $user_saved ? 'Saved' : 'Save' ?></span>
                    </button>

                    <!-- Share Button (Modal trigger) -->
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#shareModal">
                        <i class="bi bi-share-fill me-1"></i> Share
                    </button>

                    <!-- Report Button (Modal trigger) -->
                    <button type="button" class="btn btn-outline-danger ms-auto" data-bs-toggle="modal" data-bs-target="#reportModal" title="Report Note">
                        <i class="bi bi-flag-fill"></i>
                    </button>

                    <!-- Owner / Admin Edit & Delete Options -->
                    <?php if (($current_user_id === (int)$note['user_id']) || is_admin()): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                <li>
                                    <a class="dropdown-item" href="edit_note.php?id=<?= $note['id'] ?>">
                                        <i class="bi bi-pencil-square me-2 text-primary"></i> Edit Note
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item text-danger" href="delete_note.php?id=<?= $note['id'] ?>" onclick="return confirm('Are you sure you want to permanently delete this note?');">
                                        <i class="bi bi-trash-fill me-2"></i> Delete Note
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ratings & Reviews Section -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold mb-0">Student Ratings & Feedback</h5>
                    <div class="d-flex align-items-center gap-1">
                        <span class="fs-4 fw-bold text-dark"><?= number_format($rating_stats['avg_score'], 1) ?></span>
                        <div class="rating-stars fs-5">
                            <?php 
                                $avg = round($rating_stats['avg_score']);
                                for ($i = 1; $i <= 5; $i++): 
                            ?>
                                <i class="bi <?= ($i <= $avg) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="text-muted small ms-1">(<?= $rating_stats['total_ratings'] ?> ratings)</span>
                    </div>
                </div>

                <!-- Submit / Edit Rating Form -->
                <?php if (is_logged_in()): ?>
                    <form method="POST" action="rating.php" class="p-3 bg-light rounded-3 mb-4">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                        <input type="hidden" name="rating" value="<?= $user_rating_val ?>">

                        <label class="form-label small fw-bold mb-1">
                            <?= $user_rating_val > 0 ? 'Update Your Rating' : 'Leave a Rating & Review' ?>
                        </label>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="star-rating-picker" data-selected-rating="<?= $user_rating_val ?>">
                                <i class="bi bi-star star-interactive text-secondary me-1" data-value="1"></i>
                                <i class="bi bi-star star-interactive text-secondary me-1" data-value="2"></i>
                                <i class="bi bi-star star-interactive text-secondary me-1" data-value="3"></i>
                                <i class="bi bi-star star-interactive text-secondary me-1" data-value="4"></i>
                                <i class="bi bi-star star-interactive text-secondary me-1" data-value="5"></i>
                            </div>
                            <span class="small text-muted">Click stars to rate (1 to 5)</span>
                        </div>

                        <div class="mb-2">
                            <textarea name="review" class="form-control form-control-sm" rows="2" placeholder="Optional review: Did this note help you understand the concepts?"><?= htmlspecialchars($user_review_val) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm px-3">
                            <i class="bi bi-star-fill me-1 text-warning"></i> <?= $user_rating_val > 0 ? 'Update Rating' : 'Submit Rating' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-light border small text-center mb-4">
                        <a href="login.php?redirect=note.php?id=<?= $note['id'] ?>" class="fw-bold text-primary">Log in</a> to rate and leave a review on this note.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Comments Section -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <h5 class="fw-bold mb-3"><i class="bi bi-chat-dots-fill text-purple me-2"></i>Discussion & Questions (<?= count($comments) ?>)</h5>

                <?php if (is_logged_in()): ?>
                    <form method="POST" action="comments.php" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                        <input type="hidden" name="action" value="add">

                        <div class="mb-2">
                            <textarea name="comment" class="form-control" rows="3" placeholder="Ask a question about this note or leave a helpful comment..." required></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary btn-sm px-4">
                                <i class="bi bi-send-fill me-1"></i> Post Comment
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-light border small text-center mb-4">
                        <a href="login.php?redirect=note.php?id=<?= $note['id'] ?>" class="fw-bold text-primary">Log in</a> to join the academic discussion.
                    </div>
                <?php endif; ?>

                <!-- Comments List -->
                <div class="d-flex flex-column gap-3">
                    <?php if (empty($comments)): ?>
                        <div class="text-center py-4 text-muted small">
                            <i class="bi bi-chat-square-text fs-3 mb-2 d-block text-secondary"></i>
                            No comments posted yet. Be the first to start the discussion!
                        </div>
                    <?php else: ?>
                        <?php foreach ($comments as $c): ?>
                            <div class="p-3 bg-light rounded-3 border" id="comment-<?= $c['id'] ?>">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="uploads/avatars/default_avatar.svg" class="avatar-img" style="width: 32px; height: 32px;" alt="Avatar">
                                        <div>
                                            <span class="fw-bold small text-dark"><?= htmlspecialchars($c['user_name']) ?></span>
                                            <?php if ($c['user_role'] === 'admin'): ?>
                                                <span class="badge bg-danger-subtle text-danger border" style="font-size: 0.65rem;">ADMIN</span>
                                            <?php endif; ?>
                                            <span class="text-muted small ms-2">&bull; <?= time_ago($c['created_at']) ?></span>
                                        </div>
                                    </div>

                                    <!-- Comment Owner / Admin Delete Option -->
                                    <?php if ($current_user_id === (int)$c['user_id'] || is_admin()): ?>
                                        <form method="POST" action="comments.php" onsubmit="return confirm('Delete this comment?');">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" title="Delete Comment">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-secondary ps-4">
                                    <?= nl2br(htmlspecialchars($c['comment'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Column (Author Card, Document Info) -->
        <div class="col-lg-4">
            <!-- Author Card -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h6 class="fw-bold text-muted small text-uppercase mb-3">Published By</h6>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="uploads/avatars/default_avatar.svg" class="avatar-lg" style="width: 56px; height: 56px;" alt="Author Avatar">
                    <div>
                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($note['author_name']) ?></h6>
                        <span class="badge bg-primary-subtle text-primary border" style="font-size: 0.7rem;">Verified Student Contributor</span>
                    </div>
                </div>
                <div class="d-flex justify-content-between small text-muted pt-3 border-top">
                    <span>Total Notes Uploaded:</span>
                    <strong><?= $note['author_notes_count'] ?></strong>
                </div>
                <a href="profile.php?id=<?= $note['author_id'] ?>" class="btn btn-outline-primary btn-sm mt-3 w-100">
                    <i class="bi bi-person me-1"></i> View Author Profile
                </a>
            </div>

            <!-- File Properties Box -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h6 class="fw-bold text-muted small text-uppercase mb-3">Document Info</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <li class="d-flex justify-content-between">
                        <span class="text-muted">Filename:</span>
                        <strong class="text-truncate" style="max-width: 180px;"><?= htmlspecialchars($note['file_name']) ?></strong>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span class="text-muted">Format:</span>
                        <strong class="text-uppercase"><?= $ext ?></strong>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span class="text-muted">File Size:</span>
                        <strong><?= format_bytes($note['file_size']) ?></strong>
                    </li>
                    <li class="d-flex justify-content-between">
                        <span class="text-muted">Status:</span>
                        <span class="badge bg-success-subtle text-success border">Approved</span>
                    </li>
                </ul>
            </div>

            <!-- Study Group Quick Share -->
            <?php if (!empty($user_groups)): ?>
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <h6 class="fw-bold mb-2"><i class="bi bi-people-fill text-purple me-1"></i> Share to Study Group</h6>
                    <p class="small text-muted mb-3">Recommend this note to peers in your enrolled study circles.</p>
                    <button class="btn btn-purple btn-sm w-100" data-bs-toggle="modal" data-bs-target="#shareModal">
                        Choose Group &rarr;
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal 1: Share Note Modal -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="shareModalLabel"><i class="bi bi-share text-primary me-2"></i>Share Study Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="share_note.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="note_id" value="<?= $note['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label small">Share via Direct Link</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm" id="shareLinkInput" value="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/note.php?id=' . $note['id'] ?>" readonly>
                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('shareLinkInput').value); showToast('Link copied to clipboard!');">Copy</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="receiver_email" class="form-label small">Email to Student / Peer</label>
                        <input type="email" class="form-control form-control-sm" id="receiver_email" name="receiver_email" placeholder="peer@university.edu">
                    </div>

                    <?php if (!empty($user_groups)): ?>
                        <div class="mb-3">
                            <label for="group_id" class="form-label small">Or Share Inside Study Group</label>
                            <select name="group_id" id="group_id" class="form-select form-select-sm">
                                <option value="">-- Select Study Group --</option>
                                <?php foreach ($user_groups as $ug): ?>
                                    <option value="<?= $ug['id'] ?>"><?= htmlspecialchars($ug['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="mb-2">
                        <label for="share_message" class="form-label small">Personal Note (Optional)</label>
                        <textarea name="message" id="share_message" class="form-control form-control-sm" rows="2" placeholder="Hey! Check out these notes for our upcoming midterm."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Send Share Notification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Report Note Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-danger" id="reportModalLabel"><i class="bi bi-flag-fill me-2"></i>Report Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="report_note.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="note_id" value="<?= $note['id'] ?>">

                    <div class="mb-3">
                        <label for="report_reason" class="form-label small">Reason for Report <span class="text-danger">*</span></label>
                        <select name="reason" id="report_reason" class="form-select form-select-sm" required>
                            <option value="">-- Select a reason --</option>
                            <option value="Incorrect information">Incorrect information</option>
                            <option value="Duplicate note">Duplicate note</option>
                            <option value="Inappropriate content">Inappropriate content</option>
                            <option value="Copyright issue">Copyright issue</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="report_description" class="form-label small">Additional Details <span class="text-danger">*</span></label>
                        <textarea name="description" id="report_description" class="form-control form-control-sm" rows="3" placeholder="Please describe the issue in detail so our moderators can investigate..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
