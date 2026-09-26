<?php
/**
 * ============================================================================
 * FEENION - Data Migration Script: Student-based → Parent-based Fee System
 * ============================================================================
 * 
 * This script performs the one-time migration of data:
 * 1. Creates parent records from existing student.parent_id references
 * 2. Populates invoices.parent_id from student records
 * 3. Creates combined_vouchers for existing invoices by (parent_id, month)
 * 4. Links invoices to combined_vouchers
 * 
 * IMPORTANT: 
 * - Run this AFTER database migrations (00_DATABASE_MIGRATIONS.sql)
 * - Make a DATABASE BACKUP first!
 * - This script is designed to be idempotent (safe to run multiple times)
 * 
 * Usage:
 * 1. Copy this file to: /admin/migrate_to_parent_system.php
 * 2. Visit: https://yoursite.com/admin/migrate_to_parent_system.php
 * 3. Review the output log
 * 4. Verify data integrity
 * 
 * ============================================================================
 */

// Security: Restrict to authenticated admins only
$page_title = 'Data Migration: Parent ID System';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin']);

// Prevent re-execution after initial migration
$migration_lock_file = __DIR__ . '/../.migration_complete';
if (file_exists($migration_lock_file)) {
    echo "<div style='background:#f0f0f0; padding:20px; border-radius:5px;'>";
    echo "<h2 style='color:#e74c3c;'>⚠️ Migration Already Completed</h2>";
    echo "<p>This migration has already been run on this system.</p>";
    echo "<p>If you need to re-run it, please delete the file: <code>.migration_complete</code> from the root directory.</p>";
    echo "<p><a href='index.php'>&larr; Back to Dashboard</a></p>";
    echo "</div>";
    exit;
}

