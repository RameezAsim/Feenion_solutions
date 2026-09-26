<?php
// school-fees-system/admin/reports.php (With Profit & Loss)

$page_title = 'Reports';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']); // Allow Accountants to run reports
$active_branch_id = get_active_branch_id();

if (!$active_branch_id) {
    set_flash_message('You must select a branch to view reports.', 'error');
    header('Location: index.php');
    exit;
}

$report_type = $_GET['report_type'] ?? 'collection';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

$collection_data = [];
$dues_data = [];
$profit_loss_data = ['income' => 0, 'expenses' => [], 'total_expense' => 0];

// Fetch data based on report type, filtered by branch
if ($report_type === 'collection') {
    $stmt = $pdo->prepare("
        SELECT p.payment_date, p.amount, p.payment_method, u.full_name as parent_name, i.invoice_uid, s.student_name
        FROM payments p
        LEFT JOIN users u ON p.parent_id = u.id
        LEFT JOIN invoices i ON p.invoice_id = i.id
        LEFT JOIN students s ON i.student_id = s.id
        WHERE p.status = 'Verified' AND p.amount > 0
        AND DATE(p.payment_date) BETWEEN ? AND ?
        AND p.branch_id = ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$start_date, $end_date, $active_branch_id]);
    $collection_data = $stmt->fetchAll();

} elseif ($report_type === 'dues') {
    $stmt = $pdo->prepare("
        SELECT s.student_name, s.admission_no, c.class_name, u.full_name as parent_name, u.phone as parent_phone,
               (i.total_amount - i.amount_paid) as due_amount, i.due_date, i.invoice_uid
        FROM invoices i
        JOIN students s ON i.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN users u ON s.parent_id = u.id
        WHERE i.status != 'Paid'
        AND i.branch_id = ?
        ORDER BY c.class_name, s.student_name
    ");
    $stmt->execute([$active_branch_id]);
    $dues_data = $stmt->fetchAll();

} elseif ($report_type === 'profit_loss') {
    // 1. Get Total Income (All verified, positive payments)
    $stmt_income = $pdo->prepare("
        SELECT SUM(amount) FROM payments 
        WHERE status = 'Verified' AND amount > 0 AND DATE(payment_date) BETWEEN ? AND ? AND branch_id = ?
    ");
    $stmt_income->execute([$start_date, $end_date, $active_branch_id]);
    $profit_loss_data['income'] = $stmt_income->fetchColumn() ?? 0;

    // 2. Get Expense Breakdown
    $stmt_expenses = $pdo->prepare("
        SELECT ec.name, SUM(e.amount) as total
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.expense_date BETWEEN ? AND ? AND e.branch_id = ?
        GROUP BY ec.id, ec.name
        ORDER BY total DESC
    ");
    $stmt_expenses->execute([$start_date, $end_date, $active_branch_id]);
    $profit_loss_data['expenses'] = $stmt_expenses->fetchAll();
    $profit_loss_data['total_expense'] = array_sum(array_column($profit_loss_data['expenses'], 'total'));
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1 class="m-0">Reports (Current Branch)</h1></div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <?php display_flash_messages(); ?>
        <div class="card card-primary card-outline">
            <div class="card-header"><h3 class="card-title">Generate Report</h3></div>
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <div class="form-group mr-2">
                        <label for="report_type" class="mr-2">Report Type:</label>
                        <select name="report_type" id="report_type" class="form-control">
                            <option value="collection" <?= ($report_type == 'collection') ? 'selected' : '' ?>>Fee Collection</option>
                            <option value="dues" <?= ($report_type == 'dues') ? 'selected' : '' ?>>Outstanding Dues</option>
                            <option value="profit_loss" <?= ($report_type == 'profit_loss') ? 'selected' : '' ?>>Profit & Loss Summary</option>
                        </select>
                    </div>
                    
                    <?php if ($report_type == 'collection' || $report_type == 'profit_loss'): ?>
                    <div class="form-group mr-2">
                        <label for="start_date" class="mr-2">From:</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?= he($start_date) ?>">
                    </div>
                    <div class="form-group mr-2">
                        <label for="end_date" class="mr-2">To:</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?= he($end_date) ?>">
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Generate</button>
                    <a href="export_report.php?<?= http_build_query($_GET) ?>" class="btn btn-success ml-2"><i class="fas fa-file-csv"></i> Export to CSV</a>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if ($report_type == 'collection'): ?>
                    <h4>Fee Collection Report (<?= he($start_date) ?> to <?= he($end_date) ?>)</h4>
                    <table class="table table-bordered table-striped" id="reportTable">
                        <thead><tr><th>Date</th><th>Invoice ID</th><th>Student</th><th>Parent</th><th>Amount</th><th>Method</th></tr></thead>
                        <tbody>
                            <?php $total_collection = 0; ?>
                            <?php foreach ($collection_data as $row): ?>
                                <tr>
                                    <td><?= he(date('d M, Y', strtotime($row['payment_date']))) ?></td>
                                    <td><?= he($row['invoice_uid']) ?></td>
                                    <td><?= he($row['student_name']) ?></td>
                                    <td><?= he($row['parent_name']) ?></td>
                                    <td><?= he(number_format($row['amount'], 2)) ?></td>
                                    <td><?= he($row['payment_method']) ?></td>
                                </tr>
                                <?php $total_collection += $row['amount']; ?>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><th colspan="4" class="text-right">Total Collection:</th><th colspan="2">PKR <?= he(number_format($total_collection, 2)) ?></th></tr>
                        </tfoot>
                    </table>
                <?php elseif ($report_type == 'dues'): ?>
                    <h4>Outstanding Dues Report</h4>
                    <table class="table table-bordered table-striped" id="reportTable">
                        <thead><tr><th>Student</th><th>Adm No</th><th>Class</th><th>Parent</th><th>Parent Phone</th><th>Invoice ID</th><th>Due Date</th><th>Due Amount</th></tr></thead>
                        <tbody>
                            <?php $total_dues = 0; ?>
                            <?php foreach ($dues_data as $row): ?>
                                <tr>
                                    <td><?= he($row['student_name']) ?></td>
                                    <td><?= he($row['admission_no']) ?></td>
                                    <td><?= he($row['class_name']) ?></td>
                                    <td><?= he($row['parent_name']) ?></td>
                                    <td><?= he($row['parent_phone']) ?></td>
                                    <td><?= he($row['invoice_uid']) ?></td>
                                    <td><?= he(date('d M, Y', strtotime($row['due_date']))) ?></td>
                                    <td><?= he(number_format($row['due_amount'], 2)) ?></td>
                                </tr>
                                <?php $total_dues += $row['due_amount']; ?>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><th colspan="7" class="text-right">Total Outstanding Dues:</th><th>PKR <?= he(number_format($total_dues, 2)) ?></th></tr>
                        </tfoot>
                    </table>
                    
                <?php elseif ($report_type == 'profit_loss'): ?>
                    <h4>Profit & Loss Summary (<?= he($start_date) ?> to <?= he($end_date) ?>)</h4>
                    <table class="table table-bordered" id="reportTable">
                        <thead class="thead-light">
                            <tr>
                                <th>Item</th>
                                <th>Type</th>
                                <th class="text-right">Amount (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-success">
                                <td><strong>Total Fee Collection</strong></td>
                                <td><strong>Income</strong></td>
                                <td class="text-right"><strong><?= he(number_format($profit_loss_data['income'], 2)) ?></strong></td>
                            </tr>
                            <tr class="table-danger">
                                <td colspan="3"><strong>Expenses</strong></td>
                            </tr>
                            <?php if (empty($profit_loss_data['expenses'])): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No expenses logged for this period.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($profit_loss_data['expenses'] as $expense): ?>
                            <tr>
                                <td>&nbsp;&nbsp;&nbsp; <?= he($expense['name']) ?></td>
                                <td>Expense</td>
                                <td class="text-right">(-) <?= he(number_format($expense['total'], 2)) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-danger">
                                <td colspan="2" class="text-right"><strong>Total Expenses:</strong></td>
                                <td class="text-right"><strong>(-) <?= he(number_format($profit_loss_data['total_expense'], 2)) ?></strong></td>
                            </tr>
                            <?php $net_profit = $profit_loss_data['income'] - $profit_loss_data['total_expense']; ?>
                            <tr class="bg-primary">
                                <td colspan="2" class="text-right"><strong>NET PROFIT / (LOSS):</strong></td>
                                <td class="text-right"><strong>PKR <?= he(number_format($net_profit, 2)) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
$(function () {
    $('#reportTable').DataTable({
        "destroy": true,
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": <?= ($report_type == 'profit_loss') ? 'false' : 'true' ?>,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "pageLength": 25
    });
});
</script>