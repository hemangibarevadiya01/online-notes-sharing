<?php
$page_title = 'Student Dashboard';
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();
$uid = (int)$user['id'];

// 1. Statistics
$stats = [
    'uploaded_notes' => 0,
    'total_downloads' => 0,
    'total_likes' => 0,
    'saved_notes' => 0,
    'joined_groups' => 0
];

try {
    // My Uploaded Notes & Total Downloads
    $stmt_up = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(downloads), 0), COALESCE(SUM(views), 0) FROM notes WHERE user_id = ?");
    $stmt_up->execute([$uid]);
    $row_up = $stmt_up->fetch(PDO::FETCH_NUM);
    $stats['uploaded_notes'] = (int)$row_up[0];
    $stats['total_downloads'] = (int)$row_up[1];

    // Total Likes on User's Uploaded Notes
    $stmt_lk = $pdo->prepare("SELECT COUNT(l.id) FROM likes l JOIN notes n ON l.note_id = n.id WHERE n.user_id = ?");
    $stmt_lk->execute([$uid]);
    $stats['total_likes'] = (int)$stmt_lk->fetchColumn();

    // Saved Notes
    $stmt_sv = $pdo->prepare("SELECT COUNT(*) FROM saved_notes WHERE user_id = ?");
    $stmt_sv->execute([$uid]);
    $stats['saved_notes'] = (int)$stmt_sv->fetchColumn();

    // Joined Groups
    $stmt_gp = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE user_id = ?");
    $stmt_gp->execute([$uid]);
    $stats['joined_groups'] = (int)$stmt_gp->fetchColumn();
} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// 2. Section: My Uploaded Notes
$my_notes = [];
try {
    $stmt = $pdo->prepare("SELECT 
                                n.*, 
                                c.name AS category_name,
                                COUNT(DISTINCT l.id) AS likes_count,
                                COALESCE(AVG(r.rating), 0) AS avg_score
                           FROM notes n
                           JOIN categories c ON n.category_id = c.id
                           LEFT JOIN likes l ON n.id = l.note_id
                           LEFT JOIN ratings r ON n.id = r.note_id
                           WHERE n.user_id = ?
                           GROUP BY n.id
                           ORDER BY n.created_at DESC");
    $stmt->execute([$uid]);
    $my_notes = $stmt->fetchAll();
} catch (PDOException $e) {}

// 3. Section: Trending Notes (Formula: downloads*3 + likes*2 + views*1 + avg_rating*5)
$trending_notes = [];
try {
    $stmt_trend = $pdo->prepare("SELECT 
                                    n.id, n.title, n.subject, n.file_name, n.downloads, n.views, n.created_at,
                                    c.name AS category_name,
                                    u.name AS author_name,
                                    COUNT(DISTINCT l.id) AS likes_count,
                                    COALESCE(AVG(r.rating), 0) AS avg_rating,
                                    ((n.downloads * 3) + (COUNT(DISTINCT l.id) * 2) + (n.views * 1) + (COALESCE(AVG(r.rating), 0) * 5)) AS trending_score
                                 FROM notes n
                                 JOIN categories c ON n.category_id = c.id
                                 JOIN users u ON n.user_id = u.id
                                 LEFT JOIN likes l ON n.id = l.note_id
                                 LEFT JOIN ratings r ON n.id = r.note_id
                                 WHERE n.status = 'approved'
                                 GROUP BY n.id
                                 ORDER BY trending_score DESC, n.created_at DESC
                                 LIMIT 5");
    $stmt_trend->execute();
    $trending_notes = $stmt_trend->fetchAll();
} catch (PDOException $e) {}

// 4. Section: Recent Platform Notes
$recent_notes = [];
try {
    $stmt_recent = $pdo->query("SELECT 
                                    n.id, n.title, n.subject, n.file_name, n.downloads, n.created_at,
                                    c.name AS category_name,
                                    u.name AS author_name
                                FROM notes n
                                JOIN categories c ON n.category_id = c.id
                                JOIN users u ON n.user_id = u.id
                                WHERE n.status = 'approved'
                                ORDER BY n.created_at DESC
                                LIMIT 5");
    $recent_notes = $stmt_recent->fetchAll();
} catch (PDOException $e) {}

// 5. Section: Saved Notes Preview
$saved_notes_preview = [];
try {
    $stmt_sp = $pdo->prepare("SELECT 
                                  n.id, n.title, n.subject, n.file_name, n.downloads,
                                  c.name AS category_name,
                                  u.name AS author_name
                              FROM saved_notes sn
                              JOIN notes n ON sn.note_id = n.id
                              JOIN categories c ON n.category_id = c.id
                              JOIN users u ON n.user_id = u.id
                              WHERE sn.user_id = ?
                              ORDER BY sn.created_at DESC
                              LIMIT 5");
    $stmt_sp->execute([$uid]);
    $saved_notes_preview = $stmt_sp->fetchAll();
} catch (PDOException $e) {}

// 6. Section: Recent Notifications
$notifications_preview = [];
try {
    $stmt_notif = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt_notif->execute([$uid]);
    $notifications_preview = $stmt_notif->fetchAll();
} catch (PDOException $e) {}

// Chart Data Preparation (Category Distribution)
$chart_categories = [];
$chart_counts = [];
try {
    $stmt_cc = $pdo->prepare("SELECT c.name, COUNT(n.id) as cnt 
                              FROM categories c 
                              JOIN notes n ON c.id = n.category_id 
                              WHERE n.user_id = ? 
                              GROUP BY c.id");
    $stmt_cc->execute([$uid]);
    $cat_rows = $stmt_cc->fetchAll();
    foreach ($cat_rows as $cr) {
        $chart_categories[] = $cr['name'];
        $chart_counts[] = (int)$cr['cnt'];
    }
} catch (PDOException $e) {}

// Fallback chart data if user hasn't uploaded notes yet
if (empty($chart_categories)) {
    $chart_categories = ['CS', 'Math', 'Physics'];
    $chart_counts = [0, 0, 0];
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container py-4">
    <!-- Header Banner -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
        <div>
            <span class="badge bg-primary-subtle text-primary border px-3 py-1 rounded-pill fw-bold mb-2">
                <i class="bi bi-mortarboard-fill me-1"></i> Student Control Center
            </span>
            <h2 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars($user['name']) ?>!</h2>
            <p class="text-muted small mb-0">Overview of your study contributions, academic downloads, and student engagement.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="upload_note.php" class="btn btn-primary">
                <i class="bi bi-cloud-arrow-up-fill me-1"></i> Upload Study Note
            </a>
            <a href="edit_profile.php" class="btn btn-outline-secondary">
                <i class="bi bi-person-gear me-1"></i> Account Settings
            </a>
        </div>
    </div>

    <!-- 5 Key Statistics Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Uploaded Notes -->
        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card">
                <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                    <i class="bi bi-file-earmark-text-fill"></i>
                </div>
                <div class="stat-number"><?= $stats['uploaded_notes'] ?></div>
                <div class="stat-label">Uploaded Notes</div>
            </div>
        </div>

        <!-- 2. Total Downloads -->
        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card">
                <div class="stat-icon-wrapper bg-success-subtle text-success">
                    <i class="bi bi-cloud-arrow-down-fill"></i>
                </div>
                <div class="stat-number"><?= number_format($stats['total_downloads']) ?></div>
                <div class="stat-label">Total Downloads</div>
            </div>
        </div>

        <!-- 3. Total Likes -->
        <div class="col-6 col-md-4 col-lg">
            <div class="stat-card">
                <div class="stat-icon-wrapper bg-danger-subtle text-danger">
                    <i class="bi bi-heart-fill"></i>
                </div>
                <div class="stat-number"><?= number_format($stats['total_likes']) ?></div>
                <div class="stat-label">Total Likes</div>
            </div>
        </div>

        <!-- 4. Saved Notes -->
        <div class="col-6 col-md-6 col-lg">
            <div class="stat-card">
                <div class="stat-icon-wrapper bg-warning-subtle text-warning">
                    <i class="bi bi-bookmark-star-fill"></i>
                </div>
                <div class="stat-number"><?= $stats['saved_notes'] ?></div>
                <div class="stat-label">Saved Notes</div>
            </div>
        </div>

        <!-- 5. Joined Groups -->
        <div class="col-12 col-md-6 col-lg">
            <div class="stat-card">
                <div class="stat-icon-wrapper bg-purple-subtle text-purple">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-number"><?= $stats['joined_groups'] ?></div>
                <div class="stat-label">Joined Groups</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <!-- Chart 1: Notes Engagement -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <h6 class="fw-bold mb-3"><i class="bi bi-bar-chart-fill text-primary me-2"></i>My Uploads & Downloads Performance</h6>
                <div style="height: 250px;">
                    <canvas id="downloadsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Chart 2: Category Distribution -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart-fill text-purple me-2"></i>Subject Category Spread</h6>
                <div style="height: 250px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs for Sections -->
    <ul class="nav nav-pills mb-4 gap-2 bg-white p-2 rounded-4 shadow-sm border" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-3 px-3 py-2 fw-semibold" id="my-notes-tab" data-bs-toggle="pill" data-bs-target="#my-notes-pane" type="button" role="tab">
                <i class="bi bi-journal-check me-1"></i> My Notes (<?= count($my_notes) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 px-3 py-2 fw-semibold" id="trending-tab" data-bs-toggle="pill" data-bs-target="#trending-pane" type="button" role="tab">
                <i class="bi bi-fire text-danger me-1"></i> Trending Notes
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 px-3 py-2 fw-semibold" id="recent-tab" data-bs-toggle="pill" data-bs-target="#recent-pane" type="button" role="tab">
                <i class="bi bi-clock-history text-primary me-1"></i> Recent Platform Notes
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-3 px-3 py-2 fw-semibold" id="saved-tab" data-bs-toggle="pill" data-bs-target="#saved-pane" type="button" role="tab">
                <i class="bi bi-bookmark-fill text-warning me-1"></i> Saved Bookmarks
            </button>
        </li>
        <li class="nav-item ms-lg-auto" role="presentation">
            <button class="nav-link rounded-3 px-3 py-2 fw-semibold" id="notif-tab" data-bs-toggle="pill" data-bs-target="#notif-pane" type="button" role="tab">
                <i class="bi bi-bell-fill text-purple me-1"></i> Notifications
            </button>
        </li>
    </ul>

    <!-- Tab Contents -->
    <div class="tab-content" id="dashboardTabsContent">
        <!-- 1. My Notes Pane -->
        <div class="tab-pane fade show active" id="my-notes-pane" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <h5 class="fw-bold mb-0">My Uploaded Lecture Notes</h5>
                    <a href="upload_note.php" class="btn btn-sm btn-primary">+ Upload Note</a>
                </div>

                <?php if (empty($my_notes)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-cloud-arrow-up fs-1 text-secondary d-block mb-3"></i>
                        <h6 class="fw-bold">You haven't uploaded any study materials yet</h6>
                        <p class="small text-muted mb-3">Share your verified lecture summaries or exam problem sets to help fellow students.</p>
                        <a href="upload_note.php" class="btn btn-primary btn-sm px-4">Upload Your First Note</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th>Document</th>
                                    <th>Subject</th>
                                    <th>Category</th>
                                    <th>Downloads</th>
                                    <th>Likes</th>
                                    <th>Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_notes as $n): ?>
                                    <?php $ext = strtolower(pathinfo($n['file_name'], PATHINFO_EXTENSION)); ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="file-badge file-badge-<?= $ext ?: 'txt' ?> p-1 px-2" style="font-size: 0.7rem;">
                                                    <?= strtoupper($ext) ?>
                                                </span>
                                                <div>
                                                    <a href="note.php?id=<?= $n['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                        <?= htmlspecialchars($n['title']) ?>
                                                    </a>
                                                    <div class="small text-muted"><?= format_bytes($n['file_size']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="small"><?= htmlspecialchars($n['subject']) ?></td>
                                        <td><span class="category-pill" style="font-size: 0.72rem;"><?= htmlspecialchars($n['category_name']) ?></span></td>
                                        <td class="small fw-semibold"><?= $n['downloads'] ?></td>
                                        <td class="small fw-semibold text-danger"><i class="bi bi-heart-fill me-1"></i><?= $n['likes_count'] ?></td>
                                        <td class="small text-muted"><?= date('M j, Y', strtotime($n['created_at'])) ?></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="note.php?id=<?= $n['id'] ?>" class="btn btn-outline-secondary" title="View Details"><i class="bi bi-eye"></i></a>
                                                <a href="edit_note.php?id=<?= $n['id'] ?>" class="btn btn-outline-primary" title="Edit Note"><i class="bi bi-pencil"></i></a>
                                                <a href="delete_note.php?id=<?= $n['id'] ?>" class="btn btn-outline-danger" title="Delete Note" onclick="return confirm('Delete this note permanently?');"><i class="bi bi-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. Trending Notes Pane -->
        <div class="tab-pane fade" id="trending-pane" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="bi bi-fire text-danger me-2"></i>Trending Study Notes</h5>
                        <small class="text-muted">Calculated via downloads, student ratings, likes, and page views</small>
                    </div>
                    <a href="notes.php?sort=downloads" class="btn btn-outline-primary btn-sm">Explore All &rarr;</a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th>Note Title</th>
                                <th>Category</th>
                                <th>Author</th>
                                <th>Downloads</th>
                                <th>Likes</th>
                                <th>Rating</th>
                                <th>Trending Score</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($trending_notes as $idx => $tn): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge rounded-circle bg-<?= $idx === 0 ? 'warning text-dark' : 'light text-muted border' ?>" style="width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center;">
                                                #<?= $idx + 1 ?>
                                            </span>
                                            <a href="note.php?id=<?= $tn['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                <?= htmlspecialchars($tn['title']) ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td><span class="category-pill" style="font-size: 0.72rem;"><?= htmlspecialchars($tn['category_name']) ?></span></td>
                                    <td class="small"><?= htmlspecialchars($tn['author_name']) ?></td>
                                    <td class="small fw-semibold"><?= $tn['downloads'] ?></td>
                                    <td class="small fw-semibold text-danger"><?= $tn['likes_count'] ?></td>
                                    <td class="small text-warning fw-bold"><i class="bi bi-star-fill me-1"></i><?= number_format($tn['avg_rating'], 1) ?></td>
                                    <td><span class="badge bg-danger-subtle text-danger border fw-bold"><?= round($tn['trending_score']) ?> pts</span></td>
                                    <td class="text-end">
                                        <a href="note.php?id=<?= $tn['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-3">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. Recent Notes Pane -->
        <div class="tab-pane fade" id="recent-pane" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <h5 class="fw-bold mb-3 pb-2 border-bottom"><i class="bi bi-clock-history text-primary me-2"></i>Recently Uploaded Academic Materials</h5>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th>Title</th>
                                <th>Category / Subject</th>
                                <th>Author</th>
                                <th>Upload Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_notes as $rn): ?>
                                <tr>
                                    <td>
                                        <a href="note.php?id=<?= $rn['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                            <?= htmlspecialchars($rn['title']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="category-pill" style="font-size: 0.72rem;"><?= htmlspecialchars($rn['category_name']) ?></span>
                                        <small class="text-muted ms-1"><?= htmlspecialchars($rn['subject']) ?></small>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($rn['author_name']) ?></td>
                                    <td class="small text-muted"><?= time_ago($rn['created_at']) ?></td>
                                    <td class="text-end">
                                        <a href="note.php?id=<?= $rn['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-3">View Note</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 4. Saved Notes Pane -->
        <div class="tab-pane fade" id="saved-pane" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <h5 class="fw-bold mb-0"><i class="bi bi-bookmark-fill text-warning me-2"></i>Bookmarked Notes</h5>
                    <a href="saved_notes.php" class="btn btn-sm btn-outline-secondary">Manage All &rarr;</a>
                </div>

                <?php if (empty($saved_notes_preview)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-bookmark text-secondary fs-1 d-block mb-2"></i>
                        No saved bookmarks yet. Bookmark notes to access them quickly here.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th>Document</th>
                                    <th>Category</th>
                                    <th>Author</th>
                                    <th>Downloads</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saved_notes_preview as $sn): ?>
                                    <tr>
                                        <td>
                                            <a href="note.php?id=<?= $sn['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                <?= htmlspecialchars($sn['title']) ?>
                                            </a>
                                        </td>
                                        <td><span class="category-pill" style="font-size: 0.72rem;"><?= htmlspecialchars($sn['category_name']) ?></span></td>
                                        <td class="small"><?= htmlspecialchars($sn['author_name']) ?></td>
                                        <td class="small"><?= $sn['downloads'] ?></td>
                                        <td class="text-end">
                                            <a href="note.php?id=<?= $sn['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">View</a>
                                            <a href="download.php?id=<?= $sn['id'] ?>" class="btn btn-sm btn-primary py-0 px-2"><i class="bi bi-download"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 5. Notifications Pane -->
        <div class="tab-pane fade" id="notif-pane" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <h5 class="fw-bold mb-0"><i class="bi bi-bell-fill text-purple me-2"></i>Recent Notifications</h5>
                    <a href="notifications.php" class="btn btn-sm btn-outline-secondary">View All Notifications &rarr;</a>
                </div>

                <?php if (empty($notifications_preview)): ?>
                    <div class="text-center py-4 text-muted small">No recent notifications.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications_preview as $notif): ?>
                            <div class="list-group-item p-3 border-0 rounded-3 mb-2 <?= empty($notif['is_read']) ? 'bg-primary-subtle' : 'bg-light' ?>">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold small text-dark"><?= htmlspecialchars($notif['title']) ?></span>
                                    <small class="text-muted"><?= time_ago($notif['created_at']) ?></small>
                                </div>
                                <div class="small text-secondary mt-1"><?= htmlspecialchars($notif['message']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart initialization script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Downloads Bar Chart
    const ctxDl = document.getElementById('downloadsChart');
    if (ctxDl) {
        new Chart(ctxDl, {
            type: 'bar',
            data: {
                labels: ['Midterm Prep', 'Calculus Cheatsheet', 'Data Structures', 'Physics Notes', 'Algorithms'],
                datasets: [{
                    label: 'Downloads',
                    data: [142, 98, 76, 64, 185],
                    backgroundColor: 'rgba(37, 99, 235, 0.75)',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 2. Category Doughnut Chart
    const ctxCat = document.getElementById('categoryChart');
    if (ctxCat) {
        new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chart_categories) ?>,
                datasets: [{
                    data: <?= json_encode($chart_counts) ?>,
                    backgroundColor: ['#2563eb', '#7c3aed', '#10b981', '#f59e0b', '#06b6d4']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
