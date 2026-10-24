<?php
/**
 * Online Notes Sharing Platform - Responsive Navigation Bar
 */
$current_script = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <!-- Brand Logo -->
        <a class="navbar-brand" href="index.php">
            <span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
            <span>Note<span class="text-gradient">Share</span></span>
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <!-- Global Search Form -->
            <form class="d-flex mx-lg-3 my-2 my-lg-0 flex-grow-1" style="max-width: 380px;" action="search.php" method="GET">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted ps-3">
                        <i class="bi bi-search"></i>
                    </span>
                    <input class="form-control bg-light border-start-0 ps-0" type="search" name="q" placeholder="Search notes by title, subject, or author..." aria-label="Search" value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>">
                </div>
            </form>

            <!-- Navigation Links -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $current_script === 'notes.php' ? 'active' : '' ?>" href="notes.php">
                        <i class="bi bi-journal-text me-1"></i> Browse
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_script === 'categories.php' ? 'active' : '' ?>" href="categories.php">
                        <i class="bi bi-grid-fill me-1"></i> Categories
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_script === 'trending.php' ? 'active' : '' ?>" href="trending.php">
                        <i class="bi bi-fire text-danger me-1"></i> Trending
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= in_array($current_script, ['groups.php', 'group.php']) ? 'active' : '' ?>" href="groups.php">
                        <i class="bi bi-people-fill me-1"></i> Groups
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_script === 'about.php' ? 'active' : '' ?>" href="about.php">
                        About
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_script === 'contact.php' ? 'active' : '' ?>" href="contact.php">
                        Contact
                    </a>
                </li>
            </ul>

            <!-- User Authentication / Profile Section -->
            <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
                <?php if (is_logged_in() && $user): ?>
                    <!-- Quick Upload Button -->
                    <a href="upload_note.php" class="btn btn-sm btn-primary text-nowrap">
                        <i class="bi bi-cloud-arrow-up-fill"></i> Upload Note
                    </a>

                    <!-- Notification Bell -->
                    <a href="notifications.php" class="btn btn-sm btn-outline-secondary position-relative border-0 rounded-circle p-2" title="Notifications">
                        <i class="bi bi-bell-fill fs-5 text-muted"></i>
                        <?php if ($unread_notifications_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                                <?= $unread_notifications_count > 9 ? '9+' : $unread_notifications_count ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- User Dropdown Menu -->
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="uploads/avatars/default_avatar.svg" alt="Avatar" class="avatar-img me-2">
                            <span class="fw-semibold d-none d-md-inline"><?= htmlspecialchars($user['name']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 p-2" aria-labelledby="userDropdown" style="min-width: 220px;">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold text-truncate"><?= htmlspecialchars($user['name']) ?></div>
                                <div class="small text-muted text-truncate"><?= htmlspecialchars($user['email']) ?></div>
                                <span class="badge bg-primary-subtle text-primary border mt-1 text-uppercase" style="font-size: 0.65rem;">
                                    <?= htmlspecialchars($user['role']) ?>
                                </span>
                            </li>
                            <li>
                                <a class="dropdown-item rounded-2 mt-1 py-2" href="dashboard.php">
                                    <i class="bi bi-speedometer2 me-2 text-primary"></i> Dashboard
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item rounded-2 py-2" href="my_notes.php">
                                    <i class="bi bi-journal-check me-2 text-info"></i> My Uploaded Notes
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item rounded-2 py-2" href="profile.php">
                                    <i class="bi bi-person-circle me-2 text-purple"></i> My Profile & Badges
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item rounded-2 py-2" href="saved_notes.php">
                                    <i class="bi bi-bookmark-fill me-2 text-warning"></i> Saved Notes
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item rounded-2 py-2 text-danger" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </a>
                    <a href="register.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-person-plus-fill"></i> Get Started
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