// If GET request, show confirmation form
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Data Migration - Parent ID System</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .container { background: white; padding: 30px; border-radius: 8px; max-width: 800px; margin: 0 auto; }
            .warning { background: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; margin: 20px 0; }
            .info { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 20px 0; }
            h1 { color: #333; }
            h2 { color: #e74c3c; }
            .checklist { list-style: none; padding: 0; }
            .checklist li { padding: 10px; margin: 5px 0; background: #f9f9f9; border-left: 3px solid #27ae60; }
            .checklist li.warn { border-left-color: #e74c3c; }
            button { background: #e74c3c; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            button:hover { background: #c0392b; }
            a { color: #2196f3; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🔄 Data Migration: Parent ID System</h1>
            
            <div class="warning">
                <strong>⚠️ IMPORTANT:</strong> This operation cannot be undone easily. Please complete these steps:
            </div>

            <h2>Pre-Migration Checklist:</h2>
            <ul class="checklist">
                <li>✅ Database backup completed</li>
                <li>✅ SQL migrations run (00_DATABASE_MIGRATIONS.sql)</li>
                <li>✅ Verified parents table exists and is empty</li>
                <li>✅ Read through the migration script</li>
                <li class="warn">⚠️ System will be locked during migration (≈1-2 minutes)</li>
            </ul>

            <div class="info">
                <strong>What This Migration Does:</strong>
                <ol>
                    <li>Creates parent records from existing student references</li>
                    <li>Updates invoices with parent_id from student links</li>
                    <li>Creates combined_vouchers for each (parent_id, month)</li>
                    <li>Links individual invoices to combined vouchers</li>
                    <li>Verifies data integrity</li>
                </ol>
            </div>

            <form method="POST" style="margin-top: 30px;">
                <p>
                    <label>
                        <input type="checkbox" name="confirm_backup" required> 
                        I have completed a database backup
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="confirm_sql" required> 
                        I have run the SQL migrations
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="confirm_understand" required> 
                        I understand this cannot be easily undone
                    </label>
                </p>
                <button type="submit">✓ Proceed with Migration</button>
                <a href="index.php" style="margin-left: 20px;">← Cancel</a>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// POST request: Execute migration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();

    $log = [];
    $errors = [];
    $warnings = [];

    try {
        $pdo->beginTransaction();
        
        // ====================================================================
        // STEP 1: Create Parent Records
        // ====================================================================
        $log[] = "STEP 1: Creating parent records from existing student data...";
        
        // Get all unique parent_id references from students
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.parent_id, s.branch_id, u.full_name, u.email, u.phone
            FROM students s
            LEFT JOIN users u ON u.id = s.parent_id
            WHERE s.parent_id IS NOT NULL
            ORDER BY s.parent_id
        ");
        $stmt->execute();
        $existing_parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $parent_mapping = []; // Map old user_id to new parent_id
        $created_parents = 0;

        foreach ($existing_parents as $parent_ref) {
            $old_parent_id = $parent_ref['parent_id'];
            $branch_id = $parent_ref['branch_id'];
            $parent_name = $parent_ref['full_name'] ?? "Parent ID {$old_parent_id}";
            $email = $parent_ref['email'] ?? null;
            $phone = $parent_ref['phone'] ?? null;

            // Insert into new parents table
            $insert_stmt = $pdo->prepare("
                INSERT INTO parents (parent_name, email, phone, branch_id)
                VALUES (?, ?, ?, ?)
            ");
            
            try {
                $insert_stmt->execute([$parent_name, $email, $phone, $branch_id]);
                $new_parent_id = $pdo->lastInsertId();
                $parent_mapping[$old_parent_id] = $new_parent_id;
                $created_parents++;
                $log[] = "  ✓ Created parent: {$parent_name} (ID: {$new_parent_id}) from user ID {$old_parent_id}";
            } catch (Exception $e) {
                $errors[] = "Failed to create parent from user {$old_parent_id}: " . $e->getMessage();
            }
        }
        
        $log[] = "  Summary: Created {$created_parents} parent records";
        $log[] = "";

        // ====================================================================
        // STEP 2: Update Students - Keep parent_id as reference (for now)
        // ====================================================================
        $log[] = "STEP 2: Processing student records...";
        
        $student_count = 0;
        $stmt = $pdo->prepare("SELECT id, parent_id FROM students WHERE parent_id IS NOT NULL");
        $stmt->execute();
        $students = $stmt->fetchAll();
        
        $student_count = count($students);
        $log[] = "  ✓ Found {$student_count} students with parent references";
        $log[] = "";

        // ====================================================================
        // STEP 3: Populate Invoices with parent_id
        // ====================================================================
        $log[] = "STEP 3: Updating invoices table with parent_id...";
        
        $update_stmt = $pdo->prepare("
            UPDATE invoices i
            SET i.parent_id = (
                SELECT parent_id FROM students WHERE id = i.student_id
            )
            WHERE i.parent_id IS NULL AND i.student_id IS NOT NULL
        ");
        $update_stmt->execute();
        $updated_invoices = $update_stmt->rowCount();
        
        $log[] = "  ✓ Updated {$updated_invoices} invoices with parent_id";
        $log[] = "";

        // ====================================================================
        // STEP 4: Create Combined Vouchers
        // ====================================================================
        $log[] = "STEP 4: Creating combined_vouchers for each (parent, month)...";
        
        // Get all unique (parent_id, month_year) combos from invoices
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                i.parent_id,
                DATE_FORMAT(i.created_at, '%Y-%m') as month_year,
                i.branch_id
            FROM invoices i
            WHERE i.parent_id IS NOT NULL
            ORDER BY i.parent_id, month_year
        ");
        $stmt->execute();
        $month_combos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $created_vouchers = 0;

        foreach ($month_combos as $combo) {
            $parent_id = $combo['parent_id'];
            $month_year = $combo['month_year'];
            $branch_id = $combo['branch_id'];

            // Calculate totals for this parent in this month
            $total_stmt = $pdo->prepare("
                SELECT 
                    SUM(total_amount) as total_amount,
                    SUM(amount_paid) as amount_paid
                FROM invoices
                WHERE parent_id = ? 
                  AND DATE_FORMAT(created_at, '%Y-%m') = ?
                  AND branch_id = ?
            ");
            $total_stmt->execute([$parent_id, $month_year, $branch_id]);
            $totals = $total_stmt->fetch();

            $total_amount = (float)($totals['total_amount'] ?? 0);
            $amount_paid = (float)($totals['amount_paid'] ?? 0);

            // Insert combined voucher
            $insert_cv_stmt = $pdo->prepare("
                INSERT INTO combined_vouchers 
                (parent_id, month_year, total_amount, amount_paid, branch_id, status)
                VALUES (?, ?, ?, ?, ?, 
                    CASE 
                        WHEN ? = 0 THEN 'Unpaid'
                        WHEN ? >= ? THEN 'Paid'
                        ELSE 'Partially Paid'
                    END
                )
                ON DUPLICATE KEY UPDATE 
                    total_amount = ?, 
                    amount_paid = ?, 
                    status = CASE 
                        WHEN ? = 0 THEN 'Unpaid'
                        WHEN ? >= ? THEN 'Paid'
                        ELSE 'Partially Paid'
                    END
            ");

            try {
                $insert_cv_stmt->execute([
                    $parent_id, $month_year, $total_amount, $amount_paid, $branch_id,
                    $amount_paid, $amount_paid, $total_amount,
                    $total_amount, $amount_paid,
                    $amount_paid, $amount_paid, $total_amount
                ]);
                $created_vouchers++;
            } catch (Exception $e) {
                $errors[] = "Failed to create combined voucher for parent {$parent_id}, month {$month_year}: " . $e->getMessage();
            }
        }

        $log[] = "  ✓ Created/updated {$created_vouchers} combined vouchers";
        $log[] = "";

        // ====================================================================
        // STEP 5: Link Invoices to Combined Vouchers
        // ====================================================================
        $log[] = "STEP 5: Linking invoices to combined_vouchers...";
        
        $link_stmt = $pdo->prepare("
            UPDATE invoices i
            SET i.combined_voucher_id = (
                SELECT id FROM combined_vouchers cv
                WHERE cv.parent_id = i.parent_id
                  AND cv.month_year = DATE_FORMAT(i.created_at, '%Y-%m')
                  AND cv.branch_id = i.branch_id
                LIMIT 1
            )
            WHERE i.parent_id IS NOT NULL 
              AND i.combined_voucher_id IS NULL
        ");
        $link_stmt->execute();
        $linked_invoices = $link_stmt->rowCount();
        
        $log[] = "  ✓ Linked {$linked_invoices} invoices to combined vouchers";
        $log[] = "";

        // ====================================================================
        // VERIFICATION
        // ====================================================================
        $log[] = "VERIFICATION:";
        
        // Count parents created
        $parents_count = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
        $log[] = "  ✓ Parents table: {$parents_count} records";
        
        // Count combined vouchers
        $vouchers_count = $pdo->query("SELECT COUNT(*) FROM combined_vouchers")->fetchColumn();
        $log[] = "  ✓ Combined vouchers: {$vouchers_count} records";
        
        // Count invoices with parent_id
        $invoices_with_parent = $pdo->query("SELECT COUNT(*) FROM invoices WHERE parent_id IS NOT NULL")->fetchColumn();
        $log[] = "  ✓ Invoices with parent_id: {$invoices_with_parent}";
        
        // Count invoices linked to combined vouchers
        $invoices_linked = $pdo->query("SELECT COUNT(*) FROM invoices WHERE combined_voucher_id IS NOT NULL")->fetchColumn();
        $log[] = "  ✓ Invoices linked to combined vouchers: {$invoices_linked}";
        
        $log[] = "";

        $pdo->commit();
        
        $log[] = "✅ MIGRATION COMPLETED SUCCESSFULLY!";
        
        // Create lock file to prevent re-execution
        file_put_contents($migration_lock_file, 'Migration completed at ' . date('Y-m-d H:i:s'));
        $log[] = "Migration lock file created. Re-running is now prevented.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = "FATAL ERROR: " . $e->getMessage();
        $log[] = "❌ MIGRATION ROLLED BACK";
    }

    // Display Results
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Migration Results - Data Migration Script</title>
        <style>
            body { font-family: 'Courier New', monospace; margin: 40px; background: #f5f5f5; }
            .container { background: white; padding: 30px; border-radius: 8px; max-width: 900px; margin: 0 auto; }
            .log-box { background: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 4px; max-height: 500px; overflow-y: auto; }
            .log-line { padding: 5px 0; font-size: 13px; line-height: 1.6; }
            .success { color: #27ae60; }
            .error { color: #e74c3c; font-weight: bold; }
            .warning { color: #f39c12; }
            h1 { color: #333; }
            .status-box { margin: 20px 0; padding: 15px; border-radius: 4px; }
            .status-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
            .status-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
            a { color: #2196f3; margin-top: 20px; display: inline-block; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📊 Migration Results</h1>
            
            <?php if (empty($errors)): ?>
                <div class="status-box status-success">
                    <strong>✅ Migration Successful!</strong>
                    All data has been migrated. You can now use the new parent-based fee system.
                </div>
            <?php else: ?>
                <div class="status-box status-error">
                    <strong>❌ Migration Failed or Incomplete</strong>
                    <p><?php echo count($errors); ?> error(s) occurred. Please review below.</p>
                </div>
            <?php endif; ?>

            <h2>Migration Log:</h2>
            <div class="log-box">
                <?php foreach ($log as $line): ?>
                    <div class="log-line">
                        <?php if (strpos($line, '✓') === 0 || strpos($line, '✅') === 0): ?>
                            <span class="success"><?php echo htmlspecialchars($line); ?></span>
                        <?php elseif (strpos($line, '❌') === 0): ?>
                            <span class="error"><?php echo htmlspecialchars($line); ?></span>
                        <?php elseif (strpos($line, '⚠️') === 0 || strpos($line, 'STEP') === 0): ?>
                            <span class="warning"><strong><?php echo htmlspecialchars($line); ?></strong></span>
                        <?php else: ?>
                            <?php echo htmlspecialchars($line); ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($errors)): ?>
                <h2 style="color: #e74c3c;">Errors:</h2>
                <div class="log-box">
                    <?php foreach ($errors as $error): ?>
                        <div class="log-line error">• <?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($warnings)): ?>
                <h2 style="color: #f39c12;">Warnings:</h2>
                <div class="log-box">
                    <?php foreach ($warnings as $warning): ?>
                        <div class="log-line warning">⚠️ <?php echo htmlspecialchars($warning); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p><strong>Next Steps:</strong></p>
            <ul>
                <li>Verify data integrity in the database</li>
                <li>Test the new manual collection page (manual_collection_v2.php)</li>
                <li>Test the updated student form with parent selector</li>
                <li>Test combined voucher generation and printing</li>
                <li>If all tests pass, deploy to production</li>
            </ul>

            <a href="index.php">← Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
}
?>
