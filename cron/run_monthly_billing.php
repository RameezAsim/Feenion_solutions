
<?php
//You now have a powerful script.

//Change the Security Key: Open the file and change ChangeThisToA_VeryLong_Random_String_12345 to your own private password.

//Test it: You can test it by visiting this URL in your browser (use your real key): http://localhost/school-fees-system9900/cron/run_monthly_billing.php?key=YourNewSecretKey

//Automate it: On a live web server, you would set up a "Cron Job" to automatically visit this URL once per month.


// school-fees-system/cron/run_monthly_billing.php

// This script is meant to be run by a server (Cron Job), not a user.
// We set a long execution time
set_time_limit(300); // 5 minutes

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// --- Security Check ---
// We'll add a simple 'secret key' to prevent this from being run by anyone
$SECURITY_KEY = "ChangeThisToA_VeryLong_Random_String_12345";
if (($_GET['key'] ?? '') !== $SECURITY_KEY) {
    die("Access Denied.");
}

echo "<h1>Starting Monthly Invoice Generation...</h1>";

$due_date = date('Y-m-d', strtotime('+10 days')); // Set due date 10 days from now
$month = date('m');
$year = date('Y');
$total_generated = 0;
$total_skipped = 0;

try {
    // 1. Get all active branches
    $branches = $pdo->query("SELECT id, name FROM branches")->fetchAll();
    
    if (empty($branches)) {
        die("No branches found in the system.");
    }

    $pdo->beginTransaction();

    // Prepare all statements ONE time
    $check_stmt = $pdo->prepare("SELECT id FROM invoices WHERE student_id = ? AND MONTH(due_date) = ? AND YEAR(due_date) = ? AND branch_id = ?");
    $students_stmt = $pdo->prepare("SELECT id, class_id FROM students WHERE status = 'Active' AND branch_id = ?");
    $custom_fees_stmt = $pdo->prepare("SELECT fh.head_name, sf.amount FROM student_fee_structure sf JOIN fee_heads fh ON sf.fee_head_id = fh.id WHERE sf.student_id = ? AND sf.branch_id = ?");
    $class_fees_stmt = $pdo->prepare("SELECT fh.head_name, cf.amount FROM class_fees cf JOIN fee_heads fh ON cf.fee_head_id = fh.id WHERE cf.class_id = ? AND cf.branch_id = ?");
    $dues_stmt = $pdo->prepare("SELECT SUM(total_amount - amount_paid) as outstanding FROM invoices WHERE student_id = ? AND status != 'Paid' AND branch_id = ?");
    $old_invoices_stmt = $pdo->prepare("SELECT id FROM invoices WHERE student_id = ? AND status != 'Paid' AND branch_id = ?");
    $update_old_stmt = $pdo->prepare("UPDATE invoices SET status = 'Paid', amount_paid = total_amount WHERE id = ? AND branch_id = ?");
    $inv_stmt = $pdo->prepare("INSERT INTO invoices (invoice_uid, student_id, session_id, total_amount, due_date, branch_id) VALUES (?, ?, (SELECT id FROM sessions WHERE is_active=1 LIMIT 1), ?, ?, ?)");
    $item_stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, fee_head, amount) VALUES (?, ?, ?)");
    $log_stmt = $pdo->prepare("INSERT INTO invoice_rollover_log (old_invoice_id, new_invoice_id) VALUES (?, ?)");

    // 2. Loop through each branch
    foreach ($branches as $branch) {
        $active_branch_id = $branch['id'];
        echo "<hr><h2>Processing Branch: " . he($branch['name']) . "</h2>";
        $branch_generated = 0;
        $branch_skipped = 0;
        
        // 3. Get all students in this branch
        $students_stmt->execute([$active_branch_id]);
        $students_in_branch = $students_stmt->fetchAll();
        
        if(empty($students_in_branch)) {
            echo "No active students in this branch. Skipping.<br>";
            continue;
        }

        // 4. Loop through each student in the branch
        foreach ($students_in_branch as $student) {
            $check_stmt->execute([$student['id'], $month, $year, $active_branch_id]);
            if ($check_stmt->fetch()) { $branch_skipped++; continue; }
            
            $class_fees_stmt->execute([$student['class_id'], $active_branch_id]);
            $class_fees = $class_fees_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            if (empty($class_fees)) { $branch_skipped++; continue; }
            
            $final_fee_structure = $class_fees;
            $custom_fees_stmt->execute([$student['id'], $active_branch_id]);
            $custom_fees = $custom_fees_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($custom_fees as $head_name => $amount) { $final_fee_structure[$head_name] = $amount; }
            
            $dues_stmt->execute([$student['id'], $active_branch_id]);
            $outstanding_balance = $dues_stmt->fetchColumn() ?? 0;
            $old_invoice_ids = [];
            
            if ($outstanding_balance > 0) {
                $old_invoices_stmt->execute([$student['id'], $active_branch_id]);
                $old_invoice_ids = $old_invoices_stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach($old_invoice_ids as $old_id) {
                    $update_old_stmt->execute([$old_id, $active_branch_id]);
                }
            }
            
            $new_items_total = array_sum($final_fee_structure);
            $new_total_amount = $new_items_total + $outstanding_balance;
            $invoice_uid = 'INV-' . time() . '-' . $student['id'];
            
            $inv_stmt->execute([$invoice_uid, $student['id'], $new_total_amount, $due_date, $active_branch_id]);
            $new_invoice_id = $pdo->lastInsertId();

            if ($outstanding_balance > 0 && !empty($old_invoice_ids)) {
                foreach ($old_invoice_ids as $old_id) {
                    $log_stmt->execute([$old_id, $new_invoice_id]);
                }
            }
            
            if ($outstanding_balance > 0) { $item_stmt->execute([$new_invoice_id, 'Previous Balance', $outstanding_balance]); }
            foreach ($final_fee_structure as $head_name => $amount) {
                $item_stmt->execute([$new_invoice_id, $head_name, $amount]);
            }
            $branch_generated++;
        }
        
        echo "Generated: {$branch_generated}, Skipped: {$branch_skipped}<br>";
        $total_generated += $branch_generated;
        $total_skipped += $branch_skipped;
    }
    
    $pdo->commit();
    echo "<hr><h2>Process Complete!</h2>";
    echo "<p><strong>Total Generated: {$total_generated}</strong></p>";
    echo "<p><strong>Total Skipped: {$total_skipped}</strong></p>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h1>An error occurred: " . $e->getMessage() . "</h1>";
}