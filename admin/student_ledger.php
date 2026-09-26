<?php
// school-fees-system/admin/student_ledger.php
// (FIXED: Correctly fetches all family invoices for a true parent ledger)

$page_title = 'Student Ledger';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']);
$active_branch_id = get_active_branch_id();

if (!$active_branch_id) {
    set_flash_message('You must select a branch to view reports.', 'error');
    header('Location: index.php');
    exit;
}

// Fetch all active students for the current branch
$students_stmt = $pdo->prepare("SELECT id, student_name, admission_no, parent_id, advance_balance FROM students WHERE status = 'Active' AND branch_id = ? ORDER BY student_name");
$students_stmt->execute([$active_branch_id]);
$students = $students_stmt->fetchAll();

$selected_student_id = $_GET['student_id'] ?? null;
$transactions = [];
$starting_balance = 0; // This will be the student's advance credit

if ($selected_student_id) {
    // Verify student is in the active branch
    $student_stmt = $pdo->prepare("SELECT id, parent_id, advance_balance FROM students WHERE id = ? AND branch_id = ?");
    $student_stmt->execute([$selected_student_id, $active_branch_id]);
    $student = $student_stmt->fetch();

    if ($student) {
        $parent_id = $student['parent_id']; // <-- We get the parent_id
        $starting_balance = (float)$student['advance_balance'];
        
        // ---
        // --- THIS IS THE CORRECTED INVOICE QUERY ---
        // ---
        // 1. Get all invoices for this ENTIRE FAMILY (as DEBITS)
        $stmt_inv = $pdo->prepare("
            SELECT i.id, i.created_at as date, i.invoice_uid, i.total_amount, s.student_name
            FROM invoices i
            JOIN students s ON i.student_id = s.id
            WHERE s.parent_id = ? AND i.branch_id = ?
        ");
        $stmt_inv->execute([$parent_id, $active_branch_id]); // Use parent_id
        
        while($row = $stmt_inv->fetch(PDO::FETCH_ASSOC)) {
            $transactions[] = [
                'date' => $row['date'],
                // Add student name to description
                'description' => "Invoice for " . $row['student_name'] . " (#{$row['invoice_uid']})",
                'debit' => $row['total_amount'],
                'credit' => 0,
                'type' => 'invoice'
            ];
        }

        // 2. Get all VERIFIED payments for this PARENT (as CREDITS)
        $stmt_pay = $pdo->prepare("
            SELECT payment_date as date, amount, payment_method, transaction_id, remarks 
            FROM payments 
            WHERE parent_id = ? AND status = 'Verified' AND branch_id = ?
        ");
        $stmt_pay->execute([$parent_id, $active_branch_id]); 
        
        while($row = $stmt_pay->fetch(PDO::FETCH_ASSOC)) {
            if ($row['payment_method'] === 'Refund') {
                $transactions[] = [
                    'date' => $row['date'],
                    'description' => "Refund Issued: {$row['remarks']}",
                    'debit' => abs($row['amount']),
                    'credit' => 0,
                    'type' => 'refund'
                ];
            } else {
                $transactions[] = [
                    'date' => $row['date'],
                    'description' => "Payment Received ({$row['payment_method']}) - TID: {$row['transaction_id']}",
                    'debit' => 0,
                    'credit' => $row['amount'],
                    'type' => 'payment'
                ];
            }
        }
        
        // 3. Sort all transactions by date
        usort($transactions, function($a, $b) {
            return strtotime($a['date']) <=> strtotime($b['date']);
        });

    } else {
        set_flash_message("Student not found in this branch.", "error");
        $selected_student_id = null;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">Student Ledger</h1></div></div></div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">View Student's Financial Statement</h3></div>
            <div class="card-body">
                <?php display_flash_messages(); ?>
                <form method="GET" class="form-group">
                    <label for="student_select">Select Student (Current Branch)</label>
                    <select id="student_select" name="student_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Search and select a student --</option>
                        <?php foreach ($students as $student): ?>
                        <option value="<?= he($student['id']) ?>" <?= ($selected_student_id == $student['id']) ? 'selected' : '' ?>>
                            <?= he($student['student_name']) ?> (<?= he($student['admission_no']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ($selected_student_id && $student): ?>
                <hr>
                <h4>Ledger for: <strong><?= he(array_column($students, 'student_name', 'id')[$selected_student_id]) ?> and Family</strong></h4>
                <p>Current Advance Credit Balance: <strong>PKR <?= he(number_format($starting_balance, 2)) ?></strong></p>
                
                <table class="table table-bordered table-striped mt-3">
                    <thead class="thead-light">
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th class="text-right">Charges (Debit)</th>
                            <th class="text-right">Payments (Credit)</th>
                            <th class="text-right">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // ---
                        // --- LOGIC UPDATED HERE ---
                        // ---
                        $running_balance = 0 - $starting_balance;
                        $previous_invoice_total_from_db = 0; // Tracker for cumulative totals

                        foreach ($transactions as $tx):
                            
                            $debit_to_display = $tx['debit']; // Default for refunds

                            if ($tx['type'] === 'invoice') {
                                // This is a cumulative invoice. Calculate the *new* charge.
                                $marginal_debit = $tx['debit'] - $previous_invoice_total_from_db;
                                
                                // The amount to display is this new (marginal) charge
                                $debit_to_display = $marginal_debit;
                                
                                // Add ONLY the new charge to the running balance
                                $running_balance += $marginal_debit;
                                
                                // Update the tracker for the next invoice
                                $previous_invoice_total_from_db = $tx['debit'];

                            } elseif ($tx['type'] === 'payment') {
                                // This is a payment, it's a credit
                                $running_balance -= $tx['credit'];
                                
                            } elseif ($tx['type'] === 'refund') {
                                // This is a refund, it's a debit (amount is already positive)
                                $running_balance += $tx['debit'];
                            }
                        ?>
                        <tr>
                            <td><?= he(date('d M, Y', strtotime($tx['date']))) ?></td>
                            <td><?= he($tx['description']) ?></td>
                            
                            <td class="text-right text-danger"><?= $debit_to_display > 0 ? he(number_format($debit_to_display, 2)) : '-' ?></td>
                            
                            <td class="text-right text-success"><?= $tx['credit'] > 0 ? he(number_format($tx['credit'], 2)) : '-' ?></td>
                            <td class="text-right font-weight-bold">
                                <?php if ($running_balance > 0): ?>
                                    <span class="text-danger">PKR <?= he(number_format($running_balance, 2)) ?> (Due)</span>
                                <?php elseif ($running_balance < 0): ?>
                                    <span class="text-success">PKR <?= he(number_format(abs($running_balance), 2)) ?> (Credit)</span>
                                <?php else: ?>
                                    <span>PKR 0.00</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-light">
                        <tr>
                            <td colspan="4" class="text-right"><strong>Final Balance:</strong></td>
                            <td class="text-right font-weight-bold">
                                <?php if ($running_balance > 0): ?>
                                    <span class="text-danger">PKR <?= he(number_format($running_balance, 2)) ?> (Total Dues)</span>
                                <?php elseif ($running_balance < 0): ?>
                                    <span class="text-success">PKR <?= he(number_format(abs($running_balance), 2)) ?> (In Credit)</span>
                                <?php else: ?>
                                    <span>PKR 0.00</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#student_select').select2({
        placeholder: '-- Search and select a student --'
    });
});
</script>