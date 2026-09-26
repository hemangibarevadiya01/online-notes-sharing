<?php
$page_title = 'My Notifications';
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = current_user();

// Handle Mark All Read action
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'read_all') {
        try {
            $stmt_r = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt_r->execute([$user['id']]);
            flash('success', 'All notifications marked as read.');
        } catch (PDOException $e) {}
        header('Location: notifications.php');
        exit;
    } elseif ($_GET['action'] === 'mark_read' && isset($_GET['id'])) {
        $notif_id = (int)$_GET['id'];
        try {
            $stmt_one = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt_one->execute([$notif_id, $user['id']]);
        } catch (PDOException $e) {}
        header('Location: notifications.php');
        exit;
    } elseif ($_GET['action'] === 'delete' && isset($_GET['id'])) {
        $notif_id = (int)$_GET['id'];
        try {
            $stmt_del = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt_del->execute([$notif_id, $user['id']]);
            flash('info', 'Notification removed.');
        } catch (PDOException $e) {}
        header('Location: notifications.php');
        exit;
    }
}

// Filter mode
$filter = $_GET['filter'] ?? 'all';
$condition = ($filter === 'unread') ? " AND is_read = 0" : "";

// Fetch notifications
$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? {$condition} ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll();

    // Count unread
    $stmt_un = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt_un->execute([$user['id']]);
    $unread_count = (int)$stmt_un->fetchColumn();
} catch (PDOException $e) {
    error_log("Notifications error: " . $e->getMessage());
    $unread_count = 0;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4" style="max-width: 850px;">
    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-bell-fill text-primary me-2"></i>Notifications Center</h2>
            <p class="text-muted small mb-0">Stay informed about note likes, peer ratings, comments, and study group interactions</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($unread_count > 0): ?>
                <a href="notifications.php?action=read_all" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-check2-all me-1"></i> Mark All as Read
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="d-flex gap-2 mb-3">
        <a href="notifications.php?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-light border text-muted' ?>">
            All Notifications
        </a>
        <a href="notifications.php?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-light border text-muted' ?>">
            Unread Only <?= $unread_count > 0 ? "({$unread_count})" : "" ?>
        </a>
    </div>

    <!-- Notification Feed Card -->
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-bell-slash fs-1 text-secondary d-block mb-3"></i>
                <h5 class="fw-bold">No Notifications Found</h5>
                <p class="small text-muted mb-0">
                    <?= ($filter === 'unread') ? 'You have read all your notifications!' : 'Activity on your uploaded notes and study groups will be displayed here.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $n): ?>
                    <?php 
                        $icon = match($n['type']) {
                            'like'        => 'bi-heart-fill text-danger',
                            'rating'      => 'bi-star-fill text-warning',
                            'comment'     => 'bi-chat-dots-fill text-purple',
                            'note_share'  => 'bi-send-fill text-info',
                            'group_share' => 'bi-share-fill text-primary',
                            'group_join'  => 'bi-person-plus-fill text-success',
                            'note_upload' => 'bi-cloud-check-fill text-success',
                            default       => 'bi-info-circle-fill text-primary'
                        };
                    ?>
                    <div class="list-group-item p-3 border-0 rounded-3 mb-2 <?= empty($n['is_read']) ? 'bg-primary-subtle border-start border-4 border-primary' : 'bg-light' ?>">
                        <div class="d-flex align-items-start gap-3">
                            <div class="fs-4 mt-1">
                                <i class="bi <?= $icon ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($n['title']) ?></h6>
                                    <span class="small text-muted" style="font-size: 0.75rem;">
                                        <i class="bi bi-clock me-1"></i><?= time_ago($n['created_at']) ?>
                                    </span>
                                </div>
                                <p class="text-secondary small mb-2"><?= htmlspecialchars($n['message']) ?></p>
                                
                                <div class="d-flex align-items-center gap-3">
                                    <?php if (!empty($n['note_id'])): ?>
                                        <a href="note.php?id=<?= $n['note_id'] ?>" class="btn btn-sm btn-link p-0 text-primary fw-semibold text-decoration-none small">
                                            View Related Note &rarr;
                                        </a>
                                    <?php endif; ?>

                                    <?php if (empty($n['is_read'])): ?>
                                        <a href="notifications.php?action=mark_read&id=<?= $n['id'] ?>" class="btn btn-sm btn-link p-0 text-secondary text-decoration-none small">
                                            <i class="bi bi-check2"></i> Mark as Read
                                        </a>
                                    <?php endif; ?>

                                    <a href="notifications.php?action=delete&id=<?= $n['id'] ?>" class="btn btn-sm btn-link p-0 text-danger text-decoration-none small ms-auto" title="Delete Notification" onclick="return confirm('Dismiss this notification?');">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
