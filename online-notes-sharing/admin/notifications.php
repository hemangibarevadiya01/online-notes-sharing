<?php
$admin_title = 'System Notifications & Broadcast';
require_once __DIR__ . '/includes/header.php';

$errors = [];
$title = '';
$message = '';
$recipient_type = 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Security token expired.';
    }

    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $recipient_type = $_POST['recipient_type'] ?? 'all';
    $target_user_id = (int)($_POST['target_user_id'] ?? 0);

    if (empty($title)) $errors[] = 'Announcement title is required.';
    if (empty($message)) $errors[] = 'Announcement message cannot be empty.';

    if (empty($errors)) {
        try {
            if ($recipient_type === 'all') {
                $users = $pdo->query("SELECT id FROM users WHERE status = 'active'")->fetchAll();
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'announcement', 0, NOW())");
                foreach ($users as $u) {
                    $stmt->execute([$u['id'], $title, $message]);
                }
                flash('success', 'Announcement broadcasted to ' . count($users) . ' active users.');
            } else {
                if ($target_user_id > 0) {
                    create_notification($target_user_id, $title, $message, 'announcement');
                    flash('success', 'Direct notification dispatched to selected user.');
                } else {
                    $errors[] = 'Please select a recipient user.';
                }
            }

            if (empty($errors)) {
                header('Location: notifications.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Broadcast error: " . $e->getMessage());
            $errors[] = 'Failed to broadcast notification.';
        }
    }
}

// Fetch recent notifications
$recent_notifs = [];
try {
    $recent_notifs = $pdo->query("SELECT n.*, u.name AS user_name, u.email AS user_email 
                                 FROM notifications n 
                                 JOIN users u ON n.user_id = u.id 
                                 ORDER BY n.created_at DESC LIMIT 20")->fetchAll();
} catch (PDOException $e) {}

// Fetch user list for dropdown
$all_users = $pdo->query("SELECT id, name, email FROM users ORDER BY name ASC")->fetchAll();
?>

<div class="row g-4">
    <!-- Broadcast Form -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h5 class="fw-bold mb-3 pb-2 border-bottom"><i class="bi bi-broadcast text-primary me-2"></i>Send Notification</h5>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="notifications.php">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="mb-3">
                    <label class="form-label small">Recipient Scope</label>
                    <select name="recipient_type" class="form-select form-select-sm" id="recType" onchange="document.getElementById('userSelectBox').style.display = this.value === 'single' ? 'block' : 'none';">
                        <option value="all">Broadcast to All Students</option>
                        <option value="single">Send to Specific User</option>
                    </select>
                </div>

                <div class="mb-3" id="userSelectBox" style="display: none;">
                    <label class="form-label small">Select User</label>
                    <select name="target_user_id" class="form-select form-select-sm">
                        <option value="">-- Choose User --</option>
                        <?php foreach ($all_users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control form-control-sm" placeholder="e.g. Platform Maintenance / Midterm Upload Drive" value="<?= htmlspecialchars($title) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Message Content <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control form-control-sm" rows="4" placeholder="Enter the announcement text..." required><?= htmlspecialchars($message) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-sm w-100 py-2">
                    <i class="bi bi-send-fill me-1"></i> Send Notification
                </button>
            </form>
        </div>
    </div>

    <!-- Recent Notification Feed -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h5 class="fw-bold mb-3 pb-2 border-bottom">System Notification Log</h5>

            <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light text-muted">
                        <tr>
                            <th>Recipient</th>
                            <th>Title & Message</th>
                            <th>Type</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_notifs as $rn): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($rn['user_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($rn['user_email']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($rn['title']) ?></div>
                                    <div class="text-secondary text-truncate" style="max-width: 260px;"><?= htmlspecialchars($rn['message']) ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($rn['type']) ?></span></td>
                                <td class="text-muted"><?= time_ago($rn['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
