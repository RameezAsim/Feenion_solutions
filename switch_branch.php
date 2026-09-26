<?php
// school-fees-system9900/switch_branch.php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

check_session(['Admin']); // Only Admins can switch

$new_branch_id = $_GET['branch_id'] ?? null;

if ($new_branch_id && $_SESSION['manages_all_branches']) {
    // Check if this is a valid branch they can access
    $is_valid = false;
    foreach ($_SESSION['accessible_branches'] as $branch) {
        if ($branch['id'] == $new_branch_id) {
            $is_valid = true;
            break;
        }
    }
    
    if ($is_valid) {
        $_SESSION['active_branch_id'] = (int)$new_branch_id;
    }
}

// Send them back to the dashboard of the new branch
header("Location: admin/index.php");
exit();