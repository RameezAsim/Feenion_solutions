<?php
// school-fees-system/parent/index.php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Protect the page, only 'Parent' users can access
check_session(['Parent']);

$parent_id = $_SESSION['user_id'];
$children_ids = [];
$total_children = 0;
$total_invoices = 0;
$total_paid = 0;
$total_due = 0;

try {
    // 1. Get all children linked to this parent
    $stmt = $pdo->prepare("SELECT id FROM students WHERE parent_id = ?");
    $stmt->execute([$parent_id]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $children_ids = $children;
    $total_children = count($children_ids);

    if ($total_children > 0) {
        // 2. Get financial stats for all children
        $placeholders = implode(',', array_fill(0, count($children_ids), '?'));
        
        $sql = "SELECT 
                    COUNT(*) as total_invoices, 
                    SUM(total_amount) as grand_total, 
                    SUM(amount_paid) as total_paid 
                FROM invoices 
                WHERE student_id IN ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($children_ids);
        $stats = $stmt->fetch();

        $total_invoices = $stats['total_invoices'] ?? 0;
        $total_paid = $stats['total_paid'] ?? 0;
        $total_due = ($stats['grand_total'] ?? 0) - $total_paid;
    }

} catch (PDOException $e) {
    // Handle potential database errors
    die("Error fetching dashboard data: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Parent Dashboard</h1>
            </div>
        </div>
    </div>
</div>
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-child"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Children</span>
                        <span class="info-box-number"><?= he($total_children) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box mb-3">
                    <span class="info-box-icon bg-info elevation-1"><i class="fas fa-file-invoice"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Invoices</span>
                        <span class="info-box-number"><?= he($total_invoices) ?></span>
                    </div>
                </div>
            </div>
            <div class="clearfix hidden-md-up"></div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box mb-3">
                    <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Paid</span>
                        <span class="info-box-number">PKR <?= he(number_format($total_paid, 2)) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box mb-3">
                    <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-hourglass-half"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Due</span>
                        <span class="info-box-number">PKR <?= he(number_format($total_due, 2)) ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Welcome</h3>
            </div>
            <div class="card-body">
                <p>Welcome to the parent portal. Here you can view your children's fee vouchers, track payments, and manage your account.</p>
                <a href="vouchers.php" class="btn btn-primary">View Fee Vouchers</a>
            </div>
        </div>

    </div></div>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>