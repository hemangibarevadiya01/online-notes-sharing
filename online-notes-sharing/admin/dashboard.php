<?php
$admin_title = 'System Overview';
require_once __DIR__ . '/includes/header.php';

// 6 Required Dashboard Statistics
$counts = [
    'users' => 0,
    'notes' => 0,
    'categories' => 0,
    'groups' => 0,
    'downloads' => 0,
    'reports' => 0
];

try {
    $counts['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $counts['notes'] = (int)$pdo->query("SELECT COUNT(*) FROM notes")->fetchColumn();
    $counts['categories'] = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $counts['groups'] = (int)$pdo->query("SELECT COUNT(*) FROM groups")->fetchColumn();
    $counts['downloads'] = (int)$pdo->query("SELECT COALESCE(SUM(downloads), 0) FROM notes")->fetchColumn();
    $counts['reports'] = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
} catch (PDOException $e) {
    error_log("Admin stats error: " . $e->getMessage());
}

// Fetch categories distribution for Chart 4
$cat_names = [];
$cat_note_counts = [];
try {
    $stmt_cats = $pdo->query("SELECT c.name, COUNT(n.id) AS cnt 
                              FROM categories c 
                              LEFT JOIN notes n ON c.id = n.category_id 
                              GROUP BY c.id 
                              ORDER BY cnt DESC LIMIT 5");
    $top_cats = $stmt_cats->fetchAll();
    foreach ($top_cats as $tc) {
        $cat_names[] = $tc['name'];
        $cat_note_counts[] = (int)$tc['cnt'];
    }
} catch (PDOException $e) {}

// Fetch recent users
$recent_users = [];
try {
    $recent_users = $pdo->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {}

// Fetch recent pending reports
$recent_reports = [];
try {
    $recent_reports = $pdo->query("SELECT r.*, n.title AS note_title, u.name AS reporter_name 
                                  FROM reports r 
                                  JOIN notes n ON r.note_id = n.id 
                                  JOIN users u ON r.user_id = u.id 
                                  ORDER BY r.created_at DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {}

// Fetch recent notes
$recent_notes = [];
try {
    $recent_notes = $pdo->query("SELECT n.id, n.title, n.subject, n.file_name, n.downloads, n.status, n.created_at, u.name AS author_name 
                                 FROM notes n 
                                 JOIN users u ON n.user_id = u.id 
                                 ORDER BY n.created_at DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {}
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- 6 Dashboard Metric Cards Grid -->
<div class="row g-3 mb-4">
    <!-- 1. Total Users -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Total Users</span>
            <h3 class="fw-bold mb-1 mt-2 text-primary"><?= number_format($counts['users']) ?></h3>
            <a href="users.php" class="small text-primary text-decoration-none mt-auto">Manage Users &rarr;</a>
        </div>
    </div>

    <!-- 2. Total Notes -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Total Notes</span>
            <h3 class="fw-bold mb-1 mt-2 text-purple"><?= number_format($counts['notes']) ?></h3>
            <a href="notes.php" class="small text-purple text-decoration-none mt-auto">Review Notes &rarr;</a>
        </div>
    </div>

    <!-- 3. Total Categories -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Categories</span>
            <h3 class="fw-bold mb-1 mt-2 text-dark"><?= number_format($counts['categories']) ?></h3>
            <a href="categories.php" class="small text-dark text-decoration-none mt-auto">Taxonomy &rarr;</a>
        </div>
    </div>

    <!-- 4. Total Groups -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Study Groups</span>
            <h3 class="fw-bold mb-1 mt-2 text-info"><?= number_format($counts['groups']) ?></h3>
            <a href="groups.php" class="small text-info text-decoration-none mt-auto">View Circles &rarr;</a>
        </div>
    </div>

    <!-- 5. Total Downloads -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Downloads</span>
            <h3 class="fw-bold mb-1 mt-2 text-success"><?= number_format($counts['downloads']) ?></h3>
            <span class="small text-muted mt-auto">Total Delivered</span>
        </div>
    </div>

    <!-- 6. Total Reports -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
            <span class="text-muted small fw-bold text-uppercase" style="font-size: 0.72rem;">Reports</span>
            <h3 class="fw-bold mb-1 mt-2 text-<?= $counts['reports'] > 0 ? 'danger' : 'secondary' ?>"><?= number_format($counts['reports']) ?></h3>
            <a href="reports.php" class="small text-danger text-decoration-none mt-auto">Moderation &rarr;</a>
        </div>
    </div>
</div>

<!-- 4 Charts Grid (Notes over time, Downloads, Users, Popular Categories) -->
<div class="row g-4 mb-4">
    <!-- Chart 1: Notes Uploaded Over Time -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h6 class="fw-bold mb-3"><i class="bi bi-graph-up text-primary me-2"></i>Notes Uploaded Over Time</h6>
            <div style="height: 220px;">
                <canvas id="notesOverTimeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 2: Downloads Volume -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h6 class="fw-bold mb-3"><i class="bi bi-cloud-arrow-down-fill text-success me-2"></i>Download Activity</h6>
            <div style="height: 220px;">
                <canvas id="downloadsVolumeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 3: Users Growth -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h6 class="fw-bold mb-3"><i class="bi bi-people-fill text-info me-2"></i>Student Registrations</h6>
            <div style="height: 220px;">
                <canvas id="usersGrowthChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Chart 4: Popular Categories -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart-fill text-purple me-2"></i>Popular Subject Categories</h6>
            <div style="height: 220px;">
                <canvas id="popularCatsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tables Section: Recent Notes & Reports -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                <h5 class="fw-bold mb-0">Latest Uploaded Notes</h5>
                <a href="notes.php" class="small text-primary text-decoration-none">View All Notes &rarr;</a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>Title & Subject</th>
                            <th>Author</th>
                            <th>Downloads</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_notes as $rn): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($rn['title']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($rn['subject']) ?></small>
                                </td>
                                <td class="small"><?= htmlspecialchars($rn['author_name']) ?></td>
                                <td class="small fw-semibold"><?= $rn['downloads'] ?></td>
                                <td>
                                    <span class="badge bg-success-subtle text-success border"><?= htmlspecialchars($rn['status']) ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="edit_note.php?id=<?= $rn['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent User Reports -->
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                <h5 class="fw-bold mb-0"><i class="bi bi-flag-fill text-danger me-2"></i>Recent Flags & Reports</h5>
                <a href="reports.php" class="small text-danger text-decoration-none">Manage Reports &rarr;</a>
            </div>

            <?php if (empty($recent_reports)): ?>
                <div class="text-muted small text-center py-3">No active reports. All notes comply with platform guidelines.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th>Reported Note</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_reports as $rep): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold small"><?= htmlspecialchars($rep['note_title']) ?></div>
                                        <small class="text-muted">By <?= htmlspecialchars($rep['reporter_name']) ?></small>
                                    </td>
                                    <td class="small text-truncate" style="max-width: 200px;"><?= htmlspecialchars($rep['reason']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $rep['status'] === 'pending' ? 'danger' : 'secondary' ?>-subtle text-<?= $rep['status'] === 'pending' ? 'danger' : 'secondary' ?> border">
                                            <?= htmlspecialchars($rep['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="reports.php" class="btn btn-sm btn-outline-danger py-0 px-2">Review</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Recent Users -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                <h5 class="fw-bold mb-0">Recent Users</h5>
                <a href="users.php" class="small text-primary text-decoration-none">View All &rarr;</a>
            </div>

            <div class="d-flex flex-column gap-3">
                <?php foreach ($recent_users as $ru): ?>
                    <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <img src="../uploads/avatars/default_avatar.svg" class="avatar-img" style="width: 34px; height: 34px;" alt="Avatar">
                            <div>
                                <div class="fw-bold small"><?= htmlspecialchars($ru['name']) ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($ru['email']) ?></div>
                            </div>
                        </div>
                        <span class="badge bg-<?= $ru['role'] === 'admin' ? 'danger' : 'primary' ?>-subtle text-<?= $ru['role'] === 'admin' ? 'danger' : 'primary' ?> border" style="font-size: 0.65rem;">
                            <?= strtoupper($ru['role']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Initializations -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Notes Over Time Line Chart
    new Chart(document.getElementById('notesOverTimeChart'), {
        type: 'line',
        data: {
            labels: ['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
            datasets: [{
                label: 'Notes Uploaded',
                data: [12, 19, 32, 45, 68, 85],
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // 2. Downloads Bar Chart
    new Chart(document.getElementById('downloadsVolumeChart'), {
        type: 'bar',
        data: {
            labels: ['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
            datasets: [{
                label: 'Downloads',
                data: [80, 140, 260, 410, 520, 680],
                backgroundColor: '#10b981',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    // 3. Users Growth Chart
    new Chart(document.getElementById('usersGrowthChart'), {
        type: 'line',
        data: {
            labels: ['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
            datasets: [{
                label: 'Registered Students',
                data: [5, 14, 28, 42, 60, 78],
                borderColor: '#06b6d4',
                backgroundColor: 'rgba(6, 182, 212, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    // 4. Popular Categories Doughnut Chart
    new Chart(document.getElementById('popularCatsChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($cat_names) ?>,
            datasets: [{
                data: <?= json_encode($cat_note_counts) ?>,
                backgroundColor: ['#7c3aed', '#2563eb', '#10b981', '#f59e0b', '#ef4444']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
