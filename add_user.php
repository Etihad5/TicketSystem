<?php
session_start();
require 'db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// فقط المدير يستطيع إضافة مستخدمين
if ($user['role'] !== 'admin') {
    die("❌ لا تملك صلاحية إضافة مستخدمين!");
}

// قائمة المواقع
$locations = [
    'المجزر', 'البياض', 'التسمين', 'الاجداد', 
    'امهات البياض', 'بوادي 1', 'بوادي 2', 'البصرة'
];

// عند إرسال النموذج
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password_plain = $_POST['password'];
    $role = $_POST['role'];
    $location = $_POST['location'];
    $email = trim($_POST['email'] ?? '');

    if (empty($username) || empty($password_plain) || empty($role) || empty($location)) {
        $error = "يجب ملء جميع الحقول المطلوبة!";
    } elseif (strlen($password_plain) < 8) {
        $error = "كلمة المرور يجب أن تكون 8 أحرف على الأقل!";
    } else {
        $password_hashed = hash('sha256', $password_plain);

        try {
            $conn->beginTransaction();
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, location, email) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password_hashed, $role, $location, $email]);
            
            $conn->commit();
            $success = "تم إنشاء المستخدم بنجاح!";
        } catch (PDOException $e) {
            $conn->rollBack();
            if ($e->getCode() == 23000) {
                $error = "اسم المستخدم موجود مسبقاً!";
            } else {
                $error = "حدث خطأ: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة مستخدم جديد - نظام التذاكر</title>
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
        
        .user-form {
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
        
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .role-badge {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<!-- شريط التنقل -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-ticket-detailed me-2"></i>نظام التذاكر
        </a>
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
<main class="container py-4">
    <!-- عنوان الصفحة وأزرار التحكم -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark">
            <i class="bi bi-person-plus me-2"></i>
            إضافة مستخدم جديد
        </h2>
        <div>
            <a href="manage_users.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-right me-1"></i>رجوع
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div><?php echo $success; ?></div>
        </div>
    <?php endif; ?>

    <!-- نموذج إضافة المستخدم -->
    <div class="card user-form">
        <div class="card-body">
            <form method="POST" id="userForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required autofocus
                                   placeholder="أدخل اسم مستخدم فريد">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="password" class="form-control" required
                                   placeholder="8 أحرف على الأقل" minlength="8">
                            <div class="password-strength" id="passwordStrength"></div>
                            <small class="text-muted">يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label class="form-label">البريد الإلكتروني (اختياري)</label>
                            <input type="email" name="email" class="form-control"
                                   placeholder="example@domain.com">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">الدور <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="">-- اختر دور المستخدم --</option>
                                <option value="admin">
                                    مدير النظام 
                                    <span class="badge bg-danger role-badge">Admin</span>
                                </option>
                                <option value="support">
                                    دعم فني 
                                    <span class="badge bg-primary role-badge">Support</span>
                                </option>
                                <option value="tracker">
                                    متابعة 
                                    <span class="badge bg-warning text-dark role-badge">Tracker</span>
                                </option>
                                <option value="user">
                                    مستخدم عادي 
                                    <span class="badge bg-success role-badge">User</span>
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-4">
                            <label class="form-label">الموقع <span class="text-danger">*</span></label>
                            <select name="location" class="form-select" required>
                                <option value="">-- اختر الموقع --</option>
                                <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc); ?>">
                                    <?php echo htmlspecialchars($loc); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3 mt-4">
                    <button type="reset" class="btn btn-outline-secondary">
                        <i class="bi bi-eraser me-1"></i>مسح النموذج
                    </button>
                    <button type="submit" class="btn btn-custom">
                        <i class="bi bi-save me-1"></i>حفظ المستخدم
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// مؤشر قوة كلمة المرور
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    let strength = 0;
    
    if (password.length >= 8) strength += 1;
    if (password.match(/[a-z]/)) strength += 1;
    if (password.match(/[A-Z]/)) strength += 1;
    if (password.match(/[0-9]/)) strength += 1;
    if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
    
    // تحديث شريط القوة
    strengthBar.style.width = (strength * 20) + '%';
    strengthBar.style.backgroundColor = 
        strength < 2 ? '#dc3545' : 
        strength < 4 ? '#fd7e14' : '#198754';
});
</script>
</body>
</html>