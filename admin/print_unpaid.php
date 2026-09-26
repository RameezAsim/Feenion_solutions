<?php
// school-fees-system/admin/print_unpaid.php (FIXED: Dropdown Filter Logic)

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
if (isset($_POST['action']) && $_POST['action'] === 'generate_unpaid' && !empty($_POST['invoice_ids'])) {
    $invoice_ids = $_POST['invoice_ids'];
    $placeholders = implode(',', array_fill(0, count($invoice_ids), '?'));
    
    // Fetch selected invoices
    $sql = "
        SELECT i.invoice_uid, i.total_amount, i.amount_paid, i.due_date, i.status,
               s.student_name, s.admission_no, c.class_name, sec.section_name,
               (SELECT GROUP_CONCAT(CONCAT(fee_head, ': ', amount) SEPARATOR '<br>') FROM invoice_items WHERE invoice_id = i.id) as fee_details
        FROM invoices i
        JOIN students s ON i.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE i.id IN ($placeholders)
        AND i.branch_id = ?
        ORDER BY c.class_name, s.student_name
    ";
    
    $params = array_merge($invoice_ids, [$active_branch_id]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();

    if (empty($invoices)) {
        die("No invoices found.");
    }

    $school_name = get_setting('school_name');
    if (empty($school_name)) { $school_name = "School Name Not Set"; }

    $bank_details = get_setting('bank_details');
    if (empty($bank_details)) { 
        $bank_details = get_setting('easypaisa_details') . "\n" . get_setting('jazzcash_details');
    }
    
    $logo_file = get_setting('school_logo', 'logo.png');
    $logo_path = __DIR__ . '/../uploads/logos/' . $logo_file;

    // --- CSS ---
    $html = '
    <style>
        body { font-family: sans-serif; font-size: 9pt; }
        table.main-grid { width: 100%; border-collapse: collapse; }
        td.voucher-box {
            width: 50%;
            height: 144mm; 
            border: 1px dashed #999;
            padding: 5mm;
            vertical-align: top;
        }
        .v-header { text-align: center; border-bottom: 2px solid #dc3545; margin-bottom: 5px; padding-bottom: 2px; }
        .v-logo { height: 35px; margin-bottom: 2px; }
        .v-title { font-size: 12pt; font-weight: bold; margin: 0; text-transform: uppercase; }
        .v-subtitle { font-size: 9pt; margin: 0; color: #dc3545; font-weight: bold; }
        .v-info { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        .v-info td { padding: 2px; }
        .v-fees { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-top: 5px; }
        .v-fees th { background-color: #eee; border: 1px solid #000; padding: 3px; text-align: left; }
        .v-fees td { border: 1px solid #000; padding: 3px; }
        .v-footer { margin-top: 5px; font-size: 7pt; }
        .bank-box { border: 1px solid #ccc; padding: 3px; margin-bottom: 5px; height: 40px; overflow: hidden; }
    </style>
    ';

    $html .= '<table class="main-grid">';
    $counter = 0;
    $total = count($invoices);
    
    foreach ($invoices as $inv) {
        $due = $inv['total_amount'] - $inv['amount_paid'];
        if ($counter % 2 == 0) { $html .= '<tr>'; }
        
        $html .= '<td class="voucher-box">';
        $html .= '
            <div class="v-header">
                ' . (file_exists($logo_path) ? '<img src="' . $logo_path . '" class="v-logo">' : '') . '
                <h3 class="v-title">' . he($school_name) . '</h3>
                <p class="v-subtitle">FEE BILL (' . strtoupper($inv['status']) . ')</p>
            </div>
            <table class="v-info">
                <tr><td><strong>Student:</strong> ' . he($inv['student_name']) . '</td><td align="right"><strong>Adm No:</strong> ' . he($inv['admission_no']) . '</td></tr>
                <tr><td><strong>Class:</strong> ' . he($inv['class_name']) . '</td><td align="right"><strong>Invoice:</strong> ' . he($inv['invoice_uid']) . '</td></tr>
                <tr><td colspan="2"><strong>Due Date:</strong> <span style="color:red; font-weight:bold;">' . date('d-M-Y', strtotime($inv['due_date'])) . '</span></td></tr>
            </table>
            <table class="v-fees">
                <thead><tr><th>Description</th><th align="right">Amount</th></tr></thead>
                <tbody>
                    <tr>
                        <td height="60" valign="top">' . $inv['fee_details'] . '</td>
                        <td valign="top" align="right">' . number_format($inv['total_amount'], 2) . '</td>
                    </tr>
                    ' . ($inv['amount_paid'] > 0 ? '<tr><td>Less Paid</td><td align="right">-' . number_format($inv['amount_paid'], 2) . '</td></tr>' : '') . '
                    <tr style="background-color:#ddd; font-weight:bold;">
                        <td>TOTAL PAYABLE</td>
                        <td align="right">' . number_format($due, 2) . '</td>
                    </tr>
                </tbody>
            </table>
            <div class="v-footer">
                <div class="bank-box">
                    <strong>Bank/Payment Details:</strong><br>' . nl2br(he($bank_details)) . '
                </div>
                <div style="text-align: right; margin-top: 5px;">
                    _____________________<br>Accountant Signature
                </div>
                <div style="text-align: center; margin-top: 5px;">Generated: ' . date('d-M-Y') . '</div>
            </div>
        ';
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
    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0]);
    try {
        $mpdf->WriteHTML($html);
        $mpdf->Output('Fee_Bills.pdf', 'I');
    } catch (\Mpdf\MpdfException $e) {
        die("PDF Error: " . $e->getMessage());
    }
    exit;
}

// --- FETCH UNPAID INVOICES LIST ---
$stmt = $pdo->prepare("
    SELECT i.id, i.invoice_uid, i.total_amount, i.amount_paid, i.due_date, 
           s.student_name, c.class_name 
    FROM invoices i
    JOIN students s ON i.student_id = s.id 
    JOIN classes c ON s.class_id = c.id 
    WHERE i.status != 'Paid' AND i.branch_id = ? 
    ORDER BY i.due_date DESC
");
$stmt->execute([$active_branch_id]);
$unpaid_invoices = $stmt->fetchAll();

// --- FETCH LIST OF CLASSES FOR FILTER (FIXED QUERY) ---
// We join through the 'students' table because 'invoices.class_id' might be NULL
$stmt_classes = $pdo->prepare("
    SELECT DISTINCT c.class_name 
    FROM invoices i
    JOIN students s ON i.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    WHERE i.status != 'Paid' AND i.branch_id = ? 
    ORDER BY c.class_name ASC
");
$stmt_classes->execute([$active_branch_id]);
$classes_list = $stmt_classes->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="content-header"><div class="container-fluid"><h1 class="m-0">Print Unpaid Vouchers</h1></div></div>
<div class="content"><div class="container-fluid"><div class="card">
    <div class="card-header"><h3 class="card-title">Select Invoices to Print</h3></div>
    <div class="card-body">
        <form method="POST" target="_blank">
            <input type="hidden" name="action" value="generate_unpaid">
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label>Filter by Class:</label>
                    <select id="classFilter" class="form-control">
                        <option value="">Show All Classes</option>
                        <?php foreach($classes_list as $cls): ?>
                            <option value="<?= he($cls) ?>"><?= he($cls) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8 text-right align-self-end">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-print"></i> Print Selected (4 per page)</button>
                </div>
            </div>

            <table id="unpaidTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th width="10"><input type="checkbox" id="selectAll"></th>
                        <th>Invoice ID</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Due Amount</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($unpaid_invoices as $inv): ?>
                    <tr>
                        <td><input type="checkbox" name="invoice_ids[]" value="<?= $inv['id'] ?>" class="voucher-checkbox"></td>
                        <td><?= he($inv['invoice_uid']) ?></td>
                        <td><?= he($inv['student_name']) ?></td>
                        <td><?= he($inv['class_name']) ?></td> <td>PKR <?= he(number_format($inv['total_amount'] - $inv['amount_paid'], 2)) ?></td>
                        <td style="color:red; font-weight:bold;"><?= date('d-M-Y', strtotime($inv['due_date'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>
</div></div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
$(document).ready(function() {
    var table = $('#unpaidTable').DataTable({
        "destroy": true,
        "order": [[ 5, "asc" ]], 
        "pageLength": 100
    });

    // Select All Logic
    $('#selectAll').on('click', function(){
        var rows = table.rows({ 'search': 'applied' }).nodes();
        $('input[type="checkbox"]', rows).prop('checked', this.checked);
    });

    // Class Filter Logic
    $('#classFilter').on('change', function() {
        var selectedClass = $(this).val();
        // Use DataTables built-in search on Column 3 (Class Name)
        table.column(3).search(selectedClass).draw();
    });
});
</script>