<?php
// school-fees-system/admin/send_reminder.php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']);

$invoice_id = $_GET['id'] ?? null;
if (!$invoice_id) {
    die('Invoice ID is required.');
}

try {
    // Fetch all necessary details in one go
    $stmt = $pdo->prepare("
        SELECT i.invoice_uid, i.total_amount, i.amount_paid, i.due_date, 
               s.student_name, u.full_name as parent_name, u.email as parent_email
        FROM invoices i
        JOIN students s ON i.student_id = s.id
        JOIN users u ON s.parent_id = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $details = $stmt->fetch();

    if ($details) {
        $amount_due = $details['total_amount'] - $details['amount_paid'];

        // Construct the email message
        $subject = "Gentle Reminder: Fee Payment for " . $details['student_name'];
        $body = "
            <p>Dear " . he($details['parent_name']) . ",</p>
            <p>This is a friendly reminder regarding the fee payment for your child, <strong>" . he($details['student_name']) . "</strong>.</p>
            <ul>
                <li><strong>Invoice #:</strong> " . he($details['invoice_uid']) . "</li>
                <li><strong>Amount Due:</strong> PKR " . he(number_format($amount_due, 2)) . "</li>
                <li><strong>Due Date:</strong> " . he(date('d F, Y', strtotime($details['due_date']))) . "</li>
            </ul>
            <p>Please make the payment at your earliest convenience to avoid any late payment charges.</p>
            <p>Thank you.</p>
            <p><strong>" . he(get_setting('school_name')) . "</strong></p>
        ";
        
        // Send the email using our helper function
        if (send_email($details['parent_email'], $subject, $body)) {
            $message = "Reminder sent successfully to " . he($details['parent_email']);
            header("Location: invoices.php?success=" . urlencode($message));
        } else {
            throw new Exception("Failed to send email. Please check your SMTP settings.");
        }
    } else {
        throw new Exception("Invoice not found.");
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    header("Location: invoices.php?error=" . urlencode($error));
}
exit();