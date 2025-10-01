<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

/* ==== รับข้อมูลจาก Dialogflow ==== */
$request = file_get_contents("php://input");
$data = json_decode($request, true);

/* ==== Debug ==== */
file_put_contents(__DIR__ . "/df_request.json", $request);

/* ==== ข้อความที่ผู้ใช้ส่งมา ==== */
$userMessage = trim($data['queryResult']['queryText'] ?? '');

/* ==== รีเซ็ต/ทักทาย ==== */
if (preg_match('/สวัสดี|เริ่มใหม่/i', $userMessage)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "fulfillmentText" => "สวัสดีครับ เริ่มต้นการแจ้งซ่อมใหม่ได้เลยครับ",
        "outputContexts" => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ==== ดึง LINE userId จาก originalDetectIntentRequest (รองรับหลายรูปแบบ) ==== */
$lineUserId = null;
$odi = $data['originalDetectIntentRequest']['payload'] ?? [];
if (!empty($odi['data']['source']['userId'])) {
    $lineUserId = $odi['data']['source']['userId'];
} elseif (!empty($odi['data']['events'][0]['source']['userId'])) {
    $lineUserId = $odi['data']['events'][0]['source']['userId'];
} elseif (!empty($odi['source']['userId'])) {
    $lineUserId = $odi['source']['userId'];
}

/* ==== Parameters ==== */
$parameters = $data['queryResult']['parameters'] ?? [];
$nickname = $parameters['nickname'] ?? null;
$serial   = $parameters['serial'] ?? null;
$phone    = $parameters['phone'] ?? null;
$issue    = $parameters['issue'] ?? null;
$device   = $parameters['device'] ?? null;
$floor    = $parameters['floor'] ?? null;

/* ==== ดึง device จาก context ถ้าไม่มี ==== */
if (!$device) {
    foreach (($data['queryResult']['outputContexts'] ?? []) as $ctx) {
        if (!empty($ctx['parameters']['device'])) {
            $device = $ctx['parameters']['device'];
            break;
        }
    }
}

/* ==== ตรวจสอบความครบถ้วน ==== */
$missing = [];
if (!$nickname) $missing[] = "ชื่อเล่น";
if (!$serial)   $missing[] = "หมายเลขเครื่อง";
if (!$phone)    $missing[] = "เบอร์โทร";
if (!$device)   $missing[] = "อุปกรณ์";
if (!$issue)    $missing[] = "ปัญหา";
if (!$floor)    $missing[] = "เลขห้อง";
if (!empty($missing)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "fulfillmentText" => "ข้อมูลไม่ครบ: " . implode(", ", $missing) . " กรุณากรอกให้ครบครับ"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ==== เชื่อมต่อฐานข้อมูล ==== */
$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
if ($conn->connect_error) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["fulfillmentText" => "เชื่อมต่อฐานข้อมูลไม่ได้"], JSON_UNESCAPED_UNICODE);
    exit;
}
$conn->set_charset('utf8mb4');

/* ==== สร้างเลขคิวรูปแบบ d/n/y + อักษร A..Z + เลขรัน 1..10 ==== */
$dateForQueue = date("j/n/y");      // เช่น 11/9/25
$queuePrefix  = $dateForQueue . "/";

$sqlQueue = "SELECT queue_number FROM device_reports
             WHERE DATE(report_date) = CURDATE()
               AND queue_number LIKE CONCAT(?, '%')
             ORDER BY report_date DESC
             LIMIT 1";
$stmtQueue = $conn->prepare($sqlQueue);
$stmtQueue->bind_param("s", $queuePrefix);
$stmtQueue->execute();
$resQ = $stmtQueue->get_result();
$latestQueue = $resQ->fetch_assoc()['queue_number'] ?? null;
$stmtQueue->close();

if ($latestQueue) {
    // ตัดเอา A5 / B10 ท้ายๆ ออกมา
    if (preg_match('/([A-Z])(\d+)$/', $latestQueue, $m)) {
        $prefix = $m[1];
        $number = (int)$m[2];
        if ($number < 10) {
            $newPrefix = $prefix;
            $newNumber = $number + 1;
        } else {
            $newPrefix = chr(ord($prefix) + 1);
            $newNumber = 1;
        }
    } else {
        $newPrefix = 'A'; $newNumber = 1;
    }
} else {
    $newPrefix = 'A'; $newNumber = 1;
}
$queueCode = $queuePrefix . $newPrefix . $newNumber;

/* ==== บันทึกข้อมูล ==== */
/* หมายเหตุ: ต้องมีคอลัมน์ line_user_id และ status ในตารางตามข้อ 1) */
$sql = "INSERT INTO device_reports
        (username, phone_number, serial_number, device_type, floor,
         issue_description, report_date, queue_number, line_user_id, status)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'new')";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssss", $nickname, $phone, $serial, $device, $floor, $issue, $queueCode, $lineUserId);
$stmt->execute();
$stmt->close();
$conn->close();

/* ==== ตอบกลับ Dialogflow/LINE ==== */
$responseText =
    "รับการแจ้งซ่อมครับ คุณ $nickname\n" .
    "📌 คิวของคุณ: $queueCode\n" .
    "🔧 อุปกรณ์: $device\n" .
    "🔢 หมายเลขเครื่อง: $serial\n" .
    "🏢 ห้อง: $floor\n" .
    "❗ ปัญหา: $issue\n" .
    "📞 จะติดต่อกลับที่เบอร์: $phone";

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    "fulfillmentText" => $responseText,
    "outputContexts"  => []
], JSON_UNESCAPED_UNICODE);
