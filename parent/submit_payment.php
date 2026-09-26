<?php
// school-fees-system/parent/submit_payment.php

$page_title = 'Submit Payment';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Parent']);

$parent_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = $_POST['invoice_id'] ?? null;
    $amount = $_POST['amount'] ?? 0;
    $transaction_id = $_POST['transaction_id'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $proof_image = $_FILES['proof_image'] ?? null;

    // --- Validation ---
    if (empty($invoice_id) || empty($amount) || empty($transaction_id) || empty($payment_method) || $proof_image['error'] !== UPLOAD_ERR_OK) {
        $error_msg = 'All fields and a valid proof image are required.';
    } elseif ($amount <= 0) {
        $error_msg = 'Payment amount must be greater than zero.';
    } else {
        // --- Secure File Upload Handling ---
        $upload_dir = __DIR__ . '/../uploads/proofs/';
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5 MB

        if (!in_array($proof_image['type'], $allowed_types)) {
            $error_msg = 'Invalid file type. Only JPG, PNG, and PDF are allowed.';
        } elseif ($proof_image['size'] > $max_size) {
            $error_msg = 'File is too large. Maximum size is 5 MB.';
        } else {
            // Generate a unique filename
            $file_extension = pathinfo($proof_image['name'], PATHINFO_EXTENSION);
            $unique_filename = 'proof_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
            $upload_path = $upload_dir . $unique_filename;

            if (move_uploaded_file($proof_image['tmp_name'], $upload_path)) {
                // --- Insert into database ---
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO payments (invoice_id, parent_id, amount, payment_method, transaction_id, proof_image, status) 
                         VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
                    );
                    $stmt->execute([$invoice_id, $parent_id, $amount, $payment_method, $transaction_id, $unique_filename]);
                    $success_msg = 'Payment submitted successfully! It is now pending verification.';
                } catch (PDOException $e) {
                    $error_msg = "Database error: " . $e->getMessage();
                    // Optional: Delete the uploaded file if DB insert fails
                    unlink($upload_path);
                }
            } else {
                $error_msg = 'Failed to upload proof image. Please try again.';
            }
        }
    }
}


// Fetch unpaid or partially paid invoices for this parent's children
$children_ids_stmt = $pdo->prepare("SELECT id FROM students WHERE parent_id = ?");
$children_ids_stmt->execute([$parent_id]);
$children_ids = $children_ids_stmt->fetchAll(PDO::FETCH_COLUMN);

$invoices = [];
if (!empty($children_ids)) {
    $placeholders = implode(',', array_fill(0, count($children_ids), '?'));
    $sql = "SELECT i.id, i.invoice_uid, i.total_amount, i.amount_paid, s.student_name 
            FROM invoices i 
            JOIN students s ON i.student_id = s.id
            WHERE i.student_id IN ($placeholders) AND i.status != 'Paid'
            ORDER BY i.due_date ASC";
    $invoices_stmt = $pdo->prepare($sql);
    $invoices_stmt->execute($children_ids);
    $invoices = $invoices_stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">Submit Payment Proof</h1></div></div></div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Payment Form</h3></div>
            <div class="card-body">
                <?php if ($success_msg): ?><div class="alert alert-success"><?= he($success_msg) ?></div><?php endif; ?>
                <?php if ($error_msg): ?><div class="alert alert-danger"><?= he($error_msg) ?></div><?php endif; ?>

                <form method="POST" action="submit_payment.php" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="invoice_id">Select Invoice</label>
                        <select id="invoice_id" name="invoice_id" class="form-control" required>
                            <option value="">-- Select an Unpaid Invoice --</option>
                            <?php foreach ($invoices as $invoice): ?>
                                <?php $due = $invoice['total_amount'] - $invoice['amount_paid']; ?>
                                <option value="<?= he($invoice['id']) ?>">
                                    <?= he($invoice['invoice_uid']) ?> (Student: <?= he($invoice['student_name']) ?>) - Due: <?= he(number_format($due, 2)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="amount">Amount Paid</label>
                            <input type="number" id="amount" name="amount" class="form-control" placeholder="e.g., 5000" step="0.01" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="payment_method">Payment Method</label>
                            <select id="payment_method" name="payment_method" class="form-control" required>
                                <option value="">-- Select Method --</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Easypaisa">Easypaisa</option>
                                <option value="JazzCash">JazzCash</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="transaction_id">Transaction ID / Reference No</label>
                        <input type="text" id="transaction_id" name="transaction_id" class="form-control" placeholder="e.g., A1B2C3D4E5" required>
                    </div>

                    <div class="form-group">
                        <label for="proof_image">Upload Proof (Screenshot/Receipt)</label>
                        <div class="input-group">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="proof_image" name="proof_image" required>
                                <label class="custom-file-label" for="proof_image">Choose file (JPG, PNG, PDF)...</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Submit for Verification</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
// Show the selected filename in the file input
$('.custom-file-input').on('change', function() {
    let fileName = $(this).val().split('\\').pop();
    $(this).next('.custom-file-label').addClass("selected").html(fileName);
});
</script>