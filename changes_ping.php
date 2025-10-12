<?php
// changes_ping.php (เวอร์ชันใหม่ล่าสุด รองรับทุก Role)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Bangkok');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

session_start();

// รับค่า role จาก URL
$role = $_GET['role'] ?? '';

$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
$conn->set_charset("utf8mb4");

// --- Logic สำหรับหน้ารายงานซ่อม (Admin & Technician) ---
if ($role === 'admin' || $role === 'technician') {

    $baseSql = "
        SELECT
            SUM(status='new') AS c_new,
            SUM(status='in_progress') AS c_inp,
            SUM(status='done') AS c_done,
            COUNT(*) AS c_all,
            MAX(id) AS max_id,
            UNIX_TIMESTAMP(MAX(updated_at)) AS last_ts
        FROM device_reports
    ";
    
    $res = null;

    if ($role === 'admin' && isset($_SESSION['admin_id'])) {
        $res = $conn->query($baseSql)->fetch_assoc();

    } elseif ($role === 'technician' && isset($_SESSION['technician_id'])) {
        $technician_id = (int)$_SESSION['technician_id'];
        
        $sql = $baseSql . " WHERE technician_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $technician_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    $res = $res ?: ['c_new'=>0,'c_inp'=>0,'c_done'=>0,'c_all'=>0,'max_id'=>0,'last_ts'=>0];

    $payload = [
        'new'         => (int)($res['c_new'] ?? 0),
        'in_progress' => (int)($res['c_inp'] ?? 0),
        'done'        => (int)($res['c_done'] ?? 0),
        'all'         => (int)($res['c_all'] ?? 0),
        'max_id'      => (int)($res['max_id'] ?? 0),
        'last_ts'     => (int)($res['last_ts'] ?? 0),
    ];

    $sig = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));

    echo json_encode(['ok' => true, 'sig' => $sig, 'data' => $payload], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit(); // จบการทำงานสำหรับ role นี้
}

// ===== ส่วนที่เพิ่มเข้ามาสำหรับหน้าจัดการช่าง =====
elseif ($role === 'technicians_list' && isset($_SESSION['admin_id'])) {
    
    $sql = "SELECT 
                COUNT(*) as count,
                UNIX_TIMESTAMP(GREATEST(
                    COALESCE(MAX(created_at), '2000-01-01'), 
                    COALESCE(MAX(last_login), '2000-01-01')
                )) as latest_ts 
            FROM technicians";

    $res = $conn->query($sql)->fetch_assoc();
    
    $payload = [
        'count' => (int)($res['count'] ?? 0),
        'latest_ts' => (int)($res['latest_ts'] ?? 0)
    ];

    $sig = hash('sha256', json_encode($payload));

    echo json_encode(['ok' => true, 'sig' => $sig, 'data' => $payload], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit(); // จบการทำงานสำหรับ role นี้
}

// --- กรณีไม่ตรงกับ role ไหนเลย ---
echo json_encode(['ok' => true, 'sig' => hash('sha256', 'default'), 'data' => []]);
$conn->close();
?>