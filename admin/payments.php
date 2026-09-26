<?php
// school-fees-system/admin/payments.php (Multi-Branch Enabled & FIXED VOUCHER CREATION)

$page_title = 'Payment Management';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']);
$active_branch_id = get_active_branch_id();

if (!$active_branch_id) {
    set_flash_message('You must select a branch to manage payments.', 'error');
    header('Location: index.php');
    exit;
}

// --- ACTION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_csrf_token();
    
    $payment_id = $_POST['payment_id'];
    $admin_id = $_SESSION['user_id'];

    // --- VERIFY ACTION ---
    if ($_POST['action'] === 'verify') {
        $invoice_id = $_POST['invoice_id'];
        $payment_amount = (float)$_POST['payment_amount'];

        $pdo->beginTransaction();
        try {
            // Check 1: Ensure the payment belongs to the active branch
            $stmt_payment_check = $pdo->prepare("SELECT id FROM payments WHERE id = ? AND branch_id = ?");
            $stmt_payment_check->execute([$payment_id, $active_branch_id]);
            if (!$stmt_payment_check->fetch()) {
                throw new Exception("Payment not found in this branch.");
            }

            // Check 2: Get invoice details
            $stmt = $pdo->prepare("SELECT i.total_amount, i.amount_paid, i.student_id FROM invoices i WHERE i.id = ? AND i.branch_id = ?");
            $stmt->execute([$invoice_id, $active_branch_id]);
            $invoice = $stmt->fetch();

            if (!$invoice) { throw new Exception("Invoice not found in this branch."); }
            $student_id = $invoice['student_id'];
            $due_on_invoice = $invoice['total_amount'] - $invoice['amount_paid'];
            
            $overpayment = 0;
            $amount_to_apply = $payment_amount;
            if ($payment_amount > $due_on_invoice) {
                $overpayment = $payment_amount - $due_on_invoice;
                $amount_to_apply = $due_on_invoice;
            }
            
            // 1. Update Payment Status
            $stmt1 = $pdo->prepare("UPDATE payments SET status = 'Verified', verified_by = ? WHERE id = ? AND branch_id = ?");
            $stmt1->execute([$admin_id, $payment_id, $active_branch_id]);

            // 2. Update Invoice Paid Amount
            if ($amount_to_apply > 0) {
                $stmt2 = $pdo->prepare("UPDATE invoices SET amount_paid = amount_paid + ? WHERE id = ? AND branch_id = ?");
                $stmt2->execute([$amount_to_apply, $invoice_id, $active_branch_id]);
            }
            
            // 3. Update Student Credit (if overpaid)
            if ($overpayment > 0) {
                $stmt3 = $pdo->prepare("UPDATE students SET advance_balance = advance_balance + ? WHERE id = ? AND branch_id = ?");
                $stmt3->execute([$overpayment, $student_id, $active_branch_id]);
            }
            
            // 4. Update Invoice Status
            $stmt4 = $pdo->prepare("UPDATE invoices SET status = CASE WHEN amount_paid >= total_amount THEN 'Paid' ELSE 'Partially Paid' END WHERE id = ? AND branch_id = ?");
            $stmt4->execute([$invoice_id, $active_branch_id]);

            // 5. CREATE VOUCHER RECORD (THIS WAS MISSING)
            $voucher_code = 'VCH-' . time() . '-' . $payment_id;
            // Check if voucher exists to avoid duplicates
            $check_v = $pdo->prepare("SELECT id FROM vouchers WHERE payment_id = ?");
            $check_v->execute([$payment_id]);
            if(!$check_v->fetch()) {
                $stmt_v = $pdo->prepare("INSERT INTO vouchers (payment_id, voucher_code, file_path) VALUES (?, ?, '')");
                $stmt_v->execute([$payment_id, $voucher_code]);
            }

            $pdo->commit();
            log_activity("Verified payment ID #{$payment_id}. Voucher generated.");
            set_flash_message('Payment verified and voucher generated.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash_message('Error: ' . $e->getMessage(), 'error');
        }
    }

    // --- REJECT ACTION ---
    if ($_POST['action'] === 'reject') {
        $remarks = $_POST['remarks'];
        if (empty($remarks)) {
            set_flash_message('Rejection reason is required.', 'error');
        } else {
            $stmt = $pdo->prepare("UPDATE payments SET status = 'Rejected', remarks = ?, verified_by = ? WHERE id = ? AND branch_id = ?");
            if ($stmt->execute([$remarks, $admin_id, $payment_id, $active_branch_id])) {
                set_flash_message('Payment successfully rejected.');
            } else {
                set_flash_message('Failed to reject payment.', 'error');
            }
        }
    }

    // --- REVERSE ACTION ---
    if ($_POST['action'] === 'reverse') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT amount, status, invoice_id FROM payments WHERE id = ? AND branch_id = ?");
            $stmt->execute([$payment_id, $active_branch_id]);
            $payment = $stmt->fetch();
            if (!$payment) { throw new Exception("Payment not found."); }
            
            if ($payment['status'] === 'Verified') {
                $invoice_id = $payment['invoice_id'];
                $amount_to_reverse = (float)$payment['amount'];
                
                $stmt_inv = $pdo->prepare("SELECT total_amount, amount_paid, student_id FROM invoices WHERE id = ? AND branch_id = ?");
                $stmt_inv->execute([$invoice_id, $active_branch_id]);
                $invoice = $stmt_inv->fetch();
                
                $due_before = $invoice['total_amount'] - ($invoice['amount_paid'] - $amount_to_reverse);
                $overpayment_reverse = ($amount_to_reverse > $due_before) ? ($amount_to_reverse - $due_before) : 0;
                
                if ($overpayment_reverse > 0) {
                    $stmt_stud = $pdo->prepare("UPDATE students SET advance_balance = advance_balance - ? WHERE id = ? AND branch_id = ?");
                    $stmt_stud->execute([$overpayment_reverse, $invoice['student_id'], $active_branch_id]);
                }
                
                $stmt_update_invoice = $pdo->prepare("UPDATE invoices SET amount_paid = amount_paid - ? WHERE id = ? AND branch_id = ?");
                $stmt_update_invoice->execute([$amount_to_reverse, $invoice_id, $active_branch_id]);
                
                $stmt_update_status = $pdo->prepare("UPDATE invoices SET status = CASE WHEN amount_paid <= 0 THEN 'Unpaid' ELSE 'Partially Paid' END WHERE id = ? AND branch_id = ?");
                $stmt_update_status->execute([$invoice_id, $active_branch_id]);

                // DELETE VOUCHER (Clean up)
                $del_v = $pdo->prepare("DELETE FROM vouchers WHERE payment_id = ?");
                $del_v->execute([$payment_id]);
            }
            
            $stmt_reverse = $pdo->prepare("UPDATE payments SET status = 'Pending', remarks = NULL, verified_by = NULL WHERE id = ? AND branch_id = ?");
            $stmt_reverse->execute([$payment_id, $active_branch_id]);

            $pdo->commit();
            set_flash_message("Payment reversed to Pending. Voucher deleted.");
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash_message('Error: ' . $e->getMessage(), 'error');
        }
    }
    
    header("Location: payments.php?status=" . ($_GET['status'] ?? 'Pending'));
    exit();
}

