<?php
// school-fees-system/admin/audit_log.php (Multi-Branch Enabled & FIXED)

$page_title = 'Audit Log';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin']);

// Determine which logs to show
$active_branch_id = get_active_branch_id();
$is_super_admin = $_SESSION['manages_all_branches'] ?? false;

$sql = "SELECT al.*, u.full_name, b.name as branch_name 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        LEFT JOIN branches b ON al.branch_id = b.id";

$params = [];

// If not super admin, or if a specific branch is selected, filter by branch
if ($active_branch_id) {
    $sql .= " WHERE al.branch_id = ?";
    $params[] = $active_branch_id;
}

$sql .= " ORDER BY al.created_at DESC LIMIT 500"; // Limit to last 500 for performance

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1 class="m-0">Audit Log</h1></div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">System Activity (Last 500 Actions)</h3>
            </div>
            <div class="card-body">
                <table id="logTable" class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Branch</th>
                            <th>Action</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?= he(date('d M Y, h:i A', strtotime($log['created_at']))) ?></td>
                            <td><?= he($log['full_name'] ?? 'System/Unknown') ?></td>
                            <td><?= he($log['branch_name'] ?? 'Global') ?></td>
                            <td><?= he($log['action']) ?></td>
                            <td><?= he($log['ip_address']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    
    // --- THIS IS THE FIX ---
    // "destroy": true prevents the 'Cannot reinitialise' error
    $('#logTable').DataTable({
        "destroy": true, 
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": false, // Default sorting handled by SQL (DESC)
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "pageLength": 50
    });
    // -----------------------

});
</script>