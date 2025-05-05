<?php
session_start();
require 'db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// فقط Admin يمكنه إعادة تعيين كلمات المرور
if ($user['role'] !== 'admin') {
    die("❌ لا تملك صلاحية إعادة تعيين كلمة المرور!");
}

// التحقق من وجود معرف المستخدم
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("❌ رقم المستخدم غير صالح!");
}

$reset_id = (int)$_GET['id'];

// جلب بيانات المستخدم الذي نريد إعادة تعيين كلمة مروره
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$reset_id]);
$target_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target_user) {
    die("❌ المستخدم غير موجود!");
}

// عند إرسال النموذج
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password_plain = $_POST['new_password'];

    if (empty($new_password_plain)) {
        $error = "❌ يرجى إدخال كلمة مرور جديدة!";
    } else {
        $new_password_hashed = hash('sha256', $new_password_plain);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password_hashed, $reset_id]);

        $success = "✅ تم تغيير كلمة مرور المستخدم بنجاح!";
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور - نظام التذاكر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c786c;
            --secondary-color: #004445;
            --accent-color: #f8b400;
        }
        
        .password-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: none;
        }
        
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .btn-custom {
            background: var(--primary-color);
            color: white;
            padding: 8px 25px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-custom:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
    </style>
</head>
<body style="background-color: #f8f9fa;">

<!-- شريط التنقل -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--secondary-color);">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-ticket-detailed me-2"></i>نظام التذاكر
        </a>
        <div class="d-flex align-items-center">
            <a href="manage_users.php" class="btn btn-outline-light">
                <i class="bi bi-arrow-right me-1"></i> رجوع
            </a>
        </div>
    </div>
</nav>

<!-- المحتوى الرئيسي -->
<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="password-card p-4">
                <!-- العنوان -->
                <div class="text-center mb-4">
                    <h4 class="fw-bold text-dark">
                        <i class="bi bi-key-fill me-2"></i>
                        إعادة تعيين كلمة المرور
                    </h4>
                    <p class="text-muted">المستخدم: <?= htmlspecialchars($target_user['username']) ?></p>
                </div>

                <!-- رسائل النظام -->
                <?php if (isset($error)): ?>
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div><?= $error ?></div>
                </div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                <div class="alert alert-success d-flex align-items-center">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <div><?= $success ?></div>
                </div>
                <?php else: ?>

                <!-- نموذج إعادة التعيين -->
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label fw-bold">كلمة المرور الجديدة</label>
                        <input type="password" name="new_password" 
                               class="form-control form-control-lg" 
                               id="newPassword"
                               required
                               placeholder="أدخل كلمة مرور جديدة">
                        <div class="password-strength" id="passwordStrength"></div>
                        <small class="text-muted">يجب أن تحتوي على 8 أحرف على الأقل</small>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-custom btn-lg">
                            <i class="bi bi-arrow-clockwise me-2"></i> تحديث كلمة المرور
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>



<script>
// مؤشر قوة كلمة المرور
document.getElementById('newPassword').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    let strength = 0;
    
    if (password.length >= 8) strength += 1;
    if (password.match(/[a-z]/)) strength += 1;
    if (password.match(/[A-Z]/)) strength += 1;
    if (password.match(/[0-9]/)) strength += 1;
    if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
    
    strengthBar.style.width = (strength * 20) + '%';
    strengthBar.style.backgroundColor = 
        strength < 2 ? '#dc3545' : 
        strength < 4 ? '#fd7e14' : '#28a745';
});
</script>


</body>
</html>