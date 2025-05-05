<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("❌ رقم التذكرة غير صالح!");
}

$ticket_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT t.*, u.username AS reporter_name, a.username AS assignee_name 
                        FROM tickets t 
                        LEFT JOIN users u ON t.reporter = u.id 
                        LEFT JOIN users a ON t.assignee = a.id 
                        WHERE t.id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("❌ التذكرة غير موجودة!");
}
// تحديث عدد غير المقروء
$unread_count = $conn->prepare("SELECT COUNT(*) FROM notifications 
                              WHERE user_id = ? AND status = 'unread'");
$unread_count->execute([$user['id']]);
$unread_count = $unread_count->fetchColumn();
// جلب سجل الأحداث المرتبط بالتذكرة
$stmt_logs = $conn->prepare("SELECT l.*, u.username FROM ticket_logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.ticket_id = ? ORDER BY l.created_at DESC");
$stmt_logs->execute([$ticket_id]);
$logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل التذكرة #<?php echo $ticket['id']; ?> - نظام التذاكر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c786c;
            --secondary-color: #004445;
            --accent-color: #f8b400;
            --light-bg: #faf5e4;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Tajawal', sans-serif;
        }
        
        .navbar {
            background-color: var(--secondary-color) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .ticket-header {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .ticket-details {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .detail-label {
            font-weight: bold;
            color: var(--secondary-color);
            width: 150px;
            display: inline-block;
        }
        
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .ticket-description {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid var(--primary-color);
        }
        
        .log-item {
            border-left: 3px solid var(--primary-color);
            padding-right: 15px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        
        .log-item:hover {
            background-color: #f5f5f5;
        }
        
        .attachment-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 6px;
            background-color: #f8f9fa;
            margin-bottom: 8px;
        }
        
        .attachment-icon {
            font-size: 1.5rem;
            margin-left: 10px;
            color: var(--primary-color);
        }
        
        .action-buttons .btn {
            margin-left: 8px;
            min-width: 100px;
        }
    </style>
</head>
<body>

<!-- شريط التنقل -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #004445;">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-ticket-detailed me-2"></i>نظام التذاكر
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>لوحة التحكم</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="tickets.php"><i class="bi bi-ticket-detailed me-1"></i>التذاكر</a>
                </li>
                <?php if ($user['role'] == 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="manage_users.php"><i class="bi bi-people me-1"></i>إدارة المستخدمين</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link active" href="notifications.php">
                        <i class="bi bi-bell-fill me-1"></i>الإشعارات
                        <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?= $unread_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($user['username']) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text">الدور: <?= htmlspecialchars($user['role']) ?></span></li>
                        <li><span class="dropdown-item-text">الموقع: <?= htmlspecialchars($user['location'] ?? 'عام') ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i>تسجيل الخروج</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>


<!-- المحتوى الرئيسي -->
<main class="container py-4">
    <!-- عنوان التذكرة وأزرار التحكم -->
    <div class="ticket-header p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-0">
                <i class="bi bi-ticket-detailed text-primary me-2"></i>
                التذكرة #<?php echo $ticket['id']; ?>
            </h2>
            <h4 class="text-dark mt-2"><?php echo htmlspecialchars($ticket['title']); ?></h4>
        </div>

        <div class="action-buttons d-flex gap-2">
            <a href="tickets.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-right me-1"></i>رجوع
            </a>

            <?php if (($user['role'] == 'admin' || $user['id'] == $ticket['reporter']) && trim($ticket['status']) !== 'مكتملة'): ?>
            <a href="edit_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i>تعديل
            </a>
            <?php endif; ?>

            <a href="track_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-outline-primary">
                <i class="bi bi-geo-alt me-1"></i>تتبع التذكرة
            </a>
        </div>
    </div>
</div>

    </div>

    <div class="row">
        <!-- التفاصيل الرئيسية -->
        <div class="col-lg-8">
            <div class="ticket-details mb-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-card-text me-2"></i>وصف التذكرة</h5>
                <div class="ticket-description">
                    <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                </div>
                
                <hr class="my-4">
                
                <h5 class="fw-bold mb-4"><i class="bi bi-info-circle me-2"></i>معلومات التذكرة</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-3">
                            <span class="detail-label"><i class="bi bi-building me-2"></i>القسم:</span>
                            <?php echo htmlspecialchars($ticket['department']); ?>
                        </p>
                        <p class="mb-3">
                            <span class="detail-label"><i class="bi bi-flag me-2"></i>الحالة:</span>
                            <?php
                            $status_color = match($ticket['status']) {
                                'مكتملة' => 'bg-success',
                                'قيد الانتظار' => 'bg-primary',
                                'معلقة' => 'bg-danger',
                                default => 'bg-secondary',
                            };
                            ?>
                            <span class="badge <?php echo $status_color; ?> badge-status"><?php echo $ticket['status']; ?></span>
                        </p>
                        <p class="mb-3">
                            <span class="detail-label"><i class="bi bi-exclamation-triangle me-2"></i>الأولوية:</span>
                            <?php
                            $priority_color = match($ticket['priority']) {
                                'عالية' => 'bg-danger',
                                'متوسطة' => 'bg-warning text-dark',
                                'منخفضة' => 'bg-success',
                                default => 'bg-secondary',
                            };
                            ?>
                            <span class="badge <?php echo $priority_color; ?> badge-status"><?php echo $ticket['priority']; ?></span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-3">
                            <span class="detail-label"><i class="bi bi-person me-2"></i>المُبلّغ:</span>
                            <?php echo htmlspecialchars($ticket['reporter_name'] ?? 'مجهول'); ?>
                        </p>
                        <p class="mb-3">
                            <span class="detail-label"><i class="bi bi-calendar me-2"></i>تاريخ الإنشاء:</span>
                            <?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?>
                        </p>
                        <p class="mb-3">
                            <span class="detail-label"><i class="bi bi-clock me-2"></i>تاريخ الاستحقاق:</span>
                            <?php echo $ticket['due_date'] ? date('Y-m-d', strtotime($ticket['due_date'])) : 'غير محدد'; ?>
                        </p>
                        <p class="mb-3">
                            <span class="detail-label"><i class="bi bi-person-check me-2"></i>المسؤول:</span>
                            <?php echo $ticket['assignee_name'] ? htmlspecialchars($ticket['assignee_name']) : '<span class="text-muted">غير معين</span>'; ?>
                        </p>
                    </div>
                </div>
                
                <?php if (!empty($attachments)): ?>
                <hr class="my-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-paperclip me-2"></i>المرفقات</h5>
                <div class="attachments-list">
                    <?php foreach ($attachments as $attachment): ?>
                    <div class="attachment-item">
                        <i class="bi bi-file-earmark attachment-icon"></i>
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?php echo htmlspecialchars($attachment['original_name']); ?></div>
                            <small class="text-muted"><?php echo round($attachment['file_size'] / 1024, 2); ?> KB</small>
                        </div>
                        <a href="download_attachment.php?id=<?php echo $attachment['id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download"></i> تنزيل
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- سجل الأحداث -->
        <div class="col-lg-4">
            <div class="ticket-details">
                <h5 class="fw-bold mb-4"><i class="bi bi-clock-history me-2"></i>سجل الأحداث</h5>
                
                <?php if ($logs): ?>
                    <div class="timeline">
                        <?php foreach ($logs as $log): ?>
                        <div class="log-item">
                            <div class="d-flex justify-content-between">
                                <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                <small class="text-muted"><?php echo date('H:i - Y/m/d', strtotime($log['created_at'])); ?></small>
                            </div>
                            <div class="text-muted"><?php echo htmlspecialchars($log['action']); ?></div>
                            <?php if (!empty($log['notes'])): ?>
                            <div class="mt-1 small p-2 bg-light rounded"><?php echo htmlspecialchars($log['notes']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="bi bi-info-circle text-muted" style="font-size: 2rem;"></i>
                        <p class="text-muted mt-2">لا توجد أحداث مسجلة لهذه التذكرة</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>