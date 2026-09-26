<?php
// school-fees-system/accountant/invoices.php

$page_title = 'View All Invoices';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Accountant', 'Admin']); // Allow both roles to see this page

// Fetch invoices for display table
$invoices_sql = "SELECT i.id, i.invoice_uid, i.total_amount, i.amount_paid, i.due_date, i.status, s.student_name 
                 FROM invoices i
                 JOIN students s ON i.student_id = s.id
                 ORDER BY i.created_at DESC";
$invoices = $pdo->query($invoices_sql)->fetchAll();


require_once __DIR__ . '/../includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1 class="m-0">All Invoices</h1></div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Invoice Records</h3></div>
            <div class="card-body">
                <table id="invoicesTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Student Name</th>
                            <th>Total Amount</th>
                            <th>Amount Paid</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($invoices as $invoice): ?>
                        <tr>
                            <td><?= he($invoice['invoice_uid']) ?></td>
                            <td><?= he($invoice['student_name']) ?></td>
                            <td><?= he(number_format($invoice['total_amount'], 2)) ?></td>
                            <td><?= he(number_format($invoice['amount_paid'], 2)) ?></td>
                            <td><?= he(date('d M, Y', strtotime($invoice['due_date']))) ?></td>
                            <td>
                                <?php
                                $status_class = 'secondary';
                                if ($invoice['status'] == 'Paid') $status_class = 'success';
                                if ($invoice['status'] == 'Unpaid') $status_class = 'danger';
                                if ($invoice['status'] == 'Partially Paid') $status_class = 'warning';
                                ?>
                                <span class="badge badge-<?= $status_class ?>"><?= he($invoice['status']) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#invoicesTable').DataTable();
});
</script>