<?php
// school-fees-system/admin/settings.php (Multi-Branch Enabled & FIXED)

$page_title = 'System Settings';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// This page is for SUPER ADMINS ONLY.
check_session(['Admin']);
if (!$_SESSION['manages_all_branches']) {
    set_flash_message('You do not have permission to manage system settings.', 'error');
    header('Location: index.php');
    exit;
}

$active_branch_id = get_active_branch_id();
if (!$active_branch_id) {
    set_flash_message('You must select a branch to manage its settings.', 'error');
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $settings = $_POST['settings'];

    try {
        $pdo->beginTransaction();
        
        // QUERY EXPLANATION:
        // We try to INSERT the setting. If (key + branch) already exists, we UPDATE the value instead.
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, branch_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");

        // 1. Save BRANCH settings (branch_id = $active_branch_id)
        $branch_settings = $settings['branch'] ?? [];
        foreach ($branch_settings as $key => $value) {
            $stmt->execute([$key, $value, $active_branch_id, $value]);
        }
        
        // 2. Save GLOBAL settings (branch_id = 0)
        $global_settings = $settings['global'] ?? [];
        foreach ($global_settings as $key => $value) {
            if ($key === 'smtp_pass' && empty($value)) { continue; } // Don't overwrite password with empty
            $stmt->execute([$key, $value, 0, $value]);
        }
        
        // 3. Handle Logo Upload (Global - branch_id = 0)
        if (isset($_FILES['global_logo']) && $_FILES['global_logo']['error'] == 0) {
            $file = $_FILES['global_logo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            
            if (in_array($file['type'], $allowed_types) && $file['size'] <= 2 * 1024 * 1024) {
                $upload_dir = __DIR__ . '/../uploads/logos/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                $filename = 'logo.png'; // Standard filename
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                    $stmt->execute(['school_logo', $filename, 0, $filename]);
                }
            }
        }
        
        $pdo->commit();
        set_flash_message('Settings saved successfully!');
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash_message('Error saving settings: ' . $e->getMessage(), 'error');
    }
    
    header("Location: settings.php");
    exit();
}

// Fetch Data for Display
$current_branch_name = $pdo->query("SELECT name FROM branches WHERE id = $active_branch_id")->fetchColumn();
$branch_settings = get_branch_settings($pdo, $active_branch_id);
$global_settings = get_global_settings($pdo);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">System Settings</h1></div></div></div>
</div>

<div class="content">
    <div class="container-fluid">
        <?php display_flash_messages(); ?>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_input_field() ?>
            <div class="card card-primary card-tabs">
                <div class="card-header p-0 pt-1">
                    <ul class="nav nav-tabs" id="settings-tabs" role="tablist">
                        <li class="nav-item"><a class="nav-link active" id="branch-settings-tab" data-toggle="pill" href="#branch-settings" role="tab">Branch Settings (<?= he($current_branch_name) ?>)</a></li>
                        <li class="nav-item"><a class="nav-link" id="global-settings-tab" data-toggle="pill" href="#global-settings" role="tab">Global Settings</a></li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="settings-tabs-content">
                        
                        <div class="tab-pane fade show active" id="branch-settings" role="tabpanel">
                            <h4>Branch Info (Appears on Invoices)</h4>
                            <div class="form-group"><label>School/Branch Name</label><input type="text" name="settings[branch][school_name]" class="form-control" value="<?= he($branch_settings['school_name'] ?? '') ?>"></div>
                            <div class="form-group"><label>Address</label><textarea name="settings[branch][school_address]" class="form-control" rows="2"><?= he($branch_settings['school_address'] ?? '') ?></textarea></div>
                            <div class="form-group"><label>Contact Phone</label><input type="text" name="settings[branch][school_contact]" class="form-control" value="<?= he($branch_settings['school_contact'] ?? '') ?>"></div>
                            <hr>
                            <div class="form-group"><label>Bank Details</label><textarea name="settings[branch][bank_details]" class="form-control" rows="3"><?= he($branch_settings['bank_details'] ?? '') ?></textarea></div>
                            <div class="form-group"><label>Easypaisa Info</label><textarea name="settings[branch][easypaisa_details]" class="form-control" rows="2"><?= he($branch_settings['easypaisa_details'] ?? '') ?></textarea></div>
                            <div class="form-group"><label>JazzCash Info</label><textarea name="settings[branch][jazzcash_details]" class="form-control" rows="2"><?= he($branch_settings['jazzcash_details'] ?? '') ?></textarea></div>
                        </div>
                        
                        <div class="tab-pane fade" id="global-settings" role="tabpanel">
                            <h4>School Logo</h4>
                            <div class="form-group">
                                <label>Current Logo</label><br>
                                <img src="<?= SITE_URL ?>/uploads/logos/<?= he($global_settings['school_logo'] ?? 'logo.png') ?>?v=<?= time() ?>" alt="Logo" style="max-height: 60px; background: #eee; padding: 5px;">
                            </div>
                             <div class="form-group"><label>Upload New Logo</label><input type="file" name="global_logo" class="form-control-file"></div>
                            <hr>
                            <h4>SMTP Email Settings</h4>
                            <div class="form-group"><label>SMTP Host</label><input type="text" name="settings[global][smtp_host]" class="form-control" value="<?= he($global_settings['smtp_host'] ?? '') ?>"></div>
                            <div class="form-group"><label>SMTP Port</label><input type="text" name="settings[global][smtp_port]" class="form-control" value="<?= he($global_settings['smtp_port'] ?? '') ?>"></div>
                            <div class="form-group"><label>SMTP Username</label><input type="email" name="settings[global][smtp_user]" class="form-control" value="<?= he($global_settings['smtp_user'] ?? '') ?>"></div>
                            <div class="form-group"><label>SMTP Password</label><input type="password" name="settings[global][smtp_pass]" class="form-control" placeholder="Leave blank to keep current"></div>
                        </div>

                    </div>
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-primary">Save Settings</button></div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>