<?php
session_start();
require 'db.php';

// ✅ تحقق من وجود جلسة
if (!isset($_SESSION['user'])) {
    die("❌ لا يوجد دخول مصرح.");
}

$user = $_SESSION['user'];

// ✅ السماح فقط للمستخدم admin
if ($user['role'] !== 'admin') {
    die("❌ لا تملك صلاحية نقل المرحلة.");
}

// ✅ تحقق من البيانات المرسلة
if (!isset($_POST['stage_id']) || !isset($_POST['new_user_id']) || !isset($_POST['ticket_id'])) {
    die("❌ بيانات غير مكتملة.");
}

$stage_id = (int)$_POST['stage_id'];
$new_user_id = (int)$_POST['new_user_id'];
$ticket_id = (int)$_POST['ticket_id'];

// ✅ تنفيذ عملية النقل
$stmt = $conn->prepare("UPDATE ticket_stages SET assigned_to = ? WHERE id = ?");
$stmt->execute([$new_user_id, $stage_id]);

// ✅ (اختياري) تسجيل إشعار بالنقل
$notif = "🔁 تم نقل مرحلة من التذكرة #$ticket_id إلى مستخدم جديد";
$stmt = $conn->prepare("INSERT INTO notifications (user_id, content) VALUES (?, ?)");
$stmt->execute([$user['id'], $notif]);

// ✅ إعادة التوجيه إلى تتبع التذكرة
header("Location: track_ticket.php?id=$ticket_id");
exit;
?>
