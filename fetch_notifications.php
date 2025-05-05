<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user']['id'];

$stmt = $conn->prepare("
    SELECT id, content, type, priority, status, created_at 
    FROM notifications
    WHERE user_id IS NULL OR user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($notifications);
