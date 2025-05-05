<?php
session_start();
require 'db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// تفعيل تسجيل الأخطاء
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// تسجيل وقت الطلب
error_log("تم استدعاء refresh_dashboard.php في: " . date('Y-m-d H:i:s'));

$user = $_SESSION['user'];
$stmt_notifications = $conn->prepare("
    SELECT 
        id, 
        content, 
        type, 
        priority, 
        status, 
        created_at,
        related_id
    FROM notifications 
    WHERE (type = 'general' OR (type = 'private' AND user_id = ?))
    ORDER BY created_at DESC
    LIMIT 5
");
$unread_count = $conn->prepare("SELECT COUNT(*) FROM notifications 
                              WHERE (type = 'general' OR (type = 'private' AND user_id = ?)) 
                              AND status = 'unread'");
$unread_count->execute([$user['id']]);
$unread_count = $unread_count->fetchColumn();

$stmt_notifications->execute([$user['id']]);
$latest_notifications = $stmt_notifications->fetchAll(PDO::FETCH_ASSOC);
// جلب أحدث 5 تذاكر
$stmt_general = $conn->query("
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
    LIMIT 10
");
$general_tickets = $stmt_general->fetchAll(PDO::FETCH_ASSOC);


// جلب التذاكر الخاصة بالمستخدم (المكلف بها في مراحل + المسؤول عنها مباشرة)
$stmt_my_tickets = $conn->prepare("
    (SELECT t.id, t.title, t.status, t.priority, t.created_at, 'مرحلة' AS type
     FROM tickets t
     JOIN ticket_stages s ON s.ticket_id = t.id
     WHERE s.assigned_to = ?)
    UNION
    (SELECT t.id, t.title, t.status, t.priority, t.created_at, 'مباشر' AS type
     FROM tickets t
     WHERE t.assignee = ?)
    ORDER BY created_at DESC
");
$stmt_my_tickets->execute([$user['id'], $user['id']]);
$my_tickets = $stmt_my_tickets->fetchAll(PDO::FETCH_ASSOC);

// جلب تذاكر موقع المستخدم
$stmt_site_tickets = $conn->prepare("
    SELECT id, title, status, priority, created_at 
    FROM tickets 
    WHERE location = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt_site_tickets->execute([$user['location']]);
$site_tickets = $stmt_site_tickets->fetchAll(PDO::FETCH_ASSOC);

// جلب التذاكر العامة
$stmt_general_tickets = $conn->query("
    SELECT id, title, status, priority, created_at 
    FROM tickets 
    WHERE location = 'عام' OR location IS NULL OR location = ''
    ORDER BY created_at DESC 
    LIMIT 5
");
$general_tickets = $stmt_general_tickets->fetchAll(PDO::FETCH_ASSOC);

// الإحصائيات
$total_tickets = $conn->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$pending_tickets = $conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'قيد الانتظار'")->fetchColumn();
$onhold_tickets = $conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'معلقة'")->fetchColumn();
$completed_tickets = $conn->query("SELECT COUNT(*) FROM tickets WHERE status = 'مكتملة'")->fetchColumn();

$high_priority = $conn->query("SELECT COUNT(*) FROM tickets WHERE priority = 'عالية'")->fetchColumn();
$medium_priority = $conn->query("SELECT COUNT(*) FROM tickets WHERE priority = 'متوسطة'")->fetchColumn();
$low_priority = $conn->query("SELECT COUNT(*) FROM tickets WHERE priority = 'منخفضة'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - نظام التذاكر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: #2c786c;
            --secondary-color: #004445;
            --accent-color: #f8b400;
            --light-bg: #f8f9fa;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Tajawal', sans-serif;
        }

        .navbar {
            background-color: var(--secondary-color) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .card {
            border-radius: 10px;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            border-left: 4px solid;
            padding: 15px;
        }

        .stat-card .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .stat-card .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .badge {
            padding: 6px 10px;
            border-radius: 20px;
            font-weight: normal;
        }

        .table th {
            background-color: #f1f7ed;
            color: var(--secondary-color);
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .section-title {
            position: relative;
            padding-bottom: 10px;
            margin-bottom: 25px;
            color: var(--secondary-color);
        }

        .section-title:after {
            content: "";
            position: absolute;
            bottom: 0;
            right: 0;
            width: 50px;
            height: 3px;
            background-color: var(--accent-color);
        }

        .btn-custom {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
        }

        .btn-custom:hover {
            background-color: var(--secondary-color);
            color: white;
        }

        .refresh-btn {
            cursor: pointer;
            transition: transform 0.3s;
        }

        .refresh-btn:hover {
            transform: rotate(180deg);
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0, 0, 0, .1);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .badge-status-pending {
            background-color: #0d6efd;
            color: white;
        }

        .badge-status-onhold {
            background-color: #dc3545;
            color: white;
        }

        .badge-status-completed {
            background-color: #198754;
            color: white;
        }

        .badge-priority-high {
            background-color: #dc3545;
            color: white;
        }

        .badge-priority-medium {
            background-color: #ffc107;
            color: #000;
        }

        .badge-priority-low {
            background-color: #198754;
            color: white;
        }

        general-notification {
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
            color: rgb(255, 255, 255);
        }

        .unread-dot {
            width: 10px;
            height: 10px;
            background: #dc3545;
            border-radius: 50%;
            position: absolute;
            left: 15px;
            top: 15px;
            z-index: 1;
        }

        .stretched-link::after {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 2;
            content: "";
        }

        @keyframes pulse {
            0% {
                transform: scale(0.95);
            }

            70% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(0.95);
            }
        }

        .notification-actions {
            position: absolute;
            bottom: 1rem;
            left: 1rem;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 2;
        }

        .notification-card:hover .notification-actions {
            opacity: 1;
        }
    </style>
</head>

<body>

    <!-- شريط التنقل -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-ticket-detailed me-2"></i>نظام التذاكر
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>لوحة
                            التحكم</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tickets.php"><i class="bi bi-ticket-detailed me-1"></i>التذاكر</a>
                    </li>
                    <?php if ($user['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php"><i class="bi bi-people me-1"></i>إدارة
                                المستخدمين</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="notifications.php"><i class="bi bi-bell-fill"></i> عرض الإشعارات</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="userDropdown"
                            data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-lines-fill me-2"></i>الملف الشخصي</a></li>
                            <li><span class="dropdown-item-text">الدور:
                                    <?php echo htmlspecialchars($user['role']); ?></span></li>
                            <li><span class="dropdown-item-text">الموقع:
                                    <?php echo htmlspecialchars($user['location'] ?? 'عام'); ?></span></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="logout.php"><i
                                        class="bi bi-box-arrow-left me-2"></i>تسجيل الخروج</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- المحتوى الرئيسي -->
    <main class="container py-4">
        <!-- عنوان الصفحة وأزرار التحكم -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold text-dark"><i class="bi bi-speedometer2 me-2"></i>لوحة التحكم</h2>
            <div>
                <a href="tickets.php" class="btn btn-custom me-2">
                    <i class="bi bi-ticket-detailed me-1"></i>عرض التذاكر
                </a>
                <?php if ($user['role'] == 'admin'): ?>
                    <a href="manage_users.php" class="btn btn-outline-secondary">
                        <i class="bi bi-people me-1"></i>إدارة المستخدمين
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- بطاقات الإحصائيات -->
        <div class="row g-4 mb-4">
            <!-- إجمالي التذاكر -->
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card border-left-primary">
                    <div class="card-body text-center">
                        <div class="stat-icon text-primary">
                            <i class="bi bi-ticket-detailed"></i>
                        </div>
                        <div class="stat-value text-dark"><?php echo $total_tickets; ?></div>
                        <div class="stat-label text-muted">إجمالي التذاكر</div>
                    </div>
                </div>
            </div>

            <!-- قيد الانتظار -->
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card border-left-warning">
                    <div class="card-body text-center">
                        <div class="stat-icon text-warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div class="stat-value text-dark"><?php echo $pending_tickets; ?></div>
                        <div class="stat-label text-muted">قيد الانتظار</div>
                    </div>
                </div>
            </div>

            <!-- معلقة -->
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card border-left-danger">
                    <div class="card-body text-center">
                        <div class="stat-icon text-danger">
                            <i class="bi bi-pause-circle"></i>
                        </div>
                        <div class="stat-value text-dark"><?php echo $onhold_tickets; ?></div>
                        <div class="stat-label text-muted">معلقة</div>
                    </div>
                </div>
            </div>

            <!-- مكتملة -->
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card border-left-success">
                    <div class="card-body text-center">
                        <div class="stat-icon text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-value text-dark"><?php echo $completed_tickets; ?></div>
                        <div class="stat-label text-muted">مكتملة</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- قسم الإشعارات الأخيرة -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="section-title m-0">
                        <i class="bi bi-bell-fill me-2"></i>آخر الإشعارات
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?= $unread_count ?> غير مقروء</span>
                        <?php endif; ?>
                    </h5>
                    <a href="notifications.php" class="btn btn-sm btn-custom">
                        <i class="bi bi-arrow-left me-1"></i>عرض الكل
                    </a>
                </div>

                <?php if (!empty($latest_notifications)): ?>
                    <div class="row g-3">
                        <?php foreach ($latest_notifications as $notif): ?>
                            <div class="col-12">
                                <div
                                    class="notification-card <?= $notif['type'] === 'general' ? 'general-notification' : 'private-notification' ?> priority-<?= $notif['priority'] ?> position-relative">

                                    <!-- علامة غير مقروء -->
                                    <?php if ($notif['status'] === 'unread'): ?>
                                        <div class="unread-dot animate__animated animate__pulse"></div>
                                    <?php endif; ?>

                                    <a href="view_ticket.php?id=<?= $notif['related_id'] ?>" class="stretched-link"></a>

                                    <div class="p-3">
                                        <div class="d-flex align-items-center">
                                            <i
                                                class="bi <?= $notif['type'] === 'general' ? 'bi-globe' : 'bi-bell' ?> notification-icon"></i>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-1">
                                                    <?= htmlspecialchars($notif['content']) ?>
                                                    <?php if ($notif['priority'] === 'high'): ?>
                                                        <span
                                                            class="badge bg-danger ms-2"><?= $notif['type'] === 'general' ? 'عاجل' : 'مهم' ?></span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock me-1"></i>
                                                    <?= date('Y-m-d H:i', strtotime($notif['created_at'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- زر التعلم كمقروء -->
                                    <div class="notification-actions">
                                        <a href="read_notification.php?id=<?= $notif['id'] ?>"
                                            class="btn btn-sm btn-outline-secondary me-2" onclick="event.stopPropagation()">
                                            <i class="bi bi-check2"></i> تعليم كمقروء
                                        </a>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="bi bi-bell-slash text-muted" style="font-size: 2rem;"></i>
                        <p class="text-muted mt-2">لا توجد إشعارات جديدة</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- التذاكر الخاصة بي -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="section-title m-0">
                        <i class="bi bi-person-check me-2"></i>التذاكر الخاصة بي
                    </h5>
                    <div>
                        <span id="refreshMyTickets" class="refresh-btn me-2" title="تحديث البيانات">
                            <i class="bi bi-arrow-clockwise"></i>
                            <div class="loading-spinner" id="myTicketsLoading"></div>
                        </span>
                        <a href="tickets.php?assigned=<?php echo $user['id']; ?>" class="btn btn-sm btn-custom">
                            <i class="bi bi-eye me-1"></i>عرض الكل
                        </a>
                    </div>
                </div>

                <?php if (!empty($my_tickets)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr class="table-light">
                                    <th width="10%">#</th>
                                    <th width="40%">العنوان</th>
                                    <th width="15%">النوع</th>
                                    <th width="15%">الحالة</th>
                                    <th width="10%">الأولوية</th>
                                    <th width="10%">التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_tickets as $ticket): ?>
                                    <tr>
                                        <td><?php echo $ticket['id']; ?></td>
                                        <td>
                                            <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>"
                                                class="text-decoration-none text-primary">
                                                <?php echo htmlspecialchars($ticket['title']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo $ticket['type']; ?></td>
                                        <td>
                                            <span class="badge <?php
                                            echo $ticket['status'] === 'قيد الانتظار' ? 'badge-status-pending' :
                                                ($ticket['status'] === 'معلقة' ? 'badge-status-onhold' : 'badge-status-completed');
                                            ?>">
                                                <?php echo $ticket['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php
                                            echo $ticket['priority'] === 'عالية' ? 'badge-priority-high' :
                                                ($ticket['priority'] === 'متوسطة' ? 'badge-priority-medium' : 'badge-priority-low');
                                            ?>">
                                                <?php echo $ticket['priority']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($ticket['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">لا توجد تذاكر خاصة بك حالياً</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- تذاكر موقعي -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="section-title m-0">
                        <i class="bi bi-geo-alt me-2"></i>تذاكر موقعي
                        (<?php echo htmlspecialchars($user['location']); ?>)
                    </h5>
                    <div>
                        <span id="refreshSite" class="refresh-btn me-2" title="تحديث البيانات">
                            <i class="bi bi-arrow-clockwise"></i>
                            <div class="loading-spinner" id="siteLoading"></div>
                        </span>
                        <a href="tickets.php?location=<?php echo urlencode($user['location']); ?>"
                            class="btn btn-sm btn-custom">
                            <i class="bi bi-eye me-1"></i>عرض الكل
                        </a>
                    </div>
                </div>

                <?php if (!empty($site_tickets)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr class="table-light">
                                    <th width="10%">#</th>
                                    <th width="50%">العنوان</th>
                                    <th width="20%">الحالة</th>
                                    <th width="20%">التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($site_tickets as $ticket): ?>
                                    <tr>
                                        <td><?php echo $ticket['id']; ?></td>
                                        <td>
                                            <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>"
                                                class="text-decoration-none text-primary">
                                                <?php echo htmlspecialchars($ticket['title']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge <?php
                                            echo $ticket['status'] === 'قيد الانتظار' ? 'badge-status-pending' :
                                                ($ticket['status'] === 'معلقة' ? 'badge-status-onhold' : 'badge-status-completed');
                                            ?>">
                                                <?php echo $ticket['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($ticket['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3">لا توجد تذاكر في موقعك حالياً</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- الرسوم البيانية -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="section-title"><i class="bi bi-pie-chart me-2"></i>توزيع حالات التذاكر</h5>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="section-title"><i class="bi bi-bar-chart me-2"></i>توزيع أولويات التذاكر</h5>
                        <div class="chart-container">
                            <canvas id="priorityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- سكربت رسم المخططات -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // مخطط توزيع الحالات
            const ctxStatus = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(ctxStatus, {
                type: 'doughnut',
                data: {
                    labels: ['قيد الانتظار', 'معلقة', 'مكتملة'],
                    datasets: [{
                        data: [<?php echo $pending_tickets; ?>, <?php echo $onhold_tickets; ?>, <?php echo $completed_tickets; ?>],
                        backgroundColor: ['#0d6efd', '#dc3545', '#198754'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            rtl: true
                        }
                    }
                }
            });

            // مخطط توزيع الأولويات
            const ctxPriority = document.getElementById('priorityChart').getContext('2d');
            const priorityChart = new Chart(ctxPriority, {
                type: 'bar',
                data: {
                    labels: ['عالية', 'متوسطة', 'منخفضة'],
                    datasets: [{
                        label: 'عدد التذاكر',
                        data: [<?php echo $high_priority; ?>, <?php echo $medium_priority; ?>, <?php echo $low_priority; ?>],
                        backgroundColor: ['#dc3545', '#ffc107', '#198754'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                display: true,
                                color: "rgba(0, 0, 0, 0.05)"
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // دالة لتحديث جميع البيانات
            function refreshAllData() {
                $.ajax({
                    url: 'refresh_dashboard.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function (data) {
                        // تحديث التذاكر الخاصة بي
                        updateTable('myTicketsContainer', data.my_tickets);

                        // تحديث تذاكر الموقع
                        updateTable('siteTicketsContainer', data.site_tickets);

                        // تحديث التذاكر العامة
                        updateTable('generalTicketsContainer', data.general_tickets);

                        // تحديث الإحصائيات
                        $('.stat-value').eq(0).text(data.total_tickets);
                        $('.stat-value').eq(1).text(data.pending_tickets);
                        $('.stat-value').eq(2).text(data.onhold_tickets);
                        $('.stat-value').eq(3).text(data.completed_tickets);

                        // تحديث الرسوم البيانية
                        statusChart.data.datasets[0].data = [data.pending_tickets, data.onhold_tickets, data.completed_tickets];
                        statusChart.update();

                        priorityChart.data.datasets[0].data = [data.high_priority, data.medium_priority, data.low_priority];
                        priorityChart.update();
                    },
                    error: function () {
                        console.log('حدث خطأ أثناء تحديث البيانات');
                    }
                });
            }

            // دالة لتحديث جدول معين
            function updateTable(containerId, tickets) {
                let html = '';

                if (tickets.length > 0) {
                    html += '<div class="table-responsive"><table class="table table-hover"><thead><tr class="table-light">';

                    if (containerId === 'generalTicketsContainer') {
                        html += '<th>#</th><th>العنوان</th><th>المضيف</th><th>الموقع</th><th>الحالة</th><th>الأولوية</th><th>التاريخ</th>';
                    } else if (containerId === 'siteTicketsContainer' || containerId === 'myTicketsContainer') {
                        html += '<th>#</th><th>العنوان</th><th>الحالة</th><th>الأولوية</th><th>التاريخ</th>';
                    }

                    html += '</tr></thead><tbody>';

                    tickets.forEach(ticket => {
                        html += `<tr>
                <td>${ticket.id}</td>
                <td><a href="view_ticket.php?id=${ticket.id}" class="text-decoration-none text-primary">${ticket.title}</a></td>`;

                        if (containerId === 'generalTicketsContainer') {
                            html += `<td>${ticket.reporter_name}</td>`;
                            html += `<td>${ticket.location}</td>`;
                        }

                        html += `<td><span class="badge ${getStatusBadgeClass(ticket.status)}">${ticket.status}</span></td>`;
                        html += `<td>${ticket.priority}</td>`;
                        html += `<td>${ticket.created_at}</td>`;
                        html += '</tr>';
                    });

                    html += '</tbody></table></div>';
                } else {
                    html += '<div class="text-center py-4">';
                    html += '<i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>';
                    html += '<p class="text-muted mt-3">لا توجد بيانات حالياً</p>';
                    html += '</div>';
                }

                $(`#${containerId}`).html(html);
            }


            // دالة للحصول على كلاس Badge بناءً على الحالة
            function getStatusBadgeClass(status) {
                switch (status) {
                    case 'قيد الانتظار': return 'badge-status-pending';
                    case 'معلقة': return 'badge-status-onhold';
                    case 'مكتملة': return 'badge-status-completed';
                    default: return 'badge-secondary';
                }
            }

            // أحداث النقر على أزرار التحديث
            $('#refreshMyTickets, #refreshSite, #refreshGeneral').click(function () {
                const loadingId = $(this).find('.loading-spinner').attr('id');
                $(`#${loadingId}`).show();
                $(this).find('i').hide();

                refreshAllData();

                setTimeout(() => {
                    $(`#${loadingId}`).hide();
                    $(this).find('i').show();
                }, 1000);
            });

            // تحديث البيانات كل 30 ثانية
            setInterval(refreshAllData, 30000);
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>