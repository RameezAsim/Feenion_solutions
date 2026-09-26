<?php
// school-fees-system/admin/students.php (Multi-Branch Enabled & FIXED & BULK IMPORT)

$page_title = 'Student Management';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin']); // Only Admin can manage students
$active_branch_id = get_active_branch_id();

// Allow API call to proceed even if branch is not set
if (!$active_branch_id && !isset($_GET['action'])) { 
    set_flash_message('You must select a branch to manage students.', 'error');
    header('Location: index.php');
    exit;
}

// API endpoint to fetch sections for a class
if (isset($_GET['action']) && $_GET['action'] == 'get_sections' && isset($_GET['class_id'])) {
    if(empty($active_branch_id)) {
        $active_branch_id = get_active_branch_id();
    }
    $class_id = $_GET['class_id'];
    $stmt = $pdo->prepare("SELECT id, section_name FROM sections WHERE class_id = ? AND branch_id = ? ORDER BY section_name");
    $stmt->execute([$class_id, $active_branch_id]);
    $sections = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($sections);
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $action = $_POST['action'] ?? '';
    
    try {
        // --- 1. BULK IMPORT LOGIC (NEW) ---
        if ($action === 'import_students') {
            if (isset($_FILES['student_csv']) && $_FILES['student_csv']['error'] == 0) {
                $file = fopen($_FILES['student_csv']['tmp_name'], 'r');
                $import_count = 0;
                $skip_count = 0;
                $errors = [];
                
                $pdo->beginTransaction();
                
                // Skip header row
                fgetcsv($file); 

                while (($row = fgetcsv($file)) !== false) {
                    // CSV Format: Name, Adm No, Class Name, Section Name, Parent Name, Parent Email, Parent Phone
                    if(count($row) < 7) { $skip_count++; continue; } 

                    $s_name   = trim($row[0]);
                    $adm_no   = trim($row[1]);
                    $c_name   = trim($row[2]);
                    $sec_name = trim($row[3]);
                    $p_name   = trim($row[4]);
                    $p_email  = trim($row[5]);
                    $p_phone  = trim($row[6]);

                    if(empty($s_name) || empty($adm_no) || empty($c_name)) { $skip_count++; continue; }

                    // Find Class ID
                    $stmt_c = $pdo->prepare("SELECT id FROM classes WHERE class_name = ? AND branch_id = ?");
                    $stmt_c->execute([$c_name, $active_branch_id]);
                    $class_id = $stmt_c->fetchColumn();

                    if (!$class_id) { 
                        $errors[] = "Class '$c_name' not found for student $s_name";
                        $skip_count++; continue; 
                    }

                    // Find Section ID
                    $stmt_s = $pdo->prepare("SELECT id FROM sections WHERE section_name = ? AND class_id = ?");
                    $stmt_s->execute([$sec_name, $class_id]);
                    $section_id = $stmt_s->fetchColumn();
                    
                    // If section is required but not found
                    if (!$section_id && !empty($sec_name)) {
                         // Optional: You could create the section here if you wanted.
                         // For now, we skip or default to NULL.
                         // $section_id = NULL; 
                    }

                    // Find or Create Parent
                    $stmt_p = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'Parent'");
                    $stmt_p->execute([$p_email]);
                    $parent_data = $stmt_p->fetch();

                    if ($parent_data) {
                        $parent_id = $parent_data['id'];
                    } else {
                        // Create new parent (Default password: 12345678)
                        $hashed_pass = password_hash('12345678', PASSWORD_BCRYPT);
                        $stmt_new_p = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role, branch_id, manages_all_branches) VALUES (?, ?, ?, ?, 'Parent', NULL, 0)");
                        $stmt_new_p->execute([$p_name, $p_email, $p_phone, $hashed_pass]);
                        $parent_id = $pdo->lastInsertId();
                    }

                    // Insert Student
                    try {
                        $stmt_ins = $pdo->prepare("INSERT INTO students (student_name, admission_no, class_id, section_id, parent_id, branch_id) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt_ins->execute([$s_name, $adm_no, $class_id, $section_id, $parent_id, $active_branch_id]);
                        $import_count++;
                    } catch (Exception $e) {
                        $errors[] = "Error adding $s_name ($adm_no): " . $e->getMessage();
                        $skip_count++;
                    }
                }
                fclose($file);
                $pdo->commit();
                
                $msg = "Import Complete. Added: $import_count, Skipped: $skip_count.";
                if(!empty($errors)) { $msg .= " Errors: " . implode(", ", array_slice($errors, 0, 3)) . "..."; }
                set_flash_message($msg, empty($errors) ? 'success' : 'warning');
                
            } else {
                throw new Exception("Invalid file upload.");
            }

        } elseif ($action === 'add_student') {
            $parent_email = trim($_POST['parent_email']);
            $parent_phone = trim($_POST['parent_phone']);
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'Parent'");
            $stmt->execute([$parent_email]);
            $existing_parent = $stmt->fetch();

            if ($existing_parent) {
                $parent_id = $existing_parent['id'];
                set_flash_message("Student added and linked to existing parent account.");
            } else {
                $parent_password = trim($_POST['parent_password']);
                if (strlen($parent_password) < 8) { throw new Exception("Password must be at least 8 characters long."); }
                $hashed_password = password_hash($parent_password, PASSWORD_BCRYPT);
                
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role, branch_id, manages_all_branches) VALUES (?, ?, ?, ?, 'Parent', NULL, 0)");
                $stmt->execute([trim($_POST['parent_name']), $parent_email, $parent_phone, $hashed_password]);
                $parent_id = $pdo->lastInsertId();
                set_flash_message("New student and parent account created successfully.");
            }

            $stmt = $pdo->prepare("INSERT INTO students (student_name, admission_no, class_id, section_id, parent_id, branch_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([trim($_POST['student_name']), trim($_POST['admission_no']), trim($_POST['class_id']), trim($_POST['section_id']), $parent_id, $active_branch_id]);
            
            $pdo->commit();
            log_activity("Added student " . he($_POST['student_name']) . " to branch ID {$active_branch_id}.");

        } elseif ($action === 'edit_student') {
            $pdo->beginTransaction();
            
            // 1. Update Student Details
            $stmt = $pdo->prepare("UPDATE students SET student_name = ?, admission_no = ?, class_id = ?, section_id = ?, status = ? WHERE id = ? AND branch_id = ?");
            $stmt->execute([
                trim($_POST['student_name_edit']), 
                trim($_POST['admission_no_edit']), 
                trim($_POST['class_id_edit']), 
                trim($_POST['section_id_edit']), 
                trim($_POST['status_edit']), 
                $_POST['student_id'], 
                $active_branch_id
            ]);

            // 2. Update Parent Details
            $parent_id = $_POST['parent_id_edit'];
            $stmt_parent = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ? AND role = 'Parent'");
            $stmt_parent->execute([
                trim($_POST['parent_name_edit']),
                trim($_POST['parent_email_edit']),
                trim($_POST['parent_phone_edit']),
                $parent_id
            ]);

            // 3. (Optional) Update Parent Password if provided
            if (!empty($_POST['parent_password_edit'])) {
                if (strlen($_POST['parent_password_edit']) < 8) {
                    throw new Exception("Password must be at least 8 characters long.");
                }
                $hashed_password = password_hash(trim($_POST['parent_password_edit']), PASSWORD_BCRYPT);
                $stmt_pass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_pass->execute([$hashed_password, $parent_id]);
            }
            
            $pdo->commit();
            set_flash_message("Student and parent details updated successfully.");
        
        } elseif ($action === 'delete_student') {
            $stmt = $pdo->prepare("DELETE FROM students WHERE id = ? AND branch_id = ?");
            $stmt->execute([$_POST['student_id_delete'], $active_branch_id]);
            set_flash_message("Student record deleted successfully.");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_message = ($e->getCode() == 23000) ? "Error: Admission No or Parent Email already exists." : "Error: " . $e->getMessage();
        set_flash_message($error_message, 'error');
    }
    header("Location: students.php");
    exit();
}

// Data Fetching: Filter all queries by the active branch
$classes_stmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE branch_id = ? ORDER BY class_name ASC");
$classes_stmt->execute([$active_branch_id]);
$classes = $classes_stmt->fetchAll();

$filter_class = $_GET['filter_class'] ?? ''; $search_query = $_GET['search'] ?? '';
$sql = "SELECT s.id, s.student_name, s.admission_no, s.status, s.class_id, s.section_id, s.parent_id,
               c.class_name, sec.section_name, 
               u.full_name as parent_name, u.email as parent_email, u.phone as parent_phone
        FROM students s 
        JOIN classes c ON s.class_id = c.id 
        LEFT JOIN sections sec ON s.section_id = sec.id
        JOIN users u ON s.parent_id = u.id 
        WHERE s.branch_id = ?";
$params = [$active_branch_id];

if (!empty($filter_class)) { $sql .= " AND s.class_id = ?"; $params[] = $filter_class; }

// --- UPDATED SEARCH LOGIC ---
if (!empty($search_query)) {
    // Prepare the search term
    $search_term = "%{$search_query}%";
    
    // Base search: Student Name, Admission No, Parent Name, Parent Phone
    $sql .= " AND (s.student_name LIKE ? OR s.admission_no LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?";
    array_push($params, $search_term, $search_term, $search_term, $search_term);

    // Specific search: Parent ID (Only if input is a number)
    if (is_numeric($search_query)) {
        $sql .= " OR s.parent_id = ?";
        $params[] = $search_query;
    }
    
    $sql .= ")"; // Close the OR bracket
}
// ----------------------------

$students_stmt = $pdo->prepare($sql); $students_stmt->execute($params); $students = $students_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1 class="m-0">Student Management</h1></div>
            <div class="col-sm-6 text-right">
                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#importStudentModal"><i class="fas fa-file-csv"></i> Bulk Import</button>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addStudentModal"><i class="fas fa-plus"></i> Add New Student</button>
            </div>
        </div>
    </div>
</div>

<div class="content"><div class="container-fluid"><div class="card">
    <div class="card-header"><h3 class="card-title">All Students (Current Branch)</h3>
        <div class="card-tools"><form method="GET" action="" class="form-inline"><input type="text" name="search" class="form-control form-control-sm mr-2" placeholder="Search..." value="<?= he($search_query) ?>"><select name="filter_class" class="form-control form-control-sm mr-2"><option value="">All Classes</option><?php foreach ($classes as $class): ?><option value="<?= he($class['id']) ?>" <?= ($filter_class == $class['id']) ? 'selected' : '' ?>><?= he($class['class_name']) ?></option><?php endforeach; ?></select><button type="submit" class="btn btn-sm btn-default"><i class="fas fa-search"></i></button></form></div>
    </div>
    <div class="card-body">
        <?php display_flash_messages(); ?>
        <table id="studentsTable" class="table table-bordered table-striped"><thead><tr><th>Student Name</th><th>Adm No</th><th>Class</th><th>Parent</th><th>Parent Contact</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($students as $student): ?>
            <tr>
                <td><?= he($student['student_name']) ?></td>
                <td><?= he($student['admission_no']) ?></td>
                <td><?= he($student['class_name']) ?> - <?= he($student['section_name'] ?? 'N/A') ?></td>
                <td><?= he($student['parent_name']) ?> (<?= he($student['parent_email']) ?>) [ID: <?= $student['parent_id'] ?>]</td>
                <td><?= he($student['parent_phone'] ?? 'N/A') ?></td>
                <td><span class="badge badge-<?= $student['status'] == 'Active' ? 'success' : 'danger' ?>"><?= he($student['status']) ?></span></td>
                <td>
                    <button class="btn btn-sm btn-info edit-btn" 
                        data-id="<?= he($student['id']) ?>" 
                        data-name="<?= he($student['student_name']) ?>" 
                        data-admission="<?= he($student['admission_no']) ?>" 
                        data-classid="<?= he($student['class_id']) ?>" 
                        data-sectionid="<?= he($student['section_id']) ?>" 
                        data-status="<?= he($student['status']) ?>"
                        data-parentid="<?= he($student['parent_id']) ?>"
                        data-parentname="<?= he($student['parent_name']) ?>"
                        data-parentemail="<?= he($student['parent_email']) ?>"
                        data-parentphone="<?= he($student['parent_phone']) ?>">
                        <i class="fas fa-edit"></i>
                    </button> 
                    <button class="btn btn-sm btn-danger delete-btn" data-id="<?= he($student['id']) ?>"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody></table>
    </div>
</div></div></div>

<div class="modal fade" id="importStudentModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_input_field() ?>
                <input type="hidden" name="action" value="import_students">
                <div class="modal-header"><h4 class="modal-title">Import Students via CSV</h4><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <p>Upload a CSV file with columns in this order:</p>
                    <small>Student Name, Admission No, Class Name, Section Name, Parent Name, Email, Phone</small>
                    <div class="form-group mt-2">
                        <label>Select CSV File</label>
                        <input type="file" name="student_csv" class="form-control-file" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addStudentModal"><div class="modal-dialog"><div class="modal-content"><form method="POST">
    <?= csrf_input_field() ?><input type="hidden" name="action" value="add_student">
    <div class="modal-header"><h4 class="modal-title">Add New Student</h4></div>
    <div class="modal-body">
        <h5>Student Details</h5>
        <div class="form-group"><label>Full Name</label><input type="text" name="student_name" class="form-control" required></div>
        <div class="form-group"><label>Admission No</label><input type="text" name="admission_no" class="form-control" required></div>
        <div class="form-group"><label>Class</label><select name="class_id" id="classSelectorAdd" class="form-control" required><option value="">Select Class</option><?php foreach ($classes as $class): ?><option value="<?= he($class['id']) ?>"><?= he($class['class_name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Section</label><select name="section_id" id="sectionSelectorAdd" class="form-control" required><option value="">-- Select Class First --</option></select></div><hr>
        <h5>Parent Account</h5>
        <p class="text-muted small">If email exists, student will be linked to it. Otherwise, a new parent account will be created.</p>
        <div class="form-group"><label>Parent Full Name</label><input type="text" name="parent_name" class="form-control" required></div>
        <div class="form-group"><label>Parent Email</label><input type="email" name="parent_email" class="form-control" required></div>
        <div class="form-group"><label>Parent Phone / WhatsApp</label><input type="text" name="parent_phone" class="form-control" placeholder="e.g., 03001234567" required></div>
        <div class="form-group"><label>Parent Password</label><input type="password" name="parent_password" class="form-control" required minlength="8" placeholder="Required for new parents"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Add Student</button></div>
</form></div></div></div>

<div class="modal fade" id="editStudentModal"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST">
    <?= csrf_input_field() ?>
    <input type="hidden" name="action" value="edit_student">
    <input type="hidden" name="student_id" id="editStudentId">
    <input type="hidden" name="parent_id_edit" id="editParentId">
    
    <div class="modal-header"><h4 class="modal-title">Edit Student & Parent Info</h4></div>
    <div class="modal-body">
        <div class="row">
            <div class="col-md-6">
                <h5>Student Details</h5>
                <div class="form-group"><label>Full Name</label><input type="text" id="student_name_edit" name="student_name_edit" class="form-control" required></div>
                <div class="form-group"><label>Admission No</label><input type="text" id="admission_no_edit" name="admission_no_edit" class="form-control" required></div>
                <div class="form-group"><label>Class</label><select id="class_id_edit" name="class_id_edit" class="form-control" required><?php foreach ($classes as $class): ?><option value="<?= he($class['id']) ?>"><?= he($class['class_name']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Section</label><select id="section_id_edit" name="section_id_edit" class="form-control" required><option value="">-- Select Class First --</option></select></div>
                <div class="form-group"><label>Status</label><select id="status_edit" name="status_edit" class="form-control" required><option value="Active">Active</option><option value="Promoted">Promoted</option><option value="Left">Left</option></select></div>
            </div>
            <div class="col-md-6">
                <h5>Parent Details</h5>
                <div class="form-group"><label>Parent Full Name</label><input type="text" id="parent_name_edit" name="parent_name_edit" class="form-control" required></div>
                <div class="form-group"><label>Parent Email</label><input type="email" id="parent_email_edit" name="parent_email_edit" class="form-control" required></div>
                <div class="form-group"><label>Parent Phone / WhatsApp</label><input type="text" id="parent_phone_edit" name="parent_phone_edit" class="form-control" required></div>
                <hr>
                <div class="form-group"><label>Change Parent Password</label><input type="text" name="parent_password_edit" class="form-control" placeholder="Leave blank to keep current password"></div>
            </div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
</form></div></div></div>

<div class="modal fade" id="deleteStudentModal"><div class="modal-dialog"><div class="modal-content"><form method="POST">
    <?= csrf_input_field() ?><input type="hidden" name="action" value="delete_student"><input type="hidden" name="student_id_delete" id="deleteStudentId"><div class="modal-header"><h4 class="modal-title">Confirm Deletion</h4></div><div class="modal-body"><p>Are you sure you want to delete this student?</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Delete</button></div>
</form></div></div></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    
    // --- NUCLEAR OPTION FIX ---
    if ($.fn.DataTable.isDataTable('#studentsTable')) {
        $('#studentsTable').DataTable().destroy();
    }
    
    $('#studentsTable').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": false,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
    });

    // --- Edit button logic ---
    $('#studentsTable tbody').on('click', '.edit-btn', function() {
        var studentId = $(this).data('id');
        var studentName = $(this).data('name');
        var admissionNo = $(this).data('admission');
        var classId = $(this).data('classid');
        var sectionId = $(this).data('sectionid'); 
        var status = $(this).data('status');
        var parentId = $(this).data('parentid');
        var parentName = $(this).data('parentname');
        var parentEmail = $(this).data('parentemail');
        var parentPhone = $(this).data('parentphone');

        $('#editStudentId').val(studentId);
        $('#student_name_edit').val(studentName);
        $('#admission_no_edit').val(admissionNo);
        $('#class_id_edit').val(classId);
        $('#status_edit').val(status);
        
        $('#editParentId').val(parentId);
        $('#parent_name_edit').val(parentName);
        $('#parent_email_edit').val(parentEmail);
        $('#parent_phone_edit').val(parentPhone);
        $('input[name="parent_password_edit"]').val(''); 
        
        $('#class_id_edit').trigger('change', [sectionId]); 
        $('#editStudentModal').modal('show');
    });

    // --- Delete button logic ---
    $('#studentsTable tbody').on('click', '.delete-btn', function() {
        $('#deleteStudentId').val($(this).data('id'));
        $('#deleteStudentModal').modal('show');
    });

    // --- Dynamic Section loader for ADD modal ---
    $('#classSelectorAdd').on('change', function() {
        var classId = $(this).val();
        var sectionSelector = $('#sectionSelectorAdd');
        loadSections(classId, sectionSelector);
    });

    // --- Dynamic Section loader for EDIT modal ---
    $('#class_id_edit').on('change', function(e, sectionToSelect) {
        var classId = $(this).val();
        var sectionSelector = $('#section_id_edit');
        loadSections(classId, sectionSelector, sectionToSelect);
    });

    // --- Reusable function to load sections ---
    function loadSections(classId, sectionSelector, sectionToSelect = null) {
        sectionSelector.empty().append('<option value="">Loading...</option>');
        if (classId) {
            $.ajax({
                url: 'students.php?action=get_sections&class_id=' + classId,
                type: 'GET',
                dataType: 'json',
                success: function(sections) {
                    sectionSelector.empty().append('<option value="">Select Section</option>');
                    if (sections.length > 0) {
                        sections.forEach(function(section) {
                            sectionSelector.append(new Option(section.section_name, section.id));
                        });
                        if (sectionToSelect) {
                            sectionSelector.val(sectionToSelect);
                        }
                    } else {
                        sectionSelector.empty().append('<option value="">No Sections Found</option>');
                    }
                },
                error: function(xhr, status, error) {
                    sectionSelector.empty().append('<option value="">Error loading</option>');
                }
            });
        } else {
            sectionSelector.empty().append('<option value="">-- Select Class First --</option>');
        }
    }
});
</script>