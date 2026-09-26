<?php
// school-fees-system/admin/classes.php (Multi-Branch Enabled)

$page_title = 'Classes & Sections';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']);
$active_branch_id = get_active_branch_id(); // Get the currently selected branch

if (!$active_branch_id) {
    set_flash_message('You must select a branch to manage.', 'error');
    header('Location: ../admin/index.php');
    exit;
}

// Handle all form submissions for CRUD actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    validate_csrf_token();
    try {
        switch ($_POST['action']) {
            case 'add_class':
                $stmt = $pdo->prepare("INSERT INTO classes (class_name, branch_id) VALUES (?, ?)");
                $stmt->execute([trim($_POST['class_name']), $active_branch_id]);
                set_flash_message('Class added successfully!');
                break;
            case 'delete_class':
                // Ensure the class belongs to the active branch before deleting
                $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ? AND branch_id = ?");
                $stmt->execute([$_POST['class_id'], $active_branch_id]);
                set_flash_message('Class and all its sections have been deleted.');
                break;
            case 'add_section':
                $stmt = $pdo->prepare("INSERT INTO sections (section_name, class_id, branch_id) VALUES (?, ?, ?)");
                $stmt->execute([trim($_POST['section_name']), $_POST['class_id'], $active_branch_id]);
                set_flash_message('Section added successfully!');
                break;
            case 'delete_section':
                // Ensure the section belongs to the active branch
                $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ? AND branch_id = ?");
                $stmt->execute([$_POST['section_id'], $active_branch_id]);
                set_flash_message('Section deleted successfully.');
                break;
        }
    } catch (Exception $e) {
        set_flash_message('An error occurred: ' . $e->getMessage(), 'error');
    }
    header("Location: classes.php");
    exit();
}

// Fetch all classes and their sections FOR THE CURRENT BRANCH
$classes_with_sections = [];
$classes_raw = $pdo->prepare("SELECT * FROM classes WHERE branch_id = ? ORDER BY class_name ASC");
$classes_raw->execute([$active_branch_id]);
$classes = $classes_raw->fetchAll();

$sections_stmt = $pdo->prepare("SELECT * FROM sections WHERE branch_id = ? ORDER BY section_name ASC");
$sections_stmt->execute([$active_branch_id]);
$sections_raw = $sections_stmt->fetchAll();

foreach ($classes as $class) {
    $classes_with_sections[$class['id']] = $class;
    $classes_with_sections[$class['id']]['sections'] = [];
}
foreach ($sections_raw as $section) {
    if (isset($classes_with_sections[$section['class_id']])) {
        $classes_with_sections[$section['class_id']]['sections'][] = $section;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">Manage Classes & Sections</h1></div></div></div>
</div>

<div class="content">
    <div class="container-fluid">
        <?php display_flash_messages(); ?>
        <div class="row">
            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">All Classes (Current Branch)</h3></div>
                    <div class="card-body">
                        <form method="POST" class="form-inline mb-3">
                            <?= csrf_input_field() ?>
                            <input type="hidden" name="action" value="add_class">
                            <div class="form-group"><input type="text" name="class_name" class="form-control" placeholder="New Class Name" required></div>
                            <button type="submit" class="btn btn-primary ml-2">Add Class</button>
                        </form>
                        <table class="table table-bordered">
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td><?= he($class['class_name']) ?></td>
                                    <td class="text-right">
                                        <form method="POST" onsubmit="return confirm('Are you sure? Deleting a class will also delete all its sections.');" style="display:inline;">
                                            <?= csrf_input_field() ?>
                                            <input type="hidden" name="action" value="delete_class"><input type="hidden" name="class_id" value="<?= he($class['id']) ?>">
                                            <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-info">
                    <div class="card-header"><h3 class="card-title">Sections (Current Branch)</h3></div>
                    <div class="card-body">
                        <form method="POST">
                            <?= csrf_input_field() ?>
                            <input type="hidden" name="action" value="add_section">
                            <div class="row">
                                <div class="col-sm-5"><select name="class_id" class="form-control" required><option value="">Select Class</option><?php foreach ($classes as $class): ?><option value="<?= he($class['id']) ?>"><?= he($class['class_name']) ?></option><?php endforeach; ?></select></div>
                                <div class="col-sm-5"><input type="text" name="section_name" class="form-control" placeholder="New Section Name" required></div>
                                <div class="col-sm-2"><button type="submit" class="btn btn-info">Add</button></div>
                            </div>
                        </form>
                        <hr>
                        <?php foreach ($classes_with_sections as $class): ?>
                            <h5><?= he($class['class_name']) ?></h5>
                            <table class="table table-sm table-hover"><tbody>
                                <?php foreach ($class['sections'] as $section): ?>
                                <tr>
                                    <td>- <?= he($section['section_name']) ?></td>
                                    <td class="text-right">
                                        <form method="POST" onsubmit="return confirm('Are you sure?');" style="display:inline;">
                                            <?= csrf_input_field() ?>
                                            <input type="hidden" name="action" value="delete_section"><input type="hidden" name="section_id" value="<?= he($section['id']) ?>">
                                            <button type="submit" class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody></table>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>