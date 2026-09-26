<?php
// school-fees-system/admin/manual_collection.php (Multi-Branch Enabled & VOUCHER FIX)

$page_title = 'Manual Fee Collection';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']);
$active_branch_id = get_active_branch_id();

if (!$active_branch_id) {
    set_flash_message('You must select a branch to manage collections.', 'error');
    header('Location: index.php');
    exit;
}

// API endpoint to fetch a student's unpaid invoices (filtered by branch)
if (isset($_GET['action']) && $_GET['action'] == 'get_unpaid_invoices' && isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    $response = ['success' => false, 'invoices' => []];
    
    // Ensure student belongs to the active branch
    $student_stmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND branch_id = ?");
    $student_stmt->execute([$student_id, $active_branch_id]);
    
    if ($student_stmt->fetch()) {
        $stmt = $pdo->prepare("SELECT id, invoice_uid, total_amount, amount_paid FROM invoices WHERE student_id = ? AND status != 'Paid' AND branch_id = ? ORDER BY due_date ASC");
        $stmt->execute([$student_id, $active_branch_id]);
        $invoices = $stmt->fetchAll();
        
        if ($invoices) {
            $response['success'] = true;
            $response['invoices'] = $invoices;
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_csrf_token();
    
    // --- Collect Payment Logic (Branch-Aware) ---
    if ($_POST['action'] === 'collect_payment') {
        $invoice_id = $_POST['invoice_id'];
        $amount_collected = (float)($_POST['amount_collected'] ?? 0);
        $payment_method = $_POST['payment_method'];
        $admin_id = $_SESSION['user_id'];
        
        if (empty($invoice_id) || $amount_collected <= 0) {
            set_flash_message('Please select an invoice and enter a valid amount.', 'error');
        } else {
            $pdo->beginTransaction();
            try {
                // Get invoice and student details, ensuring they are in the active branch
                $stmt_inv = $pdo->prepare("SELECT student_id, total_amount, amount_paid FROM invoices WHERE id = ? AND branch_id = ?");
                $stmt_inv->execute([$invoice_id, $active_branch_id]);
                $invoice = $stmt_inv->fetch();

                if (!$invoice) {
                    throw new Exception("Invalid invoice selected for this branch.");
                }

                $student_id = $invoice['student_id'];
                $due_on_invoice = $invoice['total_amount'] - $invoice['amount_paid'];
                $overpayment = 0;
                $amount_to_apply = $amount_collected;
                if ($amount_collected > $due_on_invoice) {
                    $overpayment = $amount_collected - $due_on_invoice;
                    $amount_to_apply = $due_on_invoice;
                }
                
                $parent_id_stmt = $pdo->prepare("SELECT parent_id FROM students WHERE id = ?");
                $parent_id_stmt->execute([$student_id]);
                $parent_id = $parent_id_stmt->fetchColumn();
                
                // Add branch_id to the new payment record
                $stmt1 = $pdo->prepare("INSERT INTO payments (invoice_id, parent_id, amount, payment_method, status, verified_by, transaction_id, branch_id) VALUES (?, ?, ?, ?, 'Verified', ?, ?, ?)");
                $stmt1->execute([$invoice_id, $parent_id, $amount_collected, $payment_method, $admin_id, 'Manual-' . time(), $active_branch_id]);
                
                // --- THIS IS THE FIX: Capture the payment ID ---
                $payment_id = $pdo->lastInsertId();

                // --- THIS IS THE FIX: Create the Voucher Record ---
                $voucher_code = 'VCH-MAN-' . time() . '-' . $payment_id;
                $stmt_v = $pdo->prepare("INSERT INTO vouchers (payment_id, voucher_code, file_path) VALUES (?, ?, '')");
                $stmt_v->execute([$payment_id, $voucher_code]);
                // -------------------------------------------------
                
                if ($amount_to_apply > 0) {
                    $stmt2 = $pdo->prepare("UPDATE invoices SET amount_paid = amount_paid + ? WHERE id = ?");
                    $stmt2->execute([$amount_to_apply, $invoice_id]);
                }
                if ($overpayment > 0) {
                    $stmt3 = $pdo->prepare("UPDATE students SET advance_balance = advance_balance + ? WHERE id = ?");
                    $stmt3->execute([$overpayment, $student_id]);
                }
                
                $stmt4 = $pdo->prepare("UPDATE invoices SET status = CASE WHEN amount_paid >= total_amount THEN 'Paid' ELSE 'Partially Paid' END WHERE id = ?");
                $stmt4->execute([$invoice_id]);
                
                $pdo->commit();
                log_activity("Manually collected PKR {$amount_collected} for Invoice ID #{$invoice_id}.");
                set_flash_message("Payment recorded. Voucher generated.");
            } catch (Exception $e) { $pdo->rollBack(); set_flash_message('Error: ' . $e->getMessage(), 'error'); }
        }
    }

    // --- Issue Refund Logic (Branch-Aware) ---
    if ($_POST['action'] === 'issue_refund') {
        $student_id = $_POST['student_id_refund'];
        $amount_to_refund = (float)($_POST['amount_to_refund'] ?? 0);
        $notes = trim($_POST['refund_notes']);

        if (empty($student_id) || $amount_to_refund <= 0) {
            set_flash_message('Please select a student and enter a valid refund amount.', 'error');
        } else {
            $pdo->beginTransaction();
            try {
                // Get student's current advance balance (and check branch)
                $credit_stmt = $pdo->prepare("SELECT advance_balance FROM students WHERE id = ? AND branch_id = ? FOR UPDATE");
                $credit_stmt->execute([$student_id, $active_branch_id]);
                $advance_balance_record = $credit_stmt->fetch();

                if (!$advance_balance_record) {
                    throw new Exception("Student not found in this branch.");
                }
                $advance_balance = $advance_balance_record['advance_balance'] ?? 0;

                if ($amount_to_refund > $advance_balance) {
                    throw new Exception("Refund amount cannot be more than the student's available credit (PKR " . number_format($advance_balance, 2) . ").");
                }

                $update_stmt = $pdo->prepare("UPDATE students SET advance_balance = advance_balance - ? WHERE id = ?");
                $update_stmt->execute([$amount_to_refund, $student_id]);

                $parent_id_stmt = $pdo->prepare("SELECT parent_id FROM students WHERE id = ?");
                $parent_id_stmt->execute([$student_id]);
                $parent_id = $parent_id_stmt->fetchColumn();
                
                // Log this refund (as a negative payment) with the correct branch_id
                $log_payment_stmt = $pdo->prepare("INSERT INTO payments (parent_id, amount, payment_method, status, verified_by, transaction_id, remarks, branch_id) VALUES (?, ?, ?, 'Verified', ?, ?, ?, ?)");
                $log_payment_stmt->execute([$parent_id, -$amount_to_refund, 'Refund', $_SESSION['user_id'], 'Refund-' . time(), $notes, $active_branch_id]);
                
                $pdo->commit();
                log_activity("Issued refund of PKR {$amount_to_refund} to student ID #{$student_id}.");
                set_flash_message("Refund of PKR " . number_format($amount_to_refund, 2) . " issued successfully.");
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash_message('Error issuing refund: ' . $e->getMessage(), 'error');
            }
        }
    }
    
    header("Location: manual_collection.php");
    exit();
}

// Fetch all active students for the dropdown (from the active branch only)
$stmt_students = $pdo->prepare("SELECT id, student_name, admission_no FROM students WHERE status = 'Active' AND branch_id = ? ORDER BY student_name");
$stmt_students->execute([$active_branch_id]);
$students = $stmt_students->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">Manual Fee Collection</h1></div></div></div>
</div>

<div class="content">
    <div class="container-fluid">
        <?php display_flash_messages(); ?>
        <div class="row">
            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Collect Payment (Current Branch)</h3></div>
                    <form method="POST">
                        <?= csrf_input_field() ?><input type="hidden" name="action" value="collect_payment">
                        <div class="card-body">
                            <div class="form-group"><label>Select Student</label><select id="studentSelectorCollect" class="form-control" required><option value="">-- Select a student --</option><?php foreach($students as $student): ?><option value="<?= he($student['id']) ?>"><?= he($student['student_name']) ?> (<?= he($student['admission_no']) ?>)</option><?php endforeach; ?></select></div>
                            <div id="invoice-details-area" style="display: none;">
                                <hr><h5>Outstanding Invoices</h5>
                                <div class="form-group"><label>Select Invoice to Pay Against</label><select name="invoice_id" id="invoiceSelector" class="form-control" required></select></div>
                                <div class="row">
                                    <div class="col-md-6 form-group"><label for="amount_collected">Amount Collected</label><input type="number" name="amount_collected" id="amount_collected" class="form-control" step="0.01" required></div>
                                    <div class="col-md-6 form-group"><label for="payment_method">Payment Method</label><select name="payment_method" id="payment_method" class="form-control" required><option value="Cash">Cash</option><option value="Cheque">Cheque</option><option value="Bank Deposit (Office)">Bank Deposit (Office)</option></select></div>
                                </div>
                                <button type="submit" class="btn btn-success"><i class="fas fa-check-circle"></i> Record Payment</button>
                            </div>
                            <div id="no-invoices-message" class="alert alert-info" style="display: none;">This student has no outstanding invoices.</div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-danger">
                    <div class="card-header"><h3 class="card-title">Issue Advance Refund (Current Branch)</h3></div>
                    <form method="POST">
                        <?= csrf_input_field() ?><input type="hidden" name="action" value="issue_refund">
                        <div class="card-body">
                            <div class="form-group"><label>Select Student</label><select name="student_id_refund" id="studentSelectorRefund" class="form-control" required><option value="">-- Select a student --</option><?php foreach($students as $student): ?><option value="<?= he($student['id']) ?>"><?= he($student['student_name']) ?> (<?= he($student['admission_no']) ?>)</option><?php endforeach; ?></select></div>
                            <div id="refund-details-area" style="display: none;">
                                <hr>
                                <div class="form-group"><label>Available Advance Credit</label><input type="text" id="availableCreditDisplay" class="form-control" readonly style="font-weight: bold; color: green;"></div>
                                <div class="form-group"><label for="amount_to_refund">Amount to Refund</label><input type="number" name="amount_to_refund" id="amount_to_refund" class="form-control" step="0.01" max="0.00" required></div>
                                <div class="form-group"><label for="refund_notes">Reason / Notes</label><input type="text" name="refund_notes" id="refund_notes" class="form-control" placeholder="e.g., Cash refund for overpayment" required></div>
                                <button type="submit" class="btn btn-danger"><i class="fas fa-minus-circle"></i> Issue Refund</button>
                            </div>
                            <div id="no-credit-message" class="alert alert-warning" style="display: none;">This student has no advance credit to refund.</div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#studentSelectorCollect').select2({ placeholder: '-- Select a student --', allowClear: true });
    $('#studentSelectorRefund').select2({ placeholder: '-- Select a student --', allowClear: true });

    // --- Logic for Collect Payment ---
    $('#studentSelectorCollect').on('change', function() {
        var studentId = $(this).val();
        $('#invoice-details-area').hide();
        $('#no-invoices-message').hide();
        $('#invoiceSelector').empty();
        if (studentId) {
            $.ajax({
                url: 'manual_collection.php?action=get_unpaid_invoices&student_id=' + studentId, type: 'GET', dataType: 'json',
                success: function(response) {
                    if (response.success && response.invoices.length > 0) {
                        response.invoices.forEach(function(invoice) {
                            var balanceDue = (parseFloat(invoice.total_amount) - parseFloat(invoice.amount_paid)).toFixed(2);
                            var optionText = `${invoice.invoice_uid} - Due: ${balanceDue}`;
                            $('#invoiceSelector').append(new Option(optionText, invoice.id));
                        });
                        $('#invoice-details-area').show();
                    } else {
                        $('#no-invoices-message').show();
                    }
                }
            });
        }
    });

    // --- Logic for Issue Refund ---
    $('#studentSelectorRefund').on('change', function() {
        var studentId = $(this).val();
        $('#refund-details-area').hide();
        $('#no-credit-message').hide();

        if (studentId) {
            // We reuse the get_dues API since it returns advance_balance
            $.ajax({
                url: 'invoices.php?action=get_dues&student_id=' + studentId,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    var advance = parseFloat(response.advance_balance) || 0;
                    if (advance > 0) {
                        $('#availableCreditDisplay').val(advance.toFixed(2));
                        $('#amount_to_refund').attr('max', advance.toFixed(2));
                        $('#refund-details-area').show();
                    } else {
                        $('#no-credit-message').show();
                    }
                }
            });
        }
    });
});
</script>