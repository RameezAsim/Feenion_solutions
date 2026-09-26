<?php
// school-fees-system/cron/send_bulk_reminders.php
// This script is meant to be run automatically by a cron job on your server.

// Set a long execution time as this might take a while 
//  add this cron joob      /usr/local/bin/php /home/your_username/public_html/school-fees-system/cron/send_bulk_reminders.php
set_time_limit(300); 

// We are not in a web browser, so change the directory to the project root
chdir(dirname(__DIR__));

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

echo "Starting bulk reminder process...\n";

try {
    // Find all invoices that are unpaid/partially paid and past their due date
    $stmt = $pdo->query("
        SELECT i.id, i.invoice_uid, i.total_amount, i.amount_paid, i.due_date, 
               s.student_name, u.full_name as parent_name, u.email as parent_email
        FROM invoices i
        JOIN students s ON i.student_id = s.id
        JOIN users u ON s.parent_id = u.id
        WHERE i.status != 'Paid' AND i.due_date < CURDATE()
    ");
    $overdue_invoices = $stmt->fetchAll();

    if (empty($overdue_invoices)) {
        echo "No overdue invoices found. Exiting.\n";
        exit();
    }

    echo "Found " . count($overdue_invoices) . " overdue invoices to process.\n";

    foreach ($overdue_invoices as $invoice) {
        $amount_due = $invoice['total_amount'] - $invoice['amount_paid'];
        $subject = "URGENT: Overdue Fee Payment for " . $invoice['student_name'];
        $body = "
            <p>Dear " . he($invoice['parent_name']) . ",</p>
            <p>This is an urgent reminder that your fee payment for <strong>" . he($invoice['student_name']) . "</strong> is overdue.</p>
            <ul>
                <li><strong>Invoice #:</strong> " . he($invoice['invoice_uid']) . "</li>
                <li><strong>Amount Due:</strong> PKR " . he(number_format($amount_due, 2)) . "</li>
                <li><strong>Original Due Date:</strong> " . he(date('d F, Y', strtotime($invoice['due_date']))) . "</li>
            </ul>
            <p>Please clear the outstanding dues immediately to avoid further action as per the school policy.</p>
            <p>Thank you.</p>
            <p><strong>" . he(get_setting('school_name')) . "</strong></p>
        ";

        if (send_email($invoice['parent_email'], $subject, $body)) {
            echo "Successfully sent reminder for Invoice #" . $invoice['invoice_uid'] . " to " . $invoice['parent_email'] . "\n";
        } else {
            echo "!!! FAILED to send reminder for Invoice #" . $invoice['invoice_uid'] . " to " . $invoice['parent_email'] . "\n";
        }
        // Pause for a second to avoid spamming the email server
        sleep(1);
    }

} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}

echo "Bulk reminder process finished.\n";