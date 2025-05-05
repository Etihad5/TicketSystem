<?php
session_start();
require 'db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];

// معالجة تحديث البيانات
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // تحديث المعلومات الأساسية
    // في قسم تحديث البيانات الأساسية
    if (isset($_POST['update_profile'])) {
        // إزالة حقل الموقع من عملية التحديث
        $username = trim($_POST['username']);
        $email = trim($_POST['email'] ?? '');

        if (empty($username)) {
            $error = "يجب إدخال اسم المستخدم!";
        } else {
            try {
                // استعلام التحديث بدون الموقع
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                $stmt->execute([$username, $email, $user_id]);

                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['email'] = $email;

                $success = "تم تحديث البيانات بنجاح!";
            } catch (PDOException $e) {
                $error = "خطأ في التحديث: " . $e->getMessage();
            }
        }
    }


    // تغيير كلمة المرور
    if (isset($_POST['change_password'])) {
        $current_pass = $_POST['current_password'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
            $error = "جميع حقول كلمة المرور مطلوبة!";
        } elseif ($new_pass !== $confirm_pass) {
            $error = "كلمة المرور الجديدة غير متطابقة!";
        } elseif (strlen($new_pass) < 8) {
            $error = "كلمة المرور يجب أن تكون 8 أحرف على الأقل!";
        } else {
            // التحقق من كلمة المرور الحالية
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $db_password = $stmt->fetchColumn();

            if (hash('sha256', $current_pass) !== $db_password) {
                $error = "كلمة المرور الحالية غير صحيحة!";
            } else {
                $new_password_hashed = hash('sha256', $new_pass);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$new_password_hashed, $user_id]);
                $success = "تم تغيير كلمة المرور بنجاح!";
            }
        }
    }
}

// جلب أحدث بيانات المستخدم
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>الملف الشخصي - نظام التذاكر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .profile-header {
            border-bottom: 2px solid #f8b400;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }

        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .form-label {
            font-weight: 500;
            color: #004445;
        }
    </style>
</head>

<body>

    <!-- شريط التنقل (نفس الموجود في الملفات الأخرى) -->
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
                            <a class="nav-link" href="manage_users.php"><i class="bi bi-people me-1"></i>إدارة
                                المستخدمين</a>
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
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="userDropdown"
                            data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= htmlspecialchars($user['username']) ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text">الدور: <?= htmlspecialchars($user['role']) ?></span>
                            </li>
                            <li><span class="dropdown-item-text">الموقع:
                                    <?= htmlspecialchars($user['location'] ?? 'عام') ?></span></li>
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
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="profile-card">
                    <!-- العنوان -->
                    <div class="profile-header text-center mb-4">
                        <h3 class="fw-bold">
                            <i class="bi bi-person-circle me-2"></i>
                            الملف الشخصي
                        </h3>
                    </div>

                    <!-- رسائل النظام -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div><?= $error ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success d-flex align-items-center mb-4">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <div><?= $success ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- نموذج تعديل البيانات -->
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">اسم المستخدم</label>
                                    <input type="text" name="username" class="form-control"
                                        value="<?= htmlspecialchars($user_data['username']) ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">البريد الإلكتروني</label>
                                    <input type="email" name="email" class="form-control"
                                        value="<?= htmlspecialchars($user_data['email'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">الموقع</label>
                                    <input type="text" class="form-control"
                                        value="<?= htmlspecialchars($user_data['location']) ?>" readonly>
                                </div>
                            </div>

                            <div class="col-12 mt-4">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> حفظ التعديلات
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- نموذج تغيير كلمة المرور -->
                    <hr class="my-5">
                    <form method="POST">
                        <h5 class="mb-4 fw-bold"><i class="bi bi-shield-lock me-2"></i>تغيير كلمة المرور</h5>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">كلمة المرور الحالية</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">كلمة المرور الجديدة</label>
                                    <input type="password" name="new_password" id="new_password" class="form-control"
                                        minlength="8" required>
                                    <div class="password-strength" id="passwordStrength"></div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">تأكيد كلمة المرور</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                            </div>

                            <div class="col-12">
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="bi bi-key me-1"></i> تغيير كلمة المرور
                                </button>
                            </div>

                        </div>
                    </form>

                </div>
            </div>
        </div>

        </div>
    </main>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // مؤشر قوة كلمة المرور (نفس الموجود في add_user.php)
        document.getElementById('new_password').addEventListener('input', function () {
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
                    strength < 4 ? '#fd7e14' : '#198754';
        });
    </script>

</body>

</html>