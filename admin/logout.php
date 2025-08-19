<?php
session_start();
include '../config.php';
date_default_timezone_set('Asia/Jakarta');
$now = date('Y-m-d H:i:s');

if (isset($_SESSION['logged_in'])) {
    $log_user = ucfirst(strtolower($_SESSION['username']));
    $conn->query("INSERT INTO user_logs (user, activity, log_time) 
                  VALUES ('$log_user', 'Logout', '$now')");
}

session_destroy();
header("Location: ../index.php");
exit;
?>
