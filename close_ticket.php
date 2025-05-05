<?php

use PhpOffice\PhpSpreadsheet\Style\Style;
session_start();
require 'db.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'support'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$ticket_id = $_GET['id'] ?? 0;

// التحقق من إتمام المراحل
$stmt = $conn->prepare("SELECT COUNT(*) FROM ticket_stages WHERE ticket_id = ? AND status != 'مكتملة'");
$stmt->execute([$ticket_id]);
if ($stmt->fetchColumn() > 0) {
    $_SESSION['error'] = "❗ يلزم إكمال جميع المراحل أولاً";
    header("Location: view_ticket.php?id=$ticket_id");
    exit;
}

try {
    $conn->beginTransaction();
    
    // إغلاق التذكرة
    $stmt = $conn->prepare("UPDATE tickets SET status='مكتملة' WHERE id=?");
    $stmt->execute([$ticket_id]);
    
    // إشعار متقدم
    $content = sprintf(
        "✅ قام %s بإغلاق التذكرة #%d - %s",
        htmlspecialchars($user['username']),
        $ticket_id,
        date('Y-m-d H:i')
    );
    
    $stmt_notif = $conn->prepare("INSERT INTO notifications 
        (content, type, related_id, priority) 
        VALUES (?, 'general', ?, 'high')");
    $stmt_notif->execute([$content, $ticket_id]);
    
    $conn->commit();
    header("Location: view_ticket.php?id=$ticket_id&closed=1");
    exit;

} catch (PDOException $e) {
    $conn->rollBack();
    die("خطأ في الإغلاق: " . $e->getMessage());
}

