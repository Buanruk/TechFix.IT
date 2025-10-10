<?php
// changes_ping.php (เวอร์ชันใหม่ รองรับ Role-based)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Bangkok');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

session_start();

// รับค่า role จาก URL
$role = $_GET['role'] ?? '';

$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
$conn->set_charset("utf8mb4");

// เตรียม SQL พื้นฐาน
$baseSql = "
    SELECT
        SUM(status='new') AS c_new,
        SUM(status='in_progress') AS c_inp,
        SUM(status='done') AS c_done,
        COUNT(*) AS c_all,
        MAX(id) AS max_id,
        UNIX_TIMESTAMP(MAX(report_date)) AS last_ts
    FROM device_reports
";

$res = null;

// ตรวจสอบ role แล้วเลือกวิธีดึงข้อมูล
if ($role === 'admin') {
    // ✅ ถ้าเป็น admin, ใช้โค้ดเดิมที่ดึงข้อมูลทั้งหมด ไม่ต้องแก้
    $res = $conn->query($baseSql)->fetch_assoc();

} elseif ($role === 'technician') {
    // ✅ ถ้าเป็น technician, ต้องแน่ใจว่า login อยู่ แล้วกรองข้อมูล
    if (isset($_SESSION['technician_id'])) {
        $technician_id = (int)$_SESSION['technician_id'];
        
        $sql = $baseSql . " WHERE technician_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $technician_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// ถ้าไม่มีผลลัพธ์ (เช่น role ไม่ถูกต้อง หรือ technician ยังไม่ login) ให้ใช้ค่า default
$res = $res ?: [
    'c_new'=>0,'c_inp'=>0,'c_done'=>0,'c_all'=>0,'max_id'=>0,'last_ts'=>0
];

// ส่วนที่เหลือทำงานเหมือนเดิม
$payload = [
    'new'         => (int)($res['c_new'] ?? 0),
    'in_progress' => (int)($res['c_inp'] ?? 0),
    'done'        => (int)($res['c_done'] ?? 0),
    'all'         => (int)($res['c_all'] ?? 0),
    'max_id'      => (int)($res['max_id'] ?? 0),
    'last_ts'     => (int)($res['last_ts'] ?? 0),
];

// สร้างลายเซ็นเพื่อเทียบการเปลี่ยนแปลงครั้งก่อนหน้า
$sig = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));

echo json_encode([
    'ok'   => true,
    'sig'  => $sig,
    'data' => $payload
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>