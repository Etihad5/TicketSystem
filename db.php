<?php
// إعداد الاتصال بقاعدة البيانات
$servername = "localhost";
$username = "root"; // الافتراضي في XAMPP
$password = "root";     // عادة فارغ
$database = "ticketsystem";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$database;charset=utf8mb4", $username, $password);
    // تفعيل تقارير الأخطاء
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}
?>
