<?php
$page_title = 'User Profile';
require_once __DIR__ . '/includes/auth.php';

require_login('Please log in to view user profiles.');
$current_user_id = (int)$_SESSION['user_id'];
$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : $current_user_id;

if ($profile_id <= 0) {
    flash('warning', 'Please sign in to view your profile.');
    header('Location: login.php');
    exit;
}

try {
    // 1. Fetch user profile
    $stmt = $pdo->prepare("SELECT id, name, email, profile_image, role, status, created_at FROM users WHERE id = ?");
    $stmt->execute([$profile_id]);
    $profile_user = $stmt->fetch();

    if (!$profile_user) {
        flash('danger', 'User profile not found.');
        header('Location: notes.php');
        exit;
    }

    $is_self = ($current_user_id === (int)$profile_user['id']);

    // 2. Fetch User Metrics (Uploaded notes, downloads, likes, saved notes)
    // Uploaded notes & downloads
    $stmt_up = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(downloads), 0) FROM notes WHERE user_id = ? AND status = 'approved'");
    $stmt_up->execute([$profile_id]);
    $row_up = $stmt_up->fetch(PDO::FETCH_NUM);
    $total_uploaded_notes = (int)$row_up[0];
    $total_downloads = (int)$row_up[1];

    // Total Likes on User's Notes
    $stmt_lk = $pdo->prepare("SELECT COUNT(l.id) FROM likes l JOIN notes n ON l.note_id = n.id WHERE n.user_id = ?");
    $stmt_lk->execute([$profile_id]);
    $total_likes = (int)$stmt_lk->fetchColumn();

    // Saved Notes count (for this user)
    $stmt_sv = $pdo->prepare("SELECT COUNT(*) FROM saved_notes WHERE user_id = ?");
    $stmt_sv->execute([$profile_id]);
    $total_saved_notes = (int)$stmt_sv->fetchColumn();

    // 3. Fetch Knowledge Impact and Milestone Badges
    $user_impact = calculate_knowledge_impact($profile_id);
    $profile_badges = get_user_badges_full($profile_id);

    // 4. Fetch User's Notes List
    $stmt_notes = $pdo->prepare("SELECT 
                                    n.*, 
                                    c.name AS category_name,
                                    COUNT(DISTINCT l.id) AS likes_count,
                                    COALESCE(AVG(r.rating), 0) AS avg_rating,
                                    (SELECT COUNT(*) FROM saved_notes sn WHERE sn.note_id = n.id AND sn.user_id = :uid) AS is_saved
                                 FROM notes n
                                 JOIN categories c ON n.category_id = c.id
                                 LEFT JOIN likes l ON n.id = l.note_id
                                 LEFT JOIN ratings r ON n.id = r.note_id
                                 WHERE n.user_id = :prof_id AND n.status = 'approved'
                                 GROUP BY n.id
                                 ORDER BY n.created_at DESC");
    $stmt_notes->execute([':uid' => $current_user_id, ':prof_id' => $profile_id]);
    $user_notes = $stmt_notes->fetchAll();

} catch (PDOException $e) {
    error_log("Profile error: " . $e->getMessage());
    flash('danger', 'Error loading profile.');
    header('Location: notes.php');
    exit;
}

