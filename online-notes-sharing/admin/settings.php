<?php
/**
 * Admin Panel — Platform Configuration & Settings
 */
$admin_title = 'Platform Settings';
require_once __DIR__ . '/includes/header.php';

$errors = [];

// Handle POST save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid request security token.';
    } else {
        $fields = [
            'platform_name' => trim($_POST['platform_name'] ?? 'NoteShare — Online Notes Sharing Platform'),
            'platform_description' => trim($_POST['platform_description'] ?? ''),
            'allowed_file_types' => trim($_POST['allowed_file_types'] ?? 'pdf,doc,docx,ppt,pptx,zip'),
            'max_file_size_mb' => (string)max(1, (int)($_POST['max_file_size_mb'] ?? 25)),
            'admin_passkey' => trim($_POST['admin_passkey'] ?? 'ADMIN2026'),
            'contact_email' => trim($_POST['contact_email'] ?? 'support@noteshare.edu'),
            'enable_study_missions' => isset($_POST['enable_study_missions']) ? '1' : '0'
        ];

        try {
            $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($fields as $key => $val) {
                $stmt->execute([$key, $val]);
            }
            flash('success', 'Platform settings updated successfully.');
            header('Location: settings.php');
            exit;
        } catch (PDOException $e) {
            error_log("Settings update error: " . $e->getMessage());
            $errors[] = 'Database error while saving platform settings.';
        }
    }
}

// Fetch current settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM admin_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Settings fetch error: " . $e->getMessage());
}

$platform_name = $settings['platform_name'] ?? 'NoteShare — Online Notes Sharing Platform';
$platform_description = $settings['platform_description'] ?? 'A collaborative student-driven academic repository for discovering, previewing, and exchanging lecture notes and study resources.';
$allowed_file_types = $settings['allowed_file_types'] ?? 'pdf,doc,docx,ppt,pptx,zip';
$max_file_size_mb = $settings['max_file_size_mb'] ?? '25';
$admin_passkey = $settings['admin_passkey'] ?? 'ADMIN2026';
$contact_email = $settings['contact_email'] ?? 'support@noteshare.edu';
$enable_study_missions = ($settings['enable_study_missions'] ?? '1') === '1';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Admin</a></li>
                <li class="breadcrumb-item active" aria-current="page">Platform Settings</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0">System Configuration & Parameters</h3>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2 small mb-3">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white" style="max-width: 800px;">
    <form method="POST" action="settings.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-sliders text-primary me-2"></i>General Platform Info</h5>
        
        <div class="mb-3">
            <label for="platformName" class="form-label small fw-semibold">Platform Brand Name</label>
            <input type="text" class="form-control" id="platformName" name="platform_name" value="<?= htmlspecialchars($platform_name) ?>" required>
            <div class="form-text small">Rendered across website headers and document previews.</div>
        </div>

        <div class="mb-4">
            <label for="platformDesc" class="form-label small fw-semibold">Platform Description / Mission</label>
            <textarea class="form-control" id="platformDesc" name="platform_description" rows="3" required><?= htmlspecialchars($platform_description) ?></textarea>
            <div class="form-text small">Displayed on hero landing banners and footer metadata.</div>
        </div>

        <h5 class="fw-bold text-dark mb-3 pt-3 border-top"><i class="bi bi-file-earmark-check text-purple me-2"></i>File Upload Rules</h5>

        <div class="row g-3 mb-4">
            <div class="col-md-7">
                <label for="fileTypes" class="form-label small fw-semibold">Supported File Extensions</label>
                <input type="text" class="form-control" id="fileTypes" name="allowed_file_types" value="<?= htmlspecialchars($allowed_file_types) ?>" required>
                <div class="form-text small">Comma-separated allowed extensions (e.g. <code>pdf,doc,docx,ppt,pptx,zip</code>).</div>
            </div>
            <div class="col-md-5">
                <label for="maxSize" class="form-label small fw-semibold">Max File Upload Size (MB)</label>
                <div class="input-group">
                    <input type="number" class="form-control" id="maxSize" name="max_file_size_mb" value="<?= htmlspecialchars($max_file_size_mb) ?>" min="1" max="100" required>
                    <span class="input-group-text bg-light">MB</span>
                </div>
                <div class="form-text small">Maximum allowed single file size.</div>
            </div>
        </div>

        <h5 class="fw-bold text-dark mb-3 pt-3 border-top"><i class="bi bi-shield-lock-fill text-warning me-2"></i>Security & Administration</h5>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label for="adminPasskey" class="form-label small fw-semibold">Admin Registration Passkey</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-key-fill text-warning"></i></span>
                    <input type="text" class="form-control" id="adminPasskey" name="admin_passkey" value="<?= htmlspecialchars($admin_passkey) ?>" required>
                </div>
                <div class="form-text small">Secret authorization passkey required to register new administrator accounts.</div>
            </div>
            <div class="col-md-6">
                <label for="contactEmail" class="form-label small fw-semibold">Platform Contact Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" id="contactEmail" name="contact_email" value="<?= htmlspecialchars($contact_email) ?>" required>
                </div>
                <div class="form-text small">Receives notifications and shown on the Contact page.</div>
            </div>
        </div>

        <h5 class="fw-bold text-dark mb-3 pt-3 border-top"><i class="bi bi-trophy text-success me-2"></i>Unique Feature Settings</h5>

        <div class="mb-4">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="enableMissions" name="enable_study_missions" <?= $enable_study_missions ? 'checked' : '' ?>>
                <label class="form-check-label small fw-semibold" for="enableMissions">
                    Enable Smart Study Mission & Knowledge Impact System
                </label>
            </div>
            <div class="form-text small">Enables daily goal tracking, circular progress indicators, and automated milestone badges for students.</div>
        </div>

        <div class="pt-3 border-top d-flex gap-2">
            <button type="submit" class="btn btn-primary px-4 py-2">
                <i class="bi bi-check2-circle me-1"></i> Save Configuration
            </button>
            <a href="dashboard.php" class="btn btn-outline-secondary px-4 py-2">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
