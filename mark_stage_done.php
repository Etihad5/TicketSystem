<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$stage_id = (int)$_GET['id'];
$ticket_id = (int)$_GET['ticket'];

// التحقق من ملكية المرحلة
$stmt_check = $conn->prepare("SELECT assigned_to FROM ticket_stages WHERE id = ?");
$stmt_check->execute([$stage_id]);
$assigned_to = $stmt_check->fetchColumn();

if ($assigned_to != $user['id'] && $user['role'] != 'admin') {
    die("❌ ليس لديك صلاحية إنهاء هذه المرحلة!");
}

// تحديث حالة المرحلة
try {
    $conn->beginTransaction();
    
    $stmt = $conn->prepare("UPDATE ticket_stages SET status = 'مكتملة', finished_at = NOW() WHERE id = ?");
    $stmt->execute([$stage_id]);
    
    // إشعار خاص
    $content = sprintf(
        "✔️ تم إنهاء مرحلة بواسطة %s - التذكرة #%d",
        htmlspecialchars($user['username']),
        $ticket_id
    );
    
    $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, content, type, related_id) VALUES (?, ?, 'private', ?)");
    $stmt_notif->execute([$user['id'], $content, $ticket_id]);
    
    $conn->commit();
    header("Location: track_ticket.php?id=$ticket_id&stage_done=1");
    exit;

} catch (PDOException $e) {
    $conn->rollBack();
    die("حدث خطأ: " . $e->getMessage());
}
?>