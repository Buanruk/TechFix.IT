<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// 1. เพิ่มการตรวจสอบสิทธิ์ Admin (สำคัญมาก!)
if (!isset($_SESSION['admin_id'])) {
    // ถ้าไม่ใช่ Admin ให้หยุดการทำงานทันที
    die('Access Denied: You do not have permission to perform this action.');
}

// รับค่าจากฟอร์มที่ Admin ส่งมา
$fullname = trim($_POST['fullname'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// 2. ตรวจสอบว่ากรอกข้อมูลครบหรือไม่
if (empty($fullname) || empty($username) || empty($password)) {
    $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    // หากข้อมูลไม่ครบ ให้กลับไปหน้าฟอร์มของ Admin
    header('Location: admin_create_technician.php');
    exit;
}

// 3. ตรวจสอบว่า username นี้มีคนใช้แล้วหรือยัง
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

// 4. เข้ารหัสผ่าน (Hash)
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// 5. เตรียมคำสั่ง SQL เพื่อเพิ่มข้อมูล
$stmt = $conn->prepare("INSERT INTO technicians (fullname, username, password_hash) VALUES (?, ?, ?)");
if ($stmt === false) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดกับฐานข้อมูล';
    header('Location: admin_create_technician.php');
    exit;
}
$stmt->bind_param("sss", $fullname, $username, $password_hash);

// 6. Execute และตรวจสอบผลลัพธ์
if ($stmt->execute()) {
    // สร้างบัญชีสำเร็จ
    $_SESSION['success'] = 'สร้างบัญชีช่าง ' . htmlspecialchars($fullname) . ' เรียบร้อยแล้ว!';
    // ส่ง Admin กลับไปที่หน้า Dashboard
    header('Location: admin_dashboard.php');
    exit;
} else {
    // สร้างบัญชีไม่สำเร็จ
    $_SESSION['error'] = 'การสร้างบัญชีล้มเหลว';
    header('Location: admin_create_technician.php');
    exit;
}

$stmt->close();
$conn->close();
?>