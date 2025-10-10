<?php
// changes_ping.php (เวอร์ชันอัปเดตสำหรับ Admin และ Technician)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Bangkok');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// ✅ 1. เริ่ม session เพื่อตรวจสอบสิทธิ์
session_start();

// ✅ 2. ตรวจสอบว่ามีการล็อกอินหรือไม่ (ไม่ว่าจะเป็น Admin หรือ Technician)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['technician_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
$conn->set_charset("utf8mb4");

// ✅ 3. สร้าง SQL ตามสิทธิ์ของผู้ใช้
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

$sql = '';
$params = [];
$types = '';

if (isset($_SESSION['admin_id'])) {
    // ถ้าเป็น Admin, ใช้ SQL เดิม (ดูข้อมูลทั้งหมด)
    $sql = $baseSql;
} elseif (isset($_SESSION['technician_id'])) {
    // ถ้าเป็น Technician, เพิ่ม WHERE clause เพื่อกรองเฉพาะงานของตัวเอง
    $technician_id = (int)$_SESSION['technician_id'];
    $sql = $baseSql . " WHERE technician_id = ?";
    $types = "i";
    $params[] = $technician_id;
}

// ✅ 4. ใช้ Prepared Statement เพื่อความปลอดภัยและรองรับทั้งสองกรณี
$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc() ?: [
    'c_new'=>0, 'c_inp'=>0, 'c_done'=>0, 'c_all'=>0, 'max_id'=>0, 'last_ts'=>0
];
$stmt->close();


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