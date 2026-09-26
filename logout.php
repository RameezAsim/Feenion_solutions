<?php
// school-fees-system/logout.php

require_once 'config.php';

$_SESSION = array();
session_destroy();

header("Location: " . SITE_URL . "/login.php");
exit();