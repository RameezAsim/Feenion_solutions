// In admin/students.php
<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
check_session('Admin'); // Only allows Admins
?>

// In accountant/invoices.php
<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
check_session(['Admin', 'Accountant']); // Allows Admin or Accountant
?>