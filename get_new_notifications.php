
<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) exit;

$last_check = $_GET['last_check'] ?? date('Y-m-d H:i:s');
$user_id = $_SESSION['user']['id'];

$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications 
                       WHERE ((type = 'general') OR (type = 'private' AND user_id = ?))
                       AND created_at > ?");
$stmt->execute([$user_id, $last_check]);
echo $stmt->fetchColumn();