<?php
require_once __DIR__ . '/../config/database.php';

// Destroy session and redirect to login
session_destroy();
header("Location: login.php");
exit();
?>
