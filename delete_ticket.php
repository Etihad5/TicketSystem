<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$ticket_id = $_GET['id'] ?? 0;

try {
    // تسجيل تفاصيل الحذف
    $stmt = $conn->prepare("SELECT title FROM tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $ticket_title = $stmt->fetchColumn();

    // حذف التذكرة
    $conn->beginTransaction();
    $stmt = $conn->prepare("DELETE FROM tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);

    // إشعار تفصيلي
    $content = sprintf(
        "🗑️ قام %s بحذف التذكرة: #%d - %s",
        htmlspecialchars($user['username']),
        $ticket_id,
        htmlspecialchars($ticket_title)
    );
    
    $stmt_notif = $conn->prepare("INSERT INTO notifications 
        (content, type, priority) 
        VALUES (?, 'general', 'high')");
    $stmt_notif->execute([$content]);
    
    $conn->commit();
    
    header('Location: tickets.php?deleted=1');
    exit;

} catch (PDOException $e) {
    $conn->rollBack();
    die("خطأ في الحذف: " . $e->getMessage());
}