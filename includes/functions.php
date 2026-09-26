<?php
// school-fees-system/includes/functions.php (CLEANED & CORRECTED)

require_once __DIR__ . '/../config.php';
// FIX: Added the database connection, which is required by log_activity and get_setting
require_once __DIR__ . '/db.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// FIX: Use the Composer autoloader from the 'vendor' directory
require_once __DIR__ . '/../vendor/autoload.php';

function check_session($required_roles) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        header('Location: ' . SITE_URL . '/login.php?error=session_expired');
        exit();
    }
    $_SESSION['login_time'] = time();
    if (!in_array($_SESSION['role'], (array)$required_roles)) {
        http_response_code(403);
        die('<div style="text-align: center; margin-top: 50px;"><h1>403 - Access Denied</h1><p>You do not have permission to view this page.</p></div>');
    }
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function validate_csrf_token() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token validation failed. Request rejected.');
    }
}

function csrf_input_field() {
    generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

function set_flash_message($message, $type = 'success') {
    $_SESSION['flash_messages'][] = ['message' => $message, 'type' => $type];
}

function display_flash_messages() {
    if (isset($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $flash) {
            $type = ($flash['type'] === 'error') ? 'danger' : $flash['type'];
            echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">' . htmlspecialchars($flash['message']) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
        }
        unset($_SESSION['flash_messages']);
    }
}

function he($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function log_activity($action) {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $branch_id = $_SESSION['active_branch_id'] ?? null; 

    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, branch_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $ip_address, $branch_id]);
    } catch (PDOException $e) {
        // Fail silently
    }
}

