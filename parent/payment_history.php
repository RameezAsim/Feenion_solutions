<?php
// school-fees-system/parent/payment_history.php

$page_title = 'Payment History';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Parent']);

$parent_id = $_SESSION['user_id'];
$payments = [];

try {
    $stmt = $pdo->prepare("
        SELECT p.*, i.invoice_uid 
        FROM payments p 
        JOIN invoices i ON p.invoice_id = i.id
        WHERE p.parent_id = ? 
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$parent_id]);
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error
    die("Error fetching payment history: " . $e->getMessage());
}


require_once __DIR__ . '/../includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">My Payment History</h1></div></div></div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">All Submitted Payments</h3></div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Date</th><th>Invoice #</th><th>Amount</th><th>Method</th><th>Transaction ID</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="6" class="text-center">You have not submitted any payments yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= he(date('d M, Y', strtotime($payment['payment_date']))) ?></td>
                                <td><?= he($payment['invoice_uid']) ?></td>
                                <td><?= he(number_format($payment['amount'], 2)) ?></td>
                                <td><?= he($payment['payment_method']) ?></td>
                                <td><?= he($payment['transaction_id']) ?></td>
                                <td>
                                    <?php 
                                    $status = $payment['status'];
                                    $badge_class = 'secondary';
                                    if ($status == 'Verified') $badge_class = 'success';
                                    if ($status == 'Pending') $badge_class = 'warning';
                                    if ($status == 'Rejected') $badge_class = 'danger';
                                    ?>
                                    <span class="badge badge-<?= $badge_class ?>"><?= he($status) ?></span>
                                    <?php if ($status == 'Rejected' && !empty($payment['remarks'])): ?>
                                        <i class="fas fa-info-circle text-muted" title="<?= he($payment['remarks']) ?>"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
// Enable tooltips for rejection reasons
$(function () {
  $('[title]').tooltip()
})
</script>