<?php
// school-fees-system/includes/settings.php (Multi-Branch Enabled)

$GLOBALS['app_settings'] = [];

try {
    // 1. Fetch all GLOBAL settings (where branch_id is NULL)
    $stmt_global = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE branch_id IS NULL");
    $GLOBALS['app_settings']['global'] = $stmt_global->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2. Fetch all BRANCH-SPECIFIC settings
    $stmt_branch = $pdo->query("SELECT branch_id, setting_key, setting_value FROM settings WHERE branch_id IS NOT NULL");
    while ($row = $stmt_branch->fetch(PDO::FETCH_ASSOC)) {
        $GLOBALS['app_settings']['branch'][$row['branch_id']][$row['setting_key']] = $row['setting_value'];
    }

} catch (PDOException $e) {
    // Fail silently if the table doesn't exist yet
}

// The duplicate get_setting() function has been removed from this file.
// The correct one is in includes/functions.php.
?>