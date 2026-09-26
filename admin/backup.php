<?php
// school-fees-system/admin/backup.php (Multi-Branch Enabled & HOSTINGER COMPATIBLE)

$page_title = 'Backup & Export';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']);
$active_branch_id = get_active_branch_id();
$is_super_admin = $_SESSION['manages_all_branches'] ?? false;

// Handle Database Backup (Super Admin Only) - PHP NATIVE METHOD
if ($is_super_admin && isset($_GET['action']) && $_GET['action'] == 'backup_db') {
    try {
        // 1. Set up the download headers
        $backup_file_name = 'feenion_backup_' . DB_NAME . '_' . date("Y-m-d-H-i-s") . '.sql';
        
        // Disable buffering to prevent memory issues
        if (ob_get_level()) ob_end_clean();
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $backup_file_name . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        // Open output stream
        $output = fopen('php://output', 'w');

        // 2. Write SQL Header
        fwrite($output, "-- Feenion Database Backup\n");
        fwrite($output, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($output, "-- Database: " . DB_NAME . "\n");
        fwrite($output, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\nSTART TRANSACTION;\nSET time_zone = \"+00:00\";\n\n");

        // 3. Loop through all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            // Get CREATE TABLE statement
            $create_table_stmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            fwrite($output, "\n\n-- --------------------------------------------------------\n");
            fwrite($output, "-- Table structure for table `$table`\n");
            fwrite($output, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($output, $create_table_stmt[1] . ";\n\n");

            // Get Table Data
            $rows_stmt = $pdo->query("SELECT * FROM `$table`");
            $rows_count = $rows_stmt->rowCount();
            
            if ($rows_count > 0) {
                fwrite($output, "-- Dumping data for table `$table`\n");
                
                // Fetch all data
                while ($row = $rows_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $keys = array_map(function($k) { return "`$k`"; }, array_keys($row));
                    $values = array_map(function($v) use ($pdo) {
                        if ($v === null) return "NULL";
                        return $pdo->quote($v);
                    }, array_values($row));
                    
                    $sql = "INSERT INTO `$table` (" . implode(", ", $keys) . ") VALUES (" . implode(", ", $values) . ");\n";
                    fwrite($output, $sql);
                }
            }
        }

        // 4. Write Footer
        fwrite($output, "\nCOMMIT;\n");
        fclose($output);
        
        log_activity("Generated full database backup.");
        exit;

    } catch (Exception $e) {
        // If headers haven't been sent, show error
        if (!headers_sent()) {
            set_flash_message('Backup failed: ' . $e->getMessage(), 'error');
            header('Location: backup.php');
            exit;
        } else {
            echo "Backup Error: " . $e->getMessage();
        }
    }
}

// Handle CSV Export Action (Branch Specific)
if (isset($_GET['action']) && $_GET['action'] == 'export_csv' && $active_branch_id) {
    $export_type = $_GET['type'] ?? 'students';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $export_type . '_branch_' . $active_branch_id . '_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');

    try {
        if ($export_type == 'students') {
            fputcsv($output, ['Student Name', 'Admission No', 'Class', 'Section', 'Parent Name', 'Parent Email', 'Parent Phone', 'Status']);
            $stmt = $pdo->prepare("
                SELECT s.student_name, s.admission_no, c.class_name, sec.section_name, u.full_name, u.email, u.phone, s.status
                FROM students s
                JOIN classes c ON s.class_id = c.id
                LEFT JOIN sections sec ON s.section_id = sec.id
                JOIN users u ON s.parent_id = u.id
                WHERE s.branch_id = ?
            ");
            $stmt->execute([$active_branch_id]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, $row);
            }
        } elseif ($export_type == 'payments') {
            fputcsv($output, ['Payment Date', 'Invoice UID', 'Student Name', 'Amount', 'Method', 'Transaction ID', 'Status']);
            $stmt = $pdo->prepare("
                SELECT p.payment_date, i.invoice_uid, s.student_name, p.amount, p.payment_method, p.transaction_id, p.status
                FROM payments p
                LEFT JOIN invoices i ON p.invoice_id = i.id
                LEFT JOIN students s ON i.student_id = s.id
                WHERE p.branch_id = ?
                ORDER BY p.payment_date DESC
            ");
            $stmt->execute([$active_branch_id]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, $row);
            }
        }
    } catch (Exception $e) {
        fputcsv($output, ['Error exporting data: ' . $e->getMessage()]);
    }
    
    fclose($output);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">Backup & Export</h1></div></div></div>
</div>

<div class="content">
    <div class="container-fluid">
        <?php display_flash_messages(); ?>

        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Branch Data Export (CSV)</h3></div>
            <div class="card-body">
                <p>Export all data for your currently active branch (<strong><?= he($_SESSION['accessible_branches'][array_search($_SESSION['active_branch_id'], array_column($_SESSION['accessible_branches'], 'id'))]['name'] ?? 'N/A') ?></strong>) as a CSV file.</p>
                <a href="backup.php?action=export_csv&type=students" class="btn btn-success">
                    <i class="fas fa-file-csv"></i> Export Student List (CSV)
                </a>
                <a href="backup.php?action=export_csv&type=payments" class="btn btn-success ml-2">
                    <i class="fas fa-file-csv"></i> Export Payment History (CSV)
                </a>
            </div>
        </div>

        <?php if ($is_super_admin): ?>
        <div class="card card-danger">
            <div class="card-header"><h3 class="card-title">Full System Backup (Super Admin)</h3></div>
            <div class="card-body">
                <p>This will generate a full `.sql` backup of the entire database using a PHP-native method. This works on all hosting environments (Hostinger, GoDaddy, XAMPP) without configuration.</p>
                <a href="backup.php?action=backup_db" class="btn btn-danger" onclick="return confirm('Are you sure you want to generate a full database backup?');">
                    <i class="fas fa-database"></i> Download Database Backup (.sql)
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>