<?php
/**
 * NoteShare — Trending Notes Page
 * Ranks notes using the algorithm: (downloads * 3) + (likes * 2) + (views * 1) + (avg_rating * 5)
 */
$page_title = 'Trending Study Notes';
require_once __DIR__ . '/includes/auth.php';

require_login('Please log in to view trending study notes.');
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;

try {
    // Categories for filter
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Dynamic Trending Query
    $sql = "
        SELECT 
            n.*,
            c.name as category_name,
            u.name as author_name,
            u.profile_image as author_avatar,
            COALESCE(COUNT(DISTINCT l.id), 0) as total_likes,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COALESCE(COUNT(DISTINCT r.id), 0) as total_ratings,
            (
                (n.downloads * 3) + 
                (COUNT(DISTINCT l.id) * 2) + 
                (n.views * 1) + 
                (COALESCE(AVG(r.rating), 0) * 5)
            ) as trending_score
        FROM notes n
        LEFT JOIN categories c ON n.category_id = c.id
        LEFT JOIN users u ON n.user_id = u.id
        LEFT JOIN likes l ON n.id = l.note_id
        LEFT JOIN ratings r ON n.id = r.note_id
        WHERE n.status = 'approved'
    ";

    $params = [];
    if ($category_id > 0) {
        $sql .= " AND n.category_id = ?";
        $params[] = $category_id;
    }

    $sql .= " GROUP BY n.id ORDER BY trending_score DESC, n.downloads DESC, n.created_at DESC LIMIT 30";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trending_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Trending notes error: " . $e->getMessage());
    $trending_notes = [];
    $categories = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Hero / Header -->
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 text-white position-relative overflow-hidden" style="background: linear-gradient(135deg, #4F46E5, #7C3AED);">
        <div class="position-relative z-1" style="max-width: 650px;">
            <span class="badge bg-white text-primary px-3 py-1 rounded-pill fw-bold text-uppercase mb-2 shadow-sm" style="font-size: 0.75rem;">
                <i class="bi bi-fire text-danger me-1"></i> Community Trends
            </span>
            <h1 class="fw-bold display-6 mb-2">Trending Study Materials</h1>
            <p class="text-white-50 mb-0">
                Discover the most downloaded, highly rated, and actively reviewed lecture notes and academic guides across institutions.
            </p>
        </div>
        <div class="position-absolute end-0 bottom-0 opacity-25 d-none d-md-block pe-4 pb-2" style="font-size: 8rem; color: #fff;">
            <i class="bi bi-graph-up-arrow"></i>
        </div>
    </div>

    <!-- Category Filter Bar -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 pb-2 border-bottom">
        <div class="d-flex align-items-center gap-2 overflow-auto py-1" style="max-width: 100%;">
            <a href="trending.php" class="btn btn-sm text-nowrap <?= $category_id === 0 ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <i class="bi bi-stars me-1"></i> All Disciplines
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="trending.php?category=<?= $cat['id'] ?>" class="btn btn-sm text-nowrap <?= $category_id === (int)$cat['id'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="text-muted small">
            <i class="bi bi-info-circle me-1"></i> Ranked by downloads, likes & peer reviews
        </div>
    </div>

    <!-- Trending Notes Grid -->
    <?php if (empty($trending_notes)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
            <div class="brand-icon mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.8rem; background: rgba(79, 70, 229, 0.1); color: #4F46E5;">
                <i class="bi bi-search"></i>
            </div>
            <h5 class="fw-bold mb-1">No trending notes in this category</h5>
            <p class="text-muted small mb-3">Be the first to upload and share study notes for this academic subject.</p>
            <div>
                <a href="upload_note.php" class="btn btn-primary btn-sm px-4">
                    <i class="bi bi-cloud-arrow-up-fill me-1"></i> Upload Note
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php 
            $rank = 1;
            foreach ($trending_notes as $note): 
                $avg = round((float)$note['avg_rating'], 1);
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card note-card h-100 border-0 shadow-sm rounded-4 p-3 d-flex flex-column position-relative bg-white">
                        <!-- Rank Badge -->
                        <div class="position-absolute top-0 start-0 m-3 z-1">
                            <?php if ($rank === 1): ?>
                                <span class="badge rounded-pill bg-danger shadow-sm px-2 py-1">
                                    <i class="bi bi-trophy-fill me-1 text-warning"></i> #1 Trending
                                </span>
                            <?php elseif ($rank === 2): ?>
                                <span class="badge rounded-pill bg-primary shadow-sm px-2 py-1">
                                    <i class="bi bi-award-fill me-1"></i> #2 Trending
                                </span>
                            <?php elseif ($rank === 3): ?>
                                <span class="badge rounded-pill bg-purple shadow-sm px-2 py-1 text-white">
                                    <i class="bi bi-award me-1"></i> #3 Trending
                                </span>
                            <?php else: ?>
                                <span class="badge rounded-pill bg-secondary shadow-sm px-2 py-1">
                                    #<?= $rank ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Format & Category -->
                        <div class="d-flex justify-content-end align-items-center mb-2">
                            <span class="badge bg-secondary-subtle text-secondary text-uppercase" style="font-size: 0.7rem;">
                                <?= htmlspecialchars($note['file_type']) ?>
                            </span>
                        </div>

                        <!-- Title & Subject -->
                        <div class="mt-3 mb-2 flex-grow-1">
                            <span class="badge bg-primary-subtle text-primary mb-2" style="font-size: 0.7rem;">
                                <?= htmlspecialchars($note['category_name'] ?? 'General') ?>
                            </span>
                            <h5 class="fw-bold mb-1 text-truncate" title="<?= htmlspecialchars($note['title']) ?>">
                                <a href="note.php?id=<?= $note['id'] ?>" class="text-dark text-decoration-none">
                                    <?= htmlspecialchars($note['title']) ?>
                                </a>
                            </h5>
                            <p class="text-muted small mb-2 text-truncate"><?= htmlspecialchars($note['subject']) ?></p>
                            <p class="text-muted small mb-0" style="font-size: 0.8rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= htmlspecialchars($note['description'] ?: 'Comprehensive student study notes.') ?>
                            </p>
                        </div>

                        <!-- Stats Bar -->
                        <div class="d-flex align-items-center justify-content-between small text-muted py-2 border-top border-bottom my-2" style="font-size: 0.78rem;">
                            <span><i class="bi bi-download text-primary me-1"></i><?= number_format($note['downloads']) ?></span>
                            <span><i class="bi bi-heart-fill text-danger me-1"></i><?= number_format($note['total_likes']) ?></span>
                            <span><i class="bi bi-eye text-cyan me-1"></i><?= number_format($note['views']) ?></span>
                            <span class="text-warning fw-semibold"><i class="bi bi-star-fill me-1"></i><?= $avg > 0 ? $avg : 'New' ?></span>
                        </div>

                        <!-- Author & Action -->
                        <div class="d-flex align-items-center justify-content-between pt-1">
                            <div class="d-flex align-items-center gap-2">
                                <img src="uploads/avatars/default_avatar.svg" alt="Avatar" class="avatar-img" style="width: 28px; height: 28px;">
                                <span class="small fw-semibold text-truncate text-dark" style="max-width: 110px;">
                                    <?= htmlspecialchars($note['author_name'] ?? 'Scholar') ?>
                                </span>
                            </div>
                            <div class="d-flex gap-1">
                                <?php if (strtolower($note['file_type']) === 'pdf'): ?>
                                    <a href="preview.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-outline-primary" title="Preview Online">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="download.php?id=<?= $note['id'] ?>" class="btn btn-sm btn-primary" title="Download">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php 
            $rank++;
            endforeach; 
            ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
