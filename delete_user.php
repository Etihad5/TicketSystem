<?php
session_start();
require 'db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// فقط Admin يمكنه حذف المستخدمين
if ($user['role'] !== 'admin') {
    die("❌ لا تملك صلاحية حذف مستخدمين!");
}
// حماية إضافية: لا يمكن حذف المدير الرئيسي (id = 1)
if ($delete_id == 1) {
    die("❌ لا يمكنك حذف المدير الرئيسي للنظام!");
}


// التحقق من وجود معرف المستخدم
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("❌ رقم المستخدم غير صالح!");
}

$delete_id = (int)$_GET['id'];

// حماية: لا يمكن حذف نفسك
if ($delete_id == $user['id']) {
    die("❌ لا يمكنك حذف نفسك!");
}

// حذف المستخدم
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$delete_id]);

// الرجوع إلى صفحة إدارة المستخدمين
header('Location: manage_users.php?deleted=1');
exit;
?>
