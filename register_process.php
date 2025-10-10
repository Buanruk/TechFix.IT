<?php
session_start();
require_once __DIR__ . '/db_connect.php'; // หรือ 'db_connect.php' ตามตำแหน่งไฟล์ของคุณ

// รับค่าจากฟอร์ม
$fullname = trim($_POST['fullname'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// 1. ตรวจสอบว่ากรอกข้อมูลครบหรือไม่
if (empty($fullname) || empty($username) || empty($password) || empty($password_confirm)) {
    $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    header('Location: register.php');
    exit;
}

// 2. ตรวจสอบว่ารหัสผ่านตรงกันหรือไม่
if ($password !== $password_confirm) {
    $_SESSION['error'] = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
    header('Location: register.php');
    exit;
}

// 3. ตรวจสอบว่า username นี้มีคนใช้แล้วหรือยัง
$stmt = $conn->prepare("SELECT id FROM technicians WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $_SESSION['error'] = 'ชื่อผู้ใช้นี้มีคนใช้งานแล้ว';
    header('Location: register.php');
    exit;
}
$stmt->close();

// 4. เข้ารหัสผ่าน (Hash) เพื่อความปลอดภัย
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// 5. เตรียมคำสั่ง SQL เพื่อเพิ่มข้อมูลลงในตาราง technicians
$stmt = $conn->prepare("INSERT INTO technicians (fullname, username, password_hash) VALUES (?, ?, ?)");
if ($stmt === false) {
    // กรณี prepare statement ล้มเหลว
    $_SESSION['error'] = 'เกิดข้อผิดพลาดกับฐานข้อมูล';
    header('Location: register.php');
    exit;
}

$stmt->bind_param("sss", $fullname, $username, $password_hash);

// 6. Execute คำสั่งและตรวจสอบผลลัพธ์
if ($stmt->execute()) {
    // สมัครสำเร็จ
    $_SESSION['success'] = 'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ';
    header('Location: technician_login.php'); // ส่งไปหน้า login ของช่าง
    exit;
} else {
    // สมัครไม่สำเร็จ
    $_SESSION['error'] = 'การสมัครสมาชิกล้มเหลว';
    header('Location: register.php');
    exit;
}

$stmt->close();
$conn->close();
?>