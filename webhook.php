<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Bangkok');

/* ===== Error handling: ห้าม echo error ออกจอ ให้ log ลงไฟล์แทน ===== */
ini_set('display_errors', '0');                  // สำคัญ: ปิดการโชว์ error มิฉะนั้น JSON จะพัง
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
ob_start();                                      // กัน output หลุดมาก่อน JSON

header('Content-Type: application/json; charset=utf-8');

/* ===== Helpers ===== */
function log_to(string $fname, string $text): void {
  @file_put_contents(__DIR__ . "/$fname", '['.date('Y-m-d H:i:s')."] $text\n", FILE_APPEND);
}

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
function clean_issue(string $txt): string {
  $txt = html_entity_decode($txt, ENT_QUOTES, 'UTF-8');
  $txt = preg_replace('/^\s*(ปัญหา(เรื่อง)?|อาการ|issue)\s*[:：\-]?\s*/iu', '', $txt);
  $txt = preg_replace('/\s+/u', ' ', trim($txt));
  return $txt;
}

/** ส่ง JSON กลับอย่างปลอดภัย + ปิดสคริปต์ */
function send_json_and_exit(array $payload): void {
  // ล้าง output ที่อาจเผลอ echo มาก่อนหน้า
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

/* ทักทาย/รีเซ็ต */
$userMessage = trim($data['queryResult']['queryText'] ?? '');
if ($userMessage !== '' && preg_match('/สวัสดี|เริ่มใหม่/i', $userMessage)) {
  send_json_and_exit([
    "fulfillmentText" => "สวัสดีครับ เริ่มต้นการแจ้งซ่อมใหม่ได้เลยครับ",
    "outputContexts"  => []
  ]);
}

/* ===== ดึง LINE userId ===== */
$lineUserId = null;
$odi = $data['originalDetectIntentRequest']['payload'] ?? [];
if (!$lineUserId && !empty($odi['data']['source']['userId']))        $lineUserId = $odi['data']['source']['userId'];
if (!$lineUserId && !empty($odi['data']['events'][0]['source']['userId'])) $lineUserId = $odi['data']['events'][0]['source']['userId'];
if (!$lineUserId && !empty($odi['source']['userId']))                 $lineUserId = $odi['source']['userId'];
if (!$lineUserId && !empty($data['originalDetectIntentRequest']['source']['userId']))
  $lineUserId = $data['originalDetectIntentRequest']['source']['userId'];
if (!$lineUserId) $lineUserId = find_user_id_recursive($data['originalDetectIntentRequest'] ?? []);
if (!$lineUserId) $lineUserId = find_user_id_recursive($odi);
log_to('df_userid.log', 'userId=' . ($lineUserId ?: 'NULL'));

/* ===== Parameters ===== */
$p        = $data['queryResult']['parameters'] ?? [];
$nickname = $p['nickname'] ?? null;
$serial   = $p['serial'] ?? null;
$phone    = $p['phone'] ?? null;
$issue    = clean_issue((string)($p['issue'] ?? ''));
$device   = $p['device'] ?? null;
$floor    = $p['floor'] ?? null;

/* ดึง device จาก context ถ้ายังไม่มี */
if (!$device) {
  foreach (($data['queryResult']['outputContexts'] ?? []) as $ctx) {
    if (!empty($ctx['parameters']['device'])) { $device = $ctx['parameters']['device']; break; }
  }
}

/* ตรวจความครบ */
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

/* ===== ฐานข้อมูล ===== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  // *** 1. แก้ไขข้อมูลเชื่อมต่อฐานข้อมูลของคุณตรงนี้ ***
  $conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
  $conn->set_charset('utf8mb4');

  // สร้างเลขคิว d/n/y + A..Z + 1..10
  $dateForQueue = date("j/n/y");
  $queuePrefix  = $dateForQueue . "/";

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

/* ===== สร้างใบแจ้งซ่อมอัตโนมัติ (PDF) ===== */

// *** FIX: ใช้ชื่อไฟล์ที่ปลอดภัย ***
$safeQueueCode = str_replace('/', '-', $queueCode); 
$pdfPath = __DIR__ . "/repair_forms/{$safeQueueCode}.pdf";
if (!is_dir(dirname($pdfPath))) mkdir(dirname($pdfPath), 0777, true);

// *** 2. เรียกใช้ไลบรารี tFPDF ที่ติดตั้งไว้ ***
require_once(__DIR__ . '/fpdf/fpdf.php'); 

// --- กำหนดค่าเริ่มต้นของใบเสร็จ ---
$pdf = new tFPDF('P', 'mm', 'A4'); // P=Portrait, mm=millimeters
$pdf->AddPage();
$pdf->SetAutoPageBreak(false); 

// *** 3. เพิ่มฟอนต์ THSarabunNew (ที่อยู่ใน /fpdf/font/unifont/) ***
$pdf->AddFont('Sarabun','','THSarabunNew.ttf', true);
$pdf->AddFont('Sarabun','B','THSarabunNew Bold.ttf', true); 

// --- กำหนดขนาดและตำแหน่งของใบเสร็จ ---
$pageWidth = 210; // A4 width
$slipWidth = 90;  // ความกว้างใบเสร็จ 90mm
$slipHeight = 130; // ความสูงใบเสร็จ 130mm
$startX = ($pageWidth - $slipWidth) / 2; 
$startY = 30; 
$padding = 8; 
$contentX = $startX + $padding; 
$contentWidth = $slipWidth - ($padding * 2); // ‼️‼️ FIX: $contentWidth ถูกสร้างตรงนี้ ‼️‼️

// --- 1. วาดกรอบสี่เหลี่ยม ---
$pdf->SetDrawColor(0, 84, 166); 
$pdf->SetLineWidth(0.8);
$pdf->Rect($startX, $startY, $slipWidth, $slipHeight, 'S'); 

// --- 2. ใส่โลโก้ ---
$logoPath = __DIR__ . '/image/logo.png'; 
$pdf->SetY($startY + $padding); 
if (file_exists($logoPath)) {
    $imageWidth = 20;
    $imageX = $startX + (($slipWidth - $imageWidth) / 2); // Center image
    $pdf->Image($logoPath, $imageX, $pdf->GetY(), $imageWidth);
    $pdf->Ln($imageWidth + 2); 
} else {
    $pdf->Ln(20); 
}

// --- 3. ใส่หัวกระดาษ (TECHFIX.IT) ---
$pdf->SetFont('Sarabun','B', 18); // <- ใช้ฟอนต์ Sarabun
$pdf->SetX($contentX);
$pdf->Cell($contentWidth, 8, 'TECHFIX.IT', 0, 1, 'C');
$pdf->SetFont('Sarabun','', 10); // <- ใช้ฟอนต์ Sarabun
$pdf->SetX($contentX);
$pdf->Cell($contentWidth, 6, 'COMPUTER SERVICE', 0, 1, 'C');
$pdf->Ln(8); 

// --- 4. แยกข้อมูลวันที่ / เลขคิว ---
$datePart = $dateForQueue; 
$queuePart = 'N/A';
if (preg_match('/([A-Z])(\d+)$/', $queueCode, $m)) {
     $queuePart = $m[1] . $m[2]; 
}

// --- 5. ใส่เนื้อหา (Label + Value) ---
$lineHeight = 7; 
$labelWidth = 30; 

//
// ‼️‼️ FIX: แก้ไขฟังก์ชัน drawRow ทั้งหมด ‼️‼️
//
// --- ฟังก์ชันช่วยวาด 1 แถว (เวอร์ชันแก้ไขตัวอักษรเละ + รับ $contentWidth) ---
function drawRow($pdf, $label, $value, $contentX, $contentWidth, $labelWidth, $lineHeight) {
    // เก็บตำแหน่ง Y เริ่มต้นของแถวนี้
    $startY = $pdf->GetY();

    // --- วาด Label (หัวข้อ) ---
    $pdf->SetFont('Sarabun','B', 12);
    $pdf->SetXY($contentX, $startY); // กำหนดตำแหน่ง
    $pdf->MultiCell($labelWidth, $lineHeight, $label . ':', 0, 'L');

    // เก็บตำแหน่ง Y สุดท้าย
    $labelEndY = $pdf->GetY();

    // --- วาด Value (ข้อมูล) ---
    $pdf->SetFont('Sarabun','', 12);
    $pdf->SetXY($contentX + $labelWidth, $startY); // กำหนดตำแหน่ง (กลับไปที่ Y เริ่มต้น)
    $pdf->MultiCell($contentWidth - $labelWidth, $lineHeight, $value, 0, 'L');

    // เก็บตำแหน่ง Y สุดท้าย
    $valueEndY = $pdf->GetY();

    // เลื่อนตำแหน่ง Y ของ PDF ไปรอแถวถัดไป โดยอิงจากแถวที่ "สูงที่สุด"
    $pdf->SetY(max($labelEndY, $valueEndY));
}

// --- วาดข้อมูลลง PDF (‼️ FIX: เพิ่ม $contentWidth เข้าไป ‼️) ---
drawRow($pdf, 'วันที่', $datePart, $contentX, $contentWidth, $labelWidth, $lineHeight);
drawRow($pdf, 'เลขที่ใบซ่อม', $queuePart, $contentX, $contentWidth, $labelWidth, $lineHeight);
drawRow($pdf, 'ผู้แจ้ง', $nickname, $contentX, $contentWidth, $labelWidth, $lineHeight);
drawRow($pdf, 'เบอร์โทร', $phone, $contentX, $contentWidth, $labelWidth, $lineHeight);
drawRow($pdf, 'ห้อง', $floor, $contentX, $contentWidth, $labelWidth, $lineHeight);
drawRow($pdf, 'อุปกรณ์', $device, $contentX, $contentWidth, $labelWidth, $lineHeight); 
drawRow($pdf, 'หมายเลขเครื่อง', $serial, $contentX, $contentWidth, $labelWidth, $lineHeight);
drawRow($pdf, 'ปัญหา', $issue, $contentX, $contentWidth, $labelWidth, $lineHeight);

//
// ‼️‼️ จบการแก้ไขส่วน drawRow ‼️‼️
//

// --- 6. บันทึกไฟล์ PDF ---
$pdf->Output('F', $pdfPath); 

/* ===== จบส่วนสร้าง PDF ===== */


/* ===== ส่งกลับเข้า LINE ===== */
// *** 4. ใส่ TOKEN และ DOMAIN ของคุณตรงนี้ ***
$LINE_TOKEN = '7f0rLD4oN4UjV/DY535T4LbemrH+s7OT2lCxMk1dMJdWymlDgLvc89XZvvG/qBNg19e9/HvpKHsgxBFEHkXQlDQN5B8w3L0yhcKCSR51vfvTvUm0o5GQcq+jRlT+4TiQNN0DbIL2jI+adHfOz44YRQdB04t89/1O/w1cDnyilFU='; 
$DOMAIN_URL = 'https://techfix.asia'; // (ต้องเป็น HTTPS)

if ($lineUserId) 
{
    $msg = [
      "to" => $lineUserId,
      "messages" => [
        [
          "type" => "text",
          "text" => "ใบแจ้งซ่อมของคุณถูกสร้างเรียบร้อยครับ 📄",
        ],
        [
          "type" => "file",
          "originalContentUrl" => "{$DOMAIN_URL}/repair_forms/{$safeQueueCode}.pdf", 
          "fileName" => "{$safeQueueCode}.pdf",
          "fileSize" => filesize($pdfPath)
        ]
      ]
    ];
    $ch = curl_init("https://api.line.me/v2/bot/message/push");
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer $LINE_TOKEN"
      ],
      CURLOPT_POSTFIELDS => json_encode($msg, JSON_UNESCAPED_UNICODE)
    ]);

    // *** 5. เพิ่มโค้ดดักจับ Error ***
    $curl_response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        // ถ้า curl ล้มเหลว ให้บันทึก Error ลง log
        error_log('LINE Push Error: ' . $curl_error);
    }
    // *** จบส่วนดักจับ Error ***
} 
/* ===== จบส่วนส่ง LINE ===== */


} catch (Throwable $e) {
  error_log('DB Error: ' . $e->getMessage());
  send_json_and_exit(["fulfillmentText" => "ขออภัย ระบบบันทึกข้อมูลไม่สำเร็จ"]);
}

/* ===== ตอบกลับ ===== */
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
  "outputContexts"  => []
]);