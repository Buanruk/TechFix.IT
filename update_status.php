<?php
// webhook.php - แก้ไขให้ส่ง LINE Push Message หลังบันทึกข้อมูล

declare(strict_types=1);
date_default_timezone_set('Asia/Bangkok');

// ‼️‼️ 1. ต้องรวมไฟล์ฟังก์ชัน line_push.php ‼️‼️
// ตรวจสอบให้แน่ใจว่าไฟล์ line_push.php อยู่ในโฟลเดอร์เดียวกัน
require_once __DIR__ . '/line_push.php'; 


/* ===== Error handling: ห้าม echo error ออกจอ ให้ log ลงไฟล์แทน ===== */
ini_set('display_errors', '0');              // สำคัญ: ปิดการโชว์ error มิฉะนั้น JSON จะพัง
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
ob_start();                                 // กัน output หลุดมาก่อน JSON

header('Content-Type: application/json; charset=utf-8');

/* ===== Helpers (ส่วนอื่น ๆ ของโค้ดเดิม) ===== */
function log_to(string $fname, string $text): void {
  @file_put_contents(__DIR__ . "/$fname", '['.date('Y-m-d H:i:s')."] $text\n", FILE_APPEND);
}

// ... (find_user_id_recursive, clean_issue, send_json_and_exit เหมือนเดิม) ...
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

function clean_issue(string $txt): string {
  $txt = html_entity_decode($txt, ENT_QUOTES, 'UTF-8');
  $txt = preg_replace('/^\s*(ปัญหา(เรื่อง)?|อาการ|issue)\s*[:：\-]?\s*/iu', '', $txt);
  $txt = preg_replace('/\s+/u', ' ', trim($txt));
  return $txt;
}

function send_json_and_exit(array $payload): void {
  if (ob_get_length() !== false) { ob_clean(); }
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}


/* ===== รับ/ตรวจ input (เหมือนเดิม) ===== */
$raw = file_get_contents('php://input');
log_to('df_request.log', $raw ?: '(empty-body)');

$data = json_decode($raw, true);
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
  error_log('JSON decode error: ' . json_last_error_msg());
  send_json_and_exit(["fulfillmentText" => "ขออภัย ระบบอ่านข้อมูลไม่ถูกต้อง"]);
}
// ... (ส่วนทักทาย/รีเซ็ต, ดึง LINE userId, Parameters, ตรวจความครบ เหมือนเดิม) ...
$userMessage = trim($data['queryResult']['queryText'] ?? '');
if ($userMessage !== '' && preg_match('/สวัสดี|เริ่มใหม่/i', $userMessage)) {
  send_json_and_exit([
    "fulfillmentText" => "สวัสดีครับ เริ่มต้นการแจ้งซ่อมใหม่ได้เลยครับ",
    "outputContexts"  => []
  ]);
}
$lineUserId = null;
$odi = $data['originalDetectIntentRequest']['payload'] ?? [];
if (!$lineUserId && !empty($odi['data']['source']['userId']))        $lineUserId = $odi['data']['source']['userId'];
if (!$lineUserId && !empty($odi['data']['events'][0]['source']['userId'])) $lineUserId = $odi['data']['events'][0]['source']['userId'];
if (!$lineUserId && !empty($odi['source']['userId']))              $lineUserId = $odi['source']['userId'];
if (!$lineUserId && !empty($data['originalDetectIntentRequest']['source']['userId']))
  $lineUserId = $data['originalDetectIntentRequest']['source']['userId'];
if (!$lineUserId) $lineUserId = find_user_id_recursive($data['originalDetectIntentRequest'] ?? []);
if (!$lineUserId) $lineUserId = find_user_id_recursive($odi);

$lineUserId = (string)$lineUserId;
log_to('df_userid.log', 'userId=' . ($lineUserId ?: 'NULL_STRING'));

$p        = $data['queryResult']['parameters'] ?? [];
$nickname = $p['nickname'] ?? null;
$serial   = $p['serial'] ?? null;
$phone    = $p['phone'] ?? null;
$issue    = clean_issue((string)($p['issue'] ?? ''));
$device   = $p['device'] ?? null;
$floor    = $p['floor'] ?? null;

if (!$device) {
  foreach (($data['queryResult']['outputContexts'] ?? []) as $ctx) {
    if (!empty($ctx['parameters']['device'])) { $device = $ctx['parameters']['device']; break; }
  }
}

$missing = [];
if (!$nickname) $missing[] = "ชื่อเล่น";
if (!$serial)   $missing[] = "หมายเลขเครื่อง";
if (!$phone)    $missing[] = "เบอร์โทร";
if (!$device)   $missing[] = "อุปกรณ์";
if ($issue==='') $missing[] = "ปัญหา";
if (!$floor)    $missing[] = "เลขห้อง";

