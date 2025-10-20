<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Bangkok');

/* ===== Error handling: ห้าม echo error ออกจอ ให้ log ลงไฟล์แทน ===== */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
ob_start();

header('Content-Type: application/json; charset=utf-8');

/* ===== Helpers (เหมือนเดิม) ===== */
function log_to(string $fname, string $text): void {
  @file_put_contents(__DIR__ . "/$fname", '['.date('Y-m-d H:i:s')."] $text\n", FILE_APPEND);
}
function find_user_id_recursive($arr) { /* ... (โค้ดเหมือนเดิม) ... */ }
function clean_issue(string $txt): string { /* ... (โค้ดเหมือนเดิม) ... */ }
function send_json_and_exit(array $payload): void {
  if (ob_get_length() !== false) { ob_clean(); }
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

/* ===== รับ/ตรวจ input ===== */
$raw = file_get_contents('php://input');
log_to('df_request.log', $raw ?: '(empty-body)');

$data = json_decode($raw, true);
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
  error_log('JSON decode error: ' . json_last_error_msg());
  send_json_and_exit(["fulfillmentText" => "ขออภัย ระบบอ่านข้อมูลไม่ถูกต้อง"]);
}

/* ทักทาย/รีเซ็ต (เหมือนเดิม) */
$userMessage = trim($data['queryResult']['queryText'] ?? '');
if ($userMessage !== '' && preg_match('/สวัสดี|เริ่มใหม่/i', $userMessage)) { /* ... (โค้ดเหมือนเดิม) ... */ }

/* ===== ดึง LINE userId (เหมือนเดิม) ===== */
$lineUserId = null;
/* ... (โค้ดดึง userId ทั้งหมดเหมือนเดิม) ... */
log_to('df_userid.log', 'userId=' . ($lineUserId ?: 'NULL'));


// [--- 1. แก้ไข: เปลี่ยนวิธีดึง Parameters ให้รองรับ Context ---]

/* ===== Parameters & Context ===== */
$action = $data['queryResult']['action'] ?? '';
log_to('df_action.log', 'Action=' . ($action ?: 'NULL')); // เพื่อ debug

// 1. ดึง parameters จาก intent ปัจจุบัน
$p = $data['queryResult']['parameters'] ?? [];

// 2. ดึง parameters จาก context (ที่ intent ก่อนหน้าเก็บไว้)
$c_params = [];
if (!empty($data['queryResult']['outputContexts'])) {
  // วนหา context ที่มี parameters (ปกติคืออันแรกที่ Dialogflow ส่งมา)
  foreach($data['queryResult']['outputContexts'] as $ctx) {
    if (!empty($ctx['parameters'])) {
      $c_params = $ctx['parameters'];
      break; // เอาอันแรกที่เจอ
    }
  }
}

// 3. รวมร่าง: parameters ปัจจุบัน ($p) (เช่น 'phone') จะทับ/เพิ่ม เข้าไปใน context ($c_params)
$all_params = array_merge($c_params, $p);

// 4. กำหนดตัวแปรจาก $all_params ที่รวมแล้ว
$nickname = $all_params['nickname'] ?? null;
$serial   = $all_params['serial'] ?? null;
$phone    = $all_params['phone'] ?? null;
$issue    = clean_issue((string)($all_params['issue'] ?? ''));
$device   = $all_params['device'] ?? null;
$floor    = $all_params['floor'] ?? null;

/* ดึง device จาก context เก่าสุด (ถ้ายังไม่มี) (เหมือนเดิม) */
if (!$device) {
  foreach (($data['queryResult']['outputContexts'] ?? []) as $ctx) {
    if (!empty($ctx['parameters']['device'])) { $device = $ctx['parameters']['device']; break; }
  }
}


// [--- 2. แก้ไข: ตรวจสอบ Action ก่อนเช็คความครบถ้วน ---]

/* ===== ตรวจสอบ Action และความครบ ===== */

// ชื่อ Action ของ Intent 1 (ที่เก็บ 4 อย่าง)
// (จากรูป image_13f460.png -> 'TechFix.IT.TechFixIT-custom')
$intent1_action = 'TechFix.IT.TechFixIT-custom'; 

// ชื่อ Action ของ Intent 2 (Follow-up ที่เก็บเบอร์)
// (จากรูป image_13edfa.png -> 'TechFix.IT.TechFixIT-custom.TechFixIT-typeissue-custom')
$intent2_action = 'TechFix.IT.TechFixIT-custom.TechFixIT-typeissue-custom';


