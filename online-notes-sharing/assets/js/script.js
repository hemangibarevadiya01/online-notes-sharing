/**
 * Online Notes Sharing Platform - Interactive JavaScript
 * Handles AJAX likes, saves, star ratings, password unmasking, and form validation.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Password Visibility Toggle
    document.querySelectorAll('.password-toggle-btn').forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;

            const icon = button.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                button.setAttribute('aria-label', 'Hide password');
                if (icon) {
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }
            } else {
                input.type = 'password';
                button.setAttribute('aria-label', 'Show password');
                if (icon) {
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            }
        });
    });

    // 2. AJAX Like Button Handler
    document.querySelectorAll('.btn-like-note').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const noteId = btn.getAttribute('data-note-id');
            if (!noteId) return;

            try {
                const formData = new FormData();
                formData.append('note_id', noteId);
                formData.append('ajax', '1');

                const response = await fetch('like_note.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                if (data.status === 'success') {
                    const icon = btn.querySelector('i');
                    const countSpan = btn.querySelector('.like-count');
                    
                    if (data.action === 'liked') {
                        btn.classList.add('btn-danger');
                        btn.classList.remove('btn-outline-danger');
                        if (icon) icon.className = 'bi bi-heart-fill';
                    } else {
                        btn.classList.remove('btn-danger');
                        btn.classList.add('btn-outline-danger');
                        if (icon) icon.className = 'bi bi-heart';
                    }

                    if (countSpan && data.total_likes !== undefined) {
                        countSpan.textContent = data.total_likes;
                    }
                    showToast(data.message || (data.action === 'liked' ? 'Added to liked notes!' : 'Removed from likes.'));
                } else if (data.status === 'unauthorized') {
                    window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                } else {
                    showToast(data.message || 'Error updating like.', 'danger');
                }
            } catch (err) {
                console.error('Like error:', err);
                showToast('Unable to process like request.', 'danger');
            }
        });
    });

    // 3. AJAX Save / Bookmark Note Handler
    document.querySelectorAll('.btn-save-note').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const noteId = btn.getAttribute('data-note-id');
            if (!noteId) return;

            try {
                const formData = new FormData();
                formData.append('note_id', noteId);
                formData.append('action', 'toggle');
                formData.append('ajax', '1');

                const response = await fetch('saved_notes.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();
                if (data.status === 'success') {
                    const icon = btn.querySelector('i');
                    const textSpan = btn.querySelector('.save-text');

                    if (data.action === 'saved') {
                        btn.classList.add('btn-warning');
                        btn.classList.remove('btn-outline-secondary', 'btn-outline-warning');
                        if (icon) icon.className = 'bi bi-bookmark-fill';
                        if (textSpan) textSpan.textContent = 'Saved';
                    } else {
                        btn.classList.remove('btn-warning');
                        btn.classList.add('btn-outline-secondary');
                        if (icon) icon.className = 'bi bi-bookmark';
                        if (textSpan) textSpan.textContent = 'Save';
                    }
                    showToast(data.message || (data.action === 'saved' ? 'Note saved to your bookmarks!' : 'Note removed from bookmarks.'));
                } else if (data.status === 'unauthorized') {
                    window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                } else {
                    showToast(data.message || 'Error updating saved status.', 'danger');
                }
            } catch (err) {
                console.error('Save error:', err);
                showToast('Unable to process save request.', 'danger');
            }
        });
    });

    // 4. Interactive 5-Star Rating Widget
    const starContainers = document.querySelectorAll('.star-rating-picker');
    starContainers.forEach(container => {
        const stars = container.querySelectorAll('.star-interactive');
        const inputField = container.parentElement.querySelector('input[name="rating"]');

        const highlightStars = (rating) => {
            stars.forEach((s, idx) => {
                if (idx < rating) {
                    s.classList.remove('bi-star', 'text-secondary');
                    s.classList.add('bi-star-fill', 'text-warning');
                } else {
                    s.classList.remove('bi-star-fill', 'text-warning');
                    s.classList.add('bi-star', 'text-secondary');
                }
            });
        };

        stars.forEach(star => {
            star.addEventListener('mouseenter', () => {
                const val = parseInt(star.getAttribute('data-value'), 10);
                highlightStars(val);
            });

            star.addEventListener('click', () => {
                const val = parseInt(star.getAttribute('data-value'), 10);
                if (inputField) inputField.value = val;
                container.setAttribute('data-selected-rating', val);
                highlightStars(val);
            });
        });

        container.addEventListener('mouseleave', () => {
            const current = parseInt(container.getAttribute('data-selected-rating') || (inputField ? inputField.value : 0), 10);
            highlightStars(current);
        });

        // Initialize with default or current value
        const initialRating = parseInt(inputField ? inputField.value : 0, 10);
        if (initialRating > 0) {
            highlightStars(initialRating);
            container.setAttribute('data-selected-rating', initialRating);
        }
    });

    // 5. File Upload Verification (size & type)
    const fileInput = document.getElementById('note_file');
    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (!file) return;

            const allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip'];
            const fileExt = file.name.split('.').pop().toLowerCase();
            const maxSize = 25 * 1024 * 1024; // 25MB

            if (!allowedExtensions.includes(fileExt)) {
                alert('Invalid file format. Allowed formats: PDF, DOC, DOCX, PPT, PPTX, ZIP.');
                fileInput.value = '';
                return;
            }

            if (file.size > maxSize) {
                alert('File size exceeds the maximum limit of 25MB. Please choose a smaller file.');
                fileInput.value = '';
                return;
            }

            const fileNameDisplay = document.getElementById('selected_file_name');
            if (fileNameDisplay) {
                fileNameDisplay.textContent = `${file.name} (${(file.size / (1024 * 1024)).toFixed(2)} MB)`;
            }
        });
    }

    // 6. Auto-dismiss alerts after 6 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert-dismissible').forEach(alert => {
            try {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                if (bsAlert) bsAlert.close();
            } catch (e) {}
        });
    }, 6000);
});

/**
 * Modern floating toast message
 */
function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.position = 'fixed';
        container.style.bottom = '24px';
        container.style.right = '24px';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const bgClass = type === 'danger' ? 'bg-danger text-white' : (type === 'warning' ? 'bg-warning text-dark' : 'bg-primary text-white');
    
    toast.className = `toast align-items-center ${bgClass} border-0 shadow-lg show mb-2`;
    toast.role = 'alert';
    toast.ariaLive = 'assertive';
    toast.ariaAtomic = 'true';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body fw-semibold py-3 px-4">
                <i class="bi ${type === 'danger' ? 'bi-exclamation-triangle' : 'bi-check-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close ${type !== 'warning' ? 'btn-close-white' : ''} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}
