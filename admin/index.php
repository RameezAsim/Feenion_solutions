<?php
// school-fees-system/admin/index.php (Multi-Branch + Defaulter Filter + Detailed View Modal)

$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin']);

$active_branch_id = get_active_branch_id();

// --- DASHBOARD STATS ---
$total_students = 0;
$total_outstanding = 0;
$total_advance_credit = 0;
$collection_today = 0;
$collection_month = 0;
$expense_month = 0;
$net_profit_month = 0;
$collection_year = 0;

// --- DEFAULTER FILTER VARIABLES ---
$filter_days = isset($_GET['overdue_days']) ? (int)$_GET['overdue_days'] : 0;
$defaulters = [];

if ($active_branch_id) {
    // 1. Existing Stats Queries
    $stmt_students = $pdo->prepare("SELECT COUNT(*) FROM students WHERE status = 'Active' AND branch_id = ?");
    $stmt_students->execute([$active_branch_id]);
    $total_students = $stmt_students->fetchColumn();

    $stmt_outstanding = $pdo->prepare("SELECT SUM(total_amount - amount_paid) FROM invoices WHERE status != 'Paid' AND branch_id = ?");
    $stmt_outstanding->execute([$active_branch_id]);
    $total_outstanding = $stmt_outstanding->fetchColumn() ?? 0;

    $stmt_advance = $pdo->prepare("SELECT SUM(advance_balance) FROM students WHERE status = 'Active' AND branch_id = ?");
    $stmt_advance->execute([$active_branch_id]);
    $total_advance_credit = $stmt_advance->fetchColumn() ?? 0;

    $stmt_today = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'Verified' AND amount > 0 AND DATE(payment_date) = CURDATE() AND branch_id = ?");
    $stmt_today->execute([$active_branch_id]);
    $collection_today = $stmt_today->fetchColumn() ?? 0;

    $stmt_month = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'Verified' AND amount > 0 AND YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE()) AND branch_id = ?");
    $stmt_month->execute([$active_branch_id]);
    $collection_month = $stmt_month->fetchColumn() ?? 0;

    $stmt_exp_month = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE YEAR(expense_date) = YEAR(CURDATE()) AND MONTH(expense_date) = MONTH(CURDATE()) AND branch_id = ?");
    $stmt_exp_month->execute([$active_branch_id]);
    $expense_month = $stmt_exp_month->fetchColumn() ?? 0;
    
    $net_profit_month = $collection_month - $expense_month;

    $stmt_year = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'Verified' AND amount > 0 AND YEAR(payment_date) = YEAR(CURDATE()) AND branch_id = ?");
    $stmt_year->execute([$active_branch_id]);
    $collection_year = $stmt_year->fetchColumn() ?? 0;

    // 2. DEFAULTER QUERY
    $sql_defaulters = "
        SELECT i.id, i.invoice_uid, i.total_amount, i.amount_paid, i.due_date,
               s.student_name, s.admission_no, c.class_name,
               u.phone as parent_phone,
               DATEDIFF(NOW(), i.due_date) as days_late,
               (i.total_amount - i.amount_paid) as balance_due
        FROM invoices i
        JOIN students s ON i.student_id = s.id
        JOIN classes c ON s.class_id = c.id
        LEFT JOIN users u ON s.parent_id = u.id 
        WHERE i.branch_id = ? 
        AND i.status != 'Paid' 
        AND i.due_date < CURDATE() 
        AND DATEDIFF(NOW(), i.due_date) >= ?
        ORDER BY days_late DESC
        LIMIT 100
    ";
    $stmt_def = $pdo->prepare($sql_defaulters);
    $stmt_def->execute([$active_branch_id, $filter_days]);
    $defaulters = $stmt_def->fetchAll();
}