if ($action === $intent1_action) {
  // --- นี่คือ Call จาก Intent 1 (เก็บ 4 อย่างแรก) ---
  
  // ไม่ต้องทำอะไรเลย... แค่ส่ง JSON ว่างๆ กลับไป
  // เพื่อให้ Dialogflow รู้ตัวว่า Webhook ทำงานเสร็จแล้ว และไปทำ Follow-up Intent (ถามเบอร์) ต่อ
  send_json_and_exit([]);

} else if ($action === $intent2_action) {
  // --- นี่คือ Call จาก Intent 2 (เก็บเบอร์โทร) ---
  
  // ตอนนี้ $all_params ควรมีครบ 5-6 อย่างแล้ว
  // ทำการตรวจสอบความครบถ้วน
  $missing = [];
  if (!$nickname) $missing[] = "ชื่อเล่น";
  if (!$serial)   $missing[] = "หมายเลขเครื่อง";
  if (!$phone)    $missing[] = "เบอร์โทร"; // <--- เช็คเบอร์โทรตรงนี้
  if (!$device)   $missing[] = "อุปกรณ์";
  if ($issue==='')$missing[] = "ปัญหา";
  if (!$floor)    $missing[] = "เลขห้อง";

  if ($missing) {
    // ถ้ายังไม่ครบ (เช่น เบอร์โทรหลุด) ค่อยส่ง Error
    send_json_and_exit([
      "fulfillmentText" => "ข้อมูลไม่ครบ: " . implode(", ", $missing) . " กรุณากรอกให้ครบครับ"
    ]);
  }
  
  // ถ้าครบแล้ว... ให้โค้ดทำงานต่อไป (เพื่อ INSERT ลง DB)

} else {
  // ไม่รู้จัก Action นี้ หรือเป็น Action เก่า
  log_to('df_action.log', 'Unknown or non-final action: ' . $action);
  // (อาจจะส่ง Error หรือปล่อยผ่าน ขึ้นอยู่กับว่ามี Intent อื่นอีกหรือไม่)
  // ในที่นี้เราจะสมมติว่าถ้า Action ไม่ตรง ก็ยังไม่ควรบันทึก
  send_json_and_exit([]); // ส่งว่างๆ กลับไปก่อน
}

// ถ้าโค้ดมาถึงนี่ได้ แปลว่า $action === $intent2_action และ $missing ว่างเปล่า
// โค้ดส่วนที่เหลือ (DB Insert) จะทำงานตามปกติ


/* ===== ฐานข้อมูล (เหมือนเดิม) ===== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
  $conn->set_charset('utf8mb4');

  // สร้างเลขคิว (เหมือนเดิม)
  $dateForQueue = date("j/n/y");
  $queuePrefix  = $dateForQueue . "/";
  /* ... (โค้ดสร้าง $queueCode ทั้งหมดเหมือนเดิม) ... */
  $stmtQ = $conn->prepare( "SELECT ... " );
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


  // INSERT (เหมือนเดิม)
  $stmt = $conn->prepare(
    "INSERT INTO device_reports
     (username, phone_number, serial_number, device_type, floor,
      issue_description, report_date, queue_number, line_user_id, status)
     VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'new')"
  );
  $stmt->bind_param("ssssssss", $nickname, $phone, $serial, $device, $floor, $issue, $queueCode, $lineUserId);
  $stmt->execute();
  $stmt->close();

  // ผูก userId ย้อนหลัง (เหมือนเดิม)
  if ($lineUserId && $phone) {
    $u = $conn->prepare( "UPDATE ... " );
    $u->bind_param("ss", $lineUserId, $phone);
    $u->execute();
    $u->close();
  }

  $conn->close();

} catch (Throwable $e) {
  error_log('DB Error: ' . $e->getMessage());
  send_json_and_exit(["fulfillmentText" => "ขออภัย ระบบบันทึกข้อมูลไม่สำเร็จ"]);
}

/* ===== ตอบกลับ (เหมือนเดิม) ===== */
$responseText =
  "รับการแจ้งซ่อมครับ คุณ $nickname\n".
  "📌 คิวของคุณ: $queueCode\n".
  "🔧 อุปกรณ์: $device\n".
  "🔢 หมายเลขเครื่อง: $serial\n".
  "🏢 ห้อง: $floor\n".
  "❗ ปัญหา: $issue\n".
  "📞 จะติดต่อกลับที่เบอร์: $phone";

send_json_and_exit([
  "fulfillmentText" => $responseText,
  "outputContexts"  => [] // ล้าง Contexts เมื่อจบงาน
]);
?>