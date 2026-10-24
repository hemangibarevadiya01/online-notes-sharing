<?php
$page_title = 'Academic Categories';
require_once __DIR__ . '/includes/auth.php';

require_login('Please log in to browse academic categories.');

// Fetch all categories with note count, total downloads, and latest activity
$categories = [];
try {
    $stmt = $pdo->query("SELECT 
                            c.id, c.name, c.description, c.created_at,
                            COUNT(n.id) AS total_notes,
                            COALESCE(SUM(n.downloads), 0) AS total_downloads
                         FROM categories c
                         LEFT JOIN notes n ON c.id = n.category_id AND n.status = 'approved'
                         GROUP BY c.id
                         ORDER BY total_notes DESC, c.name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to load categories: " . $e->getMessage());
}

// Category icon mapper helper
function get_category_icon(string $name): string {
    $lower = strtolower($name);
    if (str_contains($lower, 'computer') || str_contains($lower, 'tech') || str_contains($lower, 'software')) return 'bi-laptop';
    if (str_contains($lower, 'math')) return 'bi-calculator';
    if (str_contains($lower, 'physic') || str_contains($lower, 'science')) return 'bi-radioactive';
    if (str_contains($lower, 'business') || str_contains($lower, 'econom') || str_contains($lower, 'financ')) return 'bi-graph-up-arrow';
    if (str_contains($lower, 'medic') || str_contains($lower, 'health') || str_contains($lower, 'bio')) return 'bi-heart-pulse';
    if (str_contains($lower, 'engineer')) return 'bi-gear-wide-connected';
    if (str_contains($lower, 'law') || str_contains($lower, 'govern')) return 'bi-bank';
    if (str_contains($lower, 'liter') || str_contains($lower, 'lang')) return 'bi-translate';
    return 'bi-journal-bookmark';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="text-center mb-5" style="max-width: 700px; margin: 0 auto;">
        <span class="badge bg-primary-subtle text-primary border px-3 py-2 rounded-pill fw-bold mb-2">Subject Directories</span>
        <h2 class="fw-bold mb-2">Explore Notes by Category</h2>
        <p class="text-muted">Browse organized academic resources, lecture cheat-sheets, and curated study documents across major college and university disciplines.</p>
    </div>

    <!-- Category Grid -->
    <div class="row g-4">
        <?php foreach ($categories as $cat): ?>
            <?php $icon = get_category_icon($cat['name']); ?>
            <div class="col-lg-3 col-md-6">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 stat-card d-flex flex-column text-start bg-white">
                    <div class="stat-icon-wrapper bg-primary-subtle text-primary mb-3">
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                    <h5 class="fw-bold mb-2">
                        <a href="notes.php?category=<?= $cat['id'] ?>" class="text-dark text-decoration-none">
                            <?= htmlspecialchars($cat['name']) ?>
                        </a>
                    </h5>
                    <p class="text-muted small flex-grow-1 mb-3">
                        <?= htmlspecialchars($cat['description'] ?: 'Curated study materials and reference guides.') ?>
                    </p>
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top small text-muted">
                        <span><i class="bi bi-file-earmark-text text-purple me-1"></i> <strong><?= $cat['total_notes'] ?></strong> Notes</span>
                        <span><i class="bi bi-download text-primary me-1"></i> <strong><?= $cat['total_downloads'] ?></strong> DLs</span>
                    </div>
                    <a href="notes.php?category=<?= $cat['id'] ?>" class="btn btn-outline-primary btn-sm mt-3 w-100">
                        Browse Category &rarr;
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
