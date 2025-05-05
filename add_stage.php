<?php
session_start();
require 'db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$ticket_id = (int)($_POST['ticket_id'] ?? 0);

// التحقق من أن التذكرة ليست مغلقة
$stmt = $conn->prepare("SELECT status FROM tickets WHERE id = ?");
$stmt->execute([$ticket_id]);
$ticket_status = $stmt->fetchColumn();

if ($ticket_status === 'مكتملة') {
    $_SESSION['error'] = "🚫 لا يمكن إضافة مراحل لتذكرة مغلقة!";
    header("Location: track_ticket.php?id=$ticket_id");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stage_name = trim($_POST['stage_name'] ?? '');
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $status = $_POST['status'] ?? 'قيد التنفيذ';
    $started_at = $_POST['started_at'] ?? date('Y-m-d H:i:s');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($stage_name)) {
        $_SESSION['error'] = "❗ اسم المرحلة مطلوب!";
        header("Location: track_ticket.php?id=$ticket_id");
        exit;
    }

    try {
        $conn->beginTransaction();

        // إضافة المرحلة إلى قاعدة البيانات
        $stmt = $conn->prepare("
            INSERT INTO ticket_stages (
                ticket_id, 
                stage_name, 
                description, 
                assigned_to, 
                status, 
                started_at, 
                finished_at
            ) VALUES (
                :ticket_id, 
                :stage_name, 
                :description, 
                :assigned_to, 
                :status, 
                :started_at, 
                :finished_at
            )
        ");

        $finished_at = ($status === 'مكتملة') ? date('Y-m-d H:i:s') : null;

        $stmt->execute([
            ':ticket_id'    => $ticket_id,
            ':stage_name'   => $stage_name,
            ':description'  => $description ?: null,
            ':assigned_to'  => $assigned_to,
            ':status'       => $status,
            ':started_at'   => $started_at,
            ':finished_at'  => $finished_at
        ]);

        // إرسال إشعار إذا تم تعيين مستخدم
        if ($assigned_to) {
            $content = sprintf(
                "🔄 تم تعيينك على مرحلة جديدة: **%s** (التذكرة #%d)",
                htmlspecialchars($stage_name),
                $ticket_id
            );

            $stmt_notif = $conn->prepare("
                INSERT INTO notifications 
                    (user_id, content, type, related_id, priority) 
                VALUES 
                    (:user_id, :content, 'private', :ticket_id, 'high')
            ");
            $stmt_notif->execute([
                ':user_id'    => $assigned_to,
                ':content'    => $content,
                ':ticket_id' => $ticket_id
            ]);
        }

        $conn->commit();
        $_SESSION['success'] = "✅ تمت إضافة المرحلة بنجاح!";
        header("Location: track_ticket.php?id=$ticket_id");
        exit;

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("خطأ في إضافة المرحلة: " . $e->getMessage());
        $_SESSION['error'] = "❗ حدث خطأ تقني. يرجى المحاولة لاحقاً.";
        header("Location: track_ticket.php?id=$ticket_id");
        exit;
    }
}

header("Location: track_ticket.php?id=$ticket_id");
exit;
?>