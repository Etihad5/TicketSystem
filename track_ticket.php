<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
if (!empty($assigned_user_id)) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, content, type, priority, related_ticket_id) VALUES (?, ?, 'خاص', 'عالية', ?)");
    $stmt->execute([$assigned_user_id, "📌 تم تعيينك لتذكرة: <strong>$title</strong>", $ticket_id]);
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("❌ رقم التذكرة غير صحيح");
}

$ticket_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM tickets WHERE id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);
// تحديث عدد غير المقروء
$unread_count = $conn->prepare("SELECT COUNT(*) FROM notifications 
                              WHERE user_id = ? AND status = 'unread'");
$unread_count->execute([$user['id']]);
$unread_count = $unread_count->fetchColumn();
if (!$ticket) {
    die("❌ التذكرة غير موجودة");
}

$stmt = $conn->prepare("SELECT s.*, u.username FROM ticket_stages s LEFT JOIN users u ON s.assigned_to = u.id WHERE s.ticket_id = ? ORDER BY s.id ASC");
$stmt->execute([$ticket_id]);
$stages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$users = $conn->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);


?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تتبع التذكرة #<?php echo $ticket['id']; ?> - نظام التذاكر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            border: none;
        }
        
        .ticket-header {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
        
        .badge-custom {
            background-color: var(--accent-color);
            color: #333;
        }
        
        .stage-card {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .stage-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stage-completed {
            border-left-color: #198754;
        }
        
        .stage-in-progress {
            border-left-color: var(--accent-color);
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
        hover-scale:hover {
    transform: scale(1.05) translateY(-2px);
    box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4) !important;
}

/* تأثيرات مخصصة للزر */
.hover-scale:hover {
    transform: scale(1.05) translateY(-2px);
}

/* توهج خفيف للتنبيه */
@keyframes glow {
    0% { box-shadow: 0 0 10px rgba(220, 53, 69, 0.3); }
    50% { box-shadow: 0 0 20px rgba(220, 53, 69, 0.5); }
    100% { box-shadow: 0 0 10px rgba(220, 53, 69, 0.3); }
}

.btn-danger {
    animation: glow 2s infinite;
    position: relative;
    overflow: hidden;
}

/* خط متحرك للفت الانتباه */
.btn-danger::after {
    content: "";
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        45deg,
        transparent 25%,
        rgba(255,255,255,0.15) 50%,
        transparent 75%
    );
    animation: shine 3s infinite;
}

@keyframes shine {
    to {
        transform: translate(50%, 50%);
    }
}
    </style>
</head>
<body>

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

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark">
            <i class="bi bi-list-task me-2"></i>
            تتبع التذكرة #<?php echo $ticket['id']; ?>
        </h2>
        <div>
            <a href="tickets.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-right me-1"></i>رجوع
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        </div>
    <?php endif; ?>

    <div class="col-md-12">
    <?php if (trim($ticket['status']) !== 'مكتملة'): ?>
        <div class="position-relative">
            <a href="close_ticket.php?id=<?= $ticket['id'] ?>" 
               class="btn btn-lg btn-danger d-block shadow-lg hover-scale" 
               style="
                   background: linear-gradient(45deg, #004445, #2c786c);
                   border: 3px solid #fff;
                   border-radius: 15px;
                   font-size: 1.2rem;
                   transition: all 0.3s ease;
               "
               onclick="return confirm('هل أنت متأكد من إغلاق التذكرة؟');">
                <div class="d-flex align-items-center gap-2 justify-content-center">
                    <i class="bi bi-shield-lock-fill fs-3"></i>
                    <div class="text-center">
                        <div>إغلاق التذكرة</div>
                        <small class="opacity-75">نهائي وغير قابل للتراجع</small>
                    </div>
                </div>
            </a>
            
            <!-- مؤشر تنبيهي -->
            <div class="position-absolute top-0 start-0 translate-middle">
                <span class="badge bg-white text-danger border-danger border-2 rounded-pill shadow-sm">
                    <i class="bi bi-exclamation-lg me-1"></i>هام
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>
            
            <div class="row mt-3">
                <div class="col-md-3">
                    <p><strong>القسم:</strong> <?php echo htmlspecialchars($ticket['department']); ?></p>
                </div>
                <div class="col-md-3">
                    <p><strong>الأولوية:</strong> 
                        <span class="badge <?php 
                            echo $ticket['priority'] === 'عالية' ? 'bg-danger' : 
                                ($ticket['priority'] === 'متوسطة' ? 'bg-warning text-dark' : 'bg-success'); 
                        ?>">
                            <?php echo htmlspecialchars($ticket['priority']); ?>
                        </span>
                    </p>
                </div>
                <div class="col-md-3">
                    <p><strong>الحالة:</strong> 
                        <span class="badge <?php 
                            echo $ticket['status'] === 'مكتملة' ? 'bg-success' : 
                                ($ticket['status'] === 'معلقة' ? 'bg-secondary' : 'bg-primary'); 
                        ?>">
                            <?php echo htmlspecialchars($ticket['status']); ?>
                        </span>
                    </p>
                </div>
                <div class="col-md-3">
                    <p><strong>تاريخ الإنشاء:</strong> <?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="section-title">
                <i class="bi bi-plus-circle me-2"></i>
                إضافة مرحلة جديدة
            </h5>
            <?php if (trim($ticket['status']) !== 'مكتملة'): ?>
            <form method="POST" action="add_stage.php">
                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">اسم المرحلة</label>
                        <input type="text" name="stage_name" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">المستخدم المكلف</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">-- اختر مستخدم --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo $u['username']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">الحالة</label>
                        <select name="status" class="form-select" required>
                            <option value="قيد التنفيذ">قيد التنفيذ</option>
                            <option value="مكتملة">مكتملة</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">تاريخ البدء</label>
                        <input type="datetime-local" name="started_at" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">وصف / شرح الحل</label>
                        <textarea name="description" class="form-control" rows="1" placeholder="اكتب تفاصيل المرحلة أو خطوات الحل..."></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-custom">
                        <i class="bi bi-save me-1"></i>حفظ المرحلة
                    </button>
                </div>
            </form>
            <?php else: ?>
    <div class="alert alert-warning text-center fw-bold">
        🚫 لا يمكن إضافة مراحل لتذكرة مغلقة.
    </div>
<?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="section-title">
                <i class="bi bi-list-check me-2"></i>
                مراحل التذكرة
            </h5>
            
            <?php if (empty($stages)): ?>
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    لا توجد مراحل مسجلة لهذه التذكرة بعد
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="15%">المرحلة</th>
                                <th width="25%">الوصف</th>
                                <th width="15%">المكلف</th>
                                <th width="10%">الحالة</th>
                                <th width="15%">تاريخ البدء</th>
                                <th width="20%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stages as $stage): ?>
                                <tr class="<?php echo $stage['status'] === 'مكتملة' ? 'table-success' : ''; ?>">
                                    <td><?php echo htmlspecialchars($stage['stage_name']); ?></td>
                                    <td><?php echo nl2br(htmlspecialchars($stage['description'] ?? '')); ?></td>
                                    <td><?php echo $stage['username'] ?? '<span class="text-muted">غير محدد</span>'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $stage['status'] === 'مكتملة' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                            <?php echo $stage['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($stage['started_at'])); ?></td>
                                    <td>
                                        <?php if ($stage['status'] === 'قيد التنفيذ'): ?>
                                            <div class="d-flex gap-2">
                                                <a href="mark_stage_done.php?id=<?php echo $stage['id']; ?>&ticket=<?php echo $ticket_id; ?>" 
                                                   class="btn btn-sm btn-success">
                                                   <i class="bi bi-check-circle"></i> إنهاء
                                                </a>
                                                <?php if ($user['role'] === 'admin'): ?>
    <form action="reassign_stage.php" method="POST" class="d-inline">
        <input type="hidden" name="stage_id" value="<?php echo $stage['id']; ?>">
        <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
        <select name="new_user_id" class="form-select form-select-sm d-inline w-auto">
            <?php foreach ($users as $u): ?>
                <option value="<?php echo $u['id']; ?>"><?php echo $u['username']; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-outline-primary">🔁 نقل</button>
    </form>
<?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<div class="text-muted small">صلاحية المستخدم: <?php echo $user['role']; ?></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>