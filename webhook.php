<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

header('Content-Type: application/json; charset=utf-8');

/* =========================
   Utils
   ========================= */
function log_to($fname, $text) {
  @file_put_contents(__DIR__ . "/$fname", '['.date('Y-m-d H:i:s')."] $text\n", FILE_APPEND);
}

/** หา userId แบบ recursive */
function find_user_id_recursive($arr) {
  if (!is_array($arr)) return null;
  foreach ($arr as $k => $v) {
    if ($k === 'userId' && is_string($v) && $v !== '') return $v;
    if (is_array($v)) {
      $r = find_user_id_recursive($v);
      if ($r) return $r;
    }
  }
  return null;
}

/** ลอก prefix "ปัญหา:" / "อาการ:" / "issue:" ออกกันซ้ำ */
function clean_issue($txt) {
  $txt = html_entity_decode((string)$txt, ENT_QUOTES, 'UTF-8');
  // ตัดคำนำหน้าที่มักใส่มา และช่องว่าง/เครื่องหมายหลังคำนำหน้า
  $txt = preg_replace('/^\s*(ปัญหา(เรื่อง)?|อาการ|issue)\s*[:：\-]?\s*/iu', '', $txt);
  // เก็บเว้นวรรคสวย ๆ
  $txt = preg_replace('/\s+/u', ' ', trim($txt));
  return $txt;
}

/* =========================
   รับข้อมูลจาก Dialogflow
   ========================= */
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
log_to('df_request.log', $raw);

// ข้อความผู้ใช้ (ถ้ามี)
$userMessage = trim($data['queryResult']['queryText'] ?? '');

/* คำทักทาย/รีเซ็ต */
if (preg_match('/สวัสดี|เริ่มใหม่/i', $userMessage)) {
  echo json_encode([
    "fulfillmentText" => "สวัสดีครับ เริ่มต้นการแจ้งซ่อมใหม่ได้เลยครับ",
    "outputContexts"  => []
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* =========================
   ดึง LINE userId ให้ครอบคลุม
   ========================= */
$lineUserId = null;

$odi = $data['originalDetectIntentRequest']['payload'] ?? [];
if (!$lineUserId && !empty($odi['data']['source']['userId']))            $lineUserId = $odi['data']['source']['userId'];
if (!$lineUserId && !empty($odi['data']['events'][0]['source']['userId'])) $lineUserId = $odi['data']['events'][0]['source']['userId'];
if (!$lineUserId && !empty($odi['source']['userId']))                     $lineUserId = $odi['source']['userId'];
if (!$lineUserId && !empty($data['originalDetectIntentRequest']['source']['userId']))
  $lineUserId = $data['originalDetectIntentRequest']['source']['userId'];

if (!$lineUserId) $lineUserId = find_user_id_recursive($data['originalDetectIntentRequest'] ?? []);
if (!$lineUserId) $lineUserId = find_user_id_recursive($odi);

log_to('df_userid.log', 'userId=' . ($lineUserId ?: 'NULL'));

/* =========================
   Parameters จาก Intent
   ========================= */
$p = $data['queryResult']['parameters'] ?? [];
$nickname = $p['nickname'] ?? null;
$serial   = $p['serial'] ?? null;
$phone    = $p['phone'] ?? null;
$issue    = clean_issue($p['issue'] ?? '');   // <<<<<< ทำความสะอาดที่นี่
$device   = $p['device'] ?? null;
$floor    = $p['floor'] ?? null;

/* ถ้า device ยังว่าง ลองดูจาก context */
if (!$device) {
  foreach (($data['queryResult']['outputContexts'] ?? []) as $ctx) {
    if (!empty($ctx['parameters']['device'])) { $device = $ctx['parameters']['device']; break; }
  }
}

/* ตรวจความครบถ้วน */
$missing = [];
if (!$nickname) $missing[] = "ชื่อเล่น";
if (!$serial)   $missing[] = "หมายเลขเครื่อง";
if (!$phone)    $missing[] = "เบอร์โทร";
if (!$device)   $missing[] = "อุปกรณ์";
if ($issue==='')$missing[] = "ปัญหา";
if (!$floor)    $missing[] = "เลขห้อง";

if (!empty($missing)) {
  echo json_encode([
    "fulfillmentText" => "ข้อมูลไม่ครบ: " . implode(", ", $missing) . " กรุณากรอกให้ครบครับ"
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* =========================
   เชื่อมต่อฐานข้อมูล
   ========================= */
$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
if ($conn->connect_error) {
  echo json_encode(["fulfillmentText" => "เชื่อมต่อฐานข้อมูลไม่ได้"], JSON_UNESCAPED_UNICODE);
  exit;
}
$conn->set_charset('utf8mb4');

/* =========================
   สร้างเลขคิว d/n/y + A..Z + 1..10
   ========================= */
$dateForQueue = date("j/n/y");
$queuePrefix  = $dateForQueue . "/";
$sqlQueue = "SELECT queue_number FROM device_reports
             WHERE DATE(report_date) = CURDATE()
               AND queue_number LIKE CONCAT(?, '%')
             ORDER BY report_date DESC LIMIT 1";
$stmtQ = $conn->prepare($sqlQueue);
$stmtQ->bind_param("s", $queuePrefix);
$stmtQ->execute();
$resQ = $stmtQ->get_result();
$latestQueue = $resQ->fetch_assoc()['queue_number'] ?? null;
$stmtQ->close();

if ($latestQueue && preg_match('/([A-Z])(\d+)$/', $latestQueue, $m)) {
  $prefix = $m[1]; $number = (int)$m[2];
  if ($number < 10) { $newPrefix=$prefix; $newNumber=$number+1; }
  else { $newPrefix = chr(ord($prefix)+1); $newNumber=1; }
} else { $newPrefix='A'; $newNumber=1; }
$queueCode = $queuePrefix . $newPrefix . $newNumber;

/* =========================
   INSERT งาน + เก็บ userId
   ========================= */
$sql = "INSERT INTO device_reports
        (username, phone_number, serial_number, device_type, floor,
         issue_description, report_date, queue_number, line_user_id, status)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'new')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssss", $nickname, $phone, $serial, $device, $floor, $issue, $queueCode, $lineUserId);
$stmt->execute();
$insertedId = $stmt->insert_id;
$stmt->close();

/* (ออปชัน) ผูก userId ย้อนหลังให้ทุกงานที่เบอร์เดียวกัน */
if ($lineUserId && $phone) {
  $u = $conn->prepare("UPDATE device_reports
                       SET line_user_id = ?
                       WHERE phone_number = ?
                         AND (line_user_id IS NULL OR line_user_id='')");
  $u->bind_param("ss", $lineUserId, $phone);
  $u->execute();
  $u->close();
}

$conn->close();

/* =========================
   ส่งข้อความยืนยันกลับ Dialogflow
   ========================= */
$responseText =
  "รับการแจ้งซ่อมครับ คุณ $nickname\n".
  "📌 คิวของคุณ: $queueCode\n".
  "🔧 อุปกรณ์: $device\n".
  "🔢 หมายเลขเครื่อง: $serial\n".
  "🏢 ห้อง: $floor\n".
  "❗ ปัญหา: $issue\n".        // << ใช้ข้อความที่ถูกลอก prefix แล้ว
  "📞 จะติดต่อกลับที่เบอร์: $phone";

echo json_encode([
  "fulfillmentText" => $responseText,
  "outputContexts"  => []
], JSON_UNESCAPED_UNICODE);
