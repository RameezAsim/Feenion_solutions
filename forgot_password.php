<?php
// school-fees-system9900/forgot_password.php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Forgot Password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $email = trim($_POST['email']);
    
    // 1. Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Generate a secure token
        $token = bin2hex(random_bytes(32));
        $expires = time() + 3600; // Token is valid for 1 hour
        
        // 3. Store the (hashed) token in the database
        $hashed_token = password_hash($token, PASSWORD_DEFAULT);
        $stmt_insert = $pdo->prepare("INSERT INTO password_resets (email, token, expires) VALUES (?, ?, ?)");
        $stmt_insert->execute([$email, $hashed_token, $expires]);

        // 4. Create the reset link
        $reset_link = SITE_URL . '/reset_password.php?token=' . $token . '&email=' . urlencode($email);

        // 5. Send the email
        $subject = "Password Reset Request for " . SITE_NAME;
        $body = "
            <p>Hello,</p>
            <p>We received a request to reset your password. Click the link below to set a new password:</p>
            <p><a href='{$reset_link}'>{$reset_link}</a></p>
            <p>This link is valid for 1 hour. If you did not request this, please ignore this email.</p>
        ";
        
        if (send_email($email, $subject, $body)) {
            set_flash_message('A password reset link has been sent to your email address.');
        } else {
            set_flash_message('Failed to send email. Please contact the administrator.', 'error');
        }
    } else {
        // Show a generic message even if user doesn't exist to prevent email-fishing
        set_flash_message('If an account with that email exists, a reset link has been sent.');
    }
    
    header("Location: forgot_password.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= he(SITE_NAME) ?> | Forgot Password</title>
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
            <p class="login-box-msg">Enter your email to receive a password reset link.</p>
            <?php display_flash_messages(); ?>
            <form action="forgot_password.php" method="post">
                <?= csrf_input_field() ?>
                <div class="input-group mb-3">
                    <input type="email" class="form-control" name="email" placeholder="Email" required>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block">Request Reset Link</button>
                    </div>
                </div>
            </form>
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