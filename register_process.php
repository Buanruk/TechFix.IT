<?php
session_start();
require_once __DIR__ . '/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    die('Access Denied: You do not have permission to perform this action.');
}

// 1. รับค่าจากฟอร์ม (เพิ่ม phone_number)
$fullname = trim($_POST['fullname'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? ''); // เพิ่มบรรทัดนี้
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($fullname) || empty($username) || empty($password)) {
    $_SESSION['error'] = 'กรุณากรอกข้อมูล ชื่อ, ชื่อผู้ใช้ และรหัสผ่านให้ครบ';
    header('Location: admin_create_technician.php');
    exit;
}

$stmt = $conn->prepare("SELECT id FROM technicians WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $_SESSION['error'] = 'ชื่อผู้ใช้นี้มีคนใช้งานแล้ว';
    header('Location: admin_create_technician.php');
    exit;
}
$stmt->close();

$password_hash = password_hash($password, PASSWORD_DEFAULT);

// 2. เตรียมคำสั่ง SQL (เพิ่ม phone_number)
$stmt = $conn->prepare("INSERT INTO technicians (fullname, phone_number, username, password_hash) VALUES (?, ?, ?, ?)");
if ($stmt === false) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดกับฐานข้อมูล';
    header('Location: admin_create_technician.php');
    exit;
}

// 3. Bind Param (เปลี่ยนจาก "sss" เป็น "ssss" และเพิ่มตัวแปร)
$stmt->bind_param("ssss", $fullname, $phone_number, $username, $password_hash);

if ($stmt->execute()) {
    $_SESSION['success'] = 'สร้างบัญชีช่าง ' . htmlspecialchars($fullname) . ' เรียบร้อยแล้ว!';
    header('Location: admin_dashboard.php');
    exit;
} else {
    $_SESSION['error'] = 'การสร้างบัญชีล้มเหลว';
    header('Location: admin_create_technician.php');
    exit;
}

$stmt->close();
$conn->close();
?>