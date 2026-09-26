<?php
/**
 * Online Notes Sharing Platform - Header Component
 */
if (!isset($page_title)) {
    $page_title = 'Online Notes Sharing Platform';
} else {
    $page_title = htmlspecialchars($page_title) . ' | Online Notes Sharing';
}

require_once __DIR__ . '/auth.php';
$logged_in = is_logged_in();
$user = $logged_in ? current_user() : null;

// Calculate unread notification count
$unread_notifications_count = 0;
if ($logged_in && isset($user['id'])) {
    try {
        $stmt_notif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt_notif->execute([$user['id']]);
        $unread_notifications_count = (int)$stmt_notif->fetchColumn();
    } catch (PDOException $e) {
        // Silently continue
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Custom Theme Stylesheet (Blue, White, Purple Educational Theme) -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>

<main>
    <div class="container py-2">
        <?php display_flash(); ?>
    </div>
