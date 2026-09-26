<?php
$admin_title = 'Category Management';
require_once __DIR__ . '/includes/header.php';

$categories = [];
try {
    $stmt = $pdo->query("SELECT c.*, COUNT(n.id) AS note_count 
                         FROM categories c 
                         LEFT JOIN notes n ON c.id = n.category_id 
                         GROUP BY c.id 
                         ORDER BY c.name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Categories error: " . $e->getMessage());
}
?>

<div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
        <div>
            <h5 class="fw-bold mb-0">Academic Disciplines & Categories</h5>
            <small class="text-muted">Manage the subject taxonomy for uploaded materials</small>
        </div>
        <a href="add_category.php" class="btn btn-primary btn-sm">
            <i class="bi bi-folder-plus me-1"></i> Add New Category
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Category Name</th>
                    <th>Description</th>
                    <th>Notes Count</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td class="fw-bold text-dark"><?= htmlspecialchars($cat['name']) ?></td>
                        <td class="small text-muted" style="max-width: 320px;"><?= htmlspecialchars($cat['description'] ?: 'No description.') ?></td>
                        <td class="fw-semibold small"><?= $cat['note_count'] ?></td>
                        <td class="small text-muted"><?= date('M j, Y', strtotime($cat['created_at'])) ?></td>
                        <td class="text-end">
                            <div class="d-flex align-items-center justify-content-end gap-1">
                                <a href="edit_category.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit Category">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="delete_category.php" onsubmit="return confirm('Deleting category \'<?= htmlspecialchars(addslashes($cat['name'])) ?>\' will remove associated notes. Proceed?');" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Category">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