function send_email($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $smtp_host = get_setting('smtp_host');
        $smtp_port = get_setting('smtp_port', 587); // Default to 587
        $smtp_user = get_setting('smtp_user');
        $smtp_pass = get_setting('smtp_pass');

        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->Port       = $smtp_port;

        // --- THIS IS THE FIX ---
        // Automatically set encryption based on the port
        if ($smtp_port == 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use 'ssl'
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use 'tls'
        }
        // --- END OF FIX ---

        $mail->setFrom($smtp_user, get_setting('school_name', 'Feenion'));
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Gets the ID of the branch the user is currently viewing.
 */
function get_active_branch_id() {
    // For normal users, it's just their assigned branch
    if (isset($_SESSION['manages_all_branches']) && !$_SESSION['manages_all_branches']) {
        return $_SESSION['user_branch_id'];
    }
    
    // For Super Admins, it's the one they selected from the dropdown
    return $_SESSION['active_branch_id'] ?? null;
}

// ---
// --- ALL SETTINGS FUNCTIONS (CORRECTED) ---
// ---

/**
 * Fetches all global settings (where branch_id = 0)
 */
function get_global_settings($pdo) {
    $settings = [];
    // FIX: Use branch_id = 0 for global, not IS NULL
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE branch_id = 0");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

/**
 * Fetches all settings for a specific branch
 */
function get_branch_settings($pdo, $branch_id) {
    $settings = [];
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

/**
 * Gets a single setting value.
 */
function get_setting($key, $default = '') {
    global $pdo; 
    $active_branch_id = get_active_branch_id();
    
    // 1. Try to find a setting specific to the active branch
    if ($active_branch_id) {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND branch_id = ?");
        $stmt->execute([$key, $active_branch_id]);
        $value = $stmt->fetchColumn();
        if ($value !== false) {
            return $value;
        }
    }
    
    // 2. If not found, fall back to global setting (branch_id = 0)
    // FIX: Use branch_id = 0 for global, not IS NULL
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND branch_id = 0");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    
    return ($value !== false) ? $value : $default;
}
?><?php
/**
 * ============================================================================
 * FEENION - Helper Functions for Combined Voucher System
 * ============================================================================
 * 
 * These functions should be ADDED to: includes/functions.php
 * Copy the code below and append to the end of your existing functions.php
 * 
 * Functions included:
 * 1. get_family_details()           - Get parent info and active children count
 * 2. get_parent_children()          - Get all children for a parent
 * 3. get_combined_voucher()         - Get combined voucher for specific month
 * 4. create_combined_voucher()      - Create/calculate combined voucher from invoices
 * 5. get_family_monthly_summary()   - Get financial summary for family month
 * 6. get_available_months_for_parent() - Get list of months with invoices
 * 7. distribute_payment()           - Smart payment distribution (proportional/equal)
 * 8. get_discount_reasons()         - Get list of discount reason options
 * 
 * ============================================================================
 */

/**
 * Get parent information and active children count
 * 
 * @param int $parent_id Parent ID
 * @param PDO $pdo Database connection
 * @return array Parent info with child count
 */
function get_family_details($parent_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.parent_name, p.email, p.phone, p.cnic, p.address,
               COUNT(DISTINCT s.id) as active_children
        FROM parents p
        LEFT JOIN students s ON s.parent_id = p.id AND s.status = 'Active'
        WHERE p.id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$parent_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get all active children for a parent
 * 
 * @param int $parent_id Parent ID
 * @param int $branch_id Branch ID
 * @param PDO $pdo Database connection
 * @param bool $active_only Only get active students
 * @return array Array of students
 */
function get_parent_children($parent_id, $branch_id, $pdo, $active_only = true) {
    $status_filter = $active_only ? "AND s.status = 'Active'" : "";
    
    $stmt = $pdo->prepare("
        SELECT id, student_name, admission_no, class_id
        FROM students
        WHERE parent_id = ? AND branch_id = ? {$status_filter}
        ORDER BY student_name ASC
    ");
    $stmt->execute([$parent_id, $branch_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get combined voucher for a specific month
 * 
 * @param int $parent_id Parent ID
 * @param string $month_year Format: YYYY-MM
 * @param int $branch_id Branch ID
 * @param PDO $pdo Database connection
 * @return array|null Combined voucher record or null
 */
function get_combined_voucher($parent_id, $month_year, $branch_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM combined_vouchers
        WHERE parent_id = ? AND month_year = ? AND branch_id = ?
    ");
    $stmt->execute([$parent_id, $month_year, $branch_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Create or update combined voucher for a family in a specific month
 * Calculates totals from all invoices for that parent in that month
 * 
 * @param int $parent_id Parent ID
 * @param string $month_year Format: YYYY-MM
 * @param int $branch_id Branch ID
 * @param PDO $pdo Database connection
 * @return array|bool Combined voucher data or false on failure
 */
function create_combined_voucher($parent_id, $month_year, $branch_id, $pdo) {
    try {
        // Calculate totals from invoices
        $calc_stmt = $pdo->prepare("
            SELECT 
                SUM(total_amount) as total_amount,
                SUM(amount_paid) as amount_paid
            FROM invoices
            WHERE parent_id = ? 
              AND DATE_FORMAT(created_at, '%Y-%m') = ?
              AND branch_id = ?
        ");
        $calc_stmt->execute([$parent_id, $month_year, $branch_id]);
        $totals = $calc_stmt->fetch(PDO::FETCH_ASSOC);

        $total_amount = (float)($totals['total_amount'] ?? 0);
        $amount_paid = (float)($totals['amount_paid'] ?? 0);

        // Determine status
        if ($amount_paid == 0) {
            $status = 'Unpaid';
        } elseif ($amount_paid >= $total_amount) {
            $status = 'Paid';
        } else {
            $status = 'Partially Paid';
        }

        // Insert or update
        $stmt = $pdo->prepare("
            INSERT INTO combined_vouchers 
            (parent_id, month_year, total_amount, amount_paid, status, branch_id)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                total_amount = ?,
                amount_paid = ?,
                status = ?
        ");

        $stmt->execute([
            $parent_id, $month_year, $total_amount, $amount_paid, $status, $branch_id,
            $total_amount, $amount_paid, $status
        ]);

        // Return the voucher
        return get_combined_voucher($parent_id, $month_year, $branch_id, $pdo);
    } catch (Exception $e) {
        error_log("Error creating combined voucher: " . $e->getMessage());
        return false;
    }
}

/**
 * Get financial summary for a parent for a specific month
 * 
 * @param int $parent_id Parent ID
 * @param string $month_year Format: YYYY-MM
 * @param int $branch_id Branch ID
 * @param PDO $pdo Database connection
 * @return array Summary with totals and due amounts
 */
function get_family_monthly_summary($parent_id, $month_year, $branch_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(i.total_amount) as total_amount,
            SUM(i.amount_paid) as amount_paid,
            SUM(i.total_amount - i.amount_paid) as total_due,
            COUNT(DISTINCT i.student_id) as child_count
        FROM invoices i
        WHERE i.parent_id = ? 
          AND DATE_FORMAT(i.created_at, '%Y-%m') = ?
          AND i.branch_id = ?
    ");
    $stmt->execute([$parent_id, $month_year, $branch_id]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get discount if applied
    $discount_stmt = $pdo->prepare("
        SELECT discount_amount, discount_reason FROM combined_vouchers
        WHERE parent_id = ? AND month_year = ? AND branch_id = ?
    ");
    $discount_stmt->execute([$parent_id, $month_year, $branch_id]);
    $voucher = $discount_stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'total_amount' => (float)($summary['total_amount'] ?? 0),
        'amount_paid' => (float)($summary['amount_paid'] ?? 0),
        'total_due' => (float)($summary['total_due'] ?? 0),
        'child_count' => (int)($summary['child_count'] ?? 0),
        'discount_amount' => (float)($voucher['discount_amount'] ?? 0),
        'discount_reason' => $voucher['discount_reason'] ?? null
    ];
}

/**
 * Get list of available months for a parent (months with invoices)
 * 
 * @param int $parent_id Parent ID
 * @param int $branch_id Branch ID
 * @param PDO $pdo Database connection
 * @return array Array of YYYY-MM strings
 */
function get_available_months_for_parent($parent_id, $branch_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') as month_year
        FROM invoices
        WHERE parent_id = ? AND branch_id = ?
        ORDER BY month_year DESC
    ");
    $stmt->execute([$parent_id, $branch_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Distribute payment among children based on amount owed (proportional) or equally
 * 
 * @param int $parent_id Parent ID
 * @param string $month_year Format: YYYY-MM
 * @param int $branch_id Branch ID
 * @param float $payment_amount Total payment amount
 * @param string $distribution_method 'proportional' or 'equal'
 * @param PDO $pdo Database connection
 * @return array Array of ['invoice_id' => amount_to_allocate]
 */
function distribute_payment($parent_id, $month_year, $branch_id, $payment_amount, $distribution_method = 'proportional', $pdo) {
    $distribution = [];
    
    // Get all invoices for this parent this month
    $stmt = $pdo->prepare("
        SELECT id, total_amount, amount_paid
        FROM invoices
        WHERE parent_id = ? 
          AND DATE_FORMAT(created_at, '%Y-%m') = ?
          AND branch_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$parent_id, $month_year, $branch_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($invoices)) {
        return $distribution;
    }

    if ($distribution_method === 'equal') {
        // Divide equally among all invoices
        $per_invoice = $payment_amount / count($invoices);
        foreach ($invoices as $invoice) {
            $distribution[$invoice['id']] = min($per_invoice, $invoice['total_amount'] - $invoice['amount_paid']);
        }
    } else {
        // Proportional: allocate based on amount owed
        $total_due = 0;
        foreach ($invoices as $invoice) {
            $due = $invoice['total_amount'] - $invoice['amount_paid'];
            $total_due += $due;
        }

        if ($total_due > 0) {
            $remaining_payment = $payment_amount;
            foreach ($invoices as $invoice) {
                $due = $invoice['total_amount'] - $invoice['amount_paid'];
                $proportion = $due / $total_due;
                $allocated = round($proportion * $payment_amount, 2);
                $allocated = min($allocated, $due, $remaining_payment);
                $distribution[$invoice['id']] = $allocated;
                $remaining_payment -= $allocated;
            }
        }
    }

    return $distribution;
}

/**
 * Get predefined discount reasons
 * Customize this list to match your school's discount policy
 * 
 * @return array Array of discount reason options
 */
function get_discount_reasons() {
    return [
        'Sibling Discount' => 'Sibling Discount - Multiple children in school',
        'Bulk Discount' => 'Bulk Discount - Payment for multiple months',
        'Loyalty Discount' => 'Loyalty Discount - Long-term student',
        'Financial Hardship' => 'Financial Hardship - Temporary financial difficulty',
        'Early Payment Discount' => 'Early Payment Discount - Paid before due date',
        'Staff Discount' => 'Staff Discount - Staff family member',
        'Merit Discount' => 'Merit Discount - Academic or athletic achievement',
        'Other' => 'Other - Custom reason'
    ];
}

/**
 * Format currency for display (Pakistan Rupees)
 * 
 * @param float $amount Amount to format
 * @return string Formatted currency string
 */
function format_currency($amount) {
    return 'PKR ' . number_format($amount, 2);
}

/**
 * Get students with their current due amounts for a family
 * Used in manual collection page to show breakdown
 * 
 * @param int $parent_id Parent ID
 * @param string $month_year Format: YYYY-MM
 * @param int $branch_id Branch ID
 * @param PDO $pdo Database connection
 * @return array Array of students with invoices and amounts
 */
function get_family_invoices_by_month($parent_id, $month_year, $branch_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            i.id as invoice_id,
            i.invoice_uid,
            s.id as student_id,
            s.student_name,
            s.admission_no,
            i.total_amount,
            i.amount_paid,
            i.total_amount - i.amount_paid as due_amount,
            i.status,
            GROUP_CONCAT(
                CONCAT(ii.fee_head, ': ', FORMAT(ii.amount, 2))
                SEPARATOR ' | '
            ) as fee_items
        FROM invoices i
        JOIN students s ON s.id = i.student_id
        LEFT JOIN invoice_items ii ON ii.invoice_id = i.id
        WHERE i.parent_id = ? 
          AND DATE_FORMAT(i.created_at, '%Y-%m') = ?
          AND i.branch_id = ?
        GROUP BY i.id
        ORDER BY s.student_name ASC
    ");
    $stmt->execute([$parent_id, $month_year, $branch_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update combined voucher discount
 * 
 * @param int $combined_voucher_id Combined voucher ID
 * @param float $discount_amount Discount amount
 * @param string $discount_reason Reason for discount
 * @param PDO $pdo Database connection
 * @return bool Success
 */
function update_voucher_discount($combined_voucher_id, $discount_amount, $discount_reason, $pdo) {
    try {
        $stmt = $pdo->prepare("
            UPDATE combined_vouchers
            SET discount_amount = ?, discount_reason = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$discount_amount, $discount_reason, $combined_voucher_id]);
        return true;
    } catch (Exception $e) {
        error_log("Error updating voucher discount: " . $e->getMessage());
        return false;
    }
}

/**
 * Get payment history for a family
 * 
 * @param int $parent_id Parent ID
 * @param int $branch_id Branch ID
 * @param PDO $pdo Database connection
 * @param int $limit Number of recent payments to retrieve
 * @return array Payment history
 */
function get_family_payment_history($parent_id, $branch_id, $pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.amount, p.payment_method, p.payment_date, 
               p.status, u.full_name as verified_by_name
        FROM payments p
        LEFT JOIN users u ON u.id = p.verified_by
        WHERE p.parent_id = ? AND p.branch_id = ?
        ORDER BY p.payment_date DESC
        LIMIT ?
    ");
    $stmt->execute([$parent_id, $branch_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// End of Helper Functions
?>
