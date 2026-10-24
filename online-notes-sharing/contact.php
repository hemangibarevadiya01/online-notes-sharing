<?php
/**
 * NoteShare — Contact & Support Page
 * Saves contact inquiries directly into the contact_messages database table.
 */
$page_title = 'Contact Support & Inquiries';
require_once __DIR__ . '/includes/auth.php';

$errors = [];
$name = '';
$email = '';
$subject = '';
$message = '';
$success = false;

if (is_logged_in() && $user = current_user()) {
    $name = $user['name'] ?? '';
    $email = $user['email'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = 'Invalid request security token. Please try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name)) {
        $errors[] = 'Your name is required.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if (empty($subject)) {
        $errors[] = 'Please specify a subject.';
    }
    if (empty($message) || strlen($message) < 10) {
        $errors[] = 'Your message must be at least 10 characters long.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message, status, created_at) VALUES (?, ?, ?, ?, 'unread', NOW())");
            $stmt->execute([$name, $email, $subject, $message]);

            flash('success', 'Thank you! Your message has been received by our platform administration team. We will respond to your email promptly.');
            header('Location: contact.php');
            exit;
        } catch (PDOException $e) {
            error_log("Contact message error: " . $e->getMessage());
            $errors[] = 'Failed to submit your message due to a database error. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Contact Support</li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Contact Form Column -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white h-100">
                <div class="mb-4">
                    <span class="badge bg-primary-subtle text-primary px-3 py-1 rounded-pill fw-semibold text-uppercase small mb-2">
                        Get In Touch
                    </span>
                    <h2 class="fw-bold text-dark mb-1">Contact NoteShare Team</h2>
                    <p class="text-muted small mb-0">Have questions regarding study notes, copyright inquiries, or platform feedback? Send us a message.</p>
                </div>

                <?php display_flash(); ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2 small mb-3">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="contact.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label small fw-semibold">Your Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control border-start-0" id="name" name="name" value="<?= htmlspecialchars($name) ?>" placeholder="e.g. Hemangi Patel" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label small fw-semibold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control border-start-0" id="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="you@university.edu" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="subject" class="form-label small fw-semibold">Subject / Topic</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-chat-left-dots"></i></span>
                            <input type="text" class="form-control border-start-0" id="subject" name="subject" value="<?= htmlspecialchars($subject) ?>" placeholder="e.g. Inquiry regarding Computer Science notes" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="message" class="form-label small fw-semibold">Your Message</label>
                        <textarea class="form-control" id="message" name="message" rows="5" placeholder="Write your detailed inquiry or feedback here..." required><?= htmlspecialchars($message) ?></textarea>
                        <div class="form-text small">Please provide sufficient context so our team can assist you efficiently.</div>
                    </div>

                    <button type="submit" class="btn btn-primary px-4 py-2 w-100">
                        <i class="bi bi-send-fill me-2"></i> Submit Inquiry
                    </button>
                </form>
            </div>
        </div>

        <!-- Information Column -->
        <div class="col-lg-5">
            <div class="d-flex flex-column gap-3">
                <!-- Academic Support Info -->
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <h5 class="fw-bold mb-3 text-dark">Support Channels</h5>
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="brand-icon flex-shrink-0" style="width: 40px; height: 40px; font-size: 1.1rem; background: rgba(79, 70, 229, 0.1); color: #4F46E5;">
                            <i class="bi bi-envelope-at-fill"></i>
                        </div>
                        <div>
                            <div class="fw-semibold small text-dark">Email Inquiries</div>
                            <small class="text-muted">support@noteshare.edu</small>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="brand-icon flex-shrink-0" style="width: 40px; height: 40px; font-size: 1.1rem; background: rgba(16, 185, 129, 0.1); color: #10B981;">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                            <div class="fw-semibold small text-dark">Academic Integrity & Copyright</div>
                            <small class="text-muted">Direct takedown requests via <a href="notes.php" class="text-decoration-none">Report Note</a> on document view</small>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3">
                        <div class="brand-icon flex-shrink-0" style="width: 40px; height: 40px; font-size: 1.1rem; background: rgba(124, 58, 237, 0.1); color: #7C3AED;">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div>
                            <div class="fw-semibold small text-dark">Response Window</div>
                            <small class="text-muted">Inquiries are reviewed within 24 business hours</small>
                        </div>
                    </div>
                </div>

                <!-- FAQ Accordion -->
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <h5 class="fw-bold mb-3 text-dark">Frequently Asked Questions</h5>
                    <div class="accordion accordion-flush" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed px-0 small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    What file types can I upload to NoteShare?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body px-0 text-muted small">
                                    We support PDF, DOC, DOCX, PPT, PPTX, and ZIP archives up to 25 MB per document. All uploads are scanned for security.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed px-0 small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    How do Study Missions work?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body px-0 text-muted small">
                                    Set your daily reading and download goals on your dashboard. As you read, preview, or upload notes, your progress advances automatically and unlocks achievement badges!
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed px-0 small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    How do I report an incorrect or infringing note?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body px-0 text-muted small">
                                    Open the note detail page and click the "Report Note" button. Select the appropriate reason, and platform administrators will investigate and take necessary action.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
