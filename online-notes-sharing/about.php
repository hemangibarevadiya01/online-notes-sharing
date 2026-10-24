<?php
/**
 * NoteShare — About the Platform (BCA Final Year Project)
 */
$page_title = 'About NoteShare — Platform Overview';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Hero Banner -->
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5 text-white position-relative overflow-hidden" style="background: linear-gradient(135deg, #4F46E5, #7C3AED);">
        <div class="position-relative z-1" style="max-width: 700px;">
            <span class="badge bg-white text-primary px-3 py-1 rounded-pill fw-bold text-uppercase mb-2 shadow-sm" style="font-size: 0.75rem;">
                <i class="bi bi-mortarboard-fill me-1"></i> BCA Final Year Project
            </span>
            <h1 class="fw-bold display-5 mb-3">LEARN. SHARE. GROW.</h1>
            <p class="fs-5 text-white-50 mb-4">
                NoteShare is an intelligent, student-centric academic collaboration ecosystem engineered to democratize access to high-quality lecture notes, study summaries, and collaborative learning resources.
            </p>
            <div class="d-flex flex-wrap gap-3">
                <a href="notes.php" class="btn btn-light text-primary fw-bold px-4 py-2 rounded-3">
                    <i class="bi bi-search me-1"></i> Browse Library
                </a>
                <a href="register.php" class="btn btn-outline-light px-4 py-2 rounded-3">
                    <i class="bi bi-person-plus-fill me-1"></i> Join NoteShare
                </a>
            </div>
        </div>
        <div class="position-absolute end-0 bottom-0 opacity-20 d-none d-lg-block pe-5 pb-3" style="font-size: 11rem; color: #fff;">
            <i class="bi bi-book-half"></i>
        </div>
    </div>

    <!-- Core Pillars / Why NoteShare -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <div class="brand-icon mb-3" style="width: 52px; height: 52px; font-size: 1.5rem; background: rgba(79, 70, 229, 0.1); color: #4F46E5;">
                    <i class="bi bi-mortarboard"></i>
                </div>
                <h4 class="fw-bold mb-2 text-dark">Peer-to-Peer Learning</h4>
                <p class="text-muted small mb-0">
                    Students can upload syllabus-aligned lecture notes, problem sets, and revision guides across disciplines ranging from Computer Science to Life Sciences.
                </p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <div class="brand-icon mb-3" style="width: 52px; height: 52px; font-size: 1.5rem; background: rgba(124, 58, 237, 0.1); color: #7C3AED;">
                    <i class="bi bi-trophy-fill"></i>
                </div>
                <h4 class="fw-bold mb-2 text-dark">Smart Study Missions</h4>
                <p class="text-muted small mb-0">
                    A standout gamified progress tracker that sets daily study goals, computes real Knowledge Impact scores, and rewards genuine academic milestones with earned badges.
                </p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-white">
                <div class="brand-icon mb-3" style="width: 52px; height: 52px; font-size: 1.5rem; background: rgba(16, 185, 129, 0.1); color: #10B981;">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h4 class="fw-bold mb-2 text-dark">Enterprise Security</h4>
                <p class="text-muted small mb-0">
                    Built with strict server-side authentication, PDO prepared statements, CSRF protection, file extension and MIME type sandboxing, and separate role-based control panels.
                </p>
            </div>
        </div>
    </div>

    <!-- Dual Architecture Showcase -->
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5 bg-white">
        <div class="text-center mb-5" style="max-width: 600px; margin-inline: auto;">
            <span class="badge bg-primary-subtle text-primary px-3 py-1 rounded-pill fw-semibold text-uppercase small mb-2">
                System Architecture
            </span>
            <h2 class="fw-bold text-dark">Two Independent, Specialized Panels</h2>
            <p class="text-muted small mb-0">Engineered with clear separation of concerns, independent route protection, and tailored interfaces.</p>
        </div>

        <div class="row g-4 align-items-center">
            <div class="col-lg-6">
                <div class="p-4 rounded-4 border bg-light h-100">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="brand-icon" style="width: 44px; height: 44px; font-size: 1.25rem; background: #4F46E5; color: #fff;">
                            <i class="bi bi-person-workspace"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">1. Student / User Panel</h5>
                            <small class="text-muted"><code>/online-notes-sharing/</code></small>
                        </div>
                    </div>
                    <ul class="list-unstyled small d-flex flex-column gap-2 text-muted mb-0">
                        <li><i class="bi bi-check2-circle text-success me-2"></i><strong>Multi-format Uploads:</strong> PDF, DOC, DOCX, PPT, PPTX, ZIP with metadata & keywords.</li>
                        <li><i class="bi bi-check2-circle text-success me-2"></i><strong>In-Browser PDF Viewer:</strong> High-speed previewer with secure streamed bytes.</li>
                        <li><i class="bi bi-check2-circle text-success me-2"></i><strong>Study Groups:</strong> Real-time academic circles with group note sharing.</li>
                        <li><i class="bi bi-check2-circle text-success me-2"></i><strong>Interactive Reviews:</strong> 5-star ratings, likes, bookmarks, and threaded discussions.</li>
                        <li><i class="bi bi-check2-circle text-success me-2"></i><strong>Smart Study Missions:</strong> Daily goals, circular progress indicator, and impact rankings.</li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="p-4 rounded-4 border bg-dark text-white h-100">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="brand-icon" style="width: 44px; height: 44px; font-size: 1.25rem; background: #1e293b; color: #F59E0B; border: 1px solid #334155;">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-white">2. Administrator Management Portal</h5>
                            <small class="text-white-50">Independent Academic Moderation Workspace</small>
                        </div>
                    </div>
                    <ul class="list-unstyled small d-flex flex-column gap-2 text-white-50 mb-0">
                        <li><i class="bi bi-check2-circle text-warning me-2"></i><strong>Analytics Dashboard:</strong> 4 Chart.js charts and live KPI metrics.</li>
                        <li><i class="bi bi-check2-circle text-warning me-2"></i><strong>User Administration:</strong> Full CRUD, role management, and account activation/blocking.</li>
                        <li><i class="bi bi-check2-circle text-warning me-2"></i><strong>Note Moderation:</strong> Approve, reject, inspect files, or take down infringing materials.</li>
                        <li><i class="bi bi-check2-circle text-warning me-2"></i><strong>Reports Resolution:</strong> Review community flags with audit history.</li>
                        <li><i class="bi bi-check2-circle text-warning me-2"></i><strong>Broadcast System:</strong> Push global announcements to all students.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Tech Stack Grid -->
    <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5 bg-white">
        <h4 class="fw-bold mb-4 text-center text-dark">Built With Modern Educational Technologies</h4>
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3">
                <div class="p-3 border rounded-3 bg-light">
                    <i class="bi bi-filetype-php fs-1 text-primary mb-2 d-block"></i>
                    <h6 class="fw-bold mb-1">Core PHP 8+</h6>
                    <small class="text-muted">Zero frameworks, pure OOP backend</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 border rounded-3 bg-light">
                    <i class="bi bi-database-fill fs-1 text-info mb-2 d-block"></i>
                    <h6 class="fw-bold mb-1">MySQL / MariaDB</h6>
                    <small class="text-muted">19 relational tables with cascade constraints</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 border rounded-3 bg-light">
                    <i class="bi bi-bootstrap-fill fs-1 text-purple mb-2 d-block"></i>
                    <h6 class="fw-bold mb-1">Bootstrap 5</h6>
                    <small class="text-muted">Responsive mobile-first design system</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 border rounded-3 bg-light">
                    <i class="bi bi-pie-chart-fill fs-1 text-success mb-2 d-block"></i>
                    <h6 class="fw-bold mb-1">Chart.js & Icons</h6>
                    <small class="text-muted">Dynamic analytics & Font Awesome icons</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
