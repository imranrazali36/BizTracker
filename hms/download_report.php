<?php
session_start();
include('include/config.php');
include('include/checklogin.php');
check_login();

if (isset($_SESSION['last_report_path']) && file_exists($_SESSION['last_report_path'])) {
    $file = $_SESSION['last_report_path'];
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.basename($file).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
} else {
    header('Location: prediction.php?error=report_not_found');
    exit;
}
?>