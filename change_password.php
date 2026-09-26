<?php
// school-fees-system9900/change_password.php

$page_title = 'Change Password';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// This page is accessible to ALL logged-in users.
check_session(['Admin', 'Accountant', 'Parent']);

$user_id = $_SESSION['user_id']; // Get the ID of the currently logged-in user

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    try {
        // 1. Basic Validation
        if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
            throw new Exception("All fields are required.");
        }
        if ($new_pass !== $confirm_pass) {
            throw new Exception("Your new passwords do not match.");
        }
        if (strlen($new_pass) < 8) {
            throw new Exception("New password must be at least 8 characters long.");
        }

        // 2. Verify the user's CURRENT password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current_pass, $user['password'])) {
            throw new Exception("Your current password is incorrect.");
        }

        // 3. All checks passed. Hash and update the new password
        $new_hashed_password = password_hash($new_pass, PASSWORD_BCRYPT);
        
        $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->execute([$new_hashed_password, $user_id]);

        set_flash_message('Your password has been changed successfully!');
        log_activity("User changed their own password.");
        
    } catch (Exception $e) {
        set_flash_message($e->getMessage(), 'error');
    }
    
    // Redirect back to the same page to show the message
    header("Location: change_password.php");
    exit();
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="content-header">
    <div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1 class="m-0">Change Password</h1></div></div></div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Update Your Password</h3></div>
                    <form method="POST" action="change_password.php">
                        <?= csrf_input_field() ?>
                        <div class="card-body">
                            <?php display_flash_messages(); ?>
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password (min. 8 characters)</label>
                                <input type="password" name="new_password" class="form-control" required minlength="8">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="8">
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>