<?php
// changes_ping.php — บอกหน้าแอดมินว่ามีการเปลี่ยนแปลงข้อมูลหรือยัง
// ใช้ตรวจทั้ง: งานใหม่, เปลี่ยนสถานะ, ลบงาน

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Bangkok');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
$conn->set_charset("utf8mb4");

// รวมสรุปยอด + max id + เวลาล่าสุดของการแจ้ง
$sql = "
  SELECT
    SUM(status='new')         AS c_new,
    SUM(status='in_progress') AS c_inp,
    SUM(status='done')        AS c_done,
    COUNT(*)                  AS c_all,
    MAX(id)                   AS max_id,
    UNIX_TIMESTAMP(MAX(report_date)) AS last_ts
  FROM device_reports
";
$res = $conn->query($sql)->fetch_assoc() ?: [
  'c_new'=>0,'c_inp'=>0,'c_done'=>0,'c_all'=>0,'max_id'=>0,'last_ts'=>0
];

$payload = [
  'new'        => (int)$res['c_new'],
  'in_progress'=> (int)$res['c_inp'],
  'done'       => (int)$res['c_done'],
  'all'        => (int)$res['c_all'],
  'max_id'     => (int)$res['max_id'],
  'last_ts'    => (int)$res['last_ts'],
];

// สร้างลายเซ็นเพื่อเทียบการเปลี่ยนแปลงครั้งก่อนหน้า
$sig = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));

echo json_encode([
  'ok'  => true,
  'sig' => $sig,
  'data'=> $payload
], JSON_UNESCAPED_UNICODE);

$conn->close();