if ($missing) {
  send_json_and_exit([
    "fulfillmentText" => "ข้อมูลไม่ครบ: " . implode(", ", $missing) . " กรุณากรอกให้ครบครับ"
  ]);
}


/* ===== ฐานข้อมูล (เหมือนเดิม) ===== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  // *** 1. แก้ไขข้อมูลเชื่อมต่อฐานข้อมูลของคุณตรงนี้ ***
  $conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
  $conn->set_charset('utf8mb4');

  // สร้างเลขคิว d/n/y + A..Z + 1..10
  $dateForQueue = date("j/n/y");
  $queuePrefix  = $dateForQueue . "/";

  $stmtQ = $conn->prepare(
    "SELECT queue_number FROM device_reports
      WHERE DATE(report_date) = CURDATE()
        AND queue_number LIKE CONCAT(?, '%')
      ORDER BY report_date DESC LIMIT 1"
  );
  $stmtQ->bind_param("s", $queuePrefix);
  $stmtQ->execute();
  $latestQueue = ($stmtQ->get_result()->fetch_assoc()['queue_number'] ?? null);
  $stmtQ->close();

  if ($latestQueue && preg_match('/([A-Z])(\d+)$/', $latestQueue, $m)) {
    $prefix = $m[1]; $number = (int)$m[2];
    if ($number < 10) { $newPrefix = $prefix; $newNumber = $number + 1; }
    else { $newPrefix = chr(ord($prefix) + 1); $newNumber = 1; }
  } else { $newPrefix = 'A'; $newNumber = 1; }
  $queueCode = $queuePrefix . $newPrefix . $newNumber;

  // INSERT
  $stmt = $conn->prepare(
    "INSERT INTO device_reports
      (username, phone_number, serial_number, device_type, floor,
       issue_description, report_date, queue_number, line_user_id, status)
       VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'new')"
  );
  $stmt->bind_param("ssssssss", $nickname, $phone, $serial, $device, $floor, $issue, $queueCode, $lineUserId);
  $stmt->execute();
  $stmt->close();

  // ผูก userId ย้อนหลังด้วยเบอร์
  if ($lineUserId && $phone) {
    $u = $conn->prepare(
      "UPDATE device_reports
        SET line_user_id = ?
        WHERE phone_number = ?
          AND (line_user_id IS NULL OR line_user_id='')"
    );
    $u->bind_param("ss", $lineUserId, $phone);
    $u->execute();
    $u->close();
  }

  $conn->close();

  // ‼️‼️ 2. สร้างข้อความสำหรับ LINE PUSH ‼️‼️
  $pushMessage =
    "📝 บันทึกการแจ้งซ่อมสำเร็จ\n".
    "คิว: $queueCode\n".
    "อุปกรณ์: $device\n".
    "ปัญหา: $issue\n".
    "จะติดต่อกลับที่เบอร์: $phone\n\n".
    "👉 ท่านสามารถติดตามสถานะการซ่อมได้จากหน้านี้"; // หรือข้อความอื่นๆ

  // ‼️‼️ 3. ส่ง LINE PUSH Message ‼️‼️
  if ($lineUserId) {
    list($http, $res, $err) = line_push($lineUserId, $pushMessage);
    // บันทึก Log การส่ง Push (ถ้าต้องการ)
    @file_put_contents(__DIR__ . "/line_push_log_report.txt", 
        date("Y-m-d H:i:s")." queue=$queueCode http=$http err=$err res=$res\n", FILE_APPEND);
  }


} catch (Throwable $e) {
  // บล็อกนี้จะทำงาน "เฉพาะ" เมื่อเกิดปัญหาที่ "ฐานข้อมูล" เท่านั้น
  error_log('DB Error: ' . $e->getMessage());
  send_json_and_exit(["fulfillmentText" => "ขออภัย ระบบบันทึกข้อมูลไม่สำเร็จ"]);
}


/* ===== ตอบกลับ Dialogflow (แค่ยืนยันการรับ) ===== */
// เราส่ง Push Message แล้ว ดังนั้นการตอบกลับ Dialogflow ไม่จำเป็นต้องยาวอีก
// ตอบกลับสั้น ๆ เพื่อให้ Dialogflow ทราบว่า Webhook ทำงานเสร็จ
send_json_and_exit([
  "fulfillmentText" => "รับทราบการแจ้งซ่อมเรียบร้อยแล้วครับ ระบบได้ส่งข้อความยืนยันไปยัง LINE ของท่านแล้ว",
  "outputContexts"  => []
]);