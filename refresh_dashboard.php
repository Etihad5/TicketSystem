<?php
session_start();
require 'db.php';

// التحقق من أن الطلب جاء عبر AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die("طلب غير مسموح به");
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'غير مصرح بالوصول']);
    exit;
}

$user = $_SESSION['user'];
$response = [];

try {
    // جلب التذاكر الخاصة بالمستخدم (المكلف بها في مراحل + المسؤول عنها مباشرة)
    $stmt_my_tickets = $conn->prepare("
        (SELECT t.id, t.title, t.status, t.priority, DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') AS created_at, 'مرحلة' AS type
         FROM tickets t
         JOIN ticket_stages s ON s.ticket_id = t.id
         WHERE s.assigned_to = ?)
        UNION
        (SELECT t.id, t.title, t.status, t.priority, DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') AS created_at, 'مباشر' AS type
         FROM tickets t
         WHERE t.assignee = ?)
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt_my_tickets->execute([$user['id'], $user['id']]);
    $response['my_tickets'] = $stmt_my_tickets->fetchAll(PDO::FETCH_ASSOC);

    // جلب تذاكر موقع المستخدم
    $stmt_site_tickets = $conn->prepare("
        SELECT id, title, status, priority, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') AS created_at 
        FROM tickets 
        WHERE location = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");// جلب أحدث 5 تذاكر بغض النظر عن أي شروط
    $stmt_general_tickets = $conn->query("
        SELECT 
            t.id,
            t.title,
            t.status,
            t.priority,
            DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') AS created_at,
            IFNULL(u.username, 'غير معروف') AS reporter_name,
            IFNULL(t.location, 'عام') AS location
        FROM tickets t
        LEFT JOIN users u ON t.reporter = u.id
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    
    $general_tickets = $stmt_general_tickets->fetchAll(PDO::FETCH_ASSOC);
    
    // تسجيل البيانات للفحص (يمكن إزالة هذا لاحقاً)
    error_log("التذاكر العامة: " . print_r($general_tickets, true));
    
    $response['general_tickets'] = $general_tickets;
    // جلب الإحصائيات
    $response['total_tickets'] = $conn->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
    $response['pending_tickets'] = $conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'قيد الانتظار'")->fetchColumn();
    $response['onhold_tickets'] = $conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'معلقة'")->fetchColumn();
    $response['completed_tickets'] = $conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'مكتملة'")->fetchColumn();

    $response['high_priority'] = $conn->query("SELECT COUNT(*) FROM tickets WHERE priority = 'عالية'")->fetchColumn();
    $response['medium_priority'] = $conn->query("SELECT COUNT(*) FROM tickets WHERE priority = 'متوسطة'")->fetchColumn();
    $response['low_priority'] = $conn->query("SELECT COUNT(*) FROM tickets WHERE priority = 'منخفضة'")->fetchColumn();

    // إرجاع البيانات كـ JSON
    header('Content-Type: application/json');
    echo json_encode($response);

} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'حدث خطأ في الخادم: ' . $e->getMessage()]);
}
?>