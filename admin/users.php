<?php
// school-fees-system/admin/users.php (Multi-Branch Enabled - FULL CRUD)

$page_title = 'User Management';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Only Super Admins can manage users
check_session(['Admin']);
if (!$_SESSION['manages_all_branches']) {
    set_flash_message('You do not have permission to manage users.', 'error');
    header('Location: index.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_csrf_token();
    try {
        switch ($_POST['action']) {
            case 'add_user':
                $full_name = trim($_POST['full_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $password = trim($_POST['password']);
                $role = trim($_POST['role']);
                
                $branch_id = $_POST['branch_id'] ?? null;
                $manages_all = (isset($_POST['manages_all_branches']) && $_POST['manages_all_branches'] == '1');
                
                if ($role === 'Parent') { throw new Exception("Parent accounts must be created from the 'Students' page."); }
                if (strlen($password) < 8) { throw new Exception("Password must be at least 8 characters long."); }
                if ($manages_all) { $branch_id = null; }
                elseif (empty($branch_id)) { throw new Exception("A branch must be selected for this user role."); }

                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role, status, branch_id, manages_all_branches) VALUES (?, ?, ?, ?, ?, 'Active', ?, ?)");
                $stmt->execute([$full_name, $email, $phone, $hashed_password, $role, $branch_id, $manages_all]);
                
                set_flash_message('User created successfully!');
                break;

            case 'edit_user':
                $user_id = $_POST['user_id'];
                $full_name = trim($_POST['full_name_edit']);
                $email = trim($_POST['email_edit']);
                $phone = trim($_POST['phone_edit']);
                $role = trim($_POST['role_edit']);
                $status = trim($_POST['status_edit']);
                $branch_id = $_POST['branch_id_edit'] ?? null;
                $manages_all = (isset($_POST['manages_all_branches_edit']) && $_POST['manages_all_branches_edit'] == '1');
                
                if ($role === 'Parent') { throw new Exception("Parent accounts cannot be edited from this page."); }
                if ($manages_all) { $branch_id = null; }
                elseif (empty($branch_id)) { throw new Exception("A branch must be selected for this user role."); }

                // Check if password needs to be updated
                if (!empty($_POST['password_edit'])) {
                    if (strlen($_POST['password_edit']) < 8) { throw new Exception("Password must be at least 8 characters long."); }
                    $hashed_password = password_hash($_POST['password_edit'], PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, password = ?, role = ?, status = ?, branch_id = ?, manages_all_branches = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $phone, $hashed_password, $role, $status, $branch_id, $manages_all, $user_id]);
                } else {
                    // Update without changing password
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, status = ?, branch_id = ?, manages_all_branches = ? WHERE id = ?");
                    $stmt->execute([$full_name, $email, $phone, $role, $status, $branch_id, $manages_all, $user_id]);
                }
                set_flash_message('User updated successfully!');
                break;
            
            case 'delete_user':
                $user_id = $_POST['user_id_delete'];
                if ($user_id == 1 || $user_id == $_SESSION['user_id']) {
                    throw new Exception("You cannot delete the primary admin account or your own account.");
                }
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'Parent'");
                $stmt->execute([$user_id]);
                set_flash_message('User deleted successfully.');
                break;
        }
        
    } catch (Exception $e) {
        $error_message = ($e->getCode() == 23000) ? "Error: An account with this email already exists." : "Error: " . $e->getMessage();
        set_flash_message($error_message, 'error');
    }
    header("Location: users.php");
    exit();
}

// Fetch all branches for the dropdown
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name ASC")->fetchAll();

// Fetch all non-parent users
$users = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.phone, u.role, u.status, u.manages_all_branches, u.branch_id, b.name as branch_name
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.role IN ('Admin', 'Accountant')
    ORDER BY u.full_name
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">User Management</h1></div><div class="col-sm-6"><button type="button" class="btn btn-primary float-sm-right" data-toggle="modal" data-target="#addUserModal"><i class="fas fa-plus"></i> Add New User</button></div></div></div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">All Admin & Accountant Users</h3></div>
            <div class="card-body">
                <?php display_flash_messages(); ?>
                <table class="table table-bordered table-striped" id="usersTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Branch Access</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td><?= he($user['full_name']) ?></td>
                            <td><?= he($user['email']) ?></td>
                            <td><?= he($user['phone']) ?></td>
                            <td><?= he($user['role']) ?></td>
                            <td>
                                <?php if ($user['manages_all_branches']): ?>
                                    <span class="badge badge-success">All Branches (Super Admin)</span>
                                <?php else: ?>
                                    <span class="badge badge-info"><?= he($user['branch_name'] ?? 'N/A') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $user['status'] == 'Active' ? 'success' : 'danger' ?>">
                                    <?= he($user['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info edit-btn" 
                                    data-id="<?= he($user['id']) ?>" 
                                    data-full_name="<?= he($user['full_name']) ?>"
                                    data-email="<?= he($user['email']) ?>"
                                    data-phone="<?= he($user['phone']) ?>"
                                    data-role="<?= he($user['role']) ?>"
                                    data-status="<?= he($user['status']) ?>"
                                    data-branch_id="<?= he($user['branch_id']) ?>"
                                    data-manages_all_branches="<?= he($user['manages_all_branches']) ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($user['id'] != 1 && $user['id'] != $_SESSION['user_id']): // Can't delete self or user 1 ?>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="<?= he($user['id']) ?>"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST">
    <?= csrf_input_field() ?>
    <input type="hidden" name="action" value="add_user">
    <div class="modal-header"><h4 class="modal-title">Add New User</h4></div>
    <div class="modal-body">
        <div class="row">
            <div class="col-md-6 form-group"><label>Full Name</label><input type="text" name="full_name" class="form-control" required></div>
            <div class="col-md-6 form-group"><label>Role</label>
                <select name="role" id="roleSelectorAdd" class="form-control" required>
                    <option value="">Select Role</option>
                    <option value="Admin">Admin</option>
                    <option value="Accountant">Accountant</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
            <div class="col-md-6 form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
        </div>
        <div class="form-group"><label>Password (min. 8 characters)</label><input type="password" name="password" class="form-control" required minlength="8"></div>
        <hr>
        <div id="branch-access-section-add">
            <div class="form-group" id="branch-select-group-add">
                <label>Assign to Branch</label>
                <select name="branch_id" class="form-control">
                    <option value="">-- Select a Branch --</option>
                    <?php foreach($branches as $branch): ?>
                    <option value="<?= he($branch['id']) ?>"><?= he($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-check" id="super-admin-group-add" style="display: none;">
                <input type="checkbox" class="form-check-input" id="manages_all_branches" name="manages_all_branches" value="1">
                <label class="form-check-label" for="manages_all_branches">Super Admin (Can manage ALL branches and settings)</label>
            </div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Add User</button></div>
</form></div></div></div>

<div class="modal fade" id="editModal"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST">
    <?= csrf_input_field() ?><input type="hidden" name="action" value="edit_user"><input type="hidden" name="user_id" id="editUserId">
    <div class="modal-header"><h4 class="modal-title">Edit User</h4></div>
    <div class="modal-body">
        <div class="row">
            <div class="col-md-6 form-group"><label>Full Name</label><input type="text" name="full_name_edit" id="editFullName" class="form-control" required></div>
            <div class="col-md-6 form-group"><label>Role</label>
                <select name="role_edit" id="roleSelectorEdit" class="form-control" required>
                    <option value="Admin">Admin</option>
                    <option value="Accountant">Accountant</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 form-group"><label>Email</label><input type="email" name="email_edit" id="editEmail" class="form-control" required></div>
            <div class="col-md-6 form-group"><label>Phone</label><input type="text" name="phone_edit" id="editPhone" class="form-control"></div>
        </div>
        <div class="form-group"><label>New Password (Leave blank to keep current password)</label><input type="password" name="password_edit" class="form-control" minlength="8"></div>
        <div class="form-group"><label>Status</label>
            <select name="status_edit" id="editStatus" class="form-control" required>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
        </div>
        <hr>
        <div id="branch-access-section-edit">
            <div class="form-group" id="branch-select-group-edit">
                <label>Assign to Branch</label>
                <select name="branch_id_edit" id="editBranchId" class="form-control">
                    <option value="">-- Select a Branch --</option>
                    <?php foreach($branches as $branch): ?>
                    <option value="<?= he($branch['id']) ?>"><?= he($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-check" id="super-admin-group-edit" style="display: none;">
                <input type="checkbox" class="form-check-input" id="editManagesAllBranches" name="manages_all_branches_edit" value="1">
                <label class="form-check-label" for="editManagesAllBranches">Super Admin (Can manage ALL branches and settings)</label>
            </div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
</form></div></div></div>

<div class="modal fade" id="deleteModal"><div class="modal-dialog"><div class="modal-content"><form method="POST">
    <?= csrf_input_field() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id_delete" id="deleteUserId">
    <div class="modal-header bg-danger"><h4 class="modal-title">Confirm Deletion</h4></div>
    <div class="modal-body"><p>Are you sure you want to delete this user? This cannot be undone.</p></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Confirm Delete</button></div>
</form></div></div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
$(document).ready(function() {
    $('#usersTable').DataTable();
    
    // --- Logic for ADD Modal ---
    $('#roleSelectorAdd').on('change', function() {
        if ($(this).val() === 'Admin') {
            $('#super-admin-group-add').show();
        } else {
            $('#super-admin-group-add').hide();
            $('#manages_all_branches').prop('checked', false);
        }
        if ($('#manages_all_branches').is(':checked')) {
            $('#branch-select-group-add').hide();
        } else {
            $('#branch-select-group-add').show();
        }
    });
    $('#manages_all_branches').on('change', function() {
        if ($(this).is(':checked')) {
            $('#branch-select-group-add').hide();
            $('#branch-select-group-add select').val('');
        } else {
            $('#branch-select-group-add').show();
        }
    });

    // --- Logic for EDIT Modal ---
    function toggleEditBranchSection(role, managesAll) {
        if (role === 'Admin') {
            $('#super-admin-group-edit').show();
        } else {
            $('#super-admin-group-edit').hide();
        }
        if (managesAll) {
            $('#branch-select-group-edit').hide();
        } else {
            $('#branch-select-group-edit').show();
        }
    }

    $('#roleSelectorEdit').on('change', function() {
        var managesAll = $('#editManagesAllBranches').is(':checked');
        toggleEditBranchSection($(this).val(), managesAll);
    });
    $('#editManagesAllBranches').on('change', function() {
        var role = $('#roleSelectorEdit').val();
        toggleEditBranchSection(role, $(this).is(':checked'));
    });
    
    $('#usersTable tbody').on('click', '.edit-btn', function() {
        var data = $(this).data();
        $('#editUserId').val(data.id);
        $('#editFullName').val(data.full_name);
        $('#editEmail').val(data.email);
        $('#editPhone').val(data.phone);
        $('#editRole').val(data.role);
        $('#editStatus').val(data.status);
        $('#editBranchId').val(data.branch_id);
        $('#editManagesAllBranches').prop('checked', data.manages_all_branches == 1);
        
        // Trigger the logic functions
        toggleEditBranchSection(data.role, data.manages_all_branches == 1);
        
        $('#editModal').modal('show');
    });

    $('#usersTable tbody').on('click', '.delete-btn', function() {
        $('#deleteUserId').val($(this).data('id'));
        $('#deleteModal').modal('show');
    });
});
</script>