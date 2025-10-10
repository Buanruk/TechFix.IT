<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// รับค่าจากฟอร์ม login ของช่าง
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// ตรวจสอบว่ากรอกข้อมูลครบหรือไม่
if ($username === '' || $password === '') {
    $_SESSION['error'] = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    header('Location: technician_login.php');
    exit;
}

// เตรียมคำสั่ง SQL
$sql = "SELECT id, username, fullname, password_hash FROM technicians WHERE username = ?";
$stmt = $conn->prepare($sql);

// --- เพิ่มส่วนตรวจสอบ Error ---
// ตรวจสอบว่าการ prepare สำเร็จหรือไม่ (สำคัญมาก!)
if ($stmt === false) {
    // ถ้าไม่สำเร็จ ให้แสดง Error ของฐานข้อมูล จะได้รู้ว่าผิดพลาดตรงไหน
    die("Error: ไม่สามารถเตรียมคำสั่ง SQL ได้. " . htmlspecialchars($conn->error));
}
// --- จบส่วนตรวจสอบ Error ---

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    
    // ตรวจสอบรหัสผ่าน
    if (password_verify($password, $row['password_hash'])) {
        // ล็อกอินสำเร็จ
        session_regenerate_id(true);

        $_SESSION['technician_id'] = $row['id'];
        $_SESSION['technician_username'] = $row['username'];
        $_SESSION['technician_fullname'] = $row['fullname'];

        // ส่งไปหน้า Dashboard ของช่าง
        header('Location: technician_dashboard.php');
        exit;

    } else {
        // รหัสผ่านไม่ถูกต้อง
        $_SESSION['error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        header('Location: technician_login.php');
        exit;
    }
} else {
    // ไม่พบชื่อผู้ใช้นี้ในระบบ
    $_SESSION['error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    header('Location: technician_login.php');
    exit;
}

$stmt->close();
$conn->close();
?>