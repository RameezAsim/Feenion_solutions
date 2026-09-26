<?php
/**
 * ============================================================================
 * FEENION - Enhanced Manual Fee Collection (V2)
 * ============================================================================
 * 
 * Features:
 * - Select student → shows entire family
 * - Month slider for navigation
 * - Edit voucher option
 * - Discount feature
 * - Smart payment distribution (proportional/equal)
 * - Split-screen UI: Left=Collection, Right=Voucher Preview
 * 
 * Copy this ENTIRE file to: admin/manual_collection_v2.php
 * 
 * ============================================================================
 */

$page_title = 'Manual Fee Collection (Enhanced - Family-Based)';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']);
$active_branch_id = get_active_branch_id();

if (!$active_branch_id) {
    set_flash_message('You must select a branch to manage collections.', 'error');
    header('Location: index.php');
    exit;
}

// ============================================================================
// SECTION 1: AJAX ENDPOINTS
// ============================================================================

// API: Get list of available months for a family
if (isset($_GET['action']) && $_GET['action'] == 'get_family_months') {
    $student_id = $_GET['student_id'] ?? null;
    
    if (!$student_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No student selected']);
        exit;
    }

    try {
        // Get parent from student
        $stmt = $pdo->prepare("SELECT parent_id FROM students WHERE id = ? AND branch_id = ?");
        $stmt->execute([$student_id, $active_branch_id]);
        $student = $stmt->fetch();

        if (!$student || !$student['parent_id']) {
            throw new Exception("Student or parent not found");
        }

        $parent_id = $student['parent_id'];

        // Get all months with invoices for this parent
        $months = get_available_months_for_parent($parent_id, $active_branch_id, $pdo);

        // Use current month if available, else first month
        $current_month = date('Y-m');
        $default_month = (in_array($current_month, $months)) ? $current_month : ($months[0] ?? $current_month);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'months' => $months,
            'default_month' => $default_month,
            'parent_id' => $parent_id
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// API: Get family invoices and combined voucher for a specific month
if (isset($_GET['action']) && $_GET['action'] == 'get_family_invoices') {
    $student_id = $_GET['student_id'] ?? null;
    $month_year = $_GET['month_year'] ?? date('Y-m');

    if (!$student_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No student selected']);
        exit;
    }

    try {
        // Get parent from student
        $stmt = $pdo->prepare("SELECT parent_id FROM students WHERE id = ? AND branch_id = ?");
        $stmt->execute([$student_id, $active_branch_id]);
        $student = $stmt->fetch();

        if (!$student || !$student['parent_id']) {
            throw new Exception("Student or parent not found");
        }

        $parent_id = $student['parent_id'];

        // Ensure combined voucher exists
        $voucher = create_combined_voucher($parent_id, $month_year, $active_branch_id, $pdo);

        // Get parent info
        $parent_info = get_family_details($parent_id, $pdo);

        // Get all children
        $children = get_parent_children($parent_id, $active_branch_id, $pdo);

        // Get invoices for this month with fee breakdown
        $invoices = get_family_invoices_by_month($parent_id, $month_year, $active_branch_id, $pdo);

        // Get summary totals
        $summary = get_family_monthly_summary($parent_id, $month_year, $active_branch_id, $pdo);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'parent' => $parent_info,
            'children' => $children,
            'voucher' => $voucher,
            'invoices' => $invoices,
            'summary' => $summary
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// SECTION 2: FORM PROCESSING
// ============================================================================

// Handle payment collection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_csrf_token();

    if ($_POST['action'] === 'collect_family_payment') {
        $parent_id = (int)$_POST['parent_id'];
        $month_year = $_POST['month_year'];
        $amount_collected = (float)($_POST['amount_collected'] ?? 0);
        $discount_amount = (float)($_POST['discount_amount'] ?? 0);
        $discount_reason = $_POST['discount_reason'] ?? null;
        $payment_method = $_POST['payment_method'] ?? 'Cash';
        $distribution_method = $_POST['distribution_method'] ?? 'proportional';
        $admin_id = $_SESSION['user_id'];

        // Validation
        if ($amount_collected <= 0) {
            set_flash_message('Please enter a valid payment amount.', 'error');
        } else {
            try {
                $pdo->beginTransaction();

                // Get all invoices for this family this month
                $invoices_stmt = $pdo->prepare("
                    SELECT id, student_id, total_amount, amount_paid
                    FROM invoices
                    WHERE parent_id = ? 
                    AND DATE_FORMAT(created_at, '%Y-%m') = ?
                    AND branch_id = ?
                ");
                $invoices_stmt->execute([$parent_id, $month_year, $active_branch_id]);
                $invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($invoices)) {
                    throw new Exception("No invoices found for this family this month.");
                }

                // Create payment record for the family
                $insert_payment_stmt = $pdo->prepare("
                    INSERT INTO payments 
                    (parent_id, amount, payment_method, status, verified_by, transaction_id, branch_id, payment_date)
                    VALUES (?, ?, ?, 'Verified', ?, ?, ?, NOW())
                ");
                $insert_payment_stmt->execute([
                    $parent_id,
                    $amount_collected,
                    $payment_method,
                    $admin_id,
                    'Manual-' . time(),
                    $active_branch_id
                ]);
                $payment_id = $pdo->lastInsertId();

                // Distribute payment among children's invoices
                $distribution = distribute_payment(
                    $parent_id, $month_year, $active_branch_id,
                    $amount_collected, $distribution_method, $pdo
                );

                foreach ($distribution as $invoice_id => $allocated_amount) {
                    if ($allocated_amount > 0) {
                        $update_stmt = $pdo->prepare("
                            UPDATE invoices
                            SET amount_paid = amount_paid + ?,
                                outstanding_due = total_amount - (amount_paid + ?)
                            WHERE id = ?
                        ");
                        $update_stmt->execute([$allocated_amount, $allocated_amount, $invoice_id]);
                    }
                }

                // Create voucher record
                $voucher_code = 'VCH-MAN-' . $payment_id . '-' . time();
                $voucher_stmt = $pdo->prepare("
                    INSERT INTO vouchers
                    (payment_id, voucher_code, file_path)
                    VALUES (?, ?, '')
                ");
                $voucher_stmt->execute([$payment_id, $voucher_code]);

                // Update combined voucher status and discount
                if ($discount_amount > 0 || $discount_reason) {
                    $update_voucher_stmt = $pdo->prepare("
                        UPDATE combined_vouchers
                        SET discount_amount = COALESCE(discount_amount, 0) + ?,
                            discount_reason = ?
                        WHERE parent_id = ? AND month_year = ? AND branch_id = ?
                    ");
                    $update_voucher_stmt->execute([
                        $discount_amount,
                        $discount_reason,
                        $parent_id,
                        $month_year,
                        $active_branch_id
                    ]);
                }

                // Recalculate combined voucher status
                $recalc_stmt = $pdo->prepare("
                    UPDATE combined_vouchers cv
                    SET cv.amount_paid = (
                        SELECT SUM(amount_paid) FROM invoices
                        WHERE parent_id = cv.parent_id
                        AND DATE_FORMAT(created_at, '%Y-%m') = cv.month_year
                    ),
                    cv.status = CASE
                        WHEN (SELECT SUM(amount_paid) FROM invoices
                              WHERE parent_id = cv.parent_id
                              AND DATE_FORMAT(created_at, '%Y-%m') = cv.month_year) = 0 THEN 'Unpaid'
                        WHEN (SELECT SUM(amount_paid) FROM invoices
                              WHERE parent_id = cv.parent_id
                              AND DATE_FORMAT(created_at, '%Y-%m') = cv.month_year) 
                             >= cv.total_amount THEN 'Paid'
                        ELSE 'Partially Paid'
                    END
                    WHERE parent_id = ? AND month_year = ? AND branch_id = ?
                ");
                $recalc_stmt->execute([$parent_id, $month_year, $active_branch_id]);

                $pdo->commit();

                $msg = "Payment of " . format_currency($amount_collected) . " collected successfully! ";
                if ($discount_amount > 0) {
                    $msg .= "Discount of " . format_currency($discount_amount) . " applied.";
                }
                set_flash_message($msg, 'success');

                // Redirect to same page to refresh
                header('Location: manual_collection_v2.php');
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash_message('Error processing payment: ' . $e->getMessage(), 'error');
            }
        }
    }

    // Handle voucher editing
    elseif ($_POST['action'] === 'edit_voucher') {
        $combined_voucher_id = (int)$_POST['combined_voucher_id'];
        $discount_amount = (float)($_POST['discount_amount'] ?? 0);
        $discount_reason = $_POST['discount_reason'] ?? null;

        try {
            update_voucher_discount($combined_voucher_id, $discount_amount, $discount_reason, $pdo);
            set_flash_message('Voucher discount updated successfully!', 'success');
        } catch (Exception $e) {
            set_flash_message('Error updating voucher: ' . $e->getMessage(), 'error');
        }

        header('Location: manual_collection_v2.php');
        exit;
    }
}

// ============================================================================
// SECTION 3: PAGE RENDERING
// ============================================================================

// Get all students for the dropdown
$students_stmt = $pdo->prepare("
    SELECT s.id, s.student_name, s.admission_no, s.parent_id,
           COALESCE(p.parent_name, 'Unknown Parent') as parent_name,
           CONCAT(s.student_name, ' (', s.admission_no, ') - ', COALESCE(p.parent_name, '')) as display_text
    FROM students s
    LEFT JOIN parents p ON p.id = s.parent_id
    WHERE s.branch_id = ? AND s.status = 'Active'
    ORDER BY s.student_name ASC
");
$students_stmt->execute([$active_branch_id]);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get discount reasons for dropdown
$discount_reasons = get_discount_reasons();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 20px;
            max-width: 1400px;
        }

        .panel {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .panel h2 {
            font-size: 18px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            color: #2c3e50;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
            font-size: 13px;
        }

        select, input[type="text"], input[type="email"], input[type="number"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        select:focus, input:focus, textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .month-navigator {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ecf0f1;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .month-navigator button {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .month-navigator button:hover {
            background: #2980b9;
        }

        .month-display {
            font-weight: 600;
            font-size: 16px;
            color: #2c3e50;
        }

        .family-info {
            background: #f0f8ff;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 3px solid #3498db;
        }

        .family-info p {
            margin: 5px 0;
            font-size: 13px;
            color: #2c3e50;
        }

        .invoices-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .invoices-table thead {
            background: #ecf0f1;
        }

        .invoices-table th {
            padding: 8px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
        }

        .invoices-table td {
            padding: 8px;
            border-bottom: 1px solid #ecf0f1;
        }

        .invoices-table tbody tr:hover {
            background: #f9f9f9;
        }

        .btn {
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn:hover {
            background: #229954;
        }

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .summary-box {
            background: #f0f8ff;
            border: 1px solid #3498db;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
        }

        .summary-row:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 16px;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
        }

        .spinner {
            border: 4px solid #ecf0f1;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            background: #fadbd8;
            color: #c0392b;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 3px solid #e74c3c;
        }

        .success-message {
            background: #d5f4e6;
            color: #27ae60;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 3px solid #27ae60;
        }

        .voucher-preview {
            background: #fff9e6;
            border: 2px solid #f39c12;
            padding: 15px;
            border-radius: 4px;
            font-size: 12px;
        }

        .voucher-preview h3 {
            text-align: center;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .voucher-line {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
        }

        .voucher-total {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-weight: 600;
            font-size: 14px;
        }

        @media (max-width: 1024px) {
            .container {
                grid-template-columns: 1fr;
            }
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin: 10px 0;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            margin: 0;
            font-weight: normal;
        }

        .radio-group input[type="radio"] {
            width: auto;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <!-- Include header -->
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="container">

        <!-- ================================================================= -->
        <!-- LEFT PANEL: COLLECTION FORM -->
        <!-- ================================================================= -->
        <div class="panel">
            <h2>💰 Collect Payment (Current Branch)</h2>

            <?php if ($flash = get_flash_message()): ?>
                <div class="<?php echo $flash['type'] === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo $flash['message']; ?>
                </div>
            <?php endif; ?>

            <form id="collectionForm">
                <!-- Student Selection -->
                <div class="form-group">
                    <label for="student_id">Select Student / Family:</label>
                    <select id="student_id" name="student_id" required onchange="loadFamilyData()">
                        <option value="">-- Choose a student --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['display_text']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Month Navigation -->
                <div id="monthSection" style="display:none;">
                    <div class="month-navigator">
                        <button type="button" onclick="previousMonth()">&larr; Previous</button>
                        <span class="month-display" id="monthDisplay">---</span>
                        <button type="button" onclick="nextMonth()">Next &rarr;</button>
                    </div>

                    <!-- Family Info Card -->
                    <div id="familyInfoCard" class="family-info" style="display:none;">
                        <p><strong>Parent:</strong> <span id="parentName">---</span></p>
                        <p><strong>Phone:</strong> <span id="parentPhone">---</span></p>
                        <p><strong>Children:</strong> <span id="childCount">0</span></p>
                    </div>

                    <!-- Invoices Table -->
                    <div style="margin-bottom: 15px;">
                        <h3 style="font-size: 14px; margin-bottom: 10px;">Outstanding Invoices:</h3>
                        <table class="invoices-table" id="invoicesTable">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Due</th>
                                </tr>
                            </thead>
                            <tbody id="invoicesBody">
                                <tr><td colspan="4" class="loading">Select a student first...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary Totals -->
                    <div class="summary-box">
                        <div class="summary-row">
                            <span>Total Due:</span>
                            <span id="summaryDue">PKR 0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Total Paid:</span>
                            <span id="summaryPaid">PKR 0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Discount:</span>
                            <span id="summaryDiscount">PKR 0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Payable Amount:</span>
                            <span id="summaryPayable">PKR 0.00</span>
                        </div>
                    </div>

                    <!-- Payment Form -->
                    <div class="form-group">
                        <label for="amount_collected">Amount Collected (PKR):</label>
                        <input type="number" id="amount_collected" name="amount_collected" step="0.01" min="0" placeholder="0.00" onchange="updateSummary()">
                    </div>

                    <div class="form-group">
                        <label for="discount_amount">Discount Amount (PKR):</label>
                        <input type="number" id="discount_amount" name="discount_amount" step="0.01" min="0" value="0" onchange="updateSummary()">
                    </div>

                    <div class="form-group">
                        <label for="discount_reason">Discount Reason:</label>
                        <select id="discount_reason" name="discount_reason">
                            <option value="">-- No Discount --</option>
                            <?php foreach ($discount_reasons as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment Method:</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Check">Check</option>
                            <option value="Mobile Payment">Mobile Payment</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Distribution Method:</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="distribution_method" value="proportional" checked>
                                Proportional (by amount owed)
                            </label>
                            <label>
                                <input type="radio" name="distribution_method" value="equal">
                                Equal Split
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn" style="width: 100%;">✓ Record Payment</button>

                </div>
            </form>
        </div>

        <!-- ================================================================= -->
        <!-- RIGHT PANEL: VOUCHER PREVIEW -->
        <!-- ================================================================= -->
        <div class="panel">
            <h2>📄 Voucher Preview</h2>

            <div id="voucherSection" style="display:none;">
                <div class="voucher-preview" id="voucherDisplay">
                    <!-- Populated by JavaScript -->
                </div>

                <div style="margin-top: 15px;">
                    <button type="button" class="btn btn-secondary" style="width: 100%; margin-bottom: 10px;" onclick="printVoucher()">
                        🖨️ Print Voucher
                    </button>
                    <button type="button" class="btn btn-secondary" style="width: 100%; margin-bottom: 10px;" onclick="openEditModal()">
                        ✏️ Edit Voucher
                    </button>
                </div>

                <!-- Edit Voucher Modal -->
                <div id="editModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000;">
                    <div style="background:white; width:90%; max-width:500px; margin:50px auto; padding:20px; border-radius:8px;">
                        <h3>Edit Voucher Discount</h3>
                        <form id="editVoucherForm">
                            <input type="hidden" id="edit_combined_voucher_id" name="combined_voucher_id">
                            
                            <div class="form-group">
                                <label>Discount Amount (PKR):</label>
                                <input type="number" id="edit_discount_amount" name="discount_amount" step="0.01" min="0">
                            </div>

                            <div class="form-group">
                                <label>Discount Reason:</label>
                                <select id="edit_discount_reason" name="discount_reason">
                                    <option value="">-- No Reason --</option>
                                    <?php foreach ($discount_reasons as $key => $label): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>">
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="display:flex; gap:10px;">
                                <button type="submit" class="btn" style="flex:1;">Save Changes</button>
                                <button type="button" class="btn btn-secondary" style="flex:1;" onclick="closeEditModal()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="noVoucherMessage" style="text-align:center; padding:20px; color:#7f8c8d;">
                Select a student to view family voucher details
            </div>
        </div>
    </div>

    <!-- Include footer -->
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        const baseURL = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
        let currentStudent = null;
        let currentMonth = null;
        let availableMonths = [];
        let currentFamilyData = null;

        // Initialize form submission
        document.getElementById('collectionForm').addEventListener('submit', handlePaymentSubmit);
        document.getElementById('editVoucherForm').addEventListener('submit', handleEditVoucherSubmit);

        // Load family data when student is selected
        function loadFamilyData() {
            const studentId = document.getElementById('student_id').value;
            if (!studentId) return;

            currentStudent = studentId;
            showLoading();

            fetch(`?action=get_family_months&student_id=${studentId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        availableMonths = data.months;
                        currentMonth = data.default_month;
                        document.getElementById('monthSection').style.display = 'block';
                        loadMonthData();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error loading family data');
                });
        }

        function loadMonthData() {
            if (!currentStudent || !currentMonth) return;

            showLoading();
            fetch(`?action=get_family_invoices&student_id=${currentStudent}&month_year=${currentMonth}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        currentFamilyData = data;
                        renderFamilyInfo(data);
                        renderInvoices(data);
                        renderVoucher(data);
                        updateSummary();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error loading month data');
                });
        }

        function renderFamilyInfo(data) {
            document.getElementById('parentName').textContent = data.parent.parent_name;
            document.getElementById('parentPhone').textContent = data.parent.phone || 'N/A';
            document.getElementById('childCount').textContent = data.parent.active_children;
            document.getElementById('familyInfoCard').style.display = 'block';

            document.getElementById('monthDisplay').textContent = formatMonth(currentMonth);
        }

        function renderInvoices(data) {
            const tbody = document.getElementById('invoicesBody');
            tbody.innerHTML = '';

            if (!data.invoices || data.invoices.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4">No invoices for this month</td></tr>';
                return;
            }

            data.invoices.forEach(inv => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${inv.student_name}</td>
                    <td>PKR ${parseFloat(inv.total_amount).toFixed(2)}</td>
                    <td>PKR ${parseFloat(inv.amount_paid).toFixed(2)}</td>
                    <td>PKR ${parseFloat(inv.due_amount).toFixed(2)}</td>
                `;
                tbody.appendChild(row);
            });
        }

        function renderVoucher(data) {
            const voucherDisplay = document.getElementById('voucherDisplay');
            const voucher = data.voucher;

            let html = '<h3>Combined Invoice</h3>';
            html += '<p><strong>Parent:</strong> ' + data.parent.parent_name + '</p>';
            html += '<p><strong>Month:</strong> ' + formatMonth(currentMonth) + '</p>';
            html += '<hr style="margin:10px 0;">';

            if (data.invoices && data.invoices.length > 0) {
                html += '<p><strong>Students:</strong></p>';
                data.invoices.forEach(inv => {
                    html += '<div class="voucher-line">';
                    html += '<span>' + inv.student_name + '</span>';
                    html += '<span>PKR ' + parseFloat(inv.due_amount).toFixed(2) + '</span>';
                    html += '</div>';
                });
            }

            html += '<hr style="margin:10px 0;">';
            html += '<div class="voucher-total">';
            html += '<span>TOTAL DUE:</span>';
            html += '<span>PKR ' + parseFloat(data.summary.total_due).toFixed(2) + '</span>';
            html += '</div>';

            if (voucher && voucher.discount_amount > 0) {
                html += '<div class="voucher-line">';
                html += '<span><em>Discount: ' + (voucher.discount_reason || 'Other') + '</em></span>';
                html += '<span>-PKR ' + parseFloat(voucher.discount_amount).toFixed(2) + '</span>';
                html += '</div>';
            }

            voucherDisplay.innerHTML = html;

            // Set edit form values
            if (voucher) {
                document.getElementById('edit_combined_voucher_id').value = voucher.id;
                document.getElementById('edit_discount_amount').value = voucher.discount_amount || 0;
                document.getElementById('edit_discount_reason').value = voucher.discount_reason || '';
            }

            document.getElementById('voucherSection').style.display = 'block';
            document.getElementById('noVoucherMessage').style.display = 'none';
        }

        function updateSummary() {
            if (!currentFamilyData) return;

            const totalDue = parseFloat(currentFamilyData.summary.total_due) || 0;
            const amountCollected = parseFloat(document.getElementById('amount_collected').value) || 0;
            const discount = parseFloat(document.getElementById('discount_amount').value) || 0;

            document.getElementById('summaryDue').textContent = 'PKR ' + totalDue.toFixed(2);
            document.getElementById('summaryPaid').textContent = 'PKR ' + currentFamilyData.summary.amount_paid.toFixed(2);
            document.getElementById('summaryDiscount').textContent = 'PKR ' + discount.toFixed(2);
            document.getElementById('summaryPayable').textContent = 'PKR ' + (totalDue - discount).toFixed(2);
        }

        function previousMonth() {
            const current = new Date(currentMonth + '-01');
            current.setMonth(current.getMonth() - 1);
            const newMonth = current.getFullYear() + '-' + String(current.getMonth() + 1).padStart(2, '0');
            if (availableMonths.includes(newMonth)) {
                currentMonth = newMonth;
                loadMonthData();
            } else {
                alert('No invoices for this month');
            }
        }

        function nextMonth() {
            const current = new Date(currentMonth + '-01');
            current.setMonth(current.getMonth() + 1);
            const newMonth = current.getFullYear() + '-' + String(current.getMonth() + 1).padStart(2, '0');
            if (availableMonths.includes(newMonth)) {
                currentMonth = newMonth;
                loadMonthData();
            } else {
                alert('No invoices for this month');
            }
        }

        function formatMonth(monthStr) {
            const [year, month] = monthStr.split('-');
            const date = new Date(year, month - 1);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
        }

        function showLoading() {
            document.getElementById('invoicesBody').innerHTML = '<tr><td colspan="4"><div class="loading"><div class="spinner"></div>Loading...</div></td></tr>';
        }

        function handlePaymentSubmit(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('collectionForm'));
            formData.append('action', 'collect_family_payment');
            formData.append('parent_id', currentFamilyData.parent.id);
            formData.append('month_year', currentMonth);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(html => {
                    // Reload page to show success message
                    location.reload();
                })
                .catch(err => {
                    console.error(err);
                    alert('Error processing payment');
                });
        }

        function handleEditVoucherSubmit(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('editVoucherForm'));
            formData.append('action', 'edit_voucher');

            fetch('', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(html => {
                    location.reload();
                })
                .catch(err => {
                    console.error(err);
                    alert('Error updating voucher');
                });
        }

        function openEditModal() {
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function printVoucher() {
            window.print();
        }
    </script>
</body>
</html>
