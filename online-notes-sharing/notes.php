<?php
$page_title = 'Browse Study Notes';
require_once __DIR__ . '/includes/auth.php';

require_login('Please log in to browse study notes.');
$current_user_id = (int)$_SESSION['user_id'];

// Filter and Sort Parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sort = $_GET['sort'] ?? 'latest';
$search_query = trim($_GET['q'] ?? '');

// Fetch Categories for Sidebar / Filter Menu
$categories = [];
try {
    $stmt_cats = $pdo->query("SELECT c.id, c.name, COUNT(n.id) as note_count 
                              FROM categories c 
                              LEFT JOIN notes n ON c.id = n.category_id AND n.status = 'approved' 
                              GROUP BY c.id ORDER BY c.name ASC");
    $categories = $stmt_cats->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to load categories: " . $e->getMessage());
}

// Build Dynamic SQL Query
$sql_conditions = ["n.status = 'approved'"];
$params = [':uid1' => $current_user_id, ':uid2' => $current_user_id];

if ($category_filter > 0) {
    $sql_conditions[] = "n.category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

if (!empty($search_query)) {
    $sql_conditions[] = "(n.title LIKE :q1 OR n.subject LIKE :q2 OR n.description LIKE :q3 OR u.name LIKE :q4 OR n.keywords LIKE :q5)";
    $params[':q1'] = "%{$search_query}%";
    $params[':q2'] = "%{$search_query}%";
    $params[':q3'] = "%{$search_query}%";
    $params[':q4'] = "%{$search_query}%";
    $params[':q5'] = "%{$search_query}%";
}

$where_clause = implode(' AND ', $sql_conditions);

// Sorting
$order_by = match ($sort) {
    'downloads' => 'n.downloads DESC, n.created_at DESC',
    'likes'     => 'likes_count DESC, n.created_at DESC',
    'rating'    => 'avg_rating DESC, ratings_count DESC',
    default     => 'n.created_at DESC'
};

$notes = [];
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
            WHERE {$where_clause}
            GROUP BY n.id
            ORDER BY {$order_by}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to query notes: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Header Banner -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
        <div>
            <h2 class="fw-bold mb-1">Browse Study Notes</h2>
            <p class="text-muted small mb-0">Discover top-rated lecture notes, summaries, and exam prep resources</p>
        </div>
        <div>
            <a href="upload_note.php" class="btn btn-primary">
                <i class="bi bi-cloud-arrow-up-fill me-1"></i> Upload Note
            </a>
        </div>
    </div>

    <!-- Filter and Search Bar -->
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
        <form method="GET" action="notes.php" class="row g-2 align-items-center">
            <!-- Keyword search -->
            <div class="col-lg-4 col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Search keywords..." value="<?= htmlspecialchars($search_query) ?>">
                </div>
            </div>

            <!-- Category Filter -->
            <div class="col-lg-3 col-md-6">
                <select name="category" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Academic Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($category_filter === (int)$cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?> (<?= $cat['note_count'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sorting -->
            <div class="col-lg-3 col-md-6">
                <select name="sort" class="form-select" onchange="this.form.submit()">
                    <option value="latest" <?= ($sort === 'latest') ? 'selected' : '' ?>>Sort: Latest Uploads</option>
                    <option value="downloads" <?= ($sort === 'downloads') ? 'selected' : '' ?>>Sort: Most Downloaded</option>
                    <option value="likes" <?= ($sort === 'likes') ? 'selected' : '' ?>>Sort: Most Liked</option>
                    <option value="rating" <?= ($sort === 'rating') ? 'selected' : '' ?>>Sort: Highest Rated</option>
                </select>
            </div>

            <!-- Submit Button & Reset -->
            <div class="col-lg-2 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel-fill"></i> Filter
                </button>
                <?php if ($category_filter > 0 || !empty($search_query) || $sort !== 'latest'): ?>
                    <a href="notes.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Active Filters Badges -->
    <?php if ($category_filter > 0 || !empty($search_query)): ?>
        <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
            <span class="small text-muted fw-semibold">Active filters:</span>
            <?php if (!empty($search_query)): ?>
                <span class="badge bg-light text-dark border p-2">
                    Query: "<?= htmlspecialchars($search_query) ?>" 
                    <a href="notes.php?<?= http_build_query(array_merge($_GET, ['q' => ''])) ?>" class="text-danger ms-1 text-decoration-none">&times;</a>
                </span>
            <?php endif; ?>
            <?php if ($category_filter > 0): ?>
                <?php 
                    $cat_name = 'Selected Category';
                    foreach ($categories as $c) { if ((int)$c['id'] === $category_filter) { $cat_name = $c['name']; break; } }
                ?>
                <span class="badge bg-light text-dark border p-2">
                    Category: <?= htmlspecialchars($cat_name) ?>
                    <a href="notes.php?<?= http_build_query(array_merge($_GET, ['category' => 0])) ?>" class="text-danger ms-1 text-decoration-none">&times;</a>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Notes Grid -->
    <div class="row g-4">
        <?php if (empty($notes)): ?>
            <div class="col-12 py-5 text-center">
                <div class="p-5 bg-white rounded-4 border shadow-sm" style="max-width: 500px; margin: 0 auto;">
                    <i class="bi bi-file-earmark-x text-muted" style="font-size: 3.5rem;"></i>
                    <h4 class="fw-bold mt-3">No Notes Found</h4>
                    <p class="text-muted small">No notes matched your search criteria or filter. Try adjusting your search keywords or browsing other categories.</p>
                    <a href="notes.php" class="btn btn-outline-primary btn-sm">Clear All Filters</a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($notes as $n): ?>
                <?php 
                    $ext = strtolower(pathinfo($n['file_name'], PATHINFO_EXTENSION));
                    $badge_class = 'file-badge-' . ($ext ?: 'txt');
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="edu-card">
                        <!-- Card Header -->
                        <div class="edu-card-header">
                            <span class="file-badge <?= $badge_class ?>">
                                <i class="bi bi-file-earmark-text"></i> <?= strtoupper($ext) ?>
                            </span>
                            <span class="category-pill">
                                <i class="bi bi-tag-fill"></i> <?= htmlspecialchars($n['category_name']) ?>
                            </span>
                        </div>

                        <!-- Card Body -->
                        <div class="edu-card-body">
                            <div class="text-muted small fw-semibold mb-1">
                                <i class="bi bi-book me-1 text-primary"></i> <?= htmlspecialchars($n['subject']) ?>
                            </div>
                            <h5 class="fw-bold mb-2">
                                <a href="note.php?id=<?= $n['id'] ?>" class="text-dark text-decoration-none note-title-link">
                                    <?= htmlspecialchars($n['title']) ?>
                                </a>
                            </h5>
                            <p class="text-muted small mb-3 text-truncate-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 42px;">
                                <?= htmlspecialchars($n['description'] ?: 'No description provided.') ?>
                            </p>

                            <!-- Author & Date -->
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top small text-muted">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="uploads/avatars/default_avatar.svg" class="avatar-img" style="width: 26px; height: 26px;" alt="Author">
                                    <span class="text-truncate" style="max-width: 120px;"><?= htmlspecialchars($n['author_name']) ?></span>
                                </div>
                                <span><?= time_ago($n['created_at']) ?></span>
                            </div>
                        </div>

                        <!-- Card Footer / Actions -->
                        <div class="edu-card-footer">
                            <div class="d-flex align-items-center gap-3 small text-muted">
                                <span title="Total Downloads"><i class="bi bi-download text-primary me-1"></i><?= $n['downloads'] ?></span>
                                <span title="Likes Count"><i class="bi bi-heart text-danger me-1"></i><span class="like-count"><?= $n['likes_count'] ?></span></span>
                                <span title="Rating: <?= number_format($n['avg_rating'], 1) ?> (<?= $n['ratings_count'] ?>)">
                                    <i class="bi bi-star-fill text-warning me-1"></i><?= number_format($n['avg_rating'], 1) ?>
                                </span>
                            </div>

                            <div class="d-flex align-items-center gap-1">
                                <!-- Save / Bookmark Button -->
                                <button class="btn btn-sm <?= !empty($n['is_saved']) ? 'btn-warning' : 'btn-outline-secondary' ?> btn-save-note p-1 px-2" data-note-id="<?= $n['id'] ?>" title="Save Note">
                                    <i class="bi <?= !empty($n['is_saved']) ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                                </button>

                                <!-- View Details Button -->
                                <a href="note.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-primary p-1 px-2" title="View Note Details">
                                    <i class="bi bi-eye"></i> View
                                </a>

                                <!-- Direct Download Button -->
                                <a href="download.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-primary p-1 px-2" title="Download Document">
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
