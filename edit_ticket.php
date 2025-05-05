<?php
session_start();
require 'db.php';

// التحقق من الصلاحيات
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'support'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$ticket_id = $_GET['id'] ?? 0;

// جلب بيانات التذكرة
$stmt = $conn->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("❌ التذكرة غير موجودة!");
}

// معالجة التعديل
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];

    try {
        $conn->beginTransaction();

        // تحديث التذكرة
        $stmt = $conn->prepare("UPDATE tickets SET title=?, description=?, status=? WHERE id=?");
        $stmt->execute([$title, $description, $status, $ticket_id]);

        // تسجيل الإشعار
        $content = sprintf(
            "✏️ قام %s بتعديل التذكرة #%d - الحالة الجديدة: %s",
            htmlspecialchars($user['username']),
            $ticket_id,
            htmlspecialchars($status)
        );

        $stmt_notif = $conn->prepare("INSERT INTO notifications 
            (user_id, content, type, related_id) 
            VALUES (?, ?, 'private', ?)");
        $stmt_notif->execute([$ticket['reporter'], $content, $ticket_id]);

        $conn->commit();
        header("Location: view_ticket.php?id=$ticket_id&updated=1");
        exit;

    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "حدث خطأ: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل التذكرة #<?php echo $ticket['id']; ?> - نظام التذاكر</title>
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

        .ticket-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }

        .form-label {
            font-weight: 500;
            color: var(--secondary-color);
        }

        .update-alert {
            border-left: 4px solid #2c786c;
            background: #f8f9fa;
            animation: slideIn 0.5s ease;
        }

        .action-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #2c786c;
        }

        .user-badge {
            background: #004445;
            color: white;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.9em;
        }

        .animated-notification {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.02);
            }

            100% {
                transform: scale(1);
            }
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
            }

            to {
                transform: translateX(0);
            }
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

        .ticket-info {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .info-label {
            font-weight: bold;
            color: #555;
            min-width: 120px;
            display: inline-block;
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
                    <?php if ($user['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php"><i class="bi bi-people me-1"></i>إدارة
                                المستخدمين</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" id="userDropdown"
                            data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
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
            <h2 class="fw-bold text-dark">
                <i class="bi bi-pencil-square me-2"></i>
                تعديل التذكرة #<?php echo $ticket['id']; ?>
            </h2>
            <div>
                <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-outline-primary me-2">
                    <i class="bi bi-eye me-1"></i>عرض التذكرة
                </a>
                <a href="tickets.php" class="btn btn-outline-secondary">
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

        <!-- معلومات التذكرة الأساسية -->
        <div class="ticket-info mb-4">
            <div class="row">
                <div class="col-md-6">
                    <p><span class="info-label">المُبلّغ:</span>
                        <?php echo htmlspecialchars($ticket['reporter_name'] ?? 'مجهول'); ?></p>
                    <p><span class="info-label">تاريخ الإنشاء:</span>
                        <?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></p>
                </div>
                <div class="col-md-6">
                    <p><span class="info-label">المسؤول الحالي:</span>
                        <?php echo htmlspecialchars($ticket['assignee_name'] ?? 'غير معين'); ?></p>
                    <p><span class="info-label">آخر تحديث:</span>
                        <?php echo date('Y-m-d H:i', strtotime($ticket['updated_at'] ?? $ticket['created_at'])); ?></p>
                </div>
            </div>
        </div>

        <!-- نموذج التعديل -->
        <div class="card ticket-card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-4">
                                <label class="form-label">عنوان التذكرة</label>
                                <input type="text" name="title" class="form-control"
                                    value="<?php echo htmlspecialchars($ticket['title']); ?>" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">وصف التذكرة</label>
                                <textarea name="description" class="form-control" rows="6"><?php
                                echo htmlspecialchars($ticket['description']);
                                ?></textarea>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="mb-4">
                                <label class="form-label">القسم</label>
                                <select name="department" class="form-select" required>
                                    <option value="IT" <?= $ticket['department'] == 'IT' ? 'selected' : '' ?>>IT</option>
                                    <option value="المبيعات" <?= $ticket['department'] == 'المبيعات' ? 'selected' : '' ?>>
                                        المبيعات</option>
                                    <option value="المالية" <?= $ticket['department'] == 'المالية' ? 'selected' : '' ?>>
                                        المالية</option>
                                    <option value="التسويق" <?= $ticket['department'] == 'التسويق' ? 'selected' : '' ?>>
                                        التسويق</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">حالة التذكرة</label>
                                <select name="status" class="form-select" required>
                                    <option value="قيد الانتظار" <?= $ticket['status'] == 'قيد الانتظار' ? 'selected' : '' ?>>قيد الانتظار</option>
                                    <option value="معلقة" <?= $ticket['status'] == 'معلقة' ? 'selected' : '' ?>>معلقة
                                    </option>
                                    <option value="مكتملة" <?= $ticket['status'] == 'مكتملة' ? 'selected' : '' ?>>مكتملة
                                    </option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">أولوية التذكرة</label>
                                <select name="priority" class="form-select" required>
                                    <option value="منخفضة" <?= $ticket['priority'] == 'منخفضة' ? 'selected' : '' ?>>منخفضة
                                    </option>
                                    <option value="متوسطة" <?= $ticket['priority'] == 'متوسطة' ? 'selected' : '' ?>>متوسطة
                                    </option>
                                    <option value="عالية" <?= $ticket['priority'] == 'عالية' ? 'selected' : '' ?>>عالية
                                    </option>
                                </select>
                            </div>

                            <?php if (in_array($user['role'], ['admin', 'support'])): ?>
                                <div class="mb-4">
                                    <label class="form-label">تعيين مسؤول</label>
                                    <select name="assignee" class="form-select">
                                        <option value="">-- اختر مسؤول --</option>
                                        <?php foreach ($users as $user_item): ?>
                                            <option value="<?php echo $user_item['id']; ?>"
                                                <?= $ticket['assignee'] == $user_item['id'] ? 'selected' : '' ?>>
                                                <?php echo htmlspecialchars($user_item['username']); ?>
                                                (<?php echo htmlspecialchars($user_item['role']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="mb-4">
                                <label class="form-label">تاريخ الاستحقاق</label>
                                <input type="date" name="due_date" class="form-control"
                                    value="<?php echo htmlspecialchars($ticket['due_date']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">ملاحظات التعديل (اختياري)</label>
                        <textarea name="notes" class="form-control" rows="3"
                            placeholder="أدخل أي ملاحظات حول التعديلات التي قمت بها..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="tickets.php" class="btn btn-outline-secondary">إلغاء</a>
                        <button type="submit" class="btn btn-custom">
                            <i class="bi bi-save me-1"></i>حفظ التعديلات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>