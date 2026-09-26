<?php
$page_title = 'Study Group Details';
require_once __DIR__ . '/includes/auth.php';

$group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($group_id <= 0) {
    flash('danger', 'Invalid group specified.');
    header('Location: groups.php');
    exit;
}

$current_user_id = is_logged_in() ? (int)$_SESSION['user_id'] : 0;

try {
    // 1. Fetch group info
    $stmt = $pdo->prepare("SELECT 
                                g.*, 
                                u.name AS creator_name,
                                u.email AS creator_email,
                                u.profile_image AS creator_avatar
                           FROM groups g
                           JOIN users u ON g.created_by = u.id
                           WHERE g.id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();

    if (!$group) {
        flash('danger', 'Study group not found.');
        header('Location: groups.php');
        exit;
    }

    $is_creator = ($current_user_id === (int)$group['created_by']);

    // 2. Check membership
    $is_member = false;
    if ($current_user_id > 0) {
        $stmt_mem = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt_mem->execute([$group_id, $current_user_id]);
        $is_member = (bool)$stmt_mem->fetch();
    }

    // 3. Fetch all group members
    $stmt_members = $pdo->prepare("SELECT 
                                      gm.joined_at, 
                                      u.id, u.name, u.email, u.profile_image, u.role
                                   FROM group_members gm
                                   JOIN users u ON gm.user_id = u.id
                                   WHERE gm.group_id = ?
                                   ORDER BY gm.joined_at ASC");
    $stmt_members->execute([$group_id]);
    $members = $stmt_members->fetchAll();

    // 4. Fetch notes uploaded by group members or shared
    // Gather member IDs
    $member_ids = array_column($members, 'id');
    $group_notes = [];
    if (!empty($member_ids)) {
        $in_clause = implode(',', array_fill(0, count($member_ids), '?'));
        $stmt_notes = $pdo->prepare("SELECT 
                                        n.id, n.title, n.subject, n.description, n.file_name, n.file_size, 
                                        n.downloads, n.views, n.created_at,
                                        c.name AS category_name,
                                        u.name AS author_name,
                                        COUNT(DISTINCT l.id) AS likes_count,
                                        COALESCE(AVG(r.rating), 0) AS avg_rating
                                     FROM notes n
                                     JOIN categories c ON n.category_id = c.id
                                     JOIN users u ON n.user_id = u.id
                                     LEFT JOIN likes l ON n.id = l.note_id
                                     LEFT JOIN ratings r ON n.id = r.note_id
                                     WHERE n.user_id IN ({$in_clause}) AND n.status = 'approved'
                                     GROUP BY n.id
                                     ORDER BY n.created_at DESC
                                     LIMIT 15");
        $stmt_notes->execute($member_ids);
        $group_notes = $stmt_notes->fetchAll();
    }

    // 5. Fetch user notes that can be shared to this group
    $my_notes = [];
    if ($is_member) {
        $stmt_mn = $pdo->prepare("SELECT id, title, subject FROM notes WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC");
        $stmt_mn->execute([$current_user_id]);
        $my_notes = $stmt_mn->fetchAll();
    }

} catch (PDOException $e) {
    error_log("Group details error: " . $e->getMessage());
    flash('danger', 'Unable to retrieve group information.');
    header('Location: groups.php');
    exit;
}

$page_title = htmlspecialchars($group['name']);
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="groups.php">Study Groups</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($group['name']) ?></li>
        </ol>
    </nav>

    <!-- Group Header Card -->
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white mb-4">
        <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-purple-subtle text-purple border px-3 py-1 rounded-pill fw-bold">
                        <i class="bi bi-people-fill me-1"></i> Active Study Circle
                    </span>
                    <span class="text-muted small">Created on <?= date('F j, Y', strtotime($group['created_at'])) ?></span>
                </div>
                <h2 class="fw-bold mb-2"><?= htmlspecialchars($group['name']) ?></h2>
                <p class="text-muted mb-3" style="max-width: 650px; line-height: 1.6;">
                    <?= nl2br(htmlspecialchars($group['description'] ?: 'Study workspace for sharing course notes and problem-solving techniques.')) ?>
                </p>

                <div class="d-flex align-items-center gap-3 small text-muted">
                    <span>Admin/Creator: <strong><?= htmlspecialchars($group['creator_name']) ?></strong></span>
                    <span>&bull;</span>
                    <span>Total Members: <strong><?= count($members) ?></strong></span>
                </div>
            </div>

            <!-- Group Actions -->
            <div class="d-flex flex-column gap-2" style="min-width: 180px;">
                <?php if ($is_member): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shareToGroupModal">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Share Note Here
                    </button>
                    <form method="POST" action="leave_group.php" onsubmit="return confirm('Are you sure you want to leave this study group?');">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="bi bi-box-arrow-right me-1"></i> Leave Group
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="join_group.php">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="bi bi-person-plus-fill me-1"></i> Join Study Group
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Shared Notes in this Group -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
                    <h5 class="fw-bold mb-0"><i class="bi bi-journal-bookmark-fill text-primary me-2"></i>Study Notes From Members (<?= count($group_notes) ?>)</h5>
                    <?php if ($is_member): ?>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#shareToGroupModal">
                            + Share Note
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($group_notes)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-file-earmark-plus fs-1 text-secondary d-block mb-2"></i>
                        <p class="mb-3">No notes shared in this circle yet.</p>
                        <?php if ($is_member): ?>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#shareToGroupModal">Share the First Note</button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($group_notes as $n): ?>
                            <?php 
                                $ext = strtolower(pathinfo($n['file_name'], PATHINFO_EXTENSION));
                                $badge_class = 'file-badge-' . ($ext ?: 'txt');
                            ?>
                            <div class="col-md-6">
                                <div class="edu-card">
                                    <div class="edu-card-header pb-1">
                                        <span class="file-badge <?= $badge_class ?>"><?= strtoupper($ext) ?></span>
                                        <span class="category-pill"><?= htmlspecialchars($n['category_name']) ?></span>
                                    </div>
                                    <div class="edu-card-body">
                                        <div class="text-muted small fw-semibold mb-1"><?= htmlspecialchars($n['subject']) ?></div>
                                        <h6 class="fw-bold mb-2">
                                            <a href="note.php?id=<?= $n['id'] ?>" class="text-dark text-decoration-none">
                                                <?= htmlspecialchars($n['title']) ?>
                                            </a>
                                        </h6>
                                        <div class="d-flex align-items-center justify-content-between pt-2 border-top small text-muted">
                                            <span>By: <?= htmlspecialchars($n['author_name']) ?></span>
                                            <span><?= time_ago($n['created_at']) ?></span>
                                        </div>
                                    </div>
                                    <div class="edu-card-footer">
                                        <a href="note.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2">View</a>
                                        <a href="download.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-primary py-1 px-2"><i class="bi bi-download"></i></a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Group Members List -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-people text-purple me-2"></i>Group Members (<?= count($members) ?>)</h5>
                
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($members as $m): ?>
                        <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-light">
                            <div class="d-flex align-items-center gap-2">
                                <img src="uploads/avatars/default_avatar.svg" class="avatar-img" style="width: 36px; height: 36px;" alt="Avatar">
                                <div>
                                    <div class="fw-bold small text-dark"><?= htmlspecialchars($m['name']) ?></div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">Joined <?= time_ago($m['joined_at']) ?></div>
                                </div>
                            </div>
                            <?php if ((int)$m['id'] === (int)$group['created_by']): ?>
                                <span class="badge bg-warning-subtle text-warning border fw-bold" style="font-size: 0.65rem;">ADMIN</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border" style="font-size: 0.65rem;">MEMBER</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Share to Group -->
<?php if ($is_member): ?>
<div class="modal fade" id="shareToGroupModal" tabindex="-1" aria-labelledby="shareToGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="shareToGroupModalLabel"><i class="bi bi-cloud-arrow-up text-primary me-2"></i>Share Note with <?= htmlspecialchars($group['name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="share_note.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">

                    <div class="mb-3">
                        <label for="select_note" class="form-label small">Select One of Your Published Notes <span class="text-danger">*</span></label>
                        <select name="note_id" id="select_note" class="form-select form-select-sm" required>
                            <option value="">-- Choose Note --</option>
                            <?php foreach ($my_notes as $mn): ?>
                                <option value="<?= $mn['id'] ?>"><?= htmlspecialchars($mn['title']) ?> (<?= htmlspecialchars($mn['subject']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">Don't have the note uploaded yet? <a href="upload_note.php">Upload it here</a>.</div>
                    </div>

                    <div class="mb-2">
                        <label for="group_msg" class="form-label small">Optional Announcement to Members</label>
                        <textarea name="message" id="group_msg" class="form-control form-control-sm" rows="2" placeholder="Recommended reading for this week's discussion."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Broadcast to Group</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
