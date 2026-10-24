<?php
$page_title = 'Online Notes Sharing Platform - Academic Notes & Collaboration';
require_once __DIR__ . '/includes/auth.php';

$current_user_id = is_logged_in() ? (int)$_SESSION['user_id'] : 0;

// 1. Fetch Platform Stats
$stats = ['notes' => 0, 'downloads' => 0, 'users' => 0, 'groups' => 0];
try {
    $stats['notes'] = (int)$pdo->query("SELECT COUNT(*) FROM notes WHERE status = 'approved'")->fetchColumn();
    $stats['downloads'] = (int)$pdo->query("SELECT COALESCE(SUM(downloads), 0) FROM notes")->fetchColumn();
    $stats['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
    $stats['groups'] = (int)$pdo->query("SELECT COUNT(*) FROM groups")->fetchColumn();
} catch (PDOException $e) {}

// Helper query function to fetch notes with calculated metrics
function fetch_curated_notes(string $order_by, int $limit = 6): array {
    global $pdo, $current_user_id;
    try {
        $sql = "SELECT 
                    n.id, n.title, n.subject, n.description, n.file_name, n.file_size,
                    n.downloads, n.views, n.created_at,
                    c.name AS category_name,
                    u.name AS author_name,
                    COUNT(DISTINCT l.id) AS likes_count,
                    COALESCE(AVG(r.rating), 0) AS avg_rating,
                    COUNT(DISTINCT r.id) AS ratings_count,
                    (SELECT COUNT(*) FROM saved_notes sn WHERE sn.note_id = n.id AND sn.user_id = :uid) AS is_saved,
                    ((n.downloads * 3) + (COUNT(DISTINCT l.id) * 2) + (n.views * 1) + (COALESCE(AVG(r.rating), 0) * 5)) AS trending_score
                FROM notes n
                JOIN categories c ON n.category_id = c.id
                JOIN users u ON n.user_id = u.id
                LEFT JOIN likes l ON n.id = l.note_id
                LEFT JOIN ratings r ON n.id = r.note_id
                WHERE n.status = 'approved'
                GROUP BY n.id
                ORDER BY {$order_by}
                LIMIT {$limit}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $current_user_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Curated notes query error: " . $e->getMessage());
        return [];
    }
}

// 2. Fetch the 4 Collections
$trending_notes   = fetch_curated_notes("trending_score DESC, n.created_at DESC", 6);
$recent_notes     = fetch_curated_notes("n.created_at DESC", 6);
$downloaded_notes = fetch_curated_notes("n.downloads DESC, n.created_at DESC", 6);
$liked_notes      = fetch_curated_notes("likes_count DESC, n.created_at DESC", 6);
$rated_notes      = fetch_curated_notes("avg_rating DESC, ratings_count DESC", 6);

// 3. Fetch Top Categories
$top_categories = [];
try {
    $stmt_cats = $pdo->query("SELECT c.id, c.name, COUNT(n.id) as notes_count 
                              FROM categories c 
                              LEFT JOIN notes n ON c.id = n.category_id AND n.status = 'approved'
                              GROUP BY c.id 
                              ORDER BY notes_count DESC, c.name ASC 
                              LIMIT 4");
    $top_categories = $stmt_cats->fetchAll();
} catch (PDOException $e) {}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section text-center">
    <div class="container position-relative" style="z-index: 2;">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <span class="badge bg-primary-subtle text-primary border px-3 py-2 rounded-pill fw-bold mb-3 d-inline-block">
                    <i class="bi bi-stars text-warning me-1"></i> LEARN. SHARE. GROW.
                </span>
                <h1 class="display-4 fw-extrabold text-white mb-3">
                    NoteShare — Online Notes <br class="d-none d-md-block">Sharing & Academic Platform
                </h1>
                <p class="lead text-white-50 mb-4" style="max-width: 680px; margin: 0 auto;">
                    Access thousands of peer-reviewed lecture summaries, cheat-sheets, textbook solutions, and course guides created by top students. Set daily study goals and earn knowledge badges!
                </p>

                <!-- Search Box in Hero -->
                <div class="hero-search-box mx-auto mb-4" style="max-width: 620px;">
                    <form action="search.php" method="GET" class="d-flex align-items-center">
                        <i class="bi bi-search text-muted ps-3 fs-5"></i>
                        <input type="text" name="q" class="form-control flex-grow-1" placeholder="Search by topic, e.g. Algorithms, Calculus, Chemistry, Business...">
                        <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill">Find Notes</button>
                    </form>
                </div>

                <!-- Quick CTA Links -->
                <div class="d-flex flex-wrap align-items-center justify-content-center gap-3">
                    <a href="notes.php" class="btn btn-outline-light px-4">
                        <i class="bi bi-journal-text me-1"></i> Browse Catalog
                    </a>
                    <a href="trending.php" class="btn btn-warning text-dark fw-bold px-4">
                        <i class="bi bi-fire me-1"></i> Trending Notes
                    </a>
                    <a href="upload_note.php" class="btn btn-primary px-4">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i> Upload Study Note
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Ribbon -->
<section class="py-5 bg-white border-bottom">
    <div class="container">
        <div class="row g-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                        <i class="bi bi-file-earmark-check"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['notes']) ?>+</div>
                    <div class="stat-label">Verified Notes</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper bg-purple-subtle text-purple">
                        <i class="bi bi-cloud-download"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['downloads']) ?>+</div>
                    <div class="stat-label">Total Downloads</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper bg-success-subtle text-success">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['users']) ?>+</div>
                    <div class="stat-label">Active Students</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon-wrapper bg-warning-subtle text-warning">
                        <i class="bi bi-mortarboard"></i>
                    </div>
                    <div class="stat-number"><?= number_format($stats['groups']) ?>+</div>
                    <div class="stat-label">Study Groups</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Unique Feature Spotlight: Smart Study Mission & Knowledge Impact -->
<section class="py-5" style="background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <span class="badge bg-primary-subtle text-primary border px-3 py-1 rounded-pill fw-bold mb-2">
                    <i class="bi bi-trophy-fill text-warning me-1"></i> Standout Academic Feature
                </span>
                <h2 class="fw-bold mb-3">Smart Study Mission & Knowledge Impact System</h2>
                <p class="text-muted mb-4">
                    Take your learning beyond passive reading. NoteShare rewards active study habits with daily personalized study goals, dynamic Knowledge Impact Score algorithms, and milestone badges.
                </p>
                <div class="d-flex flex-column gap-3 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="brand-icon bg-primary text-white rounded-circle flex-shrink-0" style="width: 42px; height: 42px; font-size: 1.1rem;">
                            <i class="bi bi-compass"></i>
                        </div>
                        <div>
                            <strong class="d-block text-dark">Daily Study Missions</strong>
                            <span class="text-muted small">Set custom daily targets for reading, downloading, and uploading notes with live circular progress tracking.</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3">
                        <div class="brand-icon bg-purple text-white rounded-circle flex-shrink-0" style="width: 42px; height: 42px; font-size: 1.1rem; background: #7c3aed;">
                            <i class="bi bi-lightning-charge-fill"></i>
                        </div>
                        <div>
                            <strong class="d-block text-dark">Knowledge Impact Score</strong>
                            <span class="text-muted small">A weighted scientific score combining your uploads (&times;20), downloads received (&times;5), likes (&times;3), ratings (&times;10), and study sessions.</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-3">
                        <div class="brand-icon bg-success text-white rounded-circle flex-shrink-0" style="width: 42px; height: 42px; font-size: 1.1rem; background: #10b981;">
                            <i class="bi bi-award-fill"></i>
                        </div>
                        <div>
                            <strong class="d-block text-dark">6 Milestone Badges</strong>
                            <span class="text-muted small">Unlock prestigious accolades: First Step 🌱, Knowledge Seeker 📚, Knowledge Sharer 🚀, Helpful Learner 🤝, Goal Champion 🎯, and Master Reviewer 🌟.</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= is_logged_in() ? 'dashboard.php' : 'register.php' ?>" class="btn btn-primary px-4">
                        <?= is_logged_in() ? 'View My Mission & Badges &rarr;' : 'Join NoteShare Today &rarr;' ?>
                    </a>
                    <a href="about.php" class="btn btn-outline-secondary px-3">
                        Learn More
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="p-4 bg-white rounded-4 shadow-sm border">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-shield-check me-1"></i> Preview: Student Mission Engine</h6>
                        <span class="badge bg-success-subtle text-success border">Active</span>
                    </div>
                    <div class="row g-2 text-center mb-3">
                        <div class="col-4">
                            <div class="p-3 bg-light rounded-3">
                                <span class="fs-4 d-block">🌱</span>
                                <strong class="small d-block text-dark">First Step</strong>
                                <span class="text-muted" style="font-size:0.68rem;">1st Activity</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 bg-light rounded-3">
                                <span class="fs-4 d-block">📚</span>
                                <strong class="small d-block text-dark">Knowledge Seeker</strong>
                                <span class="text-muted" style="font-size:0.68rem;">10 Reads</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 bg-light rounded-3">
                                <span class="fs-4 d-block">🚀</span>
                                <strong class="small d-block text-dark">Knowledge Sharer</strong>
                                <span class="text-muted" style="font-size:0.68rem;">3 Uploads</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-3 rounded-3" style="background: linear-gradient(135deg, #1e1b4b, #4338ca); color: #fff;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-white-50">Impact Formula:</span>
                            <span class="badge bg-warning text-dark fw-bold">Active Engine</span>
                        </div>
                        <code class="text-warning small d-block mb-1" style="font-size: 0.8rem;">
                            Score = (Notes &times; 20) + (Downloads &times; 5) + (Likes &times; 3) + (Rating &times; 10) + (Acts &times; 2)
                        </code>
                        <span class="small text-white-50">Rewards quality academic sharing and peer collaboration.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Curated Collections Tabs Section (Step 18: Trending, Recent, Most Downloaded, Most Liked, Highest Rated) -->
<section class="py-5">
    <div class="container">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <span class="text-primary fw-bold small text-uppercase">Academic Showcase</span>
                <h2 class="fw-bold mb-0">Discover Top Student Notes</h2>
            </div>
            <a href="notes.php" class="btn btn-outline-primary btn-sm">
                View Full Catalog &rarr;
            </a>
        </div>

        <!-- Pills Tabs for Collections -->
        <ul class="nav nav-pills mb-4 gap-2 bg-white p-2 rounded-4 shadow-sm border" id="curatedTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-3 px-3 py-2 fw-semibold" id="tab-trending" data-bs-toggle="pill" data-bs-target="#pane-trending" type="button" role="tab">
                    <i class="bi bi-fire text-danger me-1"></i> Trending Notes
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-3 px-3 py-2 fw-semibold" id="tab-recent" data-bs-toggle="pill" data-bs-target="#pane-recent" type="button" role="tab">
                    <i class="bi bi-clock-history text-primary me-1"></i> Recent Notes
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-3 px-3 py-2 fw-semibold" id="tab-downloaded" data-bs-toggle="pill" data-bs-target="#pane-downloaded" type="button" role="tab">
                    <i class="bi bi-cloud-arrow-down-fill text-success me-1"></i> Most Downloaded
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-3 px-3 py-2 fw-semibold" id="tab-liked" data-bs-toggle="pill" data-bs-target="#pane-liked" type="button" role="tab">
                    <i class="bi bi-heart-fill text-danger me-1"></i> Most Liked
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-3 px-3 py-2 fw-semibold" id="tab-rated" data-bs-toggle="pill" data-bs-target="#pane-rated" type="button" role="tab">
                    <i class="bi bi-star-fill text-warning me-1"></i> Highest Rated
                </button>
            </li>
        </ul>

        <!-- Reusable Card Renderer Macro for Tab Panes -->
        <div class="tab-content" id="curatedTabsContent">
            <?php
            $collections = [
                'pane-trending'   => [$trending_notes, 'show active', true],
                'pane-recent'     => [$recent_notes, '', false],
                'pane-downloaded' => [$downloaded_notes, '', false],
                'pane-liked'      => [$liked_notes, '', false],
                'pane-rated'      => [$rated_notes, '', false]
            ];

            foreach ($collections as $pane_id => [$list, $active_cls, $is_trend]):
            ?>
                <div class="tab-pane fade <?= $active_cls ?>" id="<?= $pane_id ?>" role="tabpanel">
                    <div class="row g-4">
                        <?php foreach ($list as $n): ?>
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
                                            <i class="bi bi-book text-primary me-1"></i> <?= htmlspecialchars($n['subject']) ?>
                                        </div>
                                        <h5 class="fw-bold mb-2">
                                            <a href="note.php?id=<?= $n['id'] ?>" class="text-dark text-decoration-none">
                                                <?= htmlspecialchars($n['title']) ?>
                                            </a>
                                        </h5>
                                        <p class="text-muted small mb-3 text-truncate-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 42px;">
                                            <?= htmlspecialchars($n['description'] ?: 'Curated study document with formulas and examples.') ?>
                                        </p>
                                        <div class="d-flex align-items-center justify-content-between pt-2 border-top small text-muted">
                                            <span>Author: <strong><?= htmlspecialchars($n['author_name']) ?></strong></span>
                                            <span><?= time_ago($n['created_at']) ?></span>
                                        </div>
                                    </div>
                                    <div class="edu-card-footer">
                                        <div class="d-flex align-items-center gap-3 small text-muted">
                                            <span><i class="bi bi-download text-primary me-1"></i><?= $n['downloads'] ?></span>
                                            <span><i class="bi bi-heart text-danger me-1"></i><span class="like-count"><?= $n['likes_count'] ?></span></span>
                                            <span><i class="bi bi-star-fill text-warning me-1"></i><?= number_format($n['avg_rating'], 1) ?></span>
                                        </div>
                                        <div class="d-flex align-items-center gap-1">
                                            <button class="btn btn-sm <?= !empty($n['is_saved']) ? 'btn-warning' : 'btn-outline-secondary' ?> btn-save-note p-1 px-2" data-note-id="<?= $n['id'] ?>" title="Save Note">
                                                <i class="bi <?= !empty($n['is_saved']) ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                                            </button>
                                            <a href="note.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-primary p-1 px-2">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="download.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-primary p-1 px-2">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-5 bg-white border-top border-bottom">
    <div class="container text-center">
        <div class="mb-5" style="max-width: 640px; margin: 0 auto;">
            <span class="text-primary fw-bold small text-uppercase">Simple Workflow</span>
            <h2 class="fw-bold">How NoteShare Works</h2>
            <p class="text-muted">Three simple steps to supercharge your academic journey and prepare for your exams.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="p-4 rounded-4 bg-light h-100">
                    <div class="brand-icon mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.75rem;">
                        <i class="bi bi-search"></i>
                    </div>
                    <h5 class="fw-bold mb-2">1. Explore & Search</h5>
                    <p class="text-muted small">Search by university subject, course code, professor notes, or file type to find exact study materials.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 rounded-4 bg-light h-100">
                    <div class="brand-icon mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.75rem; background: linear-gradient(135deg, #7c3aed, #9333ea);">
                        <i class="bi bi-eye"></i>
                    </div>
                    <h5 class="fw-bold mb-2">2. Preview in Browser</h5>
                    <p class="text-muted small">Instantly preview PDF notes in your browser without downloading or cluttering your device storage.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-4 rounded-4 bg-light h-100">
                    <div class="brand-icon mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.75rem; background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="bi bi-people"></i>
                    </div>
                    <h5 class="fw-bold mb-2">3. Study in Groups</h5>
                    <p class="text-muted small">Join interactive peer study circles, rate summaries, discuss tricky questions, and upload your own notes.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Banner -->
<section class="py-5" style="background: var(--hero-gradient);">
    <div class="container text-center py-4 text-white">
        <h2 class="fw-bold mb-3">Ready to Excel in Your Academic Career?</h2>
        <p class="text-white-50 mb-4" style="max-width: 580px; margin: 0 auto;">Join thousands of students sharing lecture notes and collaborating in study groups daily.</p>
        <div class="d-flex justify-content-center gap-3">
            <?php if (is_logged_in()): ?>
                <a href="upload_note.php" class="btn btn-primary btn-lg px-4">
                    <i class="bi bi-cloud-arrow-up-fill me-2"></i> Upload Note
                </a>
                <a href="dashboard.php" class="btn btn-outline-light btn-lg px-4">
                    Go to My Dashboard
                </a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary btn-lg px-4">
                    Create Free Account
                </a>
                <a href="login.php" class="btn btn-outline-light btn-lg px-4">
                    Sign In
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
