<?php
// school-fees-system9900/reset_password.php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Reset Password';
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$is_valid_token = false;

if (empty($token) || empty($email)) {
    set_flash_message('Invalid reset link.', 'error');
} else {
    // 1. Find the token in the database
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND expires > ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$email, time()]);
    $reset_request = $stmt->fetch();

    if ($reset_request && password_verify($token, $reset_request['token'])) {
        $is_valid_token = true;
    } else {
        set_flash_message('Your password reset link is invalid or has expired.', 'error');
    }
}

// Handle the new password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_valid_token) {
    validate_csrf_token();
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if (strlen($password) < 8) {
        set_flash_message('Password must be at least 8 characters long.', 'error');
    } elseif ($password !== $password_confirm) {
        set_flash_message('Passwords do not match.', 'error');
    } else {
        // 1. Hash the new password
        $new_hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // 2. Update the user's table
        $stmt_update = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt_update->execute([$new_hashed_password, $email]);

        // 3. Delete the used token
        $stmt_delete = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt_delete->execute([$email]);

        set_flash_message('Your password has been reset successfully! You can now log in.');
        header('Location: login.php');
        exit();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= he(SITE_NAME) ?> | Reset Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo">
        <a href="index.php"><b>School</b>Fees</a>
    </div>
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Set your new password.</p>
            <?php display_flash_messages(); ?>

            <?php if ($is_valid_token): ?>
                <form action="reset_password.php?token=<?= he($token) ?>&email=<?= he(urlencode($email)) ?>" method="post">
                    <?= csrf_input_field() ?>
                    <div class="input-group mb-3">
                        <input type="password" class="form-control" name="password" placeholder="New Password" required minlength="8">
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" class="form-control" name="password_confirm" placeholder="Confirm New Password" required>
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-block">Change Password</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
            <p class="mt-3 mb-1 text-center">
                <a href="login.php">Back to Login</a>
            </p>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
</body>
</html>