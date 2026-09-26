<?php
// school-fees-system/admin/student_fees.php (Multi-Branch + Analysis Dashboard)

$page_title = 'Student Fee Structures';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin', 'Accountant']);
$active_branch_id = get_active_branch_id();

if (!$active_branch_id) {
    set_flash_message('You must select a branch to manage.', 'error');
    header('Location: ../admin/index.php');
    exit;
}

// --- 1. FEE STRUCTURE ANALYSIS LOGIC ---
// Fetch all active students
$stmt_all = $pdo->prepare("SELECT s.id, s.student_name, s.admission_no, s.class_id, c.class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.status = 'Active' AND s.branch_id = ? ORDER BY c.class_name, s.student_name");
$stmt_all->execute([$active_branch_id]);
$all_students_list = $stmt_all->fetchAll();

// Fetch Classes with fees
$stmt_classes_with_fees = $pdo->prepare("SELECT DISTINCT class_id FROM class_fees WHERE branch_id = ?");
$stmt_classes_with_fees->execute([$active_branch_id]);
$classes_with_fees = $stmt_classes_with_fees->fetchAll(PDO::FETCH_COLUMN);

// Fetch Students with custom fees
$stmt_students_with_custom = $pdo->prepare("SELECT DISTINCT student_id FROM student_fee_structure WHERE branch_id = ?");
$stmt_students_with_custom->execute([$active_branch_id]);
$students_with_custom = $stmt_students_with_custom->fetchAll(PDO::FETCH_COLUMN);

$students_missing_structure = [];
$students_ready = [];

foreach ($all_students_list as $std) {
    if (in_array($std['class_id'], $classes_with_fees) || in_array($std['id'], $students_with_custom)) {
        $students_ready[] = $std;
    } else {
        $students_missing_structure[] = $std;
    }
}
$count_ready = count($students_ready);
$count_missing = count($students_missing_structure);


// --- 2. HANDLE FORM SUBMISSION (Save Custom Fees) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_student_fees') {
    validate_csrf_token();
    $student_id = $_POST['student_id'];
    $fee_amounts = $_POST['fee_amounts'];

    try {
        $stmt_check = $pdo->prepare("SELECT id FROM students WHERE id = ? AND branch_id = ?");
        $stmt_check->execute([$student_id, $active_branch_id]);
        if (!$stmt_check->fetch()) { throw new Exception("Invalid student selected for this branch."); }

        $pdo->beginTransaction();
        $delete_stmt = $pdo->prepare("DELETE FROM student_fee_structure WHERE student_id = ? AND branch_id = ?");
        $delete_stmt->execute([$student_id, $active_branch_id]);

        $insert_stmt = $pdo->prepare("INSERT INTO student_fee_structure (student_id, fee_head_id, amount, branch_id) VALUES (?, ?, ?, ?)");
        foreach ($fee_amounts as $fee_head_id => $amount) {
            if (!empty($amount) && is_numeric($amount)) {
                $insert_stmt->execute([$student_id, $fee_head_id, (float)$amount, $active_branch_id]);
            }
        }
        $pdo->commit();
        set_flash_message('Custom fee structure saved successfully!');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash_message('Error saving fee structure: ' . $e->getMessage(), 'error');
    }
    header("Location: student_fees.php?student_id=" . $student_id);
    exit();
}

// Fetch basic data for the form
$fee_heads_stmt = $pdo->prepare("SELECT id, head_name FROM fee_heads WHERE branch_id = ? ORDER BY head_name ASC");
$fee_heads_stmt->execute([$active_branch_id]);
$fee_heads = $fee_heads_stmt->fetchAll();

$selected_student_id = $_GET['student_id'] ?? null;
$current_structure = [];
if ($selected_student_id) {
    $stmt = $pdo->prepare("SELECT fee_head_id, amount FROM student_fee_structure WHERE student_id = ? AND branch_id = ?");
    $stmt->execute([$selected_student_id, $active_branch_id]);
    $current_structure = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">Student Fee Management</h1></div></div></div></div>

<div class="content">
    <div class="container-fluid">
        <?php display_flash_messages(); ?>

        <div class="row mb-4">
            <div class="col-md-5">
                <div class="card card-outline card-warning h-100">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-pie mr-1"></i> Fee Coverage Analysis</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6"><canvas id="feeStructureChart" style="height:150px;"></canvas></div>
                            <div class="col-6 d-flex flex-column justify-content-center">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="fas fa-circle text-success"></i> <strong><?= $count_ready ?></strong> Ready</li>
                                    <li><i class="fas fa-circle text-danger"></i> <strong><?= $count_missing ?></strong> Missing Info</li>
                                </ul>
                                <?php if($count_missing > 0): ?>
                                    <small class="text-danger mt-2"><strong>Action Required:</strong> <?= $count_missing ?> students will be skipped during monthly billing.</small>
                                <?php else: ?>
                                    <small class="text-success mt-2"><strong>All Good!</strong> Everyone is ready for billing.</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card card-primary card-tabs h-100">
                    <div class="card-header p-0 pt-1">
                        <ul class="nav nav-tabs" id="custom-tabs-one-tab" role="tablist">
                            <li class="nav-item"><a class="nav-link active" id="tabs-missing-tab" data-toggle="pill" href="#tabs-missing" role="tab">🔴 Missing Fees (<?= $count_missing ?>)</a></li>
                            <li class="nav-item"><a class="nav-link" id="tabs-ready-tab" data-toggle="pill" href="#tabs-ready" role="tab">🟢 Ready (<?= $count_ready ?>)</a></li>
                        </ul>
                    </div>
                    <div class="card-body p-0">
                        <div class="tab-content" id="custom-tabs-one-tabContent">
                            <div class="tab-pane fade show active" id="tabs-missing" role="tabpanel">
                                <div style="height: 200px; overflow-y: auto;" class="table-responsive">
                                    <table class="table table-sm table-head-fixed text-nowrap">
                                        <thead><tr><th>Name</th><th>Class</th><th>Adm No</th><th>Action</th></tr></thead>
                                        <tbody>
                                            <?php foreach($students_missing_structure as $s): ?>
                                            <tr>
                                                <td><?= he($s['student_name']) ?></td>
                                                <td><?= he($s['class_name']) ?></td>
                                                <td><?= he($s['admission_no']) ?></td>
                                                <td><a href="?student_id=<?= $s['id'] ?>" class="btn btn-xs btn-primary">Set Fee</a></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tabs-ready" role="tabpanel">
                                <div style="height: 200px; overflow-y: auto;" class="table-responsive">
                                    <table class="table table-sm table-head-fixed text-nowrap">
                                        <thead><tr><th>Name</th><th>Class</th><th>Adm No</th><th>Action</th></tr></thead>
                                        <tbody>
                                            <?php foreach($students_ready as $s): ?>
                                            <tr>
                                                <td><?= he($s['student_name']) ?></td>
                                                <td><?= he($s['class_name']) ?></td>
                                                <td><?= he($s['admission_no']) ?></td>
                                                <td><a href="?student_id=<?= $s['id'] ?>" class="btn btn-xs btn-info">Edit</a></td>
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
        </div>
        <div class="card card-success">
            <div class="card-header"><h3 class="card-title">Set / Edit Custom Fee</h3></div>
            <div class="card-body">
                <form method="GET" class="form-group">
                    <label>Select Student</label>
                    <select id="student_select" name="student_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Search student --</option>
                        <?php foreach ($all_students_list as $student): ?>
                        <option value="<?= he($student['id']) ?>" <?= ($selected_student_id == $student['id']) ? 'selected' : '' ?>>
                            <?= he($student['student_name']) ?> (<?= he($student['admission_no']) ?>) - <?= he($student['class_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <hr>
                <?php if ($selected_student_id): ?>
                <form method="POST">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="action" value="save_student_fees">
                    <input type="hidden" name="student_id" value="<?= he($selected_student_id) ?>">
                    
                    <p class="text-info"><i class="fas fa-info-circle"></i> Enter an amount to <strong>override</strong> the default class fee. Leave blank to use the class default.</p>
                    
                    <?php if (empty($fee_heads)): ?>
                        <div class="alert alert-warning">No fee heads found. Please <a href="fee_heads.php">add fee heads</a> first.</div>
                    <?php else: ?>
                        <div class="row">
                        <?php foreach ($fee_heads as $head): ?>
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label"><?= he($head['head_name']) ?></label>
                                <div class="col-sm-8">
                                    <input type="number" name="fee_amounts[<?= he($head['id']) ?>]" class="form-control" placeholder="Default" value="<?= he($current_structure[$head['id']] ?? '') ?>" step="0.01">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-success mt-3"><i class="fas fa-save"></i> Save Custom Fee Structure</button>
                    <?php endif; ?>
                </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script>
$(document).ready(function() {
    $('#student_select').select2({ placeholder: '-- Search and select a student --' });

    // Chart Logic
    var donutChartCanvas = $('#feeStructureChart').get(0).getContext('2d');
    var donutData = {
        labels: ['Ready', 'Missing'],
        datasets: [{
            data: [<?= $count_ready ?>, <?= $count_missing ?>],
            backgroundColor : ['#28a745', '#dc3545'],
        }]
    };
    var donutOptions = {
        maintainAspectRatio : false,
        responsive : true,
        legend: { display: false }
    };
    new Chart(donutChartCanvas, { type: 'doughnut', data: donutData, options: donutOptions });
});
</script>