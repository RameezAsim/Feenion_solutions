<?php
// school-fees-system/parent/vouchers.php (CORRECTED)

$page_title = 'Fee Vouchers';
// These two lines MUST come before the header is included.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../lib/php-qrcode/qrlib.php';

check_session(['Parent']);

$parent_id = $_SESSION['user_id'];
$all_invoices = [];
$qr_code_dir = __DIR__ . '/../uploads/qrcodes/';

try {
    $stmt_children = $pdo->prepare("SELECT id FROM students WHERE parent_id = ?");
    $stmt_children->execute([$parent_id]);
    $children_ids = $stmt_children->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($children_ids)) {
        $placeholders = implode(',', array_fill(0, count($children_ids), '?'));
        
        $sql = "SELECT i.*, s.student_name, s.admission_no, c.class_name
                FROM invoices i
                JOIN students s ON i.student_id = s.id
                JOIN classes c ON s.class_id = c.id
                WHERE i.student_id IN ($placeholders)
                ORDER BY i.due_date DESC";
        
        $stmt_invoices = $pdo->prepare($sql);
        $stmt_invoices->execute($children_ids);
        $invoices_data = $stmt_invoices->fetchAll();

        $stmt_items = $pdo->prepare("SELECT fee_head, amount FROM invoice_items WHERE invoice_id = ?");
        foreach ($invoices_data as $invoice) {
            $stmt_items->execute([$invoice['id']]);
            $invoice['items'] = $stmt_items->fetchAll();
            $all_invoices[] = $invoice;
        }
    }
} catch (PDOException $e) {
    die("Error fetching invoices: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Fee Vouchers</h1>
            </div>
            <div class="col-sm-6">
                <button onclick="window.print()" class="btn btn-primary float-sm-right no-print">
                    <i class="fas fa-print"></i> Print Vouchers
                </button>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="voucher-container">
            <?php if (empty($all_invoices)): ?>
                <div class="alert alert-info">No fee vouchers found for your children.</div>
            <?php else: ?>
                <?php foreach ($all_invoices as $invoice): ?>
                    <?php
                    $qr_data = "Invoice: {$invoice['invoice_uid']}\nStudent: {$invoice['student_name']}\nAmount: {$invoice['total_amount']}";
                    $qr_filename = "invoice_{$invoice['id']}.png";
                    $qr_filepath = $qr_code_dir . $qr_filename;
                    if (!file_exists($qr_filepath)) {
                        QRcode::png($qr_data, $qr_filepath, QR_ECLEVEL_L, 3);
                    }
                    ?>
                    <div class="fee-voucher position-relative">
                        <div class="watermark"><?= he(get_setting('school_name', 'CONFIDENTIAL')) ?></div>
                        <div class="header">
                            <div class="logo">
                                <h5><?= he(get_setting('school_name', 'School Name')) ?></h5>
                                <p>Fee Voucher</p>
                            </div>
                            <div class="invoice-details">
                                <p><strong>Voucher #:</strong> <?= he($invoice['invoice_uid']) ?></p>
                                <p><strong>Due Date:</strong> <?= he(date('d M, Y', strtotime($invoice['due_date']))) ?></p>
                            </div>
                        </div>
                        <div class="student-info">
                            <p><strong>Student:</strong> <?= he($invoice['student_name']) ?></p>
                            <p><strong>Admission No:</strong> <?= he($invoice['admission_no']) ?></p>
                            <p><strong>Class:</strong> <?= he($invoice['class_name']) ?></p>
                        </div>
                        <table class="fee-items">
                            <thead><tr><th>Description</th><th class="text-right">Amount (PKR)</th></tr></thead>
                            <tbody>
                                <?php foreach ($invoice['items'] as $item): ?>
                                    <tr><td><?= he($item['fee_head']) ?></td><td class="text-right"><?= he(number_format($item['amount'], 2)) ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="total-breakdown">
                                <tr>
                                    <td class="text-right"><strong>Current Month Total:</strong></td>
                                    <td class="text-right"><?= he(number_format($invoice['total_amount'], 2)) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                        <div class="total-section">
                            <p><strong>Total Amount Payable:</strong> <span>PKR <?= he(number_format($invoice['total_amount'], 2)) ?></span></p>
                        </div>
                         <div class="footer">
                             <div class="payment-details">
                                 <strong>How to Pay:</strong>
                                 <p><?= nl2br(he(get_setting('bank_details'))) ?></p>
                             </div>
                             <div class="qr-code">
                                <img src="<?= SITE_URL . '/uploads/qrcodes/' . $qr_filename ?>" alt="QR Code">
                             </div>
                             <div class="status-stamp <?= strtolower(str_replace(' ', '-', $invoice['status'])) ?>">
                                 <?= he($invoice['status']) ?>
                             </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>