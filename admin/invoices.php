<?php
// school-fees-system/admin/invoices.php (Multi-Branch & WhatsApp Enabled)
// (FIXED: Removed conflicting JS, fixed Date formatting & File corruption)

$page_title = 'Invoice Management';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']);
$active_branch_id = get_active_branch_id();

if (!$active_branch_id) {
    set_flash_message('You must select a branch to manage invoices.', 'error');
    header('Location: index.php');
    exit;
}

// API endpoint for fetching OUTSTANDING DUES & ADVANCE CREDIT (filtered by branch)
if (isset($_GET['action']) && $_GET['action'] == 'get_dues' && isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    $stmt = $pdo->prepare("SELECT SUM(total_amount - amount_paid) as outstanding FROM invoices WHERE student_id = ? AND status != 'Paid' AND branch_id = ?");
    $stmt->execute([$student_id, $active_branch_id]);
    $outstanding = $stmt->fetchColumn() ?? 0;
    
    $credit_stmt = $pdo->prepare("SELECT advance_balance FROM students WHERE id = ? AND branch_id = ?");
    $credit_stmt->execute([$student_id, $active_branch_id]);
    $advance_balance = $credit_stmt->fetchColumn() ?? 0;
    
    header('Content-Type: application/json');
    echo json_encode(['outstanding' => $outstanding, 'advance_balance' => $advance_balance]);
    exit();
}

