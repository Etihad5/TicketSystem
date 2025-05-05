<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

if (isset($_GET['id'])) {
    $notification_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
    
    try {
        $stmt = $conn->prepare("UPDATE notifications SET status = 'read' WHERE id = ?");
        $stmt->execute([$notification_id]);
        
        // إعادة توجيه مع رسالة نجاح
        $_SESSION['success'] = "تم تعليم الإشعار كمقروء بنجاح";
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        exit;
    }
}
?>