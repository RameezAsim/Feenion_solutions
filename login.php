<?php
// school-fees-system/login.php (CLEANED - NO CAPTCHA)

require_once 'includes/db.php';
require_once 'includes/functions.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token(); // Check CSRF token

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // 1. Form Validation
    if (empty($email) || empty($password)) {
        $error_message = "Email and password are required.";
    } else {
        // Unset any old captcha codes just in case
        unset($_SESSION['captcha_code']);

        try {
            $stmt = $pdo->prepare("SELECT id, full_name, email, password, role, status, branch_id, manages_all_branches FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'Active') {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['login_time'] = time();

                    // --- FINAL MULTI-BRANCH LOGIC ---
                    $_SESSION['manages_all_branches'] = (bool)$user['manages_all_branches'];
                    $_SESSION['user_branch_id'] = $user['branch_id'];
                    
                    if ($user['role'] === 'Parent') {
                        // Parents are global and have no active branch
                        $_SESSION['active_branch_id'] = null;
                        $_SESSION['accessible_branches'] = [];
                    } elseif ($_SESSION['manages_all_branches']) {
                        // Super Admin
                        $_SESSION['accessible_branches'] = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
                        $_SESSION['active_branch_id'] = $_SESSION['accessible_branches'][0]['id'] ?? null;
                    } else {
                        // Branch-specific user (e.g., Accountant)
                        $_SESSION['accessible_branches'] = $pdo->query("SELECT id, name FROM branches WHERE id = " . (int)$user['branch_id'])->fetchAll();
                        $_SESSION['active_branch_id'] = $user['branch_id'];
                    }
                    // --- END OF LOGIC ---

                    log_activity("User login successful."); 

                    switch ($user['role']) {
                        case 'Admin': header("Location: admin/index.php"); exit();
                        case 'Accountant': header("Location: accountant/index.php"); exit();
                        case 'Parent': header("Location: parent/index.php"); exit();
                    }
                } else {
                    $error_message = "Your account is inactive.";
                }
            } else {
                $error_message = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error_message = "An error occurred.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= he(SITE_NAME) ?> | Log in</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <style>
        /* ===== GENERAL PAGE STYLING ===== */
body {
  font-family: 'Poppins', 'Segoe UI', sans-serif;
  background: linear-gradient(135deg, #0e2a47 0%, #163d5c 100%);
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
  margin: 0;
}

/* ===== LOGIN BOX ===== */
.login-box {
  width: 360px;
  margin: 0 auto;
}

.login-logo img {
  display: block;
  margin: 0 auto 10px auto;
}

/* ===== CARD ===== */
.card {
  border: none;
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  transition: all 0.3s ease;
}
.card:hover {
  transform: translateY(-3px);
  box-shadow: 0 14px 35px rgba(0, 0, 0, 0.2);
}

/* ===== CARD BODY ===== */
.login-card-body {
  padding: 35px 30px;
  border-radius: 16px;
}

.login-box-msg {
  font-size: 15px;
  color: #555;
  text-align: center;
  margin-bottom: 25px;
  font-weight: 500;
}

/* ===== INPUT FIELDS ===== */
.input-group .form-control {
  height: 45px;
  border: 1px solid #ced4da;
  border-right: none;
  border-radius: 10px 0 0 10px;
  transition: 0.3s;
  box-shadow: none !important;
}

.input-group .form-control:focus {
  border-color: #004aad;
  box-shadow: 0 0 0 3px rgba(0, 74, 173, 0.15);
}

.input-group-text {
  border: 1px solid #ced4da;
  border-left: none;
  background: #f8f9fa;
  border-radius: 0 10px 10px 0;
  color: #666;
}

/* ===== LINKS ===== */
a {
  color: #004aad;
  font-weight: 500;
  text-decoration: none;
  transition: 0.3s;
}
a:hover {
  color: #022c69;
  text-decoration: underline;
}

/* ===== BUTTON ===== */
.btn-primary {
  background: #004aad;
  border: none;
  border-radius: 10px;
  height: 45px;
  font-weight: 600;
  font-size: 15px;
  transition: all 0.3s ease;
}
.btn-primary:hover {
  background: #022c69;
  box-shadow: 0 4px 12px rgba(0, 74, 173, 0.3);
  transform: translateY(-1px);
}
.btn-primary:active {
  transform: scale(0.98);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 480px) {
  .login-box {
    width: 90%;
  }
}

    </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo"><img src="assets/img/feenion_logo.png" alt="" width="200px"></div> <br>
    <!-- div class="login-logo"><a href="#"><b>School</b>Fees</a></div -->
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Sign in to start your session</p>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            
            <form action="login.php" method="post" autocomplete="off">
                <?= csrf_input_field() ?>
                <div class="input-group mb-3">
                    <input type="email" class="form-control" name="email" placeholder="Email" required>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                </div>
                
                <div class="row">
                    <div class="col-8">
                        <p class="mb-1">
                            <a href="forgot_password.php">I forgot my password</a>
                        </p>
                    </div>
                    <div class="col-4"><button type="submit" class="btn btn-primary btn-block">Sign In</button></div>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
</body>
</html>