<?php
session_start();
require 'db.php';

// التأكد من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// السماح فقط للمدير بالتعديل
if ($user['role'] !== 'admin') {
    die("❌ لا تملك صلاحية تعديل المستخدمين!");
}

// التحقق من رقم المستخدم
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("❌ رقم المستخدم غير صحيح!");
}

$user_id = (int)$_GET['id'];

// جلب بيانات المستخدم الحالي
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$edit_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$edit_user) {
    die("❌ المستخدم غير موجود!");
}

// تحديث المستخدم عند إرسال النموذج
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $location = $_POST['location'];

    if (empty($username) || empty($role) || empty($location)) {
        $error = "❌ يجب ملء جميع الحقول!";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, location = ? WHERE id = ?");
            $stmt->execute([$username, $role, $location, $user_id]);

            $success = "✅ تم تحديث بيانات المستخدم بنجاح!";
            // تحديث البيانات في النموذج
            $edit_user['username'] = $username;
            $edit_user['role'] = $role;
            $edit_user['location'] = $location;
        } catch (PDOException $e) {
            $error = "❌ خطأ في تحديث البيانات: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل مستخدم - نظام التذاكر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Tajawal', sans-serif;
        }
        
        .navbar {
            background-color :#004445;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .btn-primary {
            background-color: #2c786c;
            border-color: #28a745;
            padding: 8px 20px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        
        .btn-secondary {
            padding: 6px 15px;
        }
        
        h3 {
            color: #343a40;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #28a745;
            display: inline-block;
        }
        
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>

<!-- شريط التنقل -->
<nav class="navbar navbar-expand-lg navbar-dark " >
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
                <li class="nav-item">
                    <a class="nav-link active" href="manage_users.php"><i class="bi bi-people me-1"></i>إدارة المستخدمين</a>
                </li>
            </ul>
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <button class="btn btn-outline-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars($user['username']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text">الدور: <?php echo htmlspecialchars($user['role']); ?></span></li>
                        <li><span class="dropdown-item-text">الموقع: <?php echo htmlspecialchars($user['location'] ?? 'عام'); ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i>تسجيل الخروج</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- المحتوى الرئيسي -->
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold">
            <i class="bi bi-pencil-square me-2"></i>تعديل المستخدم #<?php echo $edit_user['id']; ?>
        </h3>
        <a href="manage_users.php" class="btn btn-secondary">
            <i class="bi bi-arrow-right me-1"></i> رجوع
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">اسم المستخدم</label>
                        <input type="text" name="username" class="form-control form-control-lg" 
                               value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">الدور</label>
                        <select name="role" class="form-select form-select-lg" required>
                            <option value="admin" <?php if($edit_user['role'] == 'admin') echo 'selected'; ?>>مدير</option>
                            <option value="support" <?php if($edit_user['role'] == 'support') echo 'selected'; ?>>دعم فني</option>
                            <option value="tracker" <?php if($edit_user['role'] == 'tracker') echo 'selected'; ?>>متابعة</option>
                            <option value="user" <?php if($edit_user['role'] == 'user') echo 'selected'; ?>>مستخدم عادي</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">الموقع</label>
                        <select name="location" class="form-select form-select-lg" required>
                            <?php 
                            $locations = ['المجزر', 'البياض', 'التسمين', 'الاجداد', 'امهات البياض', 'بوادي 1', 'بوادي 2', 'البصرة'];
                            foreach ($locations as $loc): ?>
                                <option value="<?php echo $loc; ?>" <?php if($edit_user['location'] == $loc) echo 'selected'; ?>>
                                    <?php echo $loc; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-check-circle me-2"></i> حفظ التعديلات
                    </button>
                    <a href="manage_users.php" class="btn btn-outline-secondary btn-lg px-4">
                        <i class="bi bi-x-circle me-2"></i> إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>