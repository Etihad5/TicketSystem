<?php
session_start();

// التحقق من وجود المفتاح
if (isset($_GET['key']) && is_numeric($_GET['key'])) {
    $key = (int)$_GET['key'];

    if (isset($_SESSION['notifications'][$key])) {
        unset($_SESSION['notifications'][$key]);
    }
}

// الرجوع إلى الصفحة السابقة أو الداشبورد
$redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: $redirect");
exit;
?>
