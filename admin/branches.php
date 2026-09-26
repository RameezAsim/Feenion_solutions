<?php
// school-fees-system/admin/branches.php

$page_title = 'Branch Management';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// This page is for SUPER ADMINS ONLY.
check_session(['Admin']);
if (!$_SESSION['manages_all_branches']) {
    set_flash_message('You do not have permission to manage branches.', 'error');
    header('Location: index.php');
    exit;
}

// Handle all form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_csrf_token();
    try {
        switch ($_POST['action']) {
            case 'add_branch':
                $stmt = $pdo->prepare("INSERT INTO branches (name, address) VALUES (?, ?)");
                $stmt->execute([trim($_POST['branch_name']), trim($_POST['branch_address'])]);
                set_flash_message('New branch created successfully!');
                break;
            case 'edit_branch':
                $stmt = $pdo->prepare("UPDATE branches SET name = ?, address = ? WHERE id = ?");
                $stmt->execute([trim($_POST['branch_name_edit']), trim($_POST['branch_address_edit']), $_POST['branch_id']]);
                set_flash_message('Branch details updated successfully!');
                break;
            case 'delete_branch':
                // This is a very destructive action due to database constraints (ON DELETE CASCADE)
                // Deleting a branch will delete all classes, sections, students, invoices, and payments in it.
                $stmt = $pdo->prepare("DELETE FROM branches WHERE id = ?");
                $stmt->execute([$_POST['branch_id']]);
                set_flash_message('Branch and all associated data have been permanently deleted.', 'warning');
                break;
        }
    } catch (Exception $e) {
        set_flash_message('An error occurred: ' . $e->getMessage(), 'error');
    }
    
    // Refresh the accessible branches in the session
    $_SESSION['accessible_branches'] = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
    
    header("Location: branches.php");
    exit();
}

// Fetch all branches for display
$branches = $pdo->query("SELECT * FROM branches ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">Branch Management</h1></div></div></div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-5">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Add New Branch</h3></div>
                    <form method="POST">
                        <?= csrf_input_field() ?>
                        <input type="hidden" name="action" value="add_branch">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="branch_name">Branch Name</label>
                                <input type="text" id="branch_name" name="branch_name" class="form-control" placeholder="e.g., North Campus" required>
                            </div>
                            <div class="form-group">
                                <label for="branch_address">Branch Address</label>
                                <textarea id="branch_address" name="branch_address" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Create Branch</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">All Branches</h3></div>
                    <div class="card-body">
                        <?php display_flash_messages(); ?>
                        <table class="table table-bordered table-hover">
                            <thead><tr><th>Branch Name</th><th>Address</th><th style="width: 100px;">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($branches as $branch): ?>
                                <tr>
                                    <td><?= he($branch['name']) ?></td>
                                    <td><?= he($branch['address']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-btn" data-id="<?= he($branch['id']) ?>" data-name="<?= he($branch['name']) ?>" data-address="<?= he($branch['address']) ?>"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm btn-danger delete-btn" data-id="<?= he($branch['id']) ?>"><i class="fas fa-trash"></i></button>
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
    <?= csrf_input_field() ?><input type="hidden" name="action" value="edit_branch"><input type="hidden" name="branch_id" id="editBranchId">
    <div class="modal-header"><h4 class="modal-title">Edit Branch</h4></div>
    <div class="modal-body">
        <div class="form-group"><label>Branch Name</label><input type="text" name="branch_name_edit" id="editBranchName" class="form-control" required></div>
        <div class="form-group"><label>Branch Address</label><textarea name="branch_address_edit" id="editBranchAddress" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
</form></div></div></div>

<div class="modal fade" id="deleteModal"><div class="modal-dialog"><div class="modal-content"><form method="POST">
    <?= csrf_input_field() ?><input type="hidden" name="action" value="delete_branch"><input type="hidden" name="branch_id" id="deleteBranchId">
    <div class="modal-header bg-danger"><h4 class="modal-title">Confirm Deletion</h4></div>
    <div class="modal-body">
        <p><strong>Are you absolutely sure?</strong></p>
        <p class="text-danger">Deleting a branch is PERMANENT and will delete ALL students, classes, invoices, and payments associated with it. This cannot be undone.</p>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Confirm Permanent Deletion</button></div>
</form></div></div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
$(document).ready(function() {
    $('.edit-btn').on('click', function() {
        $('#editBranchId').val($(this).data('id'));
        $('#editBranchName').val($(this).data('name'));
        $('#editBranchAddress').val($(this).data('address'));
        $('#editModal').modal('show');
    });
    $('.delete-btn').on('click', function() {
        $('#deleteBranchId').val($(this).data('id'));
        $('#deleteModal').modal('show');
    });
});
</script>