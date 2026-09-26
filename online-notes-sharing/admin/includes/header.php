<?php
/**
 * Admin Panel - Global Header Component
 */
if (!isset($admin_title)) {
    $admin_title = 'Admin Dashboard';
}

require_once __DIR__ . '/auth.php';
$admin_user = current_user();

// Fetch badge counters for pending reports
$pending_reports_count = 0;
try {
    $stmt_rep = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
    $pending_reports_count = (int)$stmt_rep->fetchColumn();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($admin_title) ?> | Admin Portal</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Theme CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="d-flex">
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <div class="admin-content flex-grow-1">
        <!-- Top Navigation Bar for Admin -->
        <header class="d-flex align-items-center justify-content-between pb-3 mb-4 border-bottom bg-white p-3 rounded-3 shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-secondary d-lg-none" type="button" id="sidebarToggle" aria-label="Toggle Sidebar">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <h4 class="mb-0 fw-bold"><?= htmlspecialchars($admin_title) ?></h4>
            </div>

            <div class="d-flex align-items-center gap-3">
                <a href="../index.php" class="btn btn-sm btn-outline-primary" target="_blank">
                    <i class="bi bi-box-arrow-up-right me-1"></i> View Site
                </a>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 border" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-5 text-purple"></i>
                        <span class="fw-semibold small d-none d-sm-inline"><?= htmlspecialchars($admin_user['name'] ?? 'Admin') ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2">
                        <li><a class="dropdown-item rounded-2" href="profile.php"><i class="bi bi-person me-2"></i> Profile</a></li>
                        <li><a class="dropdown-item rounded-2" href="../dashboard.php"><i class="bi bi-person-badge me-2"></i> User Dashboard</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item rounded-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Flash alerts in Admin -->
        <?php display_flash(); ?>
