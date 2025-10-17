<?php
declare(strict_types=1);
session_start();
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 1. ตรวจสอบ Admin Login
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die('Access Denied');
}

// 2. ตรวจสอบว่าเป็น POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_technicians.php');
    exit;
}

// 3. รับและตรวจสอบข้อมูล
$id = (int)($_POST['id'] ?? 0);
$fullname = trim((string)($_POST['fullname'] ?? ''));
$username = trim((string)($_POST['username'] ?? ''));
$phone_number = trim((string)($_POST['phone_number'] ?? ''));
$password = trim((string)($_POST['password'] ?? ''));

$redirect_url = 'admin_edit_technician.php?id=' . $id;

if ($id === 0) {
    $_SESSION['error'] = 'ID ไม่ถูกต้อง';
    header('Location: manage_technicians.php');
    exit;
}
if (empty($fullname) || empty($username)) {
    $_SESSION['error'] = 'กรุณากรอก ชื่อ-สกุล และ Username ให้ครบถ้วน';
    header('Location: ' . $redirect_url);
    exit;
}

// 4. เชื่อมต่อ DB
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
    $conn->set_charset('utf8mb4');

    // 5. เตรียมคำสั่ง SQL
    $params = [$fullname, $username, $phone_number];
    $types = "sss";
    $sql_password = "";

    // ตรวจสอบว่ามีการกรอกรหัสผ่านใหม่หรือไม่
    if (!empty($password)) {
        // ถ้ามี ให้ hash รหัสใหม่
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $sql_password = ", password = ?";
        $params[] = $hashed_password;
        $types .= "s";
    }
    
    // เพิ่ม ID เป็นตัวสุดท้ายสำหรับ WHERE
    $params[] = $id;
    $types .= "i";
    
    $sql = "UPDATE technicians SET fullname = ?, username = ?, phone_number = ? $sql_password WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    // 6. ตั้งค่าข้อความและ Redirect
    $_SESSION['success'] = "อัปเดตข้อมูลช่าง '".h($fullname)."' เรียบร้อยแล้ว";
    header('Location: manage_technicians.php');
    exit;

} catch (mysqli_sql_exception $e) {
    // 1062 คือ Error Code สำหรับ Duplicate entry (ข้อมูลซ้ำ)
    if ($e->getCode() === 1062 && str_contains($e->getMessage(), 'username')) {
        $_SESSION['error'] = 'Username นี้ถูกใช้งานแล้ว กรุณาใช้ชื่ออื่น';
    } else {
        $_SESSION['error'] = 'ระบบขัดข้อง: ' . $e->getMessage();
    }
    header('Location: ' . $redirect_url);
    exit;
}
?>