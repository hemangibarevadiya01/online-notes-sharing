</main>

<footer class="footer-custom">
    <div class="container">
        <div class="row g-4 mb-4">
            <!-- Brand & Description -->
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
                    <span class="fs-4 fw-bold">Edu<span class="text-gradient">Notes</span></span>
                </div>
                <p class="text-muted small pe-lg-4">
                    Online Notes Sharing Platform is a student-centric collaborative academic hub. Discover, upload, review, and exchange study notes across disciplines and institutions worldwide.
                </p>
                <div class="d-flex gap-2 mt-3">
                    <a href="#" class="btn btn-sm btn-light border rounded-circle"><i class="bi bi-github"></i></a>
                    <a href="#" class="btn btn-sm btn-light border rounded-circle"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="btn btn-sm btn-light border rounded-circle"><i class="bi bi-linkedin"></i></a>
                    <a href="#" class="btn btn-sm btn-light border rounded-circle"><i class="bi bi-envelope"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="fw-bold mb-3 text-dark">Platform</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="notes.php" class="text-decoration-none text-muted">Browse Notes</a></li>
                    <li><a href="categories.php" class="text-decoration-none text-muted">Subject Categories</a></li>
                    <li><a href="groups.php" class="text-decoration-none text-muted">Study Groups</a></li>
                    <li><a href="upload_note.php" class="text-decoration-none text-muted">Upload Material</a></li>
                    <li><a href="search.php" class="text-decoration-none text-muted">Advanced Search</a></li>
                    <li><a href="admin/admin_login.php" class="text-decoration-none text-muted"><i class="bi bi-shield-lock text-primary me-1"></i>Admin Portal</a></li>
                </ul>
            </div>

            <!-- Academic Disciplines -->
            <div class="col-lg-3 col-md-6 col-6">
                <h6 class="fw-bold mb-3 text-dark">Top Subjects</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="search.php?category=1" class="text-decoration-none text-muted">Computer Science & AI</a></li>
                    <li><a href="search.php?category=2" class="text-decoration-none text-muted">Calculus & Mathematics</a></li>
                    <li><a href="search.php?category=3" class="text-decoration-none text-muted">Physics & Wave Theory</a></li>
                    <li><a href="search.php?category=4" class="text-decoration-none text-muted">Business & Finance</a></li>
                    <li><a href="search.php?category=5" class="text-decoration-none text-muted">Medicine & Anatomy</a></li>
                </ul>
            </div>

            <!-- Student Support & Security -->
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold mb-3 text-dark">Community & Trust</h6>
                <p class="small text-muted mb-3">
                    All submitted notes are verified for community guidelines. Have questions or want to report inappropriate content?
                </p>
                <div class="p-3 bg-light rounded-3 border">
                    <div class="d-flex align-items-center gap-2 text-primary fw-semibold small mb-1">
                        <i class="bi bi-shield-check fs-5"></i> Academic Integrity
                    </div>
                    <div class="text-muted small">
                        Protected with prepared PDO queries and secure file sandboxing.
                    </div>
                </div>
            </div>
        </div>

        <div class="border-top pt-4 d-flex flex-column flex-md-row align-items-center justify-content-between small text-muted">
            <div>
                &copy; <?= date('Y') ?> <strong>Online Notes Sharing Platform</strong>. All rights reserved.
            </div>
            <div class="d-flex gap-3 mt-2 mt-md-0">
                <span class="badge bg-light text-dark border">PHP 8+</span>
                <span class="badge bg-light text-dark border">MySQL PDO</span>
                <span class="badge bg-light text-dark border">Bootstrap 5</span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JavaScript Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Custom Application Scripts -->
<script src="assets/js/script.js"></script>
</body>
</html>
