<?php
session_start();
require 'db.php';

// التأكد من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// معالجة الفلاتر
$where = [];
$params = [];

// فلترة حسب الحالة
if (!empty($_GET['status'])) {
    $where[] = "t.status = ?";
    $params[] = $_GET['status'];
}

// فلترة حسب الأولوية
if (!empty($_GET['priority'])) {
    $where[] = "t.priority = ?";
    $params[] = $_GET['priority'];
}

// فلترة حسب القسم
if (!empty($_GET['department'])) {
    $where[] = "t.department = ?";
    $params[] = $_GET['department'];
}

// بناء استعلام الأساس حسب صلاحية المستخدم
if ($user['role'] === 'admin') {
    // المدير يرى كل التذاكر
    $base_query = "SELECT t.*, u.username AS reporter_name, a.username AS assignee_name 
                   FROM tickets t
                   LEFT JOIN users u ON t.reporter = u.id
                   LEFT JOIN users a ON t.assignee = a.id";
} else {
    // المستخدم العادي يرى تذاكره أو التذاكر المكلف بها في مراحلها
    $base_query = "SELECT DISTINCT t.*, u.username AS reporter_name, a.username AS assignee_name 
                   FROM tickets t
                   LEFT JOIN users u ON t.reporter = u.id
                   LEFT JOIN users a ON t.assignee = a.id
                   LEFT JOIN ticket_stages s ON s.ticket_id = t.id
                   WHERE (t.reporter = ? OR s.assigned_to = ?)";
    array_unshift($params, $user['id'], $user['id']);
}

// إضافة الفلاتر إذا وجدت
if (!empty($where)) {
    $where_clause = implode(" AND ", $where);
    $base_query .= (strpos($base_query, 'WHERE') !== false) ? " AND " : " WHERE ";
    $base_query .= $where_clause;
}

// تحديث عدد غير المقروء
$unread_count = $conn->prepare("SELECT COUNT(*) FROM notifications 
                              WHERE user_id = ? AND status = 'unread'");
$unread_count->execute([$user['id']]);
$unread_count = $unread_count->fetchColumn();
// إضافة الترتيب
$base_query .= " ORDER BY t.created_at DESC";

// جلب التذاكر
$stmt = $conn->prepare($base_query);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب الإحصائيات (تختلف حسب صلاحية المستخدم)
if ($user['role'] === 'admin') {
    $stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'قيد الانتظار' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'معلقة' THEN 1 ELSE 0 END) as onhold,
                    SUM(CASE WHEN status = 'مكتملة' THEN 1 ELSE 0 END) as completed
                    FROM tickets";
} else {
    $stats_query = "SELECT 
                    COUNT(DISTINCT t.id) as total,
                    SUM(CASE WHEN t.status = 'قيد الانتظار' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN t.status = 'معلقة' THEN 1 ELSE 0 END) as onhold,
                    SUM(CASE WHEN t.status = 'مكتملة' THEN 1 ELSE 0 END) as completed
                    FROM tickets t
                    LEFT JOIN ticket_stages s ON s.ticket_id = t.id
                    WHERE t.reporter = ? OR s.assigned_to = ?";
}

