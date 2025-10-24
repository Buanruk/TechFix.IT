<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Bangkok');

/* ===== Error handling: ห้าม echo error ออกจอ ให้ log ลงไฟล์แทน ===== */
ini_set('display_errors', '0');              // สำคัญ: ปิดการโชว์ error มิฉะนั้น JSON จะพัง
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
ob_start();                                 // กัน output หลุดมาก่อน JSON

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
if (!$lineUserId && !empty($odi['source']['userId']))                $lineUserId = $odi['source']['userId'];
if (!$lineUserId && !empty($data['originalDetectIntentRequest']['source']['userId']))
  $lineUserId = $data['originalDetectIntentRequest']['source']['userId'];
if (!$lineUserId) $lineUserId = find_user_id_recursive($data['originalDetectIntentRequest'] ?? []);
if (!$lineUserId) $lineUserId = find_user_id_recursive($odi);

// FIX: บังคับ $lineUserId ให้เป็น string เสมอ
$lineUserId = (string)$lineUserId;
log_to('df_userid.log', 'userId=' . ($lineUserId ?: 'NULL_STRING'));

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

/*
|--------------------------------------------------------------------------
| ‼️‼️‼️ เริ่มส่วนสร้าง PDF ‼️‼️‼️
|--------------------------------------------------------------------------
*/

// *** 1. กำหนดค่าพื้นฐาน ***
$safeQueueCode = str_replace('/', '-', $queueCode); 
$pdfPath = __DIR__ . "/repair_forms/{$safeQueueCode}.pdf";
if (!is_dir(dirname($pdfPath))) mkdir(dirname($pdfPath), 0777, true);

// FIX: ระบุ Path ของฟอนต์ให้ชัดเจน
if (!defined('FPDF_FONTPATH')) {
    define('FPDF_FONTPATH', __DIR__ . '/fpdf/font/unifont/');
}

//
// ‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️
//
//          FIX: เปลี่ยนจาก fpdf.php เป็น tfpdf.php
//          เพื่อให้เรียกใช้คลาส tFPDF ได้ถูกต้อง
//
require_once(__DIR__ . '/fpdf/tfpdf.php'); 
//
// ‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️‼️
//


// --- 2. สร้าง PDF และเพิ่มฟอนต์ ---
$pdf = new tFPDF('P', 'mm', 'A4'); // <--- คลาสนี้ต้องการ tfpdf.php
$pdf->AddPage();
// การเรียก AddFont ตอนนี้จะไปหาฟอนต์จาก Path ที่เรา define ไว้
$pdf->AddFont('Sarabun','','THSarabunNew.ttf', true);
$pdf->AddFont('Sarabun','B','THSarabunNew Bold.ttf', true); 

// --- 3. ตั้งค่าหน้ากระดาษและ Margins ---
$leftMargin = 25;
$rightMargin = 25;
$topMargin = 20;
$pageWidth = 210;
$contentWidth = $pageWidth - $leftMargin - $rightMargin; // 160mm

$pdf->SetMargins($leftMargin, $topMargin, $rightMargin); 
$pdf->SetAutoPageBreak(true, 20); // Margin ล่าง 2cm

// --- 4. ใส่โลโก้และหัวกระดาษ (Header) ---
$logoPath = __DIR__ . '/image/logo.png'; 
$headerY = $pdf->GetY(); // เก็บตำแหน่ง Y เริ่มต้น

if (file_exists($logoPath)) {
    $pdf->Image($logoPath, $leftMargin, $headerY, 25); // กว้าง 25mm
}

$pdf->SetFont('Sarabun','B', 20);
$pdf->SetXY($leftMargin, $headerY + 5); 
$pdf->Cell($contentWidth, 10, 'ใบแจ้งซ่อม (REPAIR FORM)', 0, 1, 'C'); 
$pdf->SetFont('Sarabun','', 12);
$pdf->SetX($leftMargin);
$pdf->Cell($contentWidth, 8, 'TECHFIX.IT COMPUTER SERVICE', 0, 1, 'C');

// --- 5. ข้อมูลใบแจ้งซ่อม (มุมบนขวา) ---
$infoBoxWidth = 70; 
$infoBoxX = $pageWidth - $rightMargin - $infoBoxWidth; 

$pdf->SetFont('Sarabun','B', 12);
$pdf->SetXY($infoBoxX, $headerY); // กลับไป Y บนสุด
$pdf->Cell($infoBoxWidth, 8, 'เลขที่ใบซ่อม (Queue No.):', 0, 1, 'L');
$pdf->SetFont('Sarabun','', 12);
$pdf->SetX($infoBoxX + 5); 
$pdf->Cell($infoBoxWidth - 5, 8, $queueCode, 0, 1, 'L');

$pdf->SetFont('Sarabun','B', 12);
$pdf->SetX($infoBoxX);
$pdf->Cell($infoBoxWidth, 8, 'วันที่ (Date):', 0, 1, 'L');
$pdf->SetFont('Sarabun','', 12);
$pdf->SetX($infoBoxX + 5); 
$pdf->Cell($infoBoxWidth - 5, 8, $dateForQueue, 0, 1, 'L');

