<?php
// school-fees-system9900/config.php (CLEANED)

// --- DATABASE CONFIGURATION ---
// IMPORTANT: Make sure these details are correct for your XAMPP setup.
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', '9900'); // Make sure this is your exact database name

// --- SITE CONFIGURATION ---
// IMPORTANT: Make sure this URL matches your browser's address bar exactly.
define('SITE_URL', 'http://localhost/school-fees-system');
define('SITE_NAME', 'School Fees Management');

// --- SESSION & SECURITY ---
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Set the default timezone
date_default_timezone_set('Asia/Karachi');

// Start the session on every page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- PATH CONFIGURATION ---
// Full path to the mysqldump executable. Adjust if your XAMPP is installed elsewhere.
define('MYSQLDUMP_PATH', 'C:/xampp/mysql/bin/mysqldump.exe');