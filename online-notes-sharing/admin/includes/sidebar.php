<?php
/**
 * Admin Panel - Sidebar Navigation
 */
$admin_script = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Brand Info -->
    <div class="d-flex align-items-center gap-2 mb-4 px-2">
        <span class="brand-icon"><i class="bi bi-shield-check"></i></span>
        <div>
            <h5 class="text-white mb-0 fw-bold">NoteShare</h5>
            <small class="text-white-50" style="font-size: 0.75rem;">Admin Management</small>
        </div>
    </div>

    <!-- Navigation Items -->
    <nav class="nav flex-column flex-grow-1">
        <a href="dashboard.php" class="admin-nav-item <?= in_array($admin_script, ['index.php', 'dashboard.php']) ? 'active' : '' ?>">
            <i class="bi bi-speedometer2 fs-5"></i> Dashboard
        </a>
        <a href="users.php" class="admin-nav-item <?= in_array($admin_script, ['users.php', 'edit_user.php']) ? 'active' : '' ?>">
            <i class="bi bi-people-fill fs-5"></i> Users
        </a>
        <a href="notes.php" class="admin-nav-item <?= in_array($admin_script, ['notes.php', 'edit_note.php']) ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-text-fill fs-5"></i> Notes
        </a>
        <a href="categories.php" class="admin-nav-item <?= in_array($admin_script, ['categories.php', 'add_category.php', 'edit_category.php']) ? 'active' : '' ?>">
            <i class="bi bi-folder-fill fs-5"></i> Categories
        </a>
        <a href="groups.php" class="admin-nav-item <?= $admin_script === 'groups.php' ? 'active' : '' ?>">
            <i class="bi bi-collection-fill fs-5"></i> Study Groups
        </a>
        <a href="reports.php" class="admin-nav-item <?= $admin_script === 'reports.php' ? 'active' : '' ?> position-relative">
            <i class="bi bi-flag-fill fs-5"></i> Reports
            <?php if (!empty($pending_reports_count)): ?>
                <span class="badge rounded-pill bg-danger ms-auto"><?= $pending_reports_count ?></span>
            <?php endif; ?>
        </a>
        <a href="comments.php" class="admin-nav-item <?= $admin_script === 'comments.php' ? 'active' : '' ?>">
            <i class="bi bi-chat-dots-fill fs-5"></i> Comments
        </a>
        <a href="notifications.php" class="admin-nav-item <?= $admin_script === 'notifications.php' ? 'active' : '' ?>">
            <i class="bi bi-bell-fill fs-5"></i> Notifications
        </a>
        <a href="settings.php" class="admin-nav-item <?= $admin_script === 'settings.php' ? 'active' : '' ?>">
            <i class="bi bi-sliders fs-5"></i> Platform Settings
        </a>
        <a href="profile.php" class="admin-nav-item <?= $admin_script === 'profile.php' ? 'active' : '' ?>">
            <i class="bi bi-gear-fill fs-5"></i> Admin Profile
        </a>
    </nav>

    <!-- Bottom Actions -->
    <div class="border-top border-secondary pt-3 mt-3 px-2">
        <a href="../index.php" class="btn btn-outline-info w-100 btn-sm d-flex align-items-center justify-content-center gap-2 mb-2">
            <i class="bi bi-box-arrow-up-right"></i> View Main Site
        </a>
        <a href="logout.php" class="btn btn-outline-light w-100 btn-sm d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-box-arrow-right"></i> Sign Out
        </a>
    </div>
</aside>
