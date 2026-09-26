<?php
// school-fees-system/includes/db.php (CLEANED)
require_once __DIR__ . '/../config.php';
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(503);
    echo '<div style="font-family: sans-serif; text-align: center; padding: 50px;"><h1>Application Error</h1><p>Unable to connect to the database.</p></div>';
    error_log("Database connection failed: " . $e->getMessage());
    exit();
}
require_once __DIR__ . '/settings.php';