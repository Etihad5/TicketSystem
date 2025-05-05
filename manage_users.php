<?php
session_start();
require 'db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// فقط المدير يمكنه إدارة المستخدمين
if ($user['role'] !== 'admin') {
    die("❌ لا تملك صلاحية الوصول إلى هذه الصفحة!");
}

// جلب جميع المستخدمين مع تفاصيل إضافية
$stmt = $conn->prepare("SELECT 
                        id, 
                        username, 
                        role, 
                        location,
                        last_login, 
                        created_at 
                        FROM users 
                        ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تحديث عدد غير المقروء
$unread_count = $conn->prepare("SELECT COUNT(*) FROM notifications 
                              WHERE user_id = ? AND status = 'unread'");
$unread_count->execute([$user['id']]);
$unread_count = $unread_count->fetchColumn();
// معالجة رسائل النظام
$alert = '';
if (isset($_GET['deleted'])) {
    $alert = '<div class="alert alert-success d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2"></i>
                تم حذف المستخدم بنجاح
              </div>';
} elseif (isset($_GET['reset'])) {
    $alert = '<div class="alert alert-info d-flex align-items-center">
                <i class="bi bi-key-fill me-2"></i>
                تم إعادة تعيين كلمة المرور (الكلمة الجديدة: password123)
              </div>';
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين - نظام التذاكر</title>
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

        .users-table {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }

        .table th {
            background-color: #f1f7ed;
            color: var(--secondary-color);
        }

        .badge-role {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
        }

        .btn-action {
            width: 40px;
            height: 40px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            <h2 class="fw-bold text-dark">
                <i class="bi bi-people me-2"></i>
                إدارة المستخدمين
            </h2>
            <div>
                <a href="add_user.php" class="btn btn-custom">
                    <i class="bi bi-person-plus me-1"></i>مستخدم جديد
                </a>
            </div>
        </div>

        <?php echo $alert; ?>

        <!-- جدول المستخدمين -->
        <div class="card users-table">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="20%">المستخدم</th>
                                <th width="15%">الدور</th>
                                <th width="15%">الموقع</th>
                                <th width="15%">آخر دخول</th>
                                <th width="20%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $usr): ?>
                                <tr>
                                    <td><?php echo $usr['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-person-circle me-2"></i>
                                            <?php echo htmlspecialchars($usr['username']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $role_color = match ($usr['role']) {
                                            'admin' => 'bg-danger',
                                            'support' => 'bg-primary',
                                            'tracker' => 'bg-warning text-dark',
                                            default => 'bg-success'
                                        };
                                        ?>
                                        <span class="badge badge-role <?php echo $role_color; ?>">
                                            <?php echo htmlspecialchars($usr['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($usr['location'] ?? 'عام'); ?></td>
                                    <td><?php echo $usr['last_login'] ? date('Y-m-d H:i', strtotime($usr['last_login'])) : 'لم يسجل دخول'; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="edit_user.php?id=<?php echo $usr['id']; ?>"
                                                class="btn btn-sm btn-outline-warning btn-action" title="تعديل">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <?php if ($usr['id'] != $user['id']): ?>
                                                <a href="delete_user.php?id=<?php echo $usr['id']; ?>"
                                                    class="btn btn-sm btn-outline-danger btn-action" title="حذف"
                                                    onclick="return confirm('هل أنت متأكد من حذف هذا المستخدم؟');">
                                                    <i class="bi bi-trash"></i>
                                                </a>

                                                <a href="reset_password.php?id=<?php echo $usr['id']; ?>"
                                                    class="btn btn-sm btn-outline-info btn-action"
                                                    title="إعادة تعيين كلمة المرور">
                                                    <i class="bi bi-key"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">الحساب الحالي</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-people" style="font-size: 2rem;"></i>
                                        <p class="mt-2">لا يوجد مستخدمين مسجلين</p>
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