// API endpoint for fetching FULL INVOICE DETAILS (filtered by branch)
if (isset($_GET['action']) && $_GET['action'] == 'get_invoice_details' && isset($_GET['id'])) {
    $invoice_id = $_GET['id'];
    $response = ['success' => false];
    $invoice_stmt = $pdo->prepare("
        SELECT i.*, s.student_name, s.admission_no, c.class_name, sess.session_name 
        FROM invoices i 
        JOIN students s ON i.student_id = s.id 
        JOIN classes c ON s.class_id = c.id 
        LEFT JOIN sessions sess ON i.session_id = sess.id 
        WHERE i.id = ? AND i.branch_id = ?");
    $invoice_stmt->execute([$invoice_id, $active_branch_id]);
    $invoice_details = $invoice_stmt->fetch();
    
    if ($invoice_details) {
        $items_stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $items_stmt->execute([$invoice_id]);
        $invoice_details['items'] = $items_stmt->fetchAll();
        
        // --- DATE FIX ---
        $invoice_details['due_date_formatted'] = date('d M, Y', strtotime($invoice_details['due_date']));
        $invoice_details['created_at_formatted'] = date('d M, Y', strtotime($invoice_details['created_at']));
        // --- END OF FIX ---
        
        $response['success'] = true;
        $response['data'] = $invoice_details;
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// API endpoint for fetching default fees for a student's class (filtered by branch)
if (isset($_GET['action']) && $_GET['action'] == 'get_class_fees' && isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    $response = ['success' => false, 'fees' => []];
    $class_stmt = $pdo->prepare("SELECT class_id FROM students WHERE id = ? AND branch_id = ?");
    $class_stmt->execute([$student_id, $active_branch_id]);
    $class_id = $class_stmt->fetchColumn();
    if ($class_id) {
        $fees_stmt = $pdo->prepare("SELECT cf.fee_head_id, fh.head_name, cf.amount 
                                      FROM class_fees cf 
                                      JOIN fee_heads fh ON cf.fee_head_id = fh.id 
                                      WHERE cf.class_id = ? AND cf.branch_id = ?");
        $fees_stmt->execute([$class_id, $active_branch_id]);
        $fees = $fees_stmt->fetchAll();
        if ($fees) {
            $response['success'] = true;
            $response['fees'] = $fees;
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Main Action Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_csrf_token();
    
    if ($_POST['action'] === 'create_invoice') {
        $pdo->beginTransaction();
        try {
            $student_id = $_POST['student_id'];
            
            $branch_stmt = $pdo->prepare("SELECT branch_id FROM students WHERE id = ?");
            $branch_stmt->execute([$student_id]);
            $student_branch_id = $branch_stmt->fetchColumn();

            if ($student_branch_id != $active_branch_id) {
                throw new Exception("Invalid student selected for this branch.");
            }
            
            $credit_stmt = $pdo->prepare("SELECT advance_balance FROM students WHERE id = ? AND branch_id = ?");
            $credit_stmt->execute([$student_id, $active_branch_id]);
            $advance_balance = $credit_stmt->fetchColumn() ?? 0;

            $dues_stmt = $pdo->prepare("SELECT SUM(total_amount - amount_paid) as outstanding FROM invoices WHERE student_id = ? AND status != 'Paid' AND branch_id = ?");
            $dues_stmt->execute([$student_id, $active_branch_id]);
            $outstanding_balance = (float)$dues_stmt->fetchColumn() ?? 0;

            $new_items_total = 0;
            if (isset($_POST['fee_heads'])) { foreach ($_POST['fee_heads'] as $amount) { $new_items_total += (float)$amount; } }
            $new_total_amount = $new_items_total + $outstanding_balance;
            $credit_to_apply = min($advance_balance, $new_total_amount);
            $remaining_credit = $advance_balance - $credit_to_apply;
            $new_status = 'Unpaid';
            if ($credit_to_apply > 0) { $new_status = ($credit_to_apply >= $new_total_amount) ? 'Paid' : 'Partially Paid'; }

            $invoice_uid = 'INV-' . time() . '-' . $student_id;
            $stmt = $pdo->prepare("INSERT INTO invoices (invoice_uid, student_id, session_id, total_amount, amount_paid, status, due_date, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$invoice_uid, $student_id, $_POST['session_id'], $new_total_amount, $credit_to_apply, $new_status, $_POST['due_date'], $active_branch_id]);
            $new_invoice_id = $pdo->lastInsertId();
            
            if ($outstanding_balance > 0) {
                $old_invoices_stmt = $pdo->prepare("SELECT id FROM invoices WHERE student_id = ? AND status != 'Paid' AND id != ? AND branch_id = ?");
                $old_invoices_stmt->execute([$student_id, $new_invoice_id, $active_branch_id]);
                $old_invoice_ids = $old_invoices_stmt->fetchAll(PDO::FETCH_COLUMN);

                if(!empty($old_invoice_ids)) {
                    $log_stmt = $pdo->prepare("INSERT INTO invoice_rollover_log (old_invoice_id, new_invoice_id) VALUES (?, ?)");
                    $update_stmt = $pdo->prepare("UPDATE invoices SET status = 'Paid', amount_paid = total_amount WHERE id = ? AND branch_id = ?");
                    foreach ($old_invoice_ids as $old_id) {
                        $log_stmt->execute([$old_id, $new_invoice_id]);
                        $update_stmt->execute([$old_id, $active_branch_id]);
                    }
                }
            }

            $item_stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, fee_head, amount) VALUES (?, ?, ?)");
            if ($outstanding_balance > 0) { $item_stmt->execute([$new_invoice_id, 'Previous Balance', $outstanding_balance]); }
            if (isset($_POST['fee_head_ids'])) {
                foreach ($_POST['fee_head_ids'] as $index => $head_id) {
                    if (!empty($_POST['fee_heads'][$index])) {
                        $head_name_stmt = $pdo->prepare("SELECT head_name FROM fee_heads WHERE id = ? AND branch_id = ?");
                        $head_name_stmt->execute([(int)$head_id, $active_branch_id]);
                        $head_name = $head_name_stmt->fetchColumn();
                        if($head_id === 'monthly_fee') $head_name = 'Full Monthly Fee';
                        
                        if ($head_name) {
                            $item_stmt->execute([$new_invoice_id, $head_name, (float)$_POST['fee_heads'][$index]]);
                        }
                    }
                }
            }
            $update_credit_stmt = $pdo->prepare("UPDATE students SET advance_balance = ? WHERE id = ? AND branch_id = ?");
            $update_credit_stmt->execute([$remaining_credit, $student_id, $active_branch_id]);
            $pdo->commit();
            set_flash_message("Invoice created. Credit of PKR " . number_format($credit_to_apply, 2) . " applied.");
        } catch (Exception $e) { $pdo->rollBack(); set_flash_message("Error: " . $e->getMessage(), 'error'); }
        header("Location: invoices.php");
        exit();
    }
    
    if ($_POST['action'] === 'delete_invoice') {
        $invoice_id_to_delete = $_POST['invoice_id_delete'];
        $pdo->beginTransaction();
        try {
            $invoice_stmt = $pdo->prepare("SELECT student_id, amount_paid FROM invoices WHERE id = ? AND branch_id = ?");
            $invoice_stmt->execute([$invoice_id_to_delete, $active_branch_id]);
            $invoice = $invoice_stmt->fetch();
            if ($invoice) {
                $student_id = $invoice['student_id'];
                $amount_paid_on_invoice = (float)$invoice['amount_paid'];
                if ($amount_paid_on_invoice > 0) {
                    $credit_stmt = $pdo->prepare("UPDATE students SET advance_balance = advance_balance + ? WHERE id = ? AND branch_id = ?");
                    $credit_stmt->execute([$amount_paid_on_invoice, $student_id, $active_branch_id]);
                }
                $log_check_stmt = $pdo->prepare("SELECT old_invoice_id FROM invoice_rollover_log WHERE new_invoice_id = ?");
                $log_check_stmt->execute([$invoice_id_to_delete]);
                $revert_ids = $log_check_stmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($revert_ids)) {
                    $placeholders = implode(',', array_fill(0, count($revert_ids), '?'));
                    $revert_stmt = $pdo->prepare("UPDATE invoices SET status = 'Unpaid', amount_paid = 0 WHERE id IN ($placeholders) AND branch_id = ?");
                    $revert_stmt->execute(array_merge($revert_ids, [$active_branch_id]));
                    $delete_log_stmt = $pdo->prepare("DELETE FROM invoice_rollover_log WHERE new_invoice_id = ?");
                    $delete_log_stmt->execute([$invoice_id_to_delete]);
                }
                $delete_payments_stmt = $pdo->prepare("DELETE FROM payments WHERE invoice_id = ? AND branch_id = ?");
                $delete_payments_stmt->execute([$invoice_id_to_delete, $active_branch_id]);
                $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ? AND branch_id = ?");
                $stmt->execute([$invoice_id_to_delete, $active_branch_id]);
                $pdo->commit();
                set_flash_message("Invoice deleted. Any amount paid (PKR " . number_format($amount_paid_on_invoice, 2) . ") has been returned to student credit.");
            } else { throw new Exception("Invoice not found or not in this branch."); }
        } catch (Exception $e) { $pdo->rollBack(); set_flash_message("Error deleting invoice: " . $e->getMessage(), 'error'); }
        header("Location: invoices.php");
        exit();
    }

    if ($_POST['action'] === 'generate_monthly') {
        $due_date = $_POST['monthly_due_date'];
        if (empty($due_date)) {
            set_flash_message('Please provide a due date.', 'error');
            header("Location: invoices.php"); exit();
        }
        $month = date('m', strtotime($due_date));
        $year = date('Y', strtotime($due_date));
        $generated_count = 0; $skipped_count = 0;
        try {
            $pdo->beginTransaction();
            $students_stmt = $pdo->prepare("SELECT id, class_id FROM students WHERE status = 'Active' AND branch_id = ?");
            $students_stmt->execute([$active_branch_id]);
            
            $check_stmt = $pdo->prepare("SELECT id FROM invoices WHERE student_id = ? AND MONTH(due_date) = ? AND YEAR(due_date) = ? AND branch_id = ?");
            $custom_fees_stmt = $pdo->prepare("SELECT fh.head_name, sf.amount FROM student_fee_structure sf JOIN fee_heads fh ON sf.fee_head_id = fh.id WHERE sf.student_id = ? AND sf.branch_id = ?");
            $class_fees_stmt = $pdo->prepare("SELECT fh.head_name, cf.amount FROM class_fees cf JOIN fee_heads fh ON cf.fee_head_id = fh.id WHERE cf.class_id = ? AND cf.branch_id = ?");
            $dues_stmt = $pdo->prepare("SELECT SUM(total_amount - amount_paid) as outstanding FROM invoices WHERE student_id = ? AND status != 'Paid' AND branch_id = ?");
            $old_invoices_stmt = $pdo->prepare("SELECT id FROM invoices WHERE student_id = ? AND status != 'Paid' AND branch_id = ?");
            $update_old_stmt = $pdo->prepare("UPDATE invoices SET status = 'Paid', amount_paid = total_amount WHERE id = ? AND branch_id = ?");
            $inv_stmt = $pdo->prepare("INSERT INTO invoices (invoice_uid, student_id, session_id, total_amount, due_date, branch_id) VALUES (?, ?, (SELECT id FROM sessions WHERE is_active=1 LIMIT 1), ?, ?, ?)");
            $item_stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, fee_head, amount) VALUES (?, ?, ?)");
            $log_stmt = $pdo->prepare("INSERT INTO invoice_rollover_log (old_invoice_id, new_invoice_id) VALUES (?, ?)");

            foreach ($students_stmt as $student) {
                $check_stmt->execute([$student['id'], $month, $year, $active_branch_id]);
                if ($check_stmt->fetch()) { $skipped_count++; continue; }
                
                $class_fees_stmt->execute([$student['class_id'], $active_branch_id]);
                $class_fees = $class_fees_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                if (empty($class_fees)) { $skipped_count++; continue; }
                
                $final_fee_structure = $class_fees;
                $custom_fees_stmt->execute([$student['id'], $active_branch_id]);
                $custom_fees = $custom_fees_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                foreach ($custom_fees as $head_name => $amount) { $final_fee_structure[$head_name] = $amount; }
                
                $dues_stmt->execute([$student['id'], $active_branch_id]);
                $outstanding_balance = $dues_stmt->fetchColumn() ?? 0;
                $old_invoice_ids = [];
                
                if ($outstanding_balance > 0) {
                    $old_invoices_stmt->execute([$student['id'], $active_branch_id]);
                    $old_invoice_ids = $old_invoices_stmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach($old_invoice_ids as $old_id) {
                        $update_old_stmt->execute([$old_id, $active_branch_id]);
                    }
                }
                
                $new_items_total = array_sum($final_fee_structure);
                $new_total_amount = $new_items_total + $outstanding_balance;
                $invoice_uid = 'INV-' . time() . '-' . $student['id'];
                
                $inv_stmt->execute([$invoice_uid, $student['id'], $new_total_amount, $due_date, $active_branch_id]);
                $new_invoice_id = $pdo->lastInsertId();

                if ($outstanding_balance > 0 && !empty($old_invoice_ids)) {
                    foreach ($old_invoice_ids as $old_id) {
                        $log_stmt->execute([$old_id, $new_invoice_id]);
                    }
                }
                
                if ($outstanding_balance > 0) { $item_stmt->execute([$new_invoice_id, 'Previous Balance', $outstanding_balance]); }
                foreach ($final_fee_structure as $head_name => $amount) {
                    $item_stmt->execute([$new_invoice_id, $head_name, $amount]);
                }
                $generated_count++;
            }
            $pdo->commit();
            set_flash_message("Process complete! Generated: {$generated_count}, Skipped: {$skipped_count}.");
        } catch (Exception $e) { $pdo->rollBack(); set_flash_message('An error occurred: ' . $e->getMessage(), 'error'); }
        header("Location: invoices.php");
        exit();
    }
}

// Data Fetching
$students_stmt = $pdo->prepare("SELECT id, student_name, admission_no FROM students WHERE status = 'Active' AND branch_id = ? ORDER BY student_name");
$students_stmt->execute([$active_branch_id]);
$students = $students_stmt->fetchAll();

$sessions = $pdo->query("SELECT id, session_name, is_active FROM sessions ORDER BY session_name DESC")->fetchAll();

$fee_heads_stmt = $pdo->prepare("SELECT id, head_name FROM fee_heads WHERE branch_id = ? ORDER BY head_name ASC");
$fee_heads_stmt->execute([$active_branch_id]);
$fee_heads_data = $fee_heads_stmt->fetchAll();

$invoices_stmt = $pdo->prepare("
    SELECT 
        i.id, i.invoice_uid, i.total_amount, i.amount_paid, i.due_date, i.status, 
        s.student_name, 
        u.phone as parent_phone 
    FROM invoices i 
    JOIN students s ON i.student_id = s.id
    JOIN users u ON s.parent_id = u.id
    WHERE i.branch_id = ? 
    ORDER BY i.created_at DESC
");
$invoices_stmt->execute([$active_branch_id]);
$invoices = $invoices_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-8"><h1 class="m-0">Invoice Management (Current Branch)</h1></div>
            <div class="col-sm-4 text-right">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createInvoiceModal"><i class="fas fa-plus"></i> Create Single Invoice</button>
                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#generateMonthlyModal"><i class="fas fa-cogs"></i> Generate Monthly</button>
            </div>
        </div>
    </div>
</div>
<div class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">All Invoices (Current Branch)</h3></div>
            <div class="card-body">
                <?php display_flash_messages(); ?>
                <table id="invoicesTable" class="table table-bordered table-striped">
                    <thead><tr><th>Invoice ID</th><th>Student Name</th><th>Total Amount</th><th>Amount Paid</th><th>Due Date</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($invoices as $invoice): ?>
                        <tr>
                            <td><?= he($invoice['invoice_uid']) ?></td>
                            <td><?= he($invoice['student_name']) ?></td>
                            <td><?= he(number_format($invoice['total_amount'], 2)) ?></td>
                            <td><?= he(number_format($invoice['amount_paid'], 2)) ?></td>
                            <td><?= he(date('d M, Y', strtotime($invoice['due_date']))) ?></td>
                            <td>
                                <?php $status_class = ['Paid' => 'success', 'Unpaid' => 'danger', 'Partially Paid' => 'warning'][$invoice['status']] ?? 'secondary'; ?>
                                <span class="badge badge-<?= $status_class ?>"><?= he($invoice['status']) ?></span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info view-btn" data-id="<?= he($invoice['id']) ?>"><i class="fas fa-eye"></i></button>
                                
                                <?php if ($invoice['status'] != 'Paid'): ?>
                                    <a href="send_reminder.php?id=<?= he($invoice['id']) ?>" class="btn btn-sm btn-warning" title="Send Email Reminder" onclick="return confirm('Send payment reminder?')"><i class="fas fa-envelope"></i></a>
                                    
                                    <?php
                                    $phone = preg_replace('/[^0-9]/', '', $invoice['parent_phone']);
                                    if (substr($phone, 0, 2) === '03') { $phone = '92' . substr($phone, 1); }
                                    $amount_due = $invoice['total_amount'] - $invoice['amount_paid'];
                                    $due_date_formatted = date('d M, Y', strtotime($invoice['due_date']));
                                    $school_name = he(get_setting('school_name', 'your school'));
                                    $message = "Dear Parent,\n\nThis is a friendly reminder from *{$school_name}* for your child, *{$invoice['student_name']}*.\n\n";
                                    $message .= "The fee voucher *#{$invoice['invoice_uid']}* is due.\n\n";
                                    $message .= "Amount Due: *PKR " . number_format($amount_due, 2) . "*\n";
                                    $message .= "Due Date: *{$due_date_formatted}*\n\n";
                                    $message .= "Please log in to the parent portal to pay or submit your proof of payment.\n\nThank you.";
                                    $encoded_message = urlencode($message);
                                    $whatsapp_url = "https://api.whatsapp.com/send?phone={$phone}&text={$encoded_message}";
                                    ?>
                                    <a href="<?= he($whatsapp_url) ?>" class="btn btn-sm btn-success" title="Send WhatsApp Reminder" target="_blank">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-danger delete-btn" data-id="<?= he($invoice['id']) ?>"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createInvoiceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="invoices.php">
                <?= csrf_input_field() ?><input type="hidden" name="action" value="create_invoice"><input type="hidden" name="outstanding_due_hidden" id="outstandingDueHidden">
                <div class="modal-header"><h4 class="modal-title">Create New Invoice</h4><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group"><label>Select Student</label>
                            <select name="student_id" id="studentSelector" class="form-control" required>
                                <option value="">-- Select a Student --</option>
                                <?php foreach($students as $student): ?>
                                <option value="<?= he($student['id']) ?>"><?= he($student['student_name']) ?> (<?= he($student['admission_no']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group"><label>Academic Session</label><select name="session_id" class="form-control" required><?php foreach($sessions as $session): ?><option value="<?= he($session['id']) ?>" <?= $session['is_active'] ? 'selected' : '' ?>><?= he($session['session_name']) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group"><label>Due Date</label><input type="date" name="due_date" class="form-control" required></div>
                        <div class="col-md-6 form-group"><label>Previous Outstanding Dues (will be added)</label><input type="text" id="outstandingDueDisplay" class="form-control" readonly style="font-weight: bold; color: red;"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"></div>
                        <div class="col-md-6 form-group"><label>Available Credit (will be applied)</label><input type="text" id="advanceBalanceDisplay" class="form-control" readonly style="font-weight: bold; color: green;"></div>
                    </div><hr>
                    <h5>Fee Items for Current Month</h5><div id="feeItemsContainer"></div>
                    <button type="button" id="addFeeItemBtn" class="btn btn-sm btn-secondary mt-2"><i class="fas fa-plus"></i> Add Item</button><hr>
                    <div class="text-right"><h4>New Total Amount: PKR <span id="grandTotalPayable">0.00</span></h4></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Create Invoice</button></div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="generateMonthlyModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="invoices.php">
                <?= csrf_input_field() ?><input type="hidden" name="action" value="generate_monthly">
                <div class="modal-header bg-success"><h4 class="modal-title">Generate Monthly Invoices</h4><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <p>This will generate new invoices for all active students in the **current branch** based on their class's fee structure.</p><p>It will automatically roll over any previous unpaid balances.</p><p class="text-info"><strong>Note:</strong> Students who already have an invoice for the selected month will be skipped.</p><hr>
                    <div class="form-group"><label for="monthly_due_date">Set Due Date for these Invoices:</label><input type="date" id="monthly_due_date" name="monthly_due_date" class="form-control" required></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success" onclick="return confirm('Are you sure?')">Generate Now</button></div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="viewInvoiceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header">
            <h4 class="modal-title">Invoice Details</h4>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body" id="invoiceDetailContent">
            <div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
        <div class="modal-footer justify-content-between">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="button" class="btn btn-info" id="printReceiptBtn"><i class="fas fa-receipt"></i> Print Receipt</button>
        </div>
    </div></div>
</div>
<div class="modal fade" id="deleteInvoiceModal">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="invoices.php">
            <?= csrf_input_field() ?>
            <input type="hidden" name="action" value="delete_invoice">
            <input type="hidden" name="invoice_id_delete" id="deleteInvoiceId">
            <div class="modal-header bg-danger"><h4 class="modal-title">Confirm Deletion</h4><button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body">
                <p>Are you sure you want to permanently delete this invoice?</p>
                <p class="text-danger">If this invoice contains a rolled-over balance or payments, the transaction will be reversed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Delete</button>
            </div>
        </form>
    </div></div>
</div>

<div id="receipt-print-area" style="display: none;"></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /><script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    
    // --- THIS IS THE FIX ---
    $('#invoicesTable').DataTable({
        "destroy": true, // Prevents 'Cannot reinitialise' error
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        
        // 1. Show newest invoices first
        "order": [[ 0, "desc" ]], 
        
        // 2. Set page length options (100, 200, 500)
        "lengthMenu": [ [100, 200, 500, -1], [100, 200, 500, "All"] ]
    });
    // --- END OF FIX ---
    
    
    $('#studentSelector').select2({ dropdownParent: $('#createInvoiceModal') });

    $('#studentSelector').on('change', function() {
        var studentId = $(this).val();
        $('#outstandingDueDisplay').val('0.00');
        $('#outstandingDueHidden').val('0.00');
        $('#advanceBalanceDisplay').val('0.00');
        if (studentId) {
            $.ajax({
                url: 'invoices.php?action=get_dues&student_id=' + studentId, type: 'GET', dataType: 'json',
                success: function(response) {
                    $('#outstandingDueDisplay').val((parseFloat(response.outstanding) || 0).toFixed(2));
                    $('#outstandingDueHidden').val((parseFloat(response.outstanding) || 0).toFixed(2));
                    $('#advanceBalanceDisplay').val((parseFloat(response.advance_balance) || 0).toFixed(2));
                    updateTotalAmount();
                }
            });
        } else {
            updateTotalAmount();
        }
    });

    $('#addFeeItemBtn').on('click', function() {
        var feeItemHtml = `<div class="row fee-item mb-2"><div class="col-5"><select name="fee_head_ids[]" class="form-control fee-head-select" required><option value="">-- Select Fee Head --</option><option value="monthly_fee" style="font-weight:bold; color:blue;">Full Monthly Fee</option><option disabled>-----------------</option><?php foreach($fee_heads_data as $head): ?><option value="<?= he($head['id']) ?>"><?= he($head['head_name']) ?></option><?php endforeach; ?></select></div><div class="col-5"><input type="number" name="fee_heads[]" class="form-control fee-amount" placeholder="Amount" step="0.01" required></div><div class="col-2"><button type="button" class="btn btn-danger btn-sm remove-fee-item"><i class="fas fa-times"></i></button></div></div>`;
        $('#feeItemsContainer').append(feeItemHtml);
    });

    $(document).on('change', '.fee-head-select', function() {
        if ($(this).val() === 'monthly_fee') {
            var studentId = $('#studentSelector').val();
            if (!studentId) { alert('Please select a student first.'); $(this).val(''); return; }
            var amountInput = $(this).closest('.fee-item').find('.fee-amount');
            $.ajax({
                url: `invoices.php?action=get_class_fees&student_id=${studentId}`, type: 'GET', dataType: 'json',
                success: function(response) {
                    if (response.success && response.fees.length > 0) {
                        var totalMonthlyFee = 0;
                        response.fees.forEach(function(fee) { totalMonthlyFee += parseFloat(fee.amount); });
                        amountInput.val(totalMonthlyFee.toFixed(2));
                        updateTotalAmount();
                    } else {
                        alert("No default fee structure found for this student's class.");
                        $(this).val('');
                    }
                }.bind(this)
            });
        }
    });

    $(document).on('click', '.remove-fee-item', function() { $(this).closest('.fee-item').remove(); updateTotalAmount(); });
    $(document).on('input', '.fee-amount', updateTotalAmount);
    
    function updateTotalAmount() {
        var currentTotal = 0;
        $('.fee-amount').each(function() { currentTotal += parseFloat($(this).val()) || 0; });
        var outstanding = parseFloat($('#outstandingDueHidden').val()) || 0;
        var grandTotal = currentTotal + outstanding;
        $('#grandTotalPayable').text(grandTotal.toFixed(2));
    }
    
    var currentInvoiceData = null;
    
    $('#invoicesTable tbody').on('click', '.view-btn', function() {
        var invoiceId = $(this).data('id');
        currentInvoiceData = null;
        $('#invoiceDetailContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
        $('#viewInvoiceModal').modal('show');
        
        $.ajax({
            url: 'invoices.php?action=get_invoice_details&id=' + invoiceId, type: 'GET', dataType: 'json',
            success: function(response) {
                if (response.success) {
                    currentInvoiceData = response.data;
                    var inv = response.data;
                    var itemsHtml = '';
                    inv.items.forEach(function(item) { itemsHtml += `<tr><td>${item.fee_head}</td><td class="text-right">${parseFloat(item.amount).toFixed(2)}</td></tr>`; });
                    var balanceDue = (parseFloat(inv.total_amount) - parseFloat(inv.amount_paid)).toFixed(2);
                    
                    var contentHtml = `
                        <div class="row">
                            <div class="col-md-6"><p><strong>Student:</strong> ${inv.student_name}<br><strong>Adm No:</strong> ${inv.admission_no}<br><strong>Class:</strong> ${inv.class_name}</p></div>
                            <div class="col-md-6 text-md-right"><p><strong>Status:</strong> <span class="badge badge-info">${inv.status}</span><br><strong>Due Date:</strong> ${inv.due_date_formatted}</p></div>
                        </div>
                        <table class="table table-sm"><thead class="thead-light"><tr><th>Description</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>${itemsHtml}</tbody>
                        <tfoot>
                            <tr><th class="text-right">Total Amount:</th><th class="text-right">${parseFloat(inv.total_amount).toFixed(2)}</th></tr>
                            <tr><th class="text-right">Amount Paid:</th><th class="text-right">${parseFloat(inv.amount_paid).toFixed(2)}</th></tr>
                            <tr class="font-weight-bold"><th class="text-right">Balance Due:</th><th class="text-right">${balanceDue}</th></tr>
                        </tfoot>
                        </table>`;
                    $('#invoiceDetailContent').html(contentHtml);
                }
            }
        });
    });

    $('#invoicesTable tbody').on('click', '.delete-btn', function() {
        var invoiceId = $(this).data('id');
        $('#deleteInvoiceId').val(invoiceId);
        $('#deleteInvoiceModal').modal('show');
    });

    $(document).on('click', '#printReceiptBtn', function() {
        if (!currentInvoiceData) { alert('Invoice data not loaded. Please try again.'); return; }
        var inv = currentInvoiceData;
        var balanceDue = (parseFloat(inv.total_amount) - parseFloat(inv.amount_paid)).toFixed(2);
        var itemsHtml = '';
        inv.items.forEach(function(item) { itemsHtml += `<tr><td>${item.fee_head}</td><td class="amount">${parseFloat(item.amount).toFixed(2)}</td></tr>`; });
        
        var receiptHtml = `<div class="thermal-receipt">
            <div class="header">
                <h5>${ "<?= he(get_setting('school_name', 'School Name')) ?>" }</h5>
                <p>${ "<?= he(get_setting('school_address', 'School Address')) ?>" }</p>
                <p>--- Fee Receipt ---</p>
            </div>
            <p><strong>Invoice #:</strong> ${inv.invoice_uid}</p>
            <p><strong>Issue Date:</strong> ${inv.created_at_formatted}</p>
            <p><strong>Due Date:</strong> ${inv.due_date_formatted}</p>
            <p><strong>Student:</strong> ${inv.student_name}</p>
            <p><strong>Class:</strong> ${inv.class_name}</p>
            <hr>
            <table class="items-table"><thead><tr><th>Description</th><th class="amount">Amount</th></tr></thead><tbody>${itemsHtml}</tbody></table>
            <hr>
            <table class="items-table"><tbody>
                <tr><td><strong>Total Amount:</strong></td><td class="amount">${parseFloat(inv.total_amount).toFixed(2)}</td></tr>
                <tr><td><strong>Amount Paid:</strong></td><td class="amount">${parseFloat(inv.amount_paid).toFixed(2)}</td></tr>
                <tr><td><strong>Balance Due:</strong></td><td class="amount"><strong>${balanceDue}</strong></td></tr>
            </tbody></table>
            <hr>
            <p><strong>Status:</strong> ${inv.status}</p>
            <p style="text-align:center; margin-top:10px;">Thank you!</p>
        </div>`;
        
        $('#receipt-print-area').html(receiptHtml);
        $('body').addClass('receipt-print-mode');
        window.print();
        setTimeout(function() {
            $('body').removeClass('receipt-print-mode');
            $('#receipt-print-area').empty();
        }, 500);
    });

});
</script>