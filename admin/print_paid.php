<?php
// school-fees-system/admin/print_paid.php (FIXED: Layout & Data Display)

// 1. Start Output Buffering
ob_start();

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

check_session(['Admin', 'Accountant']);
$active_branch_id = get_active_branch_id();

if (!$active_branch_id) {
    set_flash_message('You must select a branch.', 'error');
    header('Location: index.php');
    exit;
}

// --- PDF GENERATION LOGIC ---
if (isset($_POST['voucher_ids']) && !empty($_POST['voucher_ids'])) {
    $voucher_ids = $_POST['voucher_ids'];
    $placeholders = implode(',', array_fill(0, count($voucher_ids), '?'));
    
    // Fetch selected vouchers
    $sql = "
        SELECT v.voucher_code, v.created_at as paid_date,
               i.invoice_uid, i.total_amount, i.amount_paid,
               s.student_name, s.admission_no, c.class_name, sec.section_name,
               p.amount as current_payment_amount, p.payment_method
        FROM vouchers v
        JOIN payments p ON v.payment_id = p.id
        JOIN invoices i ON p.invoice_id = i.id
        JOIN students s ON i.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE v.id IN ($placeholders) AND i.branch_id = ?
        ORDER BY v.created_at DESC
    ";
    
    $params = array_merge($voucher_ids, [$active_branch_id]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vouchers = $stmt->fetchAll();

    if (empty($vouchers)) {
        die("No vouchers found.");
    }

    // --- SETTINGS WITH FALLBACK ---
    $school_name = get_setting('school_name');
    if (empty($school_name)) { $school_name = "School Name Not Set"; }

    $bank_details = get_setting('bank_details');
    if (empty($bank_details)) { 
        $bank_details = get_setting('easypaisa_details') . "\n" . get_setting('jazzcash_details');
    }
    
    $logo_file = get_setting('school_logo', 'logo.png');
    $logo_path = __DIR__ . '/../uploads/logos/' . $logo_file;

    // --- CSS LAYOUT ---
    $html = '
    <style>
        body {
            font-family: sans-serif;
            font-size: 9pt;
        }
        table.main-grid {
            width: 100%;
            border-collapse: collapse;
        }
        td.voucher-box {
            width: 50%;
            height: 144mm; 
            border: 1px dashed #999;
            padding: 5mm;
            vertical-align: top;
        }
        .v-header { text-align: center; border-bottom: 2px solid #28a745; margin-bottom: 5px; padding-bottom: 2px; }
        .v-logo { height: 35px; margin-bottom: 2px; }
        .v-title { font-size: 12pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .v-subtitle { font-size: 9pt; margin: 0; color: #28a745; font-weight: bold; }
        
        .v-info { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        .v-info td { padding: 2px; }
        
        .v-fees { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-top: 5px; }
        .v-fees th { background-color: #eee; border: 1px solid #000; padding: 3px; text-align: left; }
        .v-fees td { border: 1px solid #000; padding: 3px; }
        
        .v-footer { margin-top: 5px; font-size: 7pt; }
        .stamp-box { text-align: center; margin-top: 10px; }
        .stamp { border: 2px solid #28a745; color: #28a745; font-weight: bold; padding: 2px 10px; border-radius: 5px; display: inline-block; }
    </style>
    ';

    $html .= '<table class="main-grid">';
    $counter = 0;
    $total = count($vouchers);
    
    foreach ($vouchers as $v) {
        
        if ($counter % 2 == 0) { $html .= '<tr>'; }
        
        $html .= '<td class="voucher-box">';
        
        // --- INNER CONTENT ---
        $html .= '
            <div class="v-header">
                ' . (file_exists($logo_path) ? '<img src="' . $logo_path . '" class="v-logo">' : '') . '
                <h3 class="v-title">' . he($school_name) . '</h3>
                <p class="v-subtitle">OFFICIAL RECEIPT</p>
            </div>
            
            <table class="v-info">
                <tr><td><strong>Receipt #:</strong> ' . he($v['voucher_code']) . '</td><td align="right"><strong>Date:</strong> ' . date('d-M-Y', strtotime($v['paid_date'])) . '</td></tr>
                <tr><td><strong>Student:</strong> ' . he($v['student_name']) . '</td><td align="right"><strong>Adm No:</strong> ' . he($v['admission_no']) . '</td></tr>
                <tr><td><strong>Class:</strong> ' . he($v['class_name']) . '</td><td align="right"><strong>Ref Invoice:</strong> ' . he($v['invoice_uid']) . '</td></tr>
            </table>
            
            <table class="v-fees">
                <thead><tr><th>Description</th><th align="right">Amount</th></tr></thead>
                <tbody>
                    <tr>
                        <td height="60" valign="top">
                            Fee Payment Received<br>
                            <small>Method: ' . he($v['payment_method']) . '</small>
                        </td>
                        <td valign="top" align="right">' . number_format($v['current_payment_amount'], 2) . '</td>
                    </tr>
                    <tr style="background-color:#ddd; font-weight:bold;">
                        <td>TOTAL PAID</td>
                        <td align="right">PKR ' . number_format($v['current_payment_amount'], 2) . '</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="stamp-box">
                <span class="stamp">PAID</span>
            </div>

            <div class="v-footer">
                <div style="text-align: right; margin-top: 20px;">
                    _____________________<br>Authorized Signature
                </div>
                <div style="text-align: center; margin-top: 5px;">Computer generated receipt.</div>
            </div>
        ';
        // --- END INNER CONTENT ---
        
        $html .= '</td>';
        $counter++;

        if ($counter % 2 == 0) { $html .= '</tr>'; }

        if ($counter % 4 == 0 && $counter < $total) {
            $html .= '</table><div style="page-break-after: always;"></div><table class="main-grid">';
        }
    }

    if ($counter % 2 != 0) { $html .= '<td></td></tr>'; }
    
    $html .= '</table>';

    ob_end_clean(); 

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 
        'format' => 'A4', 
        'margin_left' => 0, 
        'margin_right' => 0, 
        'margin_top' => 0, 
        'margin_bottom' => 0
    ]);
    
    try {
        $mpdf->WriteHTML($html);
        $mpdf->Output('Paid_Vouchers.pdf', 'I');
    } catch (\Mpdf\MpdfException $e) {
        die("PDF Error: " . $e->getMessage());
    }
    exit;
}

// --- FETCH PAID VOUCHERS LIST ---
$stmt = $pdo->prepare("
    SELECT v.id, v.voucher_code, s.student_name, i.total_amount, v.created_at 
    FROM vouchers v 
    JOIN payments p ON v.payment_id=p.id 
    JOIN invoices i ON p.invoice_id=i.id 
    JOIN students s ON i.student_id=s.id 
    WHERE i.branch_id=? 
    ORDER BY v.created_at DESC
");
$stmt->execute([$active_branch_id]);
$vouchers = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="content-header"><div class="container-fluid"><h1 class="m-0">Paid Vouchers</h1></div></div>
<div class="content"><div class="container-fluid"><div class="card">
    <div class="card-header"><h3 class="card-title">Generated Receipts</h3></div>
    <div class="card-body">
        <form method="POST" target="_blank">
            <button type="submit" class="btn btn-primary mb-3"><i class="fas fa-print"></i> Print Selected</button>
            
            <table id="vouchersTable" class="table table-bordered table-striped">
                <thead><tr><th width="10"><input type="checkbox" id="selectAll"></th><th>Code</th><th>Student</th><th>Invoice Total</th><th>Date Paid</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach($vouchers as $v): ?>
                    <tr>
                        <td><input type="checkbox" name="voucher_ids[]" value="<?= $v['id'] ?>" class="voucher-checkbox"></td>
                        <td><?= he($v['voucher_code']) ?></td>
                        <td><?= he($v['student_name']) ?></td>
                        <td><?= number_format($v['total_amount']) ?></td>
                        <td><?= date('d-M-Y', strtotime($v['created_at'])) ?></td>
                        <td><a href="#" class="btn btn-sm btn-secondary disabled"><i class="fas fa-check"></i> Paid</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>
</div></div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
$('#selectAll').click(function(){ $('input[name="voucher_ids[]"]').prop('checked', this.checked); });
$(document).ready(function() { 
    $('#vouchersTable').DataTable({
        "destroy": true,
        "order": [[ 4, "desc" ]],
        "pageLength": 100
    }); 
});
</script>