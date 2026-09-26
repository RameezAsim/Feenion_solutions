<?php
// school-fees-system/includes/header.php (FINAL & COMPLETE - CLEANED & UPDATED)

require_once __DIR__ . '/functions.php';

$current_page = basename($_SERVER['PHP_SELF']);
$current_user_role = $_SESSION['role'] ?? '';
$current_user_name = $_SESSION['full_name'] ?? 'Guest';
if (!isset($page_title)) {
    $page_title = 'Dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= he(SITE_NAME) ?> | <?= he($page_title) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,100..900;1,100..900&family=Source+Serif+4:ital,opsz,wght@0,8..60,200..900;1,8..60,200&display=swap" rel="stylesheet">
    
    <style>
        .main-sidebar{
            background-color:#0B2E33;
        }
        .sidebar-dark-primary .nav-sidebar>.nav-item>.nav-link.active, .sidebar-light-primary .nav-sidebar>.nav-item>.nav-link.active{
            background:#c0cfd0;
            color:#0B2E33;
        }
        .main-header {
            background-color:#4F7C82;
        }
        .navbar-light .navbar-nav .nav-link{
            color:#fff;
        }
        .wrapper .content-wrapper{
            background-color:#B8E3E9;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <img src="../assets/img/feenion_logo.png" alt="Feenion Solution" width="115px">
        </ul>
        <ul class="navbar-nav ml-auto">
            
            <?php if (isset($_SESSION['manages_all_branches']) && $_SESSION['manages_all_branches'] && !empty($_SESSION['accessible_branches'])): ?>
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#" title="Switch Branch">
                    <i class="fas fa-store-alt"></i>
                    <span class="d-none d-md-inline ml-1">
                        <?php
                        // Find the name of the active branch
                        $active_branch_name = 'Select Branch';
                        foreach ($_SESSION['accessible_branches'] as $branch) {
                            if ($branch['id'] == ($_SESSION['active_branch_id'] ?? null)) {
                                $active_branch_name = $branch['name'];
                                break;
                            }
                        }
                        echo he($active_branch_name);
                        ?>
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header">Switch Active Branch</span>
                    <?php foreach ($_SESSION['accessible_branches'] as $branch): ?>
                    <div class="dropdown-divider"></div>
                    <a href="<?= SITE_URL ?>/switch_branch.php?branch_id=<?= he($branch['id']) ?>" class="dropdown-item">
                        <i class="fas fa-map-marker-alt mr-2"></i> <?= he($branch['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link" id="dark-mode-toggle" href="#" role="button">
                    <i class="fas fa-moon"></i>
                </a>
            </li>
            
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-user-circle"></i> <?= he($current_user_name) ?>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <a href="<?= SITE_URL ?>/change_password.php" class="dropdown-item">
                        <i class="fas fa-key mr-2"></i> Change Password
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?= SITE_URL ?>/logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="#" class="brand-link">
            <span class="brand-text font-weight-light"><?= he(get_setting('school_name', SITE_NAME)) ?></span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    
                    <?php if ($current_user_role === 'Admin'): ?>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/index.php" class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/students.php" class="nav-link <?= ($current_page == 'students.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-user-graduate"></i><p>Students</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/invoices.php" class="nav-link <?= ($current_page == 'invoices.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-file-invoice-dollar"></i><p>Invoices</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/manual_collection.php" class="nav-link <?= ($current_page == 'manual_collection.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-hand-holding-usd"></i><p>Manual Collection</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/payments.php" class="nav-link <?= ($current_page == 'payments.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-money-check-alt"></i><p>Verify Payments</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/expenses.php" class="nav-link <?= ($current_page == 'expenses.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-shopping-cart"></i><p>Log Expense</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/reports.php" class="nav-link <?= ($current_page == 'reports.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-chart-pie"></i><p>Reports</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/student_ledger.php" class="nav-link <?= ($current_page == 'student_ledger.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-book"></i><p>Student Ledger</p></a></li>
                        <li class="nav-item <?= in_array($current_page, ['print_unpaid.php', 'print_paid.php']) ? 'menu-open' : '' ?>">
                            <a href="#" class="nav-link"><i class="nav-icon fas fa-print"></i><p>Print Center <i class="right fas fa-angle-left"></i></p></a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="<?= SITE_URL ?>/admin/print_unpaid.php" class="nav-link <?= ($current_page == 'print_unpaid.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>Unpaid Vouchers</p></a></li>
                                <li class="nav-item"><a href="<?= SITE_URL ?>/admin/print_paid.php" class="nav-link <?= ($current_page == 'print_paid.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>Paid Vouchers</p></a></li>
                            </ul>
                        </li>
                        <li class="nav-item <?= in_array($current_page, ['classes.php', 'fee_heads.php', 'fee_structures.php', 'student_fees.php', 'expense_categories.php', 'branches.php', 'users.php', 'settings.php', 'audit_log.php', 'backup.php']) ? 'menu-open' : '' ?>">
                            <a href="#" class="nav-link"><i class="nav-icon fas fa-cogs"></i><p>System <i class="right fas fa-angle-left"></i></p></a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="<?= SITE_URL ?>/admin/classes.php" class="nav-link <?= ($current_page == 'classes.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>Classes & Sections</p></a></li>
                                <li class="nav-item"><a href="<?= SITE_URL ?>/admin/fee_heads.php" class="nav-link <?= ($current_page == 'fee_heads.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>Fee Heads</p></a></li>
                                <li class="nav-item"><a href="<?= SITE_URL ?>/admin/expense_categories.php" class="nav-link <?= ($current_page == 'expense_categories.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>Expense Categories</p></a></li>
                                <li class="nav-item"><a href="<?= SITE_URL ?>/admin/fee_structures.php" class="nav-link <?= ($current_page == 'fee_structures.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>Class Fee Structures</p></a></li>
                                <li class="nav-item"><a href="<?= SITE_URL ?>/admin/student_fees.php" class="nav-link <?= ($current_page == 'student_fees.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>Student Fee Structures</p></a></li>
                                <?php if ($_SESSION['manages_all_branches']): ?>
                                <!-- li class="nav-item"><a href="<?= SITE_URL ?>/admin/branches.php" class="nav-link <?= ($current_page == 'branches.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>Branch Management</p></a></li-->
                                <?php endif; ?>
                                <li class="nav-item"><a href="<?= SITE_URL ?>/admin/users.php" class="nav-link <?= ($current_page == 'users.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>User Management</p></a></li>
                                <li class="nav-item"><a href="<?= SITE_URL ?>/admin/settings.php" class="nav-link <?= ($current_page == 'settings.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>System Settings</p></a></li>
                                <li class="nav-item"><a href="<?= SITE_URL ?>/admin/audit_log.php" class="nav-link <?= ($current_page == 'audit_log.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>Audit Log</p></a></li>
                                <li class="nav-item"><a href="<?= SITE_URL ?>/admin/backup.php" class="nav-link <?= ($current_page == 'backup.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>Backup / Restore</p></a></li>
                            </ul>
                        </li>

                        <li class="nav-item mt-3">
                            <a href="https://wa.me/+923394567822" class="nav-link bg-success" target="_blank">
                                <i class="nav-icon fab fa-whatsapp"></i><p>Contact Developer</p>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($current_user_role === 'Accountant'): ?>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/accountant/index.php" class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/invoices.php" class="nav-link <?= ($current_page == 'invoices.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-file-invoice-dollar"></i><p>Invoice Management</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/manual_collection.php" class="nav-link <?= ($current_page == 'manual_collection.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-hand-holding-usd"></i><p>Manual Collection</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/payments.php" class="nav-link <?= ($current_page == 'payments.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-check-circle"></i><p>Verify Payments</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/expenses.php" class="nav-link <?= ($current_page == 'expenses.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-shopping-cart"></i><p>Log Expense</p></a></li>
                        
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/fee_heads.php" class="nav-link <?= ($current_page == 'fee_heads.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-tags"></i><p>Fee Heads</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/student_fees.php" class="nav-link <?= ($current_page == 'student_fees.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-user-tag"></i><p>Student Fee Structures</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/admin/expense_categories.php" class="nav-link <?= ($current_page == 'expense_categories.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>Expense Categories</p></a></li>

                        <li class="nav-item <?= in_array($current_page, ['print_unpaid.php', 'print_paid.php']) ? 'menu-open' : '' ?>">
                            <a href="#" class="nav-link"><i class="nav-icon fas fa-print"></i><p>Print Center <i class="right fas fa-angle-left"></i></p></a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="<?= SITE_URL ?>/admin/print_unpaid.php" class="nav-link <?= ($current_page == 'print_unpaid.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>Unpaid Vouchers</p></a></li>
                                <li class="nav-item"><a href="<?= SITE_URL ?>/admin/print_paid.php" class="nav-link <?= ($current_page == 'print_paid.php') ? 'active' : '' ?>"><i class="far fa-circle nav-icon"></i><p>Paid Vouchers</p></a></li>
                            </ul>
                        </li>

                        <li class="nav-item mt-3">
                            <a href="https://wa.me/+923394567822" class="nav-link bg-success" target="_blank">
                                <i class="nav-icon fab fa-whatsapp"></i><p>Contact Developer</p>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($current_user_role === 'Parent'): ?>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/parent/index.php" class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/parent/vouchers.php" class="nav-link <?= ($current_page == 'vouchers.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-receipt"></i><p>Fee Vouchers</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/parent/submit_payment.php" class="nav-link <?= ($current_page == 'submit_payment.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-upload"></i><p>Submit Payment</p></a></li>
                        <li class="nav-item"><a href="<?= SITE_URL ?>/parent/payment_history.php" class="nav-link <?= ($current_page == 'payment_history.php') ? 'active' : '' ?>"><i class="nav-icon fas fa-history"></i><p>Payment History</p></a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </aside>
    <div class="content-wrapper">