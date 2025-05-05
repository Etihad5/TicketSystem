<?php
session_start();
require 'db.php';

// تهيئة المتغيرات الأساسية
$error = '';
$ticket_id = null;
$location = '';
$assignee = null;

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// التحقق من الصلاحيات
$allowed_roles = ['admin', 'support', 'tracker', 'user'];
if (!in_array($user['role'], $allowed_roles)) {
    die("❌ لا تملك صلاحية إضافة تذاكر جديدة!");
}

// جلب المستخدمين المسؤولين فقط (للمديرين ودعم الفني)
$assignable_users = [];
if (in_array($user['role'], ['admin', 'support'])) {
    $stmt = $conn->query("SELECT id, username, role FROM users WHERE role IN ('admin', 'support', 'tracker') ORDER BY username ASC");
    $assignable_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// قائمة المواقع
$locations = [
    'المجزر',
    'البياض',
    'التسمين',
    'الاجداد',
    'امهات البياض',
    'بوادي 1',
    'بوادي 2',
    'البصرة'
];

// تحديث عدد غير المقروء
$unread_count = $conn->prepare("SELECT COUNT(*) FROM notifications 
                              WHERE user_id = ? AND status = 'unread'");
$unread_count->execute([$user['id']]);
$unread_count = $unread_count->fetchColumn();
// معالجة إرسال النموذج
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // تنظيف المدخلات
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $department = $_POST['department'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $assignee = !empty($_POST['assignee']) ? (int)$_POST['assignee'] : null;
    $location = $_POST['location'] ?? 'عام'; // قيمة افتراضية

    if (empty($title)) {
        $error = "عنوان التذكرة مطلوب!";
    } else {
        try {
            $conn->beginTransaction();

            // إدراج التذكرة
            $stmt = $conn->prepare("INSERT INTO tickets 
                (title, description, reporter, assignee, department, priority, due_date, location) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                $description,
                $user['id'],
                $assignee,
                $department,
                $priority,
                $due_date,
                $location
            ]);

            // الحصول على آخر ID تم إدراجه
            $ticket_id = $conn->lastInsertId();

            // إضافة السجل في ticket_logs
            $log_action = "تم إنشاء التذكرة";
            $stmt_log = $conn->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action) VALUES (?, ?, ?)");
            $stmt_log->execute([$ticket_id, $user['id'], $log_action]);

            // إضافة الإشعارات
            if ($ticket_id) {
                $content = sprintf(
                    "📢 تذكرة جديدة #%d: %s - %s",
                    $ticket_id,
                    htmlspecialchars($title),
                    date('Y-m-d H:i')
                );

                // إشعار عام
                $stmt_notif = $conn->prepare("INSERT INTO notifications 
                    (content, type, priority, related_id) 
                    VALUES (?, 'general', 'high', ?)");
                $stmt_notif->execute([$content, $ticket_id]);

                // إشعار خاص للمكلف
                if ($assignee) {
                    $assignee_content = sprintf(
                        "🔔 تم تعيينك على التذكرة #%d بواسطة %s",
                        $ticket_id,
                        htmlspecialchars($user['username'])
                    );
                    $stmt_private = $conn->prepare("INSERT INTO notifications 
                        (user_id, content, type, priority, related_id) 
                        VALUES (?, ?, 'private', 'high', ?)");
                    $stmt_private->execute([$assignee, $assignee_content, $ticket_id]);
                }
            }

            $conn->commit();
            header('Location: tickets.php?added=1');
            exit;

        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "خطأ في النظام: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة تذكرة جديدة - نظام التذاكر</title>
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

        .ticket-form {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }

        .form-label {
            font-weight: 500;
            color: var(--secondary-color);
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

        .priority-high {
            color: #dc3545;
            font-weight: bold;
        }

        .priority-medium {
            color: #fd7e14;
            font-weight: bold;
        }

        .priority-low {
            color: #198754;
            font-weight: bold;
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold text-dark">
                <i class="bi bi-plus-circle me-2"></i>
                إضافة تذكرة جديدة
            </h2>
            <div>
                <a href="tickets.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-right me-1"></i>رجوع
                </a>
            </div>
        </div>


        <!-- نموذج إضافة التذكرة -->
        <div class="card ticket-form">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="mb-4">
                                <label class="form-label">عنوان التذكرة <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" required autofocus placeholder="أدخل عنواناً واضحاً للتذكرة">
                            </div>

                            <div class="mb-4">
                                <label class="form-label">وصف المشكلة <span class="text-danger">*</span></label>
                                <textarea name="description" class="form-control" rows="6" required placeholder="صف المشكلة بالتفصيل..."></textarea>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="mb-4">
                                <label class="form-label">القسم <span class="text-danger">*</span></label>
                                <select name="department" class="form-select" required>
                                    <option value="">-- اختر القسم --</option>
                                    <option value="IT">IT</option>
                                    <option value="المبيعات">المبيعات</option>
                                    <option value="المالية">المالية</option>
                                    <option value="التسويق">التسويق</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">الموقع <span class="text-danger">*</span></label>
                                <select name="location" class="form-select" required>
                                    <option value="">-- اختر الموقع --</option>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?= htmlspecialchars($loc) ?>" <?= ($location === $loc) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($loc) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">الأولوية <span class="text-danger">*</span></label>
                                <select name="priority" class="form-select" required>
                                    <option value="منخفضة">منخفضة</option>
                                    <option value="متوسطة" selected>متوسطة</option>
                                    <option value="عالية">عالية</option>
                                </select>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <span class="priority-low">منخفضة</span> - مشكلة غير عاجلة<br>
                                        <span class="priority-medium">متوسطة</span> - مشكلة تحتاج حل خلال 24 ساعة<br>
                                        <span class="priority-high">عالية</span> - مشكلة عاجلة تؤثر على العمل
                                    </small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">تاريخ الاستحقاق (اختياري)</label>
                                <input type="date" name="due_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <?php if (in_array($user['role'], ['admin', 'support'])): ?>
                                <div class="mb-4">
                                    <label class="form-label">تعيين مسؤول (اختياري)</label>
                                    <select name="assignee" class="form-select">
                                        <option value="">-- سيتم التعيين تلقائيا --</option>
                                        <?php foreach ($assignable_users as $u): ?>
                                            <option value="<?= $u['id'] ?>" <?= ($assignee == $u['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="bi bi-eraser me-1"></i>مسح النموذج
                        </button>
                        <button type="submit" class="btn btn-custom">
                            <i class="bi bi-save me-1"></i>حفظ التذكرة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>