$stats_stmt = $conn->prepare($stats_query);
if ($user['role'] === 'admin') {
    $stats_stmt->execute();
} else {
    $stats_stmt->execute([$user['id'], $user['id']]);
}
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة التذاكر - نظام التذاكر</title>
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
        
        .stat-card {
            border-radius: 10px;
            border-left: 4px solid;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .filter-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }
        
        .tickets-table {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }
        
        .table th {
            background-color: #f1f7ed;
            color: var(--secondary-color);
        }
        
        .badge-status {
            padding: 6px 10px;
            border-radius: 20px;
            font-weight: 500;
            min-width: 90px;
            display: inline-block;
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
        
        .section-title {
            position: relative;
            padding-bottom: 10px;
            margin-bottom: 20px;
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
    <!-- عنوان الصفحة وأزرار التحكم -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark"><i class="bi bi-ticket-detailed me-2"></i>إدارة التذاكر</h2>
        <div>
            <?php if (in_array($user['role'], ['admin', 'support', 'tracker', 'user'])): ?>
            <a href="add_ticket.php" class="btn btn-custom me-2">
                <i class="bi bi-plus-circle me-1"></i>تذكرة جديدة
            </a>
            <?php endif; ?>
            <a href="tickets_export_excel.php" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>تصدير Excel
            </a>
        </div>
    </div>

    <!-- بطاقات الإحصائيات -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card border-left-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">إجمالي التذاكر</h6>
                            <h3 class="mb-0"><?php echo $stats['total']; ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="bi bi-ticket-detailed text-primary" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card border-left-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">قيد الانتظار</h6>
                            <h3 class="mb-0"><?php echo $stats['pending']; ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="bi bi-hourglass-split text-warning" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card border-left-danger h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">معلقة</h6>
                            <h3 class="mb-0"><?php echo $stats['onhold']; ?></h3>
                        </div>
                        <div class="bg-danger bg-opacity-10 p-3 rounded">
                            <i class="bi bi-pause-circle text-danger" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="card stat-card border-left-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">مكتملة</h6>
                            <h3 class="mb-0"><?php echo $stats['completed']; ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="bi bi-check-circle text-success" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- فلترة التذاكر -->
    <div class="card filter-card mb-4">
        <div class="card-body">
            <h5 class="section-title"><i class="bi bi-funnel me-2"></i>تصفية التذاكر</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-6 col-lg-3">
                    <label class="form-label">حالة التذكرة</label>
                    <select name="status" class="form-select">
                        <option value="">جميع الحالات</option>
                        <option value="قيد الانتظار" <?= isset($_GET['status']) && $_GET['status'] == 'قيد الانتظار' ? 'selected' : '' ?>>قيد الانتظار</option>
                        <option value="معلقة" <?= isset($_GET['status']) && $_GET['status'] == 'معلقة' ? 'selected' : '' ?>>معلقة</option>
                        <option value="مكتملة" <?= isset($_GET['status']) && $_GET['status'] == 'مكتملة' ? 'selected' : '' ?>>مكتملة</option>
                    </select>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <label class="form-label">أولوية التذكرة</label>
                    <select name="priority" class="form-select">
                        <option value="">جميع الأولويات</option>
                        <option value="منخفضة" <?= isset($_GET['priority']) && $_GET['priority'] == 'منخفضة' ? 'selected' : '' ?>>منخفضة</option>
                        <option value="متوسطة" <?= isset($_GET['priority']) && $_GET['priority'] == 'متوسطة' ? 'selected' : '' ?>>متوسطة</option>
                        <option value="عالية" <?= isset($_GET['priority']) && $_GET['priority'] == 'عالية' ? 'selected' : '' ?>>عالية</option>
                    </select>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <label class="form-label">قسم التذكرة</label>
                    <select name="department" class="form-select">
                        <option value="">جميع الأقسام</option>
                        <option value="IT" <?= isset($_GET['department']) && $_GET['department'] == 'IT' ? 'selected' : '' ?>>IT</option>
                        <option value="المبيعات" <?= isset($_GET['department']) && $_GET['department'] == 'المبيعات' ? 'selected' : '' ?>>المبيعات</option>
                        <option value="المالية" <?= isset($_GET['department']) && $_GET['department'] == 'المالية' ? 'selected' : '' ?>>المالية</option>
                        <option value="التسويق" <?= isset($_GET['department']) && $_GET['department'] == 'التسويق' ? 'selected' : '' ?>>التسويق</option>
                    </select>
                </div>
                
                <div class="col-md-6 col-lg-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-funnel me-1"></i>تصفية
                    </button>
                    <a href="tickets.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- جدول التذاكر -->
    <div class="card tickets-table">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="20%">العنوان</th>
                            <th width="15%">القسم</th>
                            <th width="10%">الحالة</th>
                            <th width="10%">الأولوية</th>
                            <th width="15%">المُبلّغ</th>
                            <th width="15%">المسؤول</th>
                            <th width="10%">التاريخ</th>
                            <th width="10%">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><?php echo $ticket['id']; ?></td>
                            <td>
                                <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($ticket['title']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($ticket['department']); ?></td>
                            <td>
                                <span class="badge <?php 
                                    echo ($ticket['status'] == 'مكتملة') ? 'bg-success' : 
                                         (($ticket['status'] == 'قيد الانتظار') ? 'bg-primary' : 'bg-danger'); 
                                ?> badge-status">
                                    <?php echo htmlspecialchars($ticket['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php
                                    echo ($ticket['priority'] == 'عالية') ? 'bg-danger' :
                                         (($ticket['priority'] == 'متوسطة') ? 'bg-warning text-dark' : 'bg-success');
                                ?> badge-status">
                                    <?php echo htmlspecialchars($ticket['priority']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($ticket['reporter_name'] ?? 'مجهول'); ?></td>
                            <td><?php echo htmlspecialchars($ticket['assignee_name'] ?? 'غير معين'); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($ticket['created_at'])); ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary" title="عرض">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if (in_array($user['role'], ['admin', 'support', 'tracker'])): ?>
                                    <a href="edit_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-warning" title="تعديل">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($user['role'] === 'admin'): ?>
                                    <a href="delete_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-danger" title="حذف" onclick="return confirm('هل أنت متأكد من حذف هذه التذكرة؟');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p class="mt-2">لا توجد تذاكر مطابقة للبحث</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>