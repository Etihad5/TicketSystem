<?php
session_start();
require 'db.php'; // الاتصال بقاعدة البيانات

// إذا تم إرسال النموذج
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = hash('sha256', $_POST['password']); // تشفير كلمة المرور

    // التحقق من المستخدم
    $stmt = $conn->prepare("SELECT id, username, role, location FROM users WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // حفظ بيانات الجلسة
        $_SESSION['user'] = $user;
        // تحديث وقت آخر دخول
$stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$stmt->execute([$user['id']]);
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "اسم المستخدم أو كلمة المرور غير صحيحة!";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام التذاكر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c786c;
            --secondary-color: #004445;
            --accent-color: #f8b400;
            --light-bg:#f8f9fa;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Tajawal', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-container {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
        }
        
        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .login-header {
            background-color: var(--secondary-color);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .login-body {
            padding: 30px;
            background-color: white;
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(44, 120, 108, 0.25);
        }
        
        .btn-login {
            background-color: var(--primary-color);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            background-color: var(--secondary-color);
        }
        
        .input-group-text {
            background-color: #f1f1f1;
            border: 1px solid #ddd;
        }
        
        .login-logo {
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: -50px auto 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .login-logo i {
            color: var(--primary-color);
            font-size: 2.5rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h4><i class="bi bi-ticket-detailed"></i> نظام إدارة التذاكر</h4>
            </div>
            <div class="login-body">
                <div class="login-logo">
                    <i class="bi bi-shield-lock"></i>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="mt-3">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted">اسم المستخدم</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control" required autofocus placeholder="أدخل اسم المستخدم">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted">كلمة المرور</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" required placeholder="أدخل كلمة المرور">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-login text-white" type="submit">
                            <i class="bi bi-box-arrow-in-right me-2"></i>تسجيل الدخول
                        </button>
                    </div>
                </form>
                
                <div class="text-center mt-4 text-muted">
                    <small>جميع الحقوق محفوظة &copy; <?php echo date('Y'); ?></small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>