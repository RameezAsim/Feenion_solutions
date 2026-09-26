<?php
// school-fees-system/admin/expense_categories.php

$page_title = 'Expense Categories';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']);
$active_branch_id = get_active_branch_id();

if (!$active_branch_id) {
    set_flash_message('You must select a branch to manage categories.', 'error');
    header('Location: index.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_csrf_token();
    try {
        switch ($_POST['action']) {
            case 'add_category':
                $stmt = $pdo->prepare("INSERT INTO expense_categories (name, branch_id) VALUES (?, ?)");
                $stmt->execute([trim($_POST['category_name']), $active_branch_id]);
                set_flash_message('Expense category added successfully!');
                break;
            case 'delete_category':
                // Check if category is in use before deleting
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE category_id = ? AND branch_id = ?");
                $stmt_check->execute([$_POST['category_id'], $active_branch_id]);
                if ($stmt_check->fetchColumn() > 0) {
                    throw new Exception("Cannot delete category as it is already in use by one or more expense entries.");
                }
                
                $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id = ? AND branch_id = ?");
                $stmt->execute([$_POST['category_id'], $active_branch_id]);
                set_flash_message('Expense category deleted successfully.');
                break;
        }
    } catch (Exception $e) {
        set_flash_message('An error occurred: ' . $e->getMessage(), 'error');
    }
    header("Location: expense_categories.php");
    exit();
}

// Fetch all categories for the current branch
$stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE branch_id = ? ORDER BY name ASC");
$stmt->execute([$active_branch_id]);
$categories = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">Expense Categories</h1></div></div></div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-5">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Add New Category (Current Branch)</h3></div>
                    <form method="POST">
                        <?= csrf_input_field() ?>
                        <input type="hidden" name="action" value="add_category">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="category_name">Category Name</label>
                                <input type="text" id="category_name" name="category_name" class="form-control" placeholder="e.g., Staff Salaries" required>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Add Category</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Existing Categories (Current Branch)</h3></div>
                    <div class="card-body">
                        <?php display_flash_messages(); ?>
                        <table class="table table-bordered table-hover">
                            <thead><tr><th>Name</th><th style="width: 50px;">Action</th></tr></thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr><td colspan="2" class="text-center">No expense categories found.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?= he($category['name']) ?></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this category?');" style="display:inline;">
                                            <?= csrf_input_field() ?>
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?= he($category['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>