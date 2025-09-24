<?php
session_start();
require_once __DIR__ . '/db_connect.php';
// ===== DB =====
$conn = new mysqli("localhost", "phpadmin", "StrongPassword123!", "techfix");
if ($conn->connect_error) { die("DB Error"); }
$conn->set_charset("utf8");

// ป้องกัน XSS เบื้องต้น
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['error'] = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    header('Location: admin_login.php');
    exit;
}

$stmt = $conn->prepare("SELECT id, password_hash FROM admins WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows === 1) {
    $row = $res->fetch_assoc();
    if (password_verify($password, $row['password_hash'])) {
        // ล็อกอินสำเร็จ
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_username'] = $username;
        header('Location: admin_dashboard.php');
        exit;
    } else {
        $_SESSION['error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        header('Location: admin_login.php');
        exit;
    }
} else {
    $_SESSION['error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    header('Location: admin_login.php');
    exit;
}
