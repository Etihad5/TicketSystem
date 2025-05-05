<?php
session_start();
require 'db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user']['id'];

// تحديث جميع الإشعارات للمستخدم
$stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE user_id = ?");
$stmt->execute([$user_id]);

// الرجوع إلى صفحة الإشعارات
header('Location: notifications.php');
exit;
?>
