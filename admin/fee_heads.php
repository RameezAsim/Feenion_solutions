<?php
// school-fees-system/admin/fee_heads.php (CORRECTED)

$page_title = 'Manage Fee Heads';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']);
$active_branch_id = get_active_branch_id();

if (!$active_branch_id) {
    set_flash_message('You must select a branch to manage.', 'error');
    header('Location: ../admin/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_csrf_token();
    try {
        switch ($_POST['action']) {
            case 'add_fee_head':
                $stmt = $pdo->prepare("INSERT INTO fee_heads (head_name, branch_id) VALUES (?, ?)");
                $stmt->execute([trim($_POST['head_name']), $active_branch_id]);
                set_flash_message('Fee Head added successfully!');
                break;
            case 'edit_fee_head':
                $stmt = $pdo->prepare("UPDATE fee_heads SET head_name = ? WHERE id = ? AND branch_id = ?");
                $stmt->execute([trim($_POST['head_name_edit']), $_POST['fee_head_id'], $active_branch_id]);
                set_flash_message('Fee Head updated successfully!');
                break;
            case 'delete_fee_head':
                $stmt = $pdo->prepare("DELETE FROM fee_heads WHERE id = ? AND branch_id = ?");
                $stmt->execute([$_POST['fee_head_id'], $active_branch_id]);
                set_flash_message('Fee Head deleted successfully.');
                break;
        }
    } catch (Exception $e) {
        $error_message = ($e->getCode() == 23000) ? "Error: That Fee Head name already exists for this branch." : "Database error: " . $e->getMessage();
        set_flash_message($error_message, 'error');
    }
    header("Location: fee_heads.php");
    exit();
}

// Fetch fee heads FOR THE CURRENT BRANCH
$stmt = $pdo->prepare("SELECT id, head_name FROM fee_heads WHERE branch_id = ? ORDER BY head_name ASC");
$stmt->execute([$active_branch_id]);
$fee_heads = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">Manage Fee Heads</h1></div></div></div>
</div>
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-5">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Add New Fee Head (Current Branch)</h3></div>
                    <form method="POST">
                        <?= csrf_input_field() ?>
                        <input type="hidden" name="action" value="add_fee_head">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="head_name">Fee Head Name</label>
                                <input type="text" id="head_name" name="head_name" class="form-control" placeholder="e.g., Annual Sports Fee" required>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Add Fee Head</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Existing Fee Heads (Current Branch)</h3></div>
                    <div class="card-body">
                        <?php display_flash_messages(); ?>
                        <table id="feeHeadsTable" class="table table-bordered table-hover">
                            <thead><tr><th>Name</th><th style="width: 100px;">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($fee_heads as $head): ?>
                                <tr>
                                    <td><?= he($head['head_name']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-btn" data-id="<?= he($head['id']) ?>" data-name="<?= he($head['head_name']) ?>"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm btn-danger delete-btn" data-id="<?= he($head['id']) ?>"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal"><div class="modal-dialog"><div class="modal-content"><form method="POST">
    <?= csrf_input_field() ?><input type="hidden" name="action" value="edit_fee_head"><input type="hidden" name="fee_head_id" id="editFeeHeadId">
    <div class="modal-header"><h4 class="modal-title">Edit Fee Head</h4></div>
    <div class="modal-body"><div class="form-group"><label>Name</label><input type="text" name="head_name_edit" id="editHeadName" class="form-control" required></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
</form></div></div></div>

<div class="modal fade" id="deleteModal"><div class="modal-dialog"><div class="modal-content"><form method="POST">
    <?= csrf_input_field() ?><input type="hidden" name="action" value="delete_fee_head"><input type="hidden" name="fee_head_id" id="deleteFeeHeadId">
    <div class="modal-header bg-danger"><h4 class="modal-title">Confirm Deletion</h4></div>
    <div class="modal-body"><p>Are you sure you want to delete this fee head?</p><p class="text-danger small">Note: This will not affect existing invoices, but it will be removed from future fee structure options.</p></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Confirm Delete</button></div>
</form></div></div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
$(document).ready(function() {
    // Initialize the DataTable
    var table = $('#feeHeadsTable').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
    });

    // Use event delegation for the edit button
    $('#feeHeadsTable tbody').on('click', '.edit-btn', function() {
        $('#editFeeHeadId').val($(this).data('id'));
        $('#editHeadName').val($(this).data('name'));
        $('#editModal').modal('show');
    });

    // Use event delegation for the delete button
    $('#feeHeadsTable tbody').on('click', '.delete-btn', function() {
        $('#deleteFeeHeadId').val($(this).data('id'));
        $('#deleteModal').modal('show');
    });
});
</script>