<?php
session_start();
require_once __DIR__ . '/db_connect.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['error'] = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    header('Location: technician_login.php');
    exit;
}

$stmt = $conn->prepare("SELECT id, username, fullname, password_hash FROM technicians WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows === 1) {
    $row = $res->fetch_assoc();
    
    if (password_verify($password, $row['password_hash'])) {
        // ล็อกอินสำเร็จ
        session_regenerate_id(true);

        $_SESSION['technician_id'] = $row['id'];
        $_SESSION['technician_username'] = $row['username'];
        $_SESSION['technician_fullname'] = $row['fullname'];

        // ===== ส่วนที่เพิ่มเข้ามา: อัปเดตเวลา last_login =====
        $updateStmt = $conn->prepare("UPDATE technicians SET last_login = NOW() WHERE id = ?");
        $updateStmt->bind_param("i", $row['id']);
        $updateStmt->execute();
        $updateStmt->close();
        // ======================================================

        header('Location: technician_dashboard.php');
        exit;

    } else {
        $_SESSION['error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        header('Location: technician_login.php');
        exit;
    }
} else {
    $_SESSION['error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    header('Location: technician_login.php');
    exit;
}