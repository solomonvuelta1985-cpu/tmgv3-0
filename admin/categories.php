<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin access
require_admin();
check_session_timeout();

// Fetch all categories
$categories = [];
try {
    $stmt = db_query(
        "SELECT * FROM violation_categories ORDER BY display_order ASC, category_name ASC"
    );
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    set_flash('Error loading categories.', 'danger');
}

// Count violations per category
$violation_counts = [];
try {
    $stmt = db_query(
        "SELECT category_id, COUNT(*) as count
         FROM violation_types
         WHERE category_id IS NOT NULL
         GROUP BY category_id"
    );
    while ($row = $stmt->fetch()) {
        $violation_counts[$row['category_id']] = $row['count'];
    }
} catch (Exception $e) {
    error_log("Error counting violations: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Traffic Citation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary-blue: #0d6efd;
            --success-green: #198754;
            --danger-red: #dc3545;
            --white: #ffffff;
            --off-white: #f5f5f5;
            --light-gray: #f8f9fa;
            --border-gray: #dee2e6;
            --text-dark: #212529;
            --text-muted: #6c757d;
        }

        body {
            background: var(--off-white);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .content {
            padding: clamp(15px, 3vw, 20px);
        }

        .page-header h3 {
            font-size: clamp(1.5rem, 4vw, 1.75rem);
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .page-header p {
            font-size: clamp(0.85rem, 2.5vw, 0.9rem);
            color: var(--text-muted);
        }

        .stat-card {
            background: var(--white);
            border: 1px solid var(--border-gray);
            border-radius: 6px;
            padding: clamp(15px, 3vw, 20px);
            position: relative;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            border-radius: 6px 0 0 6px;
            background: var(--primary-blue);
        }

        .stat-value {
            font-size: clamp(1.5rem, 4vw, 1.75rem);
            font-weight: 600;
            color: var(--text-dark);
        }

        .stat-label {
            font-size: clamp(0.85rem, 2.5vw, 0.9rem);
            color: var(--text-muted);
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .category-card {
            background: var(--white);
            border: 2px solid var(--border-gray);
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .category-card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .category-icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .category-icon-wrapper i {
            font-size: 24px;
            color: white;
        }

        .category-info h5 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .category-count {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .category-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-gray);
        }

        .category-status {
            position: absolute;
            top: 15px;
            right: 15px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--white);
            border-radius: 8px;
            border: 2px dashed var(--border-gray);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--text-muted);
            opacity: 0.3;
            margin-bottom: 20px;
        }

        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: 2px solid var(--border-gray);
            cursor: pointer;
        }

        .icon-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
        }

        .icon-option {
            padding: 15px;
            border: 2px solid var(--border-gray);
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .icon-option:hover,
        .icon-option.selected {
            border-color: var(--primary-blue);
            background: var(--light-gray);
        }

        .icon-option i {
            font-size: 24px;
        }
    </style>
</head>
<body>
    <?php include '../public/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3><i class="fas fa-folder me-2"></i>Manage Violation Categories</h3>
                    <p class="text-muted mb-0">Organize violations into categories for better management</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus me-2"></i>Add New Category
                </button>
            </div>

            <?php echo show_flash(); ?>

            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($categories); ?></div>
                        <div class="stat-label">
                            <i class="fas fa-folder me-2"></i>Total Categories
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count(array_filter($categories, fn($c) => $c['is_active'])); ?></div>
                        <div class="stat-label">
                            <i class="fas fa-check-circle me-2"></i>Active Categories
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories Grid -->
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open d-block"></i>
                    <h5>No Categories Found</h5>
                    <p class="text-muted">Click "Add New Category" to create your first category.</p>
                </div>
            <?php else: ?>
                <div class="category-grid">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card">
                            <div class="category-status">
                                <?php if ($category['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </div>

                            <div class="category-card-header">
                                <div class="category-icon-wrapper" style="background-color: <?php echo htmlspecialchars($category['category_color']); ?>">
                                    <i data-lucide="<?php echo htmlspecialchars($category['category_icon']); ?>"></i>
                                </div>
                                <div class="category-info">
                                    <h5><?php echo htmlspecialchars($category['category_name']); ?></h5>
                                    <div class="category-count">
                                        <?php
                                        $count = $violation_counts[$category['category_id']] ?? 0;
                                        echo $count . ' violation' . ($count != 1 ? 's' : '');
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($category['description'])): ?>
                                <p class="text-muted small mb-0"><?php echo htmlspecialchars($category['description']); ?></p>
                            <?php endif; ?>

                            <div class="category-actions">
                                <button class="btn btn-sm btn-outline-primary flex-grow-1"
                                        onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <button class="btn btn-sm btn-outline-danger"
                                        onclick="deleteCategory(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars($category['category_name']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addCategoryForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">

                        <div class="mb-3">
                            <label class="form-label">Category Name *</label>
                            <input type="text" class="form-control" name="category_name" required placeholder="e.g., Helmet Violations">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Icon *</label>
                                <select class="form-select" name="category_icon" id="add_icon" required>
                                    <option value="shield">Shield</option>
                                    <option value="credit-card">Credit Card</option>
                                    <option value="wrench">Wrench</option>
                                    <option value="alert-circle">Alert Circle</option>
                                    <option value="traffic-cone">Traffic Cone</option>
                                    <option value="list">List</option>
                                    <option value="more-horizontal">More</option>
                                    <option value="flag">Flag</option>
                                    <option value="bookmark">Bookmark</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Color *</label>
                                <input type="color" class="form-control color-preview" name="category_color" value="#3b82f6" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Display Order</label>
                            <input type="number" class="form-control" name="display_order" value="0" min="0">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description (Optional)</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Brief description of this category"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editCategoryForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">
                        <input type="hidden" name="category_id" id="edit_category_id">

                        <div class="mb-3">
                            <label class="form-label">Category Name *</label>
                            <input type="text" class="form-control" name="category_name" id="edit_category_name" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Icon *</label>
                                <select class="form-select" name="category_icon" id="edit_icon" required>
                                    <option value="shield">Shield</option>
                                    <option value="credit-card">Credit Card</option>
                                    <option value="wrench">Wrench</option>
                                    <option value="alert-circle">Alert Circle</option>
                                    <option value="traffic-cone">Traffic Cone</option>
                                    <option value="list">List</option>
                                    <option value="more-horizontal">More</option>
                                    <option value="flag">Flag</option>
                                    <option value="bookmark">Bookmark</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Color *</label>
                                <input type="color" class="form-control color-preview" name="category_color" id="edit_color" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Display Order</label>
                            <input type="number" class="form-control" name="display_order" id="edit_order" min="0">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description (Optional)</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="is_active" id="edit_status">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the category:</p>
                    <p class="fw-bold fs-5" id="deleteCategoryName"></p>
                    <p class="text-warning"><i class="fas fa-info-circle me-2"></i>Violations in this category will be set to "Other"</p>
                </div>
                <div class="modal-footer">
                    <input type="hidden" id="deleteCategoryId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                        <i class="fas fa-trash me-2"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Edit category
        function editCategory(category) {
            document.getElementById('edit_category_id').value = category.category_id;
            document.getElementById('edit_category_name').value = category.category_name;
            document.getElementById('edit_icon').value = category.category_icon;
            document.getElementById('edit_color').value = category.category_color;
            document.getElementById('edit_order').value = category.display_order;
            document.getElementById('edit_description').value = category.description || '';
            document.getElementById('edit_status').value = category.is_active;

            const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            modal.show();
        }

        // Delete category
        function deleteCategory(id, name) {
            document.getElementById('deleteCategoryId').value = id;
            document.getElementById('deleteCategoryName').textContent = name;

            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        // Confirm delete
        function confirmDelete() {
            const id = document.getElementById('deleteCategoryId').value;
            const formData = new FormData();
            formData.append('category_id', id);
            formData.append('csrf_token', '<?php echo generate_token(); ?>');

            fetch('../api/category_delete.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }

        // Add category form
        document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('../api/category_save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        });

        // Edit category form
        document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('../api/category_update.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        });
    </script>
</body>
</html>
