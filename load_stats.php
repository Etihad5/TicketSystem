<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    die(json_encode(['error' => 'غير مصرح بالوصول']));
}

$stats = [
    'total_tickets' => $conn->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'pending_tickets' => $conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'قيد الانتظار'")->fetchColumn(),
    'onhold_tickets' => $conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'معلقة'")->fetchColumn(),
    'completed_tickets' => $conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'مكتملة'")->fetchColumn(),
    'high_priority' => $conn->query("SELECT COUNT(*) FROM tickets WHERE priority = 'عالية'")->fetchColumn(),
    'medium_priority' => $conn->query("SELECT COUNT(*) FROM tickets WHERE priority = 'متوسطة'")->fetchColumn(),
    'low_priority' => $conn->query("SELECT COUNT(*) FROM tickets WHERE priority = 'منخفضة'")->fetchColumn()
];

header('Content-Type: application/json');
echo json_encode($stats);