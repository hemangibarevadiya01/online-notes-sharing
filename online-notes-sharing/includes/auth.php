<?php
/**
 * Online Notes Sharing Platform - Authentication & Session Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Check if the user is currently logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if current logged in user is an administrator
 */
function is_admin(): bool {
    return is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Enforce that the user must be logged in to view the page
 */
function require_login(string $redirect_to = ''): void {
    if (!is_logged_in()) {
        $target = $redirect_to ?: $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
        flash('warning', 'Please sign in to access this page.');
        header('Location: login.php?redirect=' . urlencode($target));
        exit;
    }
}

/**
 * Enforce that the current user must have admin privileges
 */
function require_admin(): void {
    if (!is_logged_in()) {
        flash('danger', 'Administrator access required. Please log in.');
        header('Location: ../admin/login.php');
        exit;
    }

    if (!is_admin()) {
        flash('danger', 'Access denied. You do not have administrator permissions.');
        header('Location: ../dashboard.php');
        exit;
    }
}

/**
 * Get current logged in user details from database
 */
function current_user(): ?array {
    global $pdo;
    if (!is_logged_in()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, role, profile_image, status, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || $user['status'] === 'blocked') {
            // Log out blocked or deleted user immediately
            session_unset();
            session_destroy();
            return null;
        }
        return $user;
    } catch (PDOException $e) {
        error_log("Error fetching current user: " . $e->getMessage());
        return null;
    }
}

/**
 * Generate or retrieve CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function verify_csrf(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize string input
 */
function sanitize(?string $data): string {
    if ($data === null) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Set a session flash message
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash message
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render flash alert in Bootstrap 5 style
 */
function display_flash(): void {
    $flash = get_flash();
    if ($flash) {
        $type = htmlspecialchars($flash['type']);
        $msg = htmlspecialchars($flash['message']);
        $icon = match ($type) {
            'success' => 'bi-check-circle-fill',
            'danger' => 'bi-exclamation-octagon-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            default => 'bi-info-circle-fill'
        };
        echo "<div class='alert alert-{$type} alert-dismissible fade show d-flex align-items-center shadow-sm my-3' role='alert'>
                <i class='bi {$icon} me-2 fs-5'></i>
                <div class='flex-grow-1'>{$msg}</div>
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
}

/**
 * Format bytes into human-readable size
 */
function format_bytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Format time to "time ago" string
 */
function time_ago(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $time);
}

/**
 * Create a system notification
 */
function create_notification(int $user_id, string $title, string $message, string $type = 'general', ?int $note_id = null): bool {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, note_id, is_read) VALUES (?, ?, ?, ?, ?, 0)");
        return $stmt->execute([$user_id, $title, $message, $type, $note_id]);
    } catch (PDOException $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}