$page_title = htmlspecialchars($profile_user['name']) . ' - Profile';
$avatar_src = (!empty($profile_user['profile_image']) && file_exists(__DIR__ . '/uploads/avatars/' . $profile_user['profile_image'])) 
    ? 'uploads/avatars/' . htmlspecialchars($profile_user['profile_image']) 
    : 'uploads/avatars/default_avatar.svg';

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Profile Hero Card -->
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4">
        <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start gap-4 text-center text-md-start">
            <img src="<?= $avatar_src ?>" class="avatar-lg" style="width: 104px; height: 104px;" alt="Avatar">

            <div class="flex-grow-1">
                <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <h3 class="fw-bold mb-0"><?= htmlspecialchars($profile_user['name']) ?></h3>
                            <span class="badge bg-primary-subtle text-primary border text-uppercase" style="font-size: 0.72rem;">
                                <?= htmlspecialchars($profile_user['role']) ?>
                            </span>
                            <span class="badge <?= $user_impact['level_badge'] ?> text-uppercase" style="font-size: 0.72rem;">
                                <i class="bi bi-lightning-charge-fill me-1"></i><?= htmlspecialchars($user_impact['level']) ?> (<?= number_format($user_impact['score']) ?> pts)
                            </span>
                        </div>
                    </div>

                    <?php if ($is_self): ?>
                        <a href="edit_profile.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil me-1"></i> Edit Profile
                        </a>
                    <?php endif; ?>
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-3 small text-muted mb-3">
                    <?php if ($is_self || is_admin()): ?>
                        <span><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($profile_user['email']) ?></span>
                        <span>&bull;</span>
                    <?php endif; ?>
                    <span><i class="bi bi-calendar-check me-1"></i> Joined <?= date('F j, Y', strtotime($profile_user['created_at'])) ?></span>
                </div>

                <!-- 4 Metrics Display -->
                <div class="row g-2 pt-3 border-top text-center text-md-start">
                    <div class="col-6 col-md-3">
                        <span class="text-muted small d-block">Uploaded Notes</span>
                        <strong class="fs-5 text-dark"><?= $total_uploaded_notes ?></strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="text-muted small d-block">Total Downloads</span>
                        <strong class="fs-5 text-success"><?= number_format($total_downloads) ?></strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="text-muted small d-block">Total Likes</span>
                        <strong class="fs-5 text-danger"><?= number_format($total_likes) ?></strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="text-muted small d-block">Saved Bookmarks</span>
                        <strong class="fs-5 text-warning"><?= $total_saved_notes ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Milestone Badges for this Student -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
            <div>
                <h5 class="fw-bold mb-0"><i class="bi bi-award-fill text-warning me-2"></i> Academic Milestone Badges</h5>
                <p class="text-muted small mb-0">Milestones achieved by this scholar through community contributions and learning missions.</p>
            </div>
            <span class="badge bg-purple-subtle text-purple border px-3 py-1 rounded-pill fw-semibold">
                <?php 
                    $u_count = 0;
                    foreach ($profile_badges as $pb) { if (!empty($pb['is_unlocked'])) $u_count++; }
                    echo $u_count . ' of ' . count($profile_badges) . ' Unlocked';
                ?>
            </span>
        </div>

        <div class="row g-3">
            <?php foreach ($profile_badges as $badge): ?>
                <?php $is_u = !empty($badge['is_unlocked']); ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="badge-item-card <?= $is_u ? 'unlocked' : 'locked' ?>" title="<?= htmlspecialchars($badge['description']) ?>">
                        <div class="badge-icon-bubble">
                            <?= $badge['symbol'] ?: '🏆' ?>
                        </div>
                        <h6 class="fw-bold mb-1 small text-truncate" title="<?= htmlspecialchars($badge['title']) ?>">
                            <?= htmlspecialchars($badge['title']) ?>
                        </h6>
                        <p class="text-muted mb-2 text-truncate-2" style="font-size: 0.72rem; height: 32px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                            <?= htmlspecialchars($badge['description']) ?>
                        </p>
                        <?php if ($is_u): ?>
                            <span class="badge bg-success-subtle text-success border px-2 py-1 rounded-pill" style="font-size: 0.65rem;">
                                <i class="bi bi-check-circle-fill me-1"></i> <?= !empty($badge['awarded_at']) ? date('M j, Y', strtotime($badge['awarded_at'])) : 'Unlocked' ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 rounded-pill" style="font-size: 0.65rem;">
                                <i class="bi bi-lock-fill me-1"></i> Locked
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Notes Published by this User -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="fw-bold mb-0">Published Notes (<?= count($user_notes) ?>)</h4>
    </div>

    <div class="row g-4">
        <?php if (empty($user_notes)): ?>
            <div class="col-12 text-center py-5">
                <div class="p-5 bg-white rounded-4 border shadow-sm" style="max-width: 480px; margin: 0 auto;">
                    <i class="bi bi-journal-x text-muted" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold mt-3">No Notes Published Yet</h5>
                    <p class="text-muted small">This student contributor has not published any study documents yet.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($user_notes as $n): ?>
                <?php 
                    $ext = strtolower(pathinfo($n['file_name'], PATHINFO_EXTENSION));
                    $badge_class = 'file-badge-' . ($ext ?: 'txt');
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="edu-card">
                        <div class="edu-card-header">
                            <span class="file-badge <?= $badge_class ?>"><?= strtoupper($ext) ?></span>
                            <span class="category-pill"><?= htmlspecialchars($n['category_name']) ?></span>
                        </div>
                        <div class="edu-card-body">
                            <div class="text-muted small fw-semibold mb-1"><?= htmlspecialchars($n['subject']) ?></div>
                            <h5 class="fw-bold mb-2">
                                <a href="note.php?id=<?= $n['id'] ?>" class="text-dark text-decoration-none">
                                    <?= htmlspecialchars($n['title']) ?>
                                </a>
                            </h5>
                            <p class="text-muted small mb-3 text-truncate-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 42px;">
                                <?= htmlspecialchars($n['description'] ?: 'No description provided.') ?>
                            </p>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top small text-muted">
                                <span>Uploaded <?= time_ago($n['created_at']) ?></span>
                                <span><i class="bi bi-download text-primary me-1"></i><?= $n['downloads'] ?></span>
                            </div>
                        </div>
                        <div class="edu-card-footer">
                            <button class="btn btn-sm <?= !empty($n['is_saved']) ? 'btn-warning' : 'btn-outline-secondary' ?> btn-save-note p-1 px-2" data-note-id="<?= $n['id'] ?>">
                                <i class="bi <?= !empty($n['is_saved']) ? 'bi-bookmark-fill' : 'bi-bookmark' ?>"></i>
                            </button>
                            <a href="note.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-3">View Note</a>
                            <a href="download.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-primary py-1 px-3"><i class="bi bi-download"></i></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
