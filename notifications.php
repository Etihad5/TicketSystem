<?php
session_start();
require 'db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// جلب الإشعارات مع الفلترة
$stmt = $conn->prepare("SELECT 
    id, 
    content, 
    type, 
    priority, 
    status, 
    created_at,
    related_id
FROM notifications 
WHERE (type = 'general' OR (type = 'private' AND user_id = ?))
ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تحديث عدد غير المقروء
$unread_count = $conn->prepare("SELECT COUNT(*) FROM notifications 
                              WHERE user_id = ? AND status = 'unread'");
$unread_count->execute([$user['id']]);
$unread_count = $unread_count->fetchColumn();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإشعارات - نظام التذاكر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        .notification-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 15px;
        }

        .notification-header {
            background: #004445;
            color: white;
            padding: 1rem;
            border-radius: 12px 12px 0 0;
        }

        .notification-card {
            background: white;
            border-radius: 12px;
            margin: 1rem 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 6px solid;
            position: relative;
            overflow: hidden;
        }

        .notification-card:hover {
            transform: translateX(-10px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }

        .notification-badge {
            position: absolute;
            left: -30px;
            top: 50%;
            transform: translateY(-50%) rotate(-45deg);
            width: 120px;
            text-align: center;
            font-size: 0.8rem;
            padding: 3px 0;
        }

        .general-notification {
            border-color: #2c786c;
        }

        .private-notification {
            border-color: #f8b400;
        }

        .priority-high {
            border-color: #dc3545 !important;
            background: linear-gradient(90deg, #fff5f5 95%, #dc3545 5%);
        }

        .priority-medium {
            border-color: #ffc107 !important;
            background: linear-gradient(90deg, #fff9e6 95%, #ffc107 5%);
        }

        .notification-icon {
            font-size: 1.8rem;
            margin-left: 1rem;
            color: #2c786c;
        }

        .notification-time {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .unread-dot {
            width: 10px;
            height: 10px;
            background: #dc3545;
            border-radius: 50%;
            position: absolute;
            right: 1rem;
            top: 1.5rem;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); }
            70% { transform: scale(1.1); }
            100% { transform: scale(0.95); }
        }

        .notification-actions {
            position: absolute;
            bottom: 1rem;
            left: 1rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .notification-card:hover .notification-actions {
            opacity: 1;
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

<!-- محتوى الصفحة -->
<div class="notification-container">
    <div class="notification-header">
        <h3><i class="bi bi-bell-fill me-2"></i>مركز الإشعارات</h3>
        <div class="d-flex justify-content-between mt-3">
            <span>الإشعارات غير المقروءة: <?= $unread_count ?></span>
            <div>
                <a href="clear_notifications.php?type=general" class="btn btn-sm btn-outline-light me-2">
                    مسح العامة
                </a>
                <a href="clear_notifications.php?type=private" class="btn btn-sm btn-outline-light">
                    مسح الخاصة
                </a>
            </div>
        </div>
    </div>

 <!-- الإشعارات العامة -->
<div class="mt-4">
    <h4 class="mb-3 text-secondary"><i class="bi bi-megaphone me-2"></i>الإشعارات العامة</h4>
    <?php foreach ($notifications as $notif): ?>
        <?php if ($notif['type'] === 'general'): ?>
            <div class="notification-card general-notification priority-<?= $notif['priority'] ?>">
                <?php if ($notif['status'] === 'unread'): ?>
                    <div class="unread-dot"></div>
                    <span class="badge bg-danger position-absolute" style="left: 1rem; top: 1rem;">غير مقروء</span>
                <?php endif; ?>
                
                <div class="p-4 position-relative">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-globe notification-icon"></i>
                        <div class="flex-grow-1">
                            <h5 class="mb-2">
                                <?= $notif['content'] ?>
                                <?php if ($notif['priority'] === 'high'): ?>
                                    <span class="badge bg-danger ms-2">عاجل</span>
                                <?php endif; ?>
                            </h5>
                            
                            <div class="notification-time">
                                <i class="bi bi-clock me-1"></i>
                                <?= date('Y-m-d H:i', strtotime($notif['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="notification-actions">
                        <a href="read_notification.php?id=<?= $notif['id'] ?>" 
                           class="btn btn-sm btn-outline-secondary me-2">
                           <i class="bi bi-check2"></i> تعليم كمقروء
                        </a>
                        <?php if ($notif['related_id']): ?>
                        <a href="view_ticket.php?id=<?= $notif['related_id'] ?>" 
                           class="btn btn-sm btn-primary">
                           <i class="bi bi-arrow-left"></i> عرض التذكرة
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- الإشعارات الخاصة -->
<div class="mt-5">
    <h4 class="mb-3 text-secondary"><i class="bi bi-person-circle me-2"></i>الإشعارات الخاصة</h4>
    <?php foreach ($notifications as $notif): ?>
        <?php if ($notif['type'] === 'private'): ?>
            <div class="notification-card private-notification priority-<?= $notif['priority'] ?>">
                <?php if ($notif['status'] === 'unread'): ?>
                    <div class="notification-badge bg-warning text-dark">جديد</div>
                <?php endif; ?>
                
                <div class="p-4 position-relative">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-bell notification-icon"></i>
                        <div class="flex-grow-1">
                            <h5 class="mb-2">
                                <?= $notif['content'] ?>
                                <?php if ($notif['priority'] === 'high'): ?>
                                    <span class="badge bg-danger ms-2">مهم</span>
                                <?php endif; ?>
                            </h5>
                            
                            <div class="notification-time">
                                <i class="bi bi-clock me-1"></i>
                                <?= date('Y-m-d H:i', strtotime($notif['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="notification-actions">
                        <a href="read_notification.php?id=<?= $notif['id'] ?>" 
                           class="btn btn-sm btn-outline-secondary me-2">
                           <i class="bi bi-check2"></i> تعليم كمقروء
                        </a>
                        <?php if ($notif['related_id']): ?>
                        <a href="view_ticket.php?id=<?= $notif['related_id'] ?>" 
                           class="btn btn-sm btn-primary">
                           <i class="bi bi-arrow-left"></i> عرض التفاصيل
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php if (empty($notifications)): ?>
    <div class="text-center py-5">
        <i class="bi bi-bell-slash" style="font-size: 4rem; color: #6c757d;"></i>
        <h4 class="mt-3 text-muted">لا توجد إشعارات لعرضها</h4>
        <p class="text-muted">سيظهر هنا أي إشعارات جديدة تتلقاها</p>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// تحديث تلقائي للإشعارات كل 30 ثانية
setInterval(() => {
    fetch('get_new_notifications.php')
    .then(response => response.text())
    .then(data => {
        if(data) {
            location.reload();
        }
    });
}, 30000);
</script>
</body>
</html>