// --- 6. เส้นคั่น และส่วนเนื้อหา ---
$pdf->SetY($headerY + 40); // เลื่อน Y ลงมาให้พ้นส่วนหัว
$pdf->SetDrawColor(0, 84, 166); 
$pdf->SetLineWidth(0.5);
$pdf->Line($leftMargin, $pdf->GetY(), $pageWidth - $rightMargin, $pdf->GetY());
$pdf->Ln(8); // เว้นบรรทัด

// --- 7. ฟังก์ชันช่วยวาดแถวข้อมูล (แบบใหม่) ---
function drawDataRow($pdf, $label, $value, $contentWidth) {
    $lineHeight = 8;    
    $labelWidth = 40;   
    $valueWidth = $contentWidth - $labelWidth - 5; 
    $startX = $pdf->GetX();
    $startY = $pdf->GetY();

    $pdf->SetFont('Sarabun','B', 12);
    $pdf->MultiCell($labelWidth, $lineHeight, $label . ' :', 0, 'L');
    $labelEndY = $pdf->GetY();

    $pdf->SetFont('Sarabun','', 12);
    $pdf->SetXY($startX + $labelWidth + 5, $startY); 
    $pdf->MultiCell($valueWidth, $lineHeight, $value, 0, 'L');
    $valueEndY = $pdf->GetY();

    $pdf->SetY(max($labelEndY, $valueEndY));
    $pdf->Ln(2); 
}

// --- 8. วาดข้อมูลลง PDF ---
drawDataRow($pdf, 'ผู้แจ้ง', $nickname, $contentWidth);
drawDataRow($pdf, 'เบอร์โทร', $phone, $contentWidth);
drawDataRow($pdf, 'ห้อง', $floor, $contentWidth);
$pdf->Ln(5); 
drawDataRow($pdf, 'อุปกรณ์', $device, $contentWidth); 
drawDataRow($pdf, 'หมายเลขเครื่อง', $serial, $contentWidth);
$pdf->Ln(5); 

$pdf->SetFont('Sarabun','B', 12);
$pdf->Cell($contentWidth, 8, 'ปัญหา/อาการที่พบ :', 0, 1, 'L');
$pdf->SetFont('Sarabun','', 12);
$pdf->SetDrawColor(200, 200, 200); 
$pdf->MultiCell($contentWidth, 8, $issue, 1, 'L'); 

// --- 9. ส่วนท้าย (Footer) ---
$pdf->SetY(-30); // 30mm จากด้านล่าง
$pdf->SetFont('Sarabun','', 10);
$pdf->SetDrawColor(0, 84, 166);
$pdf->SetLineWidth(0.5);
$pdf->Line($leftMargin, $pdf->GetY(), $pageWidth - $rightMargin, $pdf->GetY());
$pdf->Ln(5);
$pdf->Cell($contentWidth, 6, 'ขอบคุณที่ใช้บริการ TECHFIX.IT', 0, 1, 'C');

// --- 10. บันทึกไฟล์ PDF ---
$pdf->Output('F', $pdfPath); 

/*
|--------------------------------------------------------------------------
| ‼️‼️‼️ จบส่วนสร้าง PDF ‼️‼️‼️
|--------------------------------------------------------------------------
*/

/* ===== จบส่วนสร้าง PDF ===== */


/* ===== ส่งกลับเข้า LINE ===== */
$LINE_TOKEN = '7f0rLD4oN4UjV/DY535T4LbemrH+s7OT2lCxMk1dMJdWymlDgLvc89XZvvG/qBNg19e9/HvpKHsgxBFEHkXQlDQN5B8w3L0yhcKCSR51vfvTvUm0o5GQcq+jRlT+4TiQNN0DbIL2jI+adHfOz44YRQdB04t89/1O/w1cDnyilFU='; 
$DOMAIN_URL = 'https://techfix.asia'; 

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
          "type" => "text",
          "text" => "คลิกเพื่อดาวน์โหลดใบแจ้งซ่อม (A4): {$DOMAIN_URL}/repair_forms/{$safeQueueCode}.pdf"
        ]
      ]
    ];

    $ch = curl_init("https://api.line.me/v2/bot/message/push");
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: " . "Bearer " . $LINE_TOKEN
      ],
      CURLOPT_POSTFIELDS => json_encode($msg, JSON_UNESCAPED_UNICODE)
    ]);
    
    $curl_response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
    curl_close($ch);

    if ($curl_error) {
        error_log('LINE Push cURL Error: ' . $curl_error);
    } elseif ($http_code != 200 && $http_code != 202) {
        error_log('LINE Push API Error: HTTP Code ' . $http_code . ' | Response: ' . $curl_response);
    }
} 
/* ===== จบส่วนส่ง LINE ===== */


} catch (Throwable $e) {
  // บล็อกนี้จะทำงาน "เฉพาะ" เมื่อเกิดปัญหาร้ายแรงจริงๆ เท่านั้น
  error_log('Fatal Error (DB or PDF): ' . $e->getMessage() . ' on line ' . $e->getLine());
  send_json_and_exit(["fulfillmentText" => "ขออภัย ระบบบันทึกข้อมูลไม่สำเร็จ"]);
}

/* ===== ตอบกลับ ===== */
// สคริปต์จะมาถึงตรงนี้ "ก็ต่อเมื่อ" ทุกอย่าง (DB และ PDF) สำเร็จแล้ว
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