<?php
// school-fees-system/accountant/index.php

$page_title = 'Accountant Dashboard';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Accountant']); // Only Accountants
$active_branch_id = get_active_branch_id(); // Get their assigned branch

if (!$active_branch_id) {
    set_flash_message('You are not assigned to a branch. Please contact the administrator.', 'error');
}

// --- DASHBOARD STATS (Branch-Specific) ---

// Total Pending Verifications
$stmt_pending = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE status = 'Pending' AND branch_id = ?");
$stmt_pending->execute([$active_branch_id]);
$pending_verifications = $stmt_pending->fetchColumn();

// Total Advance Credit
$stmt_advance = $pdo->prepare("SELECT SUM(advance_balance) FROM students WHERE status = 'Active' AND branch_id = ?");
$stmt_advance->execute([$active_branch_id]);
$total_advance_credit = $stmt_advance->fetchColumn() ?? 0;

// Collection Today
$stmt_today = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'Verified' AND DATE(payment_date) = CURDATE() AND branch_id = ?");
$stmt_today->execute([$active_branch_id]);
$collection_today = $stmt_today->fetchColumn() ?? 0;

// Collection This Month
$stmt_month = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE status = 'Verified' AND YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE()) AND branch_id = ?");
$stmt_month->execute([$active_branch_id]);
$collection_month = $stmt_month->fetchColumn() ?? 0;

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Accountant Dashboard</h1>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <?php display_flash_messages(); ?>
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?= he($pending_verifications) ?></h3>
                        <p>Pending Verifications</p>
                    </div>
                    <div class="icon"><i class="fas fa-hourglass-start"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-teal">
                    <div class="inner">
                        <h3><?= he(number_format($total_advance_credit, 2)) ?></h3>
                        <p>Total Advance Credit</p>
                    </div>
                    <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?= he(number_format($collection_today, 2)) ?></h3>
                        <p>Collection Today</p>
                    </div>
                    <div class="icon"><i class="fas fa-calendar-day"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?= he(number_format($collection_month, 2)) ?></h3>
                        <p>Collection This Month</p>
                    </div>
                    <div class="icon"><i class="fas fa-wallet"></i></div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><h3 class="card-title">Quick Actions</h3></div>
            <div class="card-body">
                <a href="<?= SITE_URL ?>/admin/invoices.php" class="btn btn-app bg-primary">
                    <i class="fas fa-file-invoice-dollar"></i> Manage Invoices
                </a>
                <a href="<?= SITE_URL ?>/admin/manual_collection.php" class="btn btn-app bg-info">
                    <i class="fas fa-hand-holding-usd"></i> Manual Collection
                </a>
                <a href="<?= SITE_URL ?>/admin/payments.php" class="btn btn-app bg-warning">
                    <i class="fas fa-check-circle"></i> Verify Payments
                </a>
                <a href="<?= SITE_URL ?>/admin/expenses.php" class="btn btn-app bg-danger">
                    <i class="fas fa-shopping-cart"></i> Log Expense
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>