// --- CHART DATA ---
$chart_data = [];
$chart_json = json_encode($chart_data);
if ($active_branch_id) {
    $start_month = date('Y-m-01', strtotime('-11 months'));
    $stmt_chart = $pdo->prepare("
        SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total
        FROM payments
        WHERE status = 'Verified' AND amount > 0 AND payment_date >= ? AND branch_id = ?
        GROUP BY month ORDER BY month ASC
    ");
    $stmt_chart->execute([$start_month, $active_branch_id]);
    $db_data = $stmt_chart->fetchAll(PDO::FETCH_KEY_PAIR);
    for ($i = 11; $i >= 0; $i--) {
        $month_key = date('Y-m', strtotime("-$i months"));
        $month_label = date('M Y', strtotime("-$i months"));
        $chart_data['labels'][] = $month_label;
        $chart_data['data'][] = $db_data[$month_key] ?? 0;
    }
    $chart_json = json_encode($chart_data);
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1 class="m-0">Admin Dashboard</h1></div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <?php display_flash_messages(); ?>
        <?php if (!$active_branch_id && $_SESSION['manages_all_branches']): ?>
            <div class="alert alert-info">
                <h5><i class="icon fas fa-info"></i> Welcome, Super Admin!</h5>
                Please use the **Branch Selector** in the top-right corner to select a branch and view its data.
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner"><h3><?= he($total_students) ?></h3><p>Total Active Students</p></div>
                    <div class="icon"><i class="fas fa-user-graduate"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner"><h3><?= he(number_format($total_outstanding, 2)) ?></h3><p>Total Outstanding Dues</p></div>
                    <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-teal">
                    <div class="inner"><h3><?= he(number_format($total_advance_credit, 2)) ?></h3><p>Total Advance Credit</p></div>
                    <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner"><h3><?= he(number_format($collection_today, 2)) ?></h3><p>Collection Today</p></div>
                    <div class="icon"><i class="fas fa-calendar-day"></i></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header border-0"><h3 class="card-title">Monthly Collection (Last 12 Months)</h3></div>
                    <div class="card-body"><canvas id="collectionChart" style="height:300px;"></canvas></div>
                </div>
                
                <div class="card card-danger card-outline mt-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-search-dollar mr-1"></i> Fee Defaulters Filter</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="form-inline mb-3">
                            <label class="mr-2">Show students overdue by more than:</label>
                            <div class="input-group mr-2">
                                <input type="number" name="overdue_days" class="form-control" value="<?= $filter_days ?>" min="0" style="width: 80px;">
                                <div class="input-group-append"><span class="input-group-text">Days</span></div>
                            </div>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-filter"></i> Filter</button>
                            <?php if($filter_days > 0): ?><a href="index.php" class="btn btn-secondary ml-2">Clear</a><?php endif; ?>
                        </form>

                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-bordered table-striped table-hover table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Student</th><th>Class</th><th>Invoice #</th><th>Due Date</th>
                                        <th class="text-center text-danger">Days Late</th>
                                        <th class="text-right">Balance</th><th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($defaulters)): ?>
                                        <tr><td colspan="7" class="text-center">No students found matching this criteria.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($defaulters as $row): 
                                            // Prepare WhatsApp Link
                                            $phone = preg_replace('/[^0-9]/', '', $row['parent_phone']);
                                            if (substr($phone, 0, 2) === '03') { $phone = '92' . substr($phone, 1); }
                                            
                                            $school_name = he(get_setting('school_name', 'School Administration'));
                                            $msg_text = "Dear Parent,\n\nThis is a friendly reminder from *$school_name*.\n\nThe fee for your child *{$row['student_name']}* is overdue by *{$row['days_late']} days*.\n\nInvoice: *{$row['invoice_uid']}*\nBalance Due: *PKR " . number_format($row['balance_due']) . "*\n\nPlease pay as soon as possible to avoid inconvenience.\nThank you.";
                                            
                                            $wa_url = "https://web.whatsapp.com/send?phone={$phone}&text=" . urlencode($msg_text);
                                        ?>
                                        <tr>
                                            <td><?= he($row['student_name']) ?> <small class="text-muted">(<?= he($row['admission_no']) ?>)</small></td>
                                            <td><?= he($row['class_name']) ?></td>
                                            <td><?= he($row['invoice_uid']) ?></td>
                                            <td><?= date('d M, Y', strtotime($row['due_date'])) ?></td>
                                            <td class="text-center"><span class="badge badge-danger"><?= $row['days_late'] ?> Days</span></td>
                                            <td class="text-right font-weight-bold">PKR <?= number_format($row['balance_due']) ?></td>
                                            <td>
                                                <button class="btn btn-xs btn-primary view-btn" data-id="<?= $row['id'] ?>"><i class="fas fa-eye"></i> View</button>
                                                <a href="<?= $wa_url ?>" target="_blank" class="btn btn-xs btn-success" title="Send WhatsApp Reminder">
                                                    <i class="fab fa-whatsapp"></i> Remind
                                                </a>
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
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Financial Summary</h3></div>
                    <div class="card-body">
                        <div class="info-box mb-3 bg-success">
                            <span class="info-box-icon"><i class="fas fa-arrow-down"></i></span>
                            <div class="info-box-content"><span class="info-box-text">Collection This Month</span><span class="info-box-number">PKR <?= he(number_format($collection_month, 2)) ?></span></div>
                        </div>
                        <div class="info-box mb-3 bg-danger">
                            <span class="info-box-icon"><i class="fas fa-arrow-up"></i></span>
                            <div class="info-box-content"><span class="info-box-text">Expenses This Month</span><span class="info-box-number">PKR <?= he(number_format($expense_month, 2)) ?></span></div>
                        </div>
                        <div class="info-box mb-3 bg-<?= ($net_profit_month >= 0) ? 'primary' : 'warning' ?>">
                            <span class="info-box-icon"><i class="fas fa-balance-scale"></i></span>
                            <div class="info-box-content"><span class="info-box-text">Net Profit (Month)</span><span class="info-box-number">PKR <?= he(number_format($net_profit_month, 2)) ?></span></div>
                        </div>
                        <div class="info-box mb-3 bg-info">
                            <span class="info-box-icon"><i class="fas fa-wallet"></i></span>
                            <div class="info-box-content"><span class="info-box-text">Total Collection (Year)</span><span class="info-box-number">PKR <?= he(number_format($collection_year, 2)) ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewInvoiceModal"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h4 class="modal-title">Invoice Details</h4><button type="button" class="close" data-dismiss="modal">&times;</button></div><div class="modal-body" id="invoiceDetailContent"><div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="button" class="btn btn-info" id="printReceiptBtn">Print Receipt</button></div></div></div></div>
<div id="receipt-print-area" style="display:none"></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(function () {
    // Chart Logic
    var chartData = <?= $chart_json ?>;
    if (chartData.labels && chartData.labels.length > 0) {
        var ctx = document.getElementById('collectionChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Monthly Collection',
                    data: chartData.data,
                    backgroundColor: 'rgba(0, 123, 255, 0.7)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    // View Invoice Modal Logic
    var currentInvoiceData;
    $('.view-btn').click(function(){
        var id = $(this).data('id');
        $('#invoiceDetailContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
        $('#viewInvoiceModal').modal('show');
        
        $.get('invoices.php?action=get_invoice_details&id='+id, function(d){
            if(d.success) {
                currentInvoiceData = d.data;
                var total = parseFloat(d.data.total_amount);
                var paid = parseFloat(d.data.amount_paid);
                var balance = total - paid;
                
                // --- FIXED MODAL CONTENT FOR PARTIAL PAYMENTS ---
                var h = `<h5>Invoice #${d.data.invoice_uid}</h5>
                         <p>Student: ${d.data.student_name} (${d.data.admission_no})</p>
                         <p>Class: ${d.data.class_name}</p>
                         <table class="table table-sm table-striped">
                            <thead class="thead-light"><tr><th>Fee Head</th><th class="text-right">Amount</th></tr></thead>
                            <tbody>`;
                
                d.data.items.forEach(i => h += `<tr><td>${i.fee_head}</td><td class="text-right">${parseFloat(i.amount).toLocaleString()}</td></tr>`);
                
                h += `  </tbody>
                         </table>
                         <div class="border-top pt-2">
                            <div class="d-flex justify-content-between"><span>Total Amount:</span> <strong>PKR ${total.toLocaleString()}</strong></div>
                            <div class="d-flex justify-content-between text-success"><span>Paid Amount:</span> <strong>(-) PKR ${paid.toLocaleString()}</strong></div>
                            <div class="d-flex justify-content-between text-danger mt-2" style="font-size:1.2em;"><span>Balance Due:</span> <strong>PKR ${balance.toLocaleString()}</strong></div>
                         </div>`;
                
                $('#invoiceDetailContent').html(h);
            }
        }, 'json');
    });

    // Print Logic
    $('#printReceiptBtn').click(function(){
        if(!currentInvoiceData) return;
        var balance = (parseFloat(currentInvoiceData.total_amount) - parseFloat(currentInvoiceData.amount_paid)).toFixed(2);
        var h = `<div class="thermal-receipt"><h3>Feenion School</h3><p>Invoice: ${currentInvoiceData.invoice_uid}</p><p>Date: ${currentInvoiceData.created_at_formatted}</p><hr><table>`;
        currentInvoiceData.items.forEach(i => h += `<tr><td>${i.fee_head}</td><td align="right">${i.amount}</td></tr>`);
        h += `</table><hr>
              <table style="width:100%">
                <tr><td>Total:</td><td align="right">${currentInvoiceData.total_amount}</td></tr>
                <tr><td>Paid:</td><td align="right">${currentInvoiceData.amount_paid}</td></tr>
                <tr><td><strong>Balance:</strong></td><td align="right"><strong>${balance}</strong></td></tr>
              </table></div>`;
        $('#receipt-print-area').html(h);
        $('body').addClass('receipt-print-mode');
        window.print();
        setTimeout(() => { $('body').removeClass('receipt-print-mode'); }, 500);
    });
});
</script>