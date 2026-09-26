<?php
// school-fees-system/admin/expenses.php

$page_title = 'Expense Management';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']);
$active_branch_id = get_active_branch_id();
$user_id = $_SESSION['user_id'];

if (!$active_branch_id) {
    set_flash_message('You must select a branch to manage expenses.', 'error');
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    validate_csrf_token();
    try {
        $category_id = $_POST['category_id'];
        $amount = (float)$_POST['amount'];
        $description = trim($_POST['description']);
        $expense_date = $_POST['expense_date'];

        if (empty($category_id) || $amount <= 0 || empty($expense_date)) {
            throw new Exception("Category, Amount, and Date are required.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO expenses (category_id, branch_id, user_id, amount, description, expense_date)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$category_id, $active_branch_id, $user_id, $amount, $description, $expense_date]);
        
        set_flash_message('Expense recorded successfully!');
        log_activity("Recorded expense of PKR {$amount}.");

    } catch (Exception $e) {
        set_flash_message('An error occurred: ' . $e->getMessage(), 'error');
    }
    header("Location: expenses.php");
    exit();
}

// Fetch categories for the dropdown (for the active branch)
$stmt_cat = $pdo->prepare("SELECT id, name FROM expense_categories WHERE branch_id = ? ORDER BY name ASC");
$stmt_cat->execute([$active_branch_id]);
$categories = $stmt_cat->fetchAll();

// Fetch recent expenses (for the active branch)
$stmt_exp = $pdo->prepare("
    SELECT e.id, e.amount, e.description, e.expense_date, ec.name as category_name, u.full_name as user_name
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN users u ON e.user_id = u.id
    WHERE e.branch_id = ?
    ORDER BY e.expense_date DESC
    LIMIT 100
");
$stmt_exp->execute([$active_branch_id]);
$expenses = $stmt_exp->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">Expense Management</h1></div></div></div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-5">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Log New Expense (Current Branch)</h3></div>
                    <form method="POST">
                        <?= csrf_input_field() ?>
                        <input type="hidden" name="action" value="add_expense">
                        <div class="card-body">
                            <?php if (empty($categories)): ?>
                                <div class="alert alert-warning">
                                    Please <a href="expense_categories.php">add at least one expense category</a> before logging an expense.
                                </div>
                            <?php else: ?>
                                <div class="form-group">
                                    <label for="category_id">Category</label>
                                    <select id="category_id" name="category_id" class="form-control" required>
                                        <option value="">-- Select a Category --</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?= he($category['id']) ?>"><?= he($category['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="amount">Amount (PKR)</label>
                                    <input type="number" id="amount" name="amount" class="form-control" step="0.01" required>
                                </div>
                                <div class="form-group">
                                    <label for="expense_date">Date of Expense</label>
                                    <input type="date" id="expense_date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="description">Description / Notes</label>
                                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="e.g., Staff salaries for October"></textarea>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <?php if (!empty($categories)): ?>
                                <button type="submit" class="btn btn-primary">Log Expense</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Recent Expenses (Current Branch)</h3></div>
                    <div class="card-body">
                        <?php display_flash_messages(); ?>
                        <table id="expensesTable" class="table table-bordered table-hover">
                            <thead><tr><th>Date</th><th>Category</th><th>Amount</th><th>Description</th><th>Logged By</th></tr></thead>
                            <tbody>
                                <?php if (empty($expenses)): ?>
                                    <tr><td colspan="5" class="text-center">No expenses logged for this branch.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?= he(date('d M, Y', strtotime($expense['expense_date']))) ?></td>
                                    <td><?= he($expense['category_name']) ?></td>
                                    <td><?= he(number_format($expense['amount'], 2)) ?></td>
                                    <td><?= he($expense['description']) ?></td>
                                    <td><?= he($expense['user_name']) ?></td>
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
<script>
$(document).ready(function() {
    $('#expensesTable').DataTable({
        "destroy": true,
        "paging": true,
        "lengthChange": false,
        "searching": true,
        "ordering": true,
        "order": [[ 0, "desc" ]],
        "info": true,
        "autoWidth": false,
        "responsive": true
    });
});
</script>