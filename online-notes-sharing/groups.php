<?php
$page_title = 'Study Groups';
require_once __DIR__ . '/includes/auth.php';

require_login('Please log in to access study groups.');
$current_user_id = (int)$_SESSION['user_id'];
$search_group = trim($_GET['q'] ?? '');

$params = [];
$where = "1=1";

if (!empty($search_group)) {
    $where = "(g.name LIKE :q1 OR g.description LIKE :q2 OR u.name LIKE :q3)";
    $params[':q1'] = "%{$search_group}%";
    $params[':q2'] = "%{$search_group}%";
    $params[':q3'] = "%{$search_group}%";
}

$groups = [];
try {
    $sql = "SELECT 
                g.id, g.name, g.description, g.created_by, g.created_at,
                u.name AS creator_name,
                COUNT(DISTINCT gm.user_id) AS members_count,
                (SELECT COUNT(*) FROM group_members WHERE group_id = g.id AND user_id = {$current_user_id}) AS is_member
            FROM groups g
            JOIN users u ON g.created_by = u.id
            LEFT JOIN group_members gm ON g.id = gm.group_id
            WHERE {$where}
            GROUP BY g.id
            ORDER BY members_count DESC, g.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $groups = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Groups list error: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-people-fill text-purple me-2"></i>Peer Study Groups</h2>
            <p class="text-muted small mb-0">Collaborate with fellow students, prepare for midterms, and share curated course notes</p>
        </div>
        <div>
            <a href="create_group.php" class="btn btn-primary">
                <i class="bi bi-plus-circle-fill me-1"></i> Create Study Group
            </a>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
        <form method="GET" action="groups.php" class="d-flex gap-2">
            <div class="input-group">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control border-start-0" placeholder="Search study groups by topic, course, or creator name..." value="<?= htmlspecialchars($search_group) ?>">
            </div>
            <button type="submit" class="btn btn-primary px-4">Search</button>
            <?php if (!empty($search_group)): ?>
                <a href="groups.php" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Groups Grid -->
    <div class="row g-4">
        <?php if (empty($groups)): ?>
            <div class="col-12 text-center py-5">
                <div class="p-5 bg-white rounded-4 border shadow-sm" style="max-width: 500px; margin: 0 auto;">
                    <i class="bi bi-collection text-muted" style="font-size: 3.5rem;"></i>
                    <h4 class="fw-bold mt-3">No Study Groups Found</h4>
                    <p class="text-muted small">Start the first study group for your course or exam preparation!</p>
                    <a href="create_group.php" class="btn btn-primary btn-sm px-4">Create a Group Now</a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($groups as $g): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="edu-card">
                        <div class="edu-card-header pb-2">
                            <span class="badge bg-purple-subtle text-purple border rounded-pill px-3 py-1 fw-bold">
                                <i class="bi bi-mortarboard me-1"></i> Academic Circle
                            </span>
                            <span class="text-muted small">
                                <i class="bi bi-people-fill text-primary me-1"></i> <strong><?= $g['members_count'] ?></strong> member(s)
                            </span>
                        </div>

                        <div class="edu-card-body">
                            <h5 class="fw-bold mb-2">
                                <a href="group.php?id=<?= $g['id'] ?>" class="text-dark text-decoration-none">
                                    <?= htmlspecialchars($g['name']) ?>
                                </a>
                            </h5>
                            <p class="text-muted small mb-3 text-truncate-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 42px;">
                                <?= htmlspecialchars($g['description'] ?: 'Collaborative student workspace for notes exchange and discussions.') ?>
                            </p>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top small text-muted">
                                <span>Leader: <strong><?= htmlspecialchars($g['creator_name']) ?></strong></span>
                                <span>Created <?= time_ago($g['created_at']) ?></span>
                            </div>
                        </div>

                        <div class="edu-card-footer">
                            <a href="group.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View Group
                            </a>

                            <?php if ($current_user_id > 0): ?>
                                <?php if (!empty($g['is_member'])): ?>
                                    <span class="badge bg-success-subtle text-success border py-2 px-3">
                                        <i class="bi bi-check2 me-1"></i> Member
                                    </span>
                                <?php else: ?>
                                    <form method="POST" action="join_group.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-person-plus-fill me-1"></i> Join Group
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="login.php?redirect=group.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    Sign In to Join
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
