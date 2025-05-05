// clear_notifications.php
<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    exit;
}

$type = $_GET['type'] ?? null;
$user_id = $_SESSION['user']['id'];

if ($type === 'general') {
    $conn->exec("DELETE FROM notifications WHERE type = 'general'");
} elseif ($type === 'private') {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE type = 'private' AND user_id = ?");
    $stmt->execute([$user_id]);
}

header('Location: notifications.php');