// Fetch payments
$pending_payments = $pdo->prepare("SELECT p.*, u.full_name as parent_name, i.invoice_uid FROM payments p LEFT JOIN users u ON p.parent_id = u.id LEFT JOIN invoices i ON p.invoice_id = i.id WHERE p.status = 'Pending' AND p.branch_id = ? ORDER BY p.payment_date ASC");
$pending_payments->execute([$active_branch_id]);
$pending_payments = $pending_payments->fetchAll();

$verified_payments = $pdo->prepare("SELECT p.*, u.full_name as parent_name, i.invoice_uid, verifier.full_name as verifier_name FROM payments p LEFT JOIN users u ON p.parent_id = u.id LEFT JOIN invoices i ON p.invoice_id = i.id LEFT JOIN users verifier ON p.verified_by = verifier.id WHERE p.status = 'Verified' AND p.branch_id = ? ORDER BY p.payment_date DESC LIMIT 50");
$verified_payments->execute([$active_branch_id]);
$verified_payments = $verified_payments->fetchAll();

$rejected_payments = $pdo->prepare("SELECT p.*, u.full_name as parent_name, i.invoice_uid, verifier.full_name as verifier_name FROM payments p LEFT JOIN users u ON p.parent_id = u.id LEFT JOIN invoices i ON p.invoice_id = i.id LEFT JOIN users verifier ON p.verified_by = verifier.id WHERE p.status = 'Rejected' AND p.branch_id = ? ORDER BY p.payment_date DESC LIMIT 50");
$rejected_payments->execute([$active_branch_id]);
$rejected_payments = $rejected_payments->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header"><div class="container-fluid"><h1 class="m-0">Verify Payments</h1></div></div>
<div class="content">
    <div class="container-fluid">
        <?php display_flash_messages(); ?>
        <div class="card card-primary card-tabs">
            <div class="card-header p-0 pt-1"><ul class="nav nav-tabs" id="payment-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" id="pending-tab" data-toggle="pill" href="#pending-content" role="tab">Pending <span class="badge badge-warning"><?= count($pending_payments) ?></span></a></li>
                <li class="nav-item"><a class="nav-link" id="verified-tab" data-toggle="pill" href="#verified-content" role="tab">Verified</a></li>
                <li class="nav-item"><a class="nav-link" id="rejected-tab" data-toggle="pill" href="#rejected-content" role="tab">Rejected</a></li>
            </ul></div>
            <div class="card-body">
                <div class="tab-content" id="payment-tabs-content">
                    
                    <div class="tab-pane fade show active" id="pending-content" role="tabpanel">
                        <table class="table table-bordered table-hover" id="pendingTable">
                            <thead><tr><th>Parent</th><th>Invoice</th><th>Amount</th><th>Proof</th><th>Date</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($pending_payments as $payment): ?>
                                    <tr>
                                        <td><?= he($payment['parent_name']) ?></td><td><?= he($payment['invoice_uid']) ?></td><td><?= he(number_format($payment['amount'], 2)) ?></td>
                                        <td><a href="<?= SITE_URL . '/uploads/proofs/' . he($payment['proof_image']) ?>" target="_blank" class="btn btn-sm btn-info">Proof</a></td>
                                        <td><?= he(date('d M Y', strtotime($payment['payment_date']))) ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;"><?= csrf_input_field() ?><input type="hidden" name="action" value="verify"><input type="hidden" name="payment_id" value="<?= $payment['id'] ?>"><input type="hidden" name="invoice_id" value="<?= $payment['invoice_id'] ?>"><input type="hidden" name="payment_amount" value="<?= $payment['amount'] ?>"><button type="submit" class="btn btn-sm btn-success">Verify</button></form>
                                            <button class="btn btn-sm btn-danger reject-btn" data-id="<?= $payment['id'] ?>" data-toggle="modal" data-target="#rejectModal">Reject</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="tab-pane fade" id="verified-content" role="tabpanel">
                        <table class="table table-bordered table-hover" id="verifiedTable">
                            <thead><tr><th>Parent</th><th>Invoice</th><th>Amount</th><th>Verifier</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($verified_payments as $payment): ?>
                                    <tr>
                                        <td><?= he($payment['parent_name']) ?></td><td><?= he($payment['invoice_uid']) ?></td><td><?= he(number_format($payment['amount'], 2)) ?></td>
                                        <td><?= he($payment['verifier_name']) ?></td>
                                        <td><form method="POST"><?= csrf_input_field() ?><input type="hidden" name="action" value="reverse"><input type="hidden" name="payment_id" value="<?= $payment['id'] ?>"><button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Reverse this payment?')">Reverse</button></form></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="tab-pane fade" id="rejected-content" role="tabpanel">
                        <table class="table table-bordered" id="rejectedTable">
                            <thead><tr><th>Parent</th><th>Amount</th><th>Reason</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($rejected_payments as $payment): ?>
                                    <tr>
                                        <td><?= he($payment['parent_name']) ?></td><td><?= he($payment['amount']) ?></td><td><?= he($payment['remarks']) ?></td>
                                        <td><form method="POST"><?= csrf_input_field() ?><input type="hidden" name="action" value="reverse"><input type="hidden" name="payment_id" value="<?= $payment['id'] ?>"><button type="submit" class="btn btn-sm btn-secondary">Reverse</button></form></td>
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

<div class="modal fade" id="rejectModal">
    <div class="modal-dialog"><div class="modal-content"><form method="POST">
        <?= csrf_input_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="payment_id" id="rejectPaymentId">
        <div class="modal-header"><h4 class="modal-title">Reject Payment</h4></div>
        <div class="modal-body"><textarea name="remarks" class="form-control" required placeholder="Reason..."></textarea></div>
        <div class="modal-footer"><button type="submit" class="btn btn-danger">Reject</button></div>
    </form></div></div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
$(document).ready(function() {
    $('#pendingTable, #verifiedTable, #rejectedTable').DataTable({"destroy":true, "order":[[0, "desc"]]});
    $('.reject-btn').click(function(){ $('#rejectPaymentId').val($(this).data('id')); });
});
</script>