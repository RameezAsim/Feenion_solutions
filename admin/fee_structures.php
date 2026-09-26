<?php
// school-fees-system/admin/fee_structures.php (Multi-Branch Enabled)

$page_title = 'Fee Structures';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

check_session(['Admin']);
$active_branch_id = get_active_branch_id();

if (!$active_branch_id) {
    set_flash_message('You must select a branch to manage.', 'error');
    header('Location: ../admin/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $class_id = $_POST['class_id'];
    $fee_amounts = $_POST['fee_amounts'];

    try {
        $pdo->beginTransaction();
        // Delete old structure for this class AND branch
        $delete_stmt = $pdo->prepare("DELETE FROM class_fees WHERE class_id = ? AND branch_id = ?");
        $delete_stmt->execute([$class_id, $active_branch_id]);

        $insert_stmt = $pdo->prepare("INSERT INTO class_fees (class_id, fee_head_id, amount, branch_id) VALUES (?, ?, ?, ?)");
        foreach ($fee_amounts as $fee_head_id => $amount) {
            if (!empty($amount) && is_numeric($amount)) {
                $insert_stmt->execute([$class_id, $fee_head_id, (float)$amount, $active_branch_id]);
            }
        }
        $pdo->commit();
        set_flash_message('Fee structure saved successfully!');
    } catch (PDOException $e) {
        $pdo->rollBack();
        set_flash_message('Error: ' . $e->getMessage(), 'error');
    }
    header("Location: fee_structures.php?class_id=" . $class_id);
    exit();
}

// Fetch classes and fee heads FOR THE CURRENT BRANCH
$classes_stmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE branch_id = ? ORDER BY class_name ASC");
$classes_stmt->execute([$active_branch_id]);
$classes = $classes_stmt->fetchAll();

$fee_heads_stmt = $pdo->prepare("SELECT id, head_name FROM fee_heads WHERE branch_id = ? ORDER BY head_name ASC");
$fee_heads_stmt->execute([$active_branch_id]);
$fee_heads = $fee_heads_stmt->fetchAll();

$selected_class_id = $_GET['class_id'] ?? ($classes[0]['id'] ?? null);

$current_structure = [];
if ($selected_class_id) {
    $stmt = $pdo->prepare("SELECT fee_head_id, amount FROM class_fees WHERE class_id = ? AND branch_id = ?");
    $stmt->execute([$selected_class_id, $active_branch_id]);
    $current_structure = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">Class Fee Structures</h1></div></div></div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Define Monthly Fees (Current Branch)</h3></div>
            <div class="card-body">
                <?php display_flash_messages(); ?>
                <form method="GET" class="form-inline mb-4">
                    <label for="class_id_select" class="mr-2">Select Class:</label>
                    <select id="class_id_select" name="class_id" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($classes as $class): ?>
                        <option value="<?= he($class['id']) ?>" <?= ($selected_class_id == $class['id']) ? 'selected' : '' ?>><?= he($class['class_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <hr>
                <?php if ($selected_class_id): ?>
                <form method="POST">
                    <?= csrf_input_field() ?>
                    <input type="hidden" name="class_id" value="<?= he($selected_class_id) ?>">
                    <h5>Fee Heads for <?= he(array_column($classes, 'class_name', 'id')[$selected_class_id]) ?>:</h5>
                    <?php foreach ($fee_heads as $head): ?>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label"><?= he($head['head_name']) ?></label>
                        <div class="col-sm-9">
                            <input type="number" name="fee_amounts[<?= he($head['id']) ?>]" class="form-control" placeholder="Enter amount" value="<?= he($current_structure[$head['id']] ?? '') ?>" step="0.01">
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-success mt-3">Save Structure</button>
                </form>
                <?php elseif (empty($classes)): ?>
                    <p class="text-info">Please <a href="classes.php">add a class</a> for this branch first.</p>
                <?php elseif (empty($fee_heads)): ?>
                    <p class="text-info">Please <a href="fee_heads.php">add a fee head</a> for this branch first.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>