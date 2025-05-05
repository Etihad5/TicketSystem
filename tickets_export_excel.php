<?php
session_start();
require 'vendor/autoload.php';
require 'db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'support', 'tracker'])) {
    die("❌ لا تملك صلاحية تصدير البيانات.");
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('التذاكر');

// رؤوس الأعمدة
$headers = [
    'رقم', 'العنوان', 'الوصف', 'القسم', 'الحالة', 'الأولوية', 
    'المُبلّغ', 'الإنشاء', 'الاستحقاق', 'عدد المراحل', 
    'مكتملة', 'نسبة الإنجاز', 'المراحل (التفاصيل)'
];
$sheet->fromArray($headers, NULL, 'A1');

// جلب التذاكر
$stmt = $conn->query("
    SELECT t.*, u.username AS reporter_name,
    (SELECT COUNT(*) FROM ticket_stages WHERE ticket_id = t.id) AS total_stages,
    (SELECT COUNT(*) FROM ticket_stages WHERE ticket_id = t.id AND status = 'مكتملة') AS completed_stages
    FROM tickets t
    LEFT JOIN users u ON t.reporter = u.id
    ORDER BY t.created_at DESC
");
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$row = 2;
foreach ($tickets as $ticket) {
    $stmtStages = $conn->prepare("SELECT stage_name, status, description, assigned_to, started_at FROM ticket_stages WHERE ticket_id = ?");
    $stmtStages->execute([$ticket['id']]);
    $stages = $stmtStages->fetchAll(PDO::FETCH_ASSOC);
    
    $stagesDetails = [];
    foreach ($stages as $stage) {
        $stagesDetails[] = sprintf(
            "%s (الحالة: %s, المسؤول: %s, التاريخ: %s)",
            $stage['stage_name'],
            $stage['status'],
            $stage['assigned_to'],
            $stage['started_at']
        );
    }
    $stagesText = implode("\n", $stagesDetails);

    $percentage = ($ticket['total_stages'] > 0) 
        ? round(($ticket['completed_stages'] / $ticket['total_stages']) * 100, 1) . '%'
        : '0%';

    $sheet->fromArray([
        $ticket['id'],
        $ticket['title'],
        $ticket['description'],
        $ticket['department'],
        $ticket['status'],
        $ticket['priority'],
        $ticket['reporter_name'] ?? 'غير معروف',
        $ticket['created_at'],
        $ticket['due_date'] ?? 'غير محدد',
        $ticket['total_stages'],
        $ticket['completed_stages'],
        $percentage,
        $stagesText
    ], NULL, 'A' . $row);

    // ------ تلوين حسب الأولوية ------
    $priorityColor = match ($ticket['priority']) {
        'عالية' => 'FF0000', // أحمر
        'متوسطة' => 'FFA500', // برتقالي
        'منخفضة' => '00FF00', // أخضر
        default => 'FFFFFF',
    };
    $sheet->getStyle('F' . $row)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->setStartColor(new Color($priorityColor));

    // ------ تلوين حسب الحالة ------
    $statusColor = match ($ticket['status']) {
        'مكتملة' => '008000', // أخضر داكن
        'قيد الانتظار' => '0000FF', // أزرق
        'معلقة' => '808080', // رمادي
        default => 'FFFFFF',
    };
    $sheet->getStyle('E' . $row)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->setStartColor(new Color($statusColor));

    $row++;
}

// تنسيق الجدول
foreach (range('A', 'M') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$sheet->getStyle('A1:M1')->getFont()->setBold(true);

// تصدير الملف
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="tickets_colored_' . date('Ymd_His') . '.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;