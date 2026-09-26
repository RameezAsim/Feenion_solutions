<?php
// school-fees-system9900/index.php

/**
 * This is the main entry point of the application.
 * Its only job is to redirect the user to the login page.
 */

// We need the config file to get the SITE_URL
require_once 'config.php';

// Redirect to the login page
header('Location: ' . SITE_URL . '/login.php');
exit();