<?php
$page_title = 'Search Study Notes';
require_once __DIR__ . '/includes/auth.php';

require_login('Please log in to search study notes.');
$current_user_id = (int)$_SESSION['user_id'];

$keyword = trim($_GET['q'] ?? '');
$subject = trim($_GET['subject'] ?? '');
$author = trim($_GET['author'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$file_type = trim($_GET['file_type'] ?? '');
$sort = $_GET['sort'] ?? 'latest';

// Fetch all categories for filter dropdown
$categories = [];
try {
    $stmt_cats = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt_cats->fetchAll();
} catch (PDOException $e) {}

// Construct search SQL query with prepared statements
$where_clauses = ["n.status = 'approved'"];
$params = [':uid1' => $current_user_id, ':uid2' => $current_user_id];

if (!empty($keyword)) {
    $where_clauses[] = "(n.title LIKE :kw1 OR n.description LIKE :kw2 OR n.subject LIKE :kw3 OR u.name LIKE :kw4 OR n.keywords LIKE :kw5)";
    $params[':kw1'] = "%{$keyword}%";
    $params[':kw2'] = "%{$keyword}%";
    $params[':kw3'] = "%{$keyword}%";
    $params[':kw4'] = "%{$keyword}%";
    $params[':kw5'] = "%{$keyword}%";
}

if (!empty($subject)) {
    $where_clauses[] = "n.subject LIKE :sub";
    $params[':sub'] = "%{$subject}%";
}

if (!empty($author)) {
    $where_clauses[] = "u.name LIKE :auth";
    $params[':auth'] = "%{$author}%";
}

if ($category_id > 0) {
    $where_clauses[] = "n.category_id = :cat_id";
    $params[':cat_id'] = $category_id;
}

if (!empty($file_type)) {
    $where_clauses[] = "n.file_name LIKE :ft";
    $params[':ft'] = "%." . $file_type;
}

$where_sql = implode(' AND ', $where_clauses);

$order_by = match ($sort) {
    'downloads' => 'n.downloads DESC, n.created_at DESC',
    'likes'     => 'likes_count DESC, n.created_at DESC',
    'rating'    => 'avg_rating DESC, ratings_count DESC',
    default     => 'n.created_at DESC'
};

$results = [];
try {
    $sql = "SELECT 
                n.id, n.title, n.subject, n.description, n.file_name, n.file_path, n.file_type, n.file_size,
                n.downloads, n.views, n.status, n.created_at,
                u.id AS author_id, u.name AS author_name, u.profile_image AS author_image,
                c.id AS category_id, c.name AS category_name,
                COUNT(DISTINCT l.id) AS likes_count,
                COALESCE(AVG(r.rating), 0) AS avg_rating,
                COUNT(DISTINCT r.id) AS ratings_count,
                (SELECT COUNT(*) FROM saved_notes sn WHERE sn.note_id = n.id AND sn.user_id = :uid1) AS is_saved,
                (SELECT COUNT(*) FROM likes lk WHERE lk.note_id = n.id AND lk.user_id = :uid2) AS is_liked
            FROM notes n
            JOIN users u ON n.user_id = u.id
            JOIN categories c ON n.category_id = c.id
            LEFT JOIN likes l ON n.id = l.note_id
            LEFT JOIN ratings r ON n.id = r.note_id
            WHERE {$where_sql}
            GROUP BY n.id
            ORDER BY {$order_by}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Search execution error: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Search Bar Card -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
        <h4 class="fw-bold mb-3"><i class="bi bi-search text-primary me-2"></i>Advanced Notes Search</h4>
        <form method="GET" action="search.php" class="row g-3">
            <div class="col-lg-6 col-md-12">
                <label class="form-label small">Keyword (Title or Description)</label>
                <input type="text" name="q" class="form-control" placeholder="Search keywords..." value="<?= htmlspecialchars($keyword) ?>">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label small">Subject</label>
                <input type="text" name="subject" class="form-control" placeholder="e.g. Calculus, Physics" value="<?= htmlspecialchars($subject) ?>">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label small">Author</label>
                <input type="text" name="author" class="form-control" placeholder="Author name" value="<?= htmlspecialchars($author) ?>">
            </div>
            <div class="col-lg-4 col-md-6">
                <label class="form-label small">Academic Category</label>
                <select name="category" class="form-select">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($category_id === (int)$cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-4 col-md-6">
                <label class="form-label small">Document Format</label>
                <select name="file_type" class="form-select">
                    <option value="">All Formats</option>
                    <option value="pdf" <?= ($file_type === 'pdf') ? 'selected' : '' ?>>PDF Document (.pdf)</option>
                    <option value="docx" <?= ($file_type === 'docx') ? 'selected' : '' ?>>Word Document (.docx)</option>
                    <option value="pptx" <?= ($file_type === 'pptx') ? 'selected' : '' ?>>PowerPoint Presentation (.pptx)</option>
                    <option value="zip" <?= ($file_type === 'zip') ? 'selected' : '' ?>>Zip Archive (.zip)</option>
                </select>
            </div>
            <div class="col-lg-4 col-md-12">
                <label class="form-label small">Sort Order</label>
                <select name="sort" class="form-select">
                    <option value="latest" <?= ($sort === 'latest') ? 'selected' : '' ?>>Latest Uploads</option>
                    <option value="downloads" <?= ($sort === 'downloads') ? 'selected' : '' ?>>Most Downloaded</option>
                    <option value="likes" <?= ($sort === 'likes') ? 'selected' : '' ?>>Most Liked</option>
                    <option value="rating" <?= ($sort === 'rating') ? 'selected' : '' ?>>Highest Rated</option>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                <a href="search.php" class="btn btn-outline-secondary btn-sm px-3">Reset Search</a>
                <button type="submit" class="btn btn-primary btn-sm px-4"><i class="bi bi-funnel-fill me-1"></i> Apply Search Filter</button>
            </div>
        </form>
    </div>

    <!-- Results Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-bold mb-0">Search Results (<?= count($results) ?> notes found)</h5>
        <a href="notes.php" class="small text-primary text-decoration-none">View All Notes &rarr;</a>
    </div>

    <!-- Search Results Cards -->
    <div class="row g-4">
        <?php if (empty($results)): ?>
            <div class="col-12 text-center py-5">
                <div class="p-5 bg-white rounded-4 border" style="max-width: 500px; margin: 0 auto;">
                    <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold mt-3">No Notes Matched Your Search</h5>
                    <p class="text-muted small">Try using broader search terms, selecting "All Categories", or removing filters.</p>
                    <a href="notes.php" class="btn btn-primary btn-sm">Explore All Study Notes</a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($results as $n): ?>
                <?php 
                    $ext = strtolower(pathinfo($n['file_name'], PATHINFO_EXTENSION));
                    $badge_class = 'file-badge-' . ($ext ?: 'txt');
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="edu-card">
                        <div class="edu-card-header">
                            <span class="file-badge <?= $badge_class ?>">
                                <i class="bi bi-file-earmark-text"></i> <?= strtoupper($ext) ?>
                            </span>
                            <span class="category-pill">
                                <i class="bi bi-tag-fill"></i> <?= htmlspecialchars($n['category_name']) ?>
                            </span>
                        </div>
                        <div class="edu-card-body">
                            <div class="text-muted small fw-semibold mb-1">
                                <i class="bi bi-book me-1 text-primary"></i> <?= htmlspecialchars($n['subject']) ?>
                            </div>
                            <h5 class="fw-bold mb-2">
                                <a href="note.php?id=<?= $n['id'] ?>" class="text-dark text-decoration-none">
                                    <?= htmlspecialchars($n['title']) ?>
                                </a>
                            </h5>
                            <p class="text-muted small mb-3 text-truncate-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 42px;">
                                <?= htmlspecialchars($n['description'] ?: 'No description provided.') ?>
                            </p>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top small text-muted">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="uploads/avatars/default_avatar.svg" class="avatar-img" style="width: 26px; height: 26px;" alt="Author">
                                    <span class="text-truncate" style="max-width: 120px;"><?= htmlspecialchars($n['author_name']) ?></span>
                                </div>
                                <span><?= time_ago($n['created_at']) ?></span>
                            </div>
                        </div>
                        <div class="edu-card-footer">
                            <div class="d-flex align-items-center gap-3 small text-muted">
                                <span title="Downloads"><i class="bi bi-download text-primary me-1"></i><?= $n['downloads'] ?></span>
                                <span title="Likes"><i class="bi bi-heart text-danger me-1"></i><span class="like-count"><?= $n['likes_count'] ?></span></span>
                                <span title="Rating"><i class="bi bi-star-fill text-warning me-1"></i><?= number_format($n['avg_rating'], 1) ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <button class="btn btn-sm <?= !empty($n['is_saved']) ? 'btn-warning' : 'btn-outline-secondary' ?> btn-save-note p-1 px-2" data-note-id="<?= $n['id'] ?>" title="Save Note">
                                    <i class="bi <?= !empty($n['is_saved']) ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                                </button>
                                <a href="note.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-primary p-1 px-2">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <a href="download.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-primary p-1 px-2">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
