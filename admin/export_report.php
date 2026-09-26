<?php
// school-fees-system/admin/export_report.php (With Profit & Loss)

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']);
$active_branch_id = get_active_branch_id();

if (!$active_branch_id) {
    die("No active branch selected.");
}

$report_type = $_GET['report_type'] ?? 'collection';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="report_' . $report_type . '_' . date('Y-m-d') . '.csv"');
$output = fopen('php://output', 'w');

try {
    if ($report_type === 'collection') {
        fputcsv($output, ['Date', 'Invoice ID', 'Student', 'Parent', 'Amount', 'Method']);
        $stmt = $pdo->prepare("
            SELECT p.payment_date, i.invoice_uid, s.student_name, u.full_name, p.amount, p.payment_method
            FROM payments p
            LEFT JOIN users u ON p.parent_id = u.id
            LEFT JOIN invoices i ON p.invoice_id = i.id
            LEFT JOIN students s ON i.student_id = s.id
            WHERE p.status = 'Verified' AND p.amount > 0 AND DATE(p.payment_date) BETWEEN ? AND ? AND p.branch_id = ?
            ORDER BY p.payment_date DESC
        ");
        $stmt->execute([$start_date, $end_date, $active_branch_id]);
        $total = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
            $total += $row['amount'];
        }
        fputcsv($output, []);
        fputcsv($output, ['Total Collection', '', '', '', $total]);
    
    } elseif ($report_type === 'dues') {
        fputcsv($output, ['Student', 'Adm No', 'Class', 'Parent', 'Parent Phone', 'Invoice ID', 'Due Date', 'Due Amount']);
        $stmt = $pdo->prepare("
            SELECT s.student_name, s.admission_no, c.class_name, u.full_name, u.phone, i.invoice_uid, i.due_date, (i.total_amount - i.amount_paid) as due_amount
            FROM invoices i
            JOIN students s ON i.student_id = s.id
            JOIN classes c ON s.class_id = c.id
            LEFT JOIN users u ON s.parent_id = u.id
            WHERE i.status != 'Paid' AND i.branch_id = ?
            ORDER BY c.class_name, s.student_name
        ");
        $stmt->execute([$active_branch_id]);
        $total = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
            $total += $row['due_amount'];
        }
        fputcsv($output, []);
        fputcsv($output, ['Total Outstanding Dues', '', '', '', '', '', '', $total]);
    
    } elseif ($report_type === 'profit_loss') {
        fputcsv($output, ['Item', 'Type', 'Amount']);
        
        // 1. Get Income
        $stmt_income = $pdo->prepare("
            SELECT SUM(amount) FROM payments 
            WHERE status = 'Verified' AND amount > 0 AND DATE(payment_date) BETWEEN ? AND ? AND branch_id = ?
        ");
        $stmt_income->execute([$start_date, $end_date, $active_branch_id]);
        $total_income = $stmt_income->fetchColumn() ?? 0;
        fputcsv($output, ['Total Fee Collection', 'Income', $total_income]);
        
        // 2. Get Expenses
        $stmt_expenses = $pdo->prepare("
            SELECT ec.name, SUM(e.amount) as total
            FROM expenses e
            JOIN expense_categories ec ON e.category_id = ec.id
            WHERE e.expense_date BETWEEN ? AND ? AND e.branch_id = ?
            GROUP BY ec.id, ec.name
            ORDER BY total DESC
        ");
        $stmt_expenses->execute([$start_date, $end_date, $active_branch_id]);
        $total_expense = 0;
        while ($row = $stmt_expenses->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [$row['name'], 'Expense', -$row['total']]);
            $total_expense += $row['total'];
        }
        
        // 3. Totals
        fputcsv($output, []);
        fputcsv($output, ['Total Income', '', $total_income]);
        fputcsv($output, ['Total Expenses', '', -$total_expense]);
        fputcsv($output, ['NET PROFIT / (LOSS)', '', ($total_income - $total_expense)]);
    }

} catch (Exception $e) {
    fputcsv($output, ['Error exporting data: ' . $e->getMessage()]);
}

fclose($output);
exit;