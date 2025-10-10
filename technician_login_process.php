<?php
session_start();
// ใช้ db_connect.php ที่มีอยู่แล้ว
require_once __DIR__ . '/db_connect.php';

// รับค่าจากฟอร์ม login ของช่าง
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// ตรวจสอบว่ากรอกข้อมูลครบหรือไม่
if ($username === '' || $password === '') {
    $_SESSION['error'] = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    // ส่งกลับไปหน้า login ของช่าง
    header('Location: technician_login.php');
    exit;
}

// === จุดที่ 1: เปลี่ยน SQL ให้ค้นหาจากตาราง technicians และดึง fullname มาด้วย ===
$stmt = $conn->prepare("SELECT id, username, fullname, password_hash FROM technicians WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$res = $stmt->get_result();
// ============ โค้ดสำหรับ Debug เริ่ม (วางต่อจาก $res = $stmt->get_result();) ============

if ($res->num_rows === 0) {
    // กรณีนี้คือหา username 'peerapat' ไม่เจอในตารางเลย
    die("DEBUG MODE: ไม่พบ username 'peerapat' ในตาราง `technicians`");
}

$row = $res->fetch_assoc();
echo "DEBUG MODE: ข้อมูลที่ดึงมาจากฐานข้อมูล:<br>";
var_dump($row); // แสดงข้อมูลทั้งหมดที่ดึงมา รวมถึง hash

$password_from_form = $password; // รหัสผ่านจากฟอร์ม
$hash_from_db = $row['password_hash']; // hash จากฐานข้อมูล
$is_password_correct = password_verify($password_from_form, $hash_from_db); // ตรวจสอบรหัสผ่าน

echo "<hr>";
echo "รหัสผ่านที่กรอก: " . htmlspecialchars($password_from_form) . "<br>";
echo "Hash จากฐานข้อมูล: " . htmlspecialchars($hash_from_db) . "<br>";
echo "ผลการตรวจสอบรหัสผ่าน (password_verify) คือ: ";
var_dump($is_password_correct); // แสดงผลลัพธ์เป็น true (ถูกต้อง) หรือ false (ผิด)

exit; // *** สำคัญมาก: สั่งให้สคริปต์หยุดทำงานตรงนี้เลย จะได้ไม่ redirect ไปไหน ***

// ============ โค้ดสำหรับ Debug จบ ============


// โค้ดเดิมที่ใช้เช็คจริงๆ จะอยู่ข้างล่างต่อไป (ตอนนี้จะไม่ถูกรันเพราะเรา exit ไปก่อน)
if ($res && $res->num_rows === 1) {
// ...

// ตรวจสอบว่าเจอ username นี้ในระบบหรือไม่
if ($res && $res->num_rows === 1) {
    $row = $res->fetch_assoc();
    
    // ตรวจสอบรหัสผ่านที่ hash ไว้
    if (password_verify($password, $row['password_hash'])) {
        // ล็อกอินสำเร็จ
        session_regenerate_id(true);

        // === จุดที่ 2: ตั้ง Session สำหรับ Technician ===
        $_SESSION['technician_id'] = $row['id'];
        $_SESSION['technician_username'] = $row['username'];
        $_SESSION['technician_fullname'] = $row['fullname']; // เก็บชื่อเต็มไว้ใช้งาน

        // === จุดที่ 3: ส่งไปหน้า Dashboard ของช่าง ===
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
    header('Location: technician_login.php'); // แก้ไข .php ที่ขาดไป
    exit;
}