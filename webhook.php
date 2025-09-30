<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ==== ตั้ง Timezone ====
date_default_timezone_set('Asia/Bangkok');

// ==== รับข้อมูลจาก Dialogflow ====
$request = file_get_contents("php://input");
$data = json_decode($request, true);

// ==== Debug ====
file_put_contents(__DIR__ . "/df_request.json", $request);

// ==== ข้อความที่ผู้ใช้ส่งมา ====
$userMessage = trim($data['queryResult']['queryText'] ?? '');

if (preg_match('/สวัสดี|เริ่มใหม่/i', $userMessage)) {
    echo json_encode([
        "fulfillmentText" => "สวัสดีครับ เริ่มต้นการแจ้งซ่อมใหม่ได้เลยครับ",
        "outputContexts" => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==== Parameters ====
$parameters = $data['queryResult']['parameters'] ?? [];
$nickname = $parameters['nickname'] ?? null;
$serial   = $parameters['serial'] ?? null;
$phone    = $parameters['phone'] ?? null;
$issue    = $parameters['issue'] ?? null;
$device   = $parameters['device'] ?? null;
$floor    = $parameters['floor'] ?? null;

// ==== ดึง device จาก context ถ้าไม่มี ====
if (!$device) {
    foreach (($data['queryResult']['outputContexts'] ?? []) as $ctx) {
        if (!empty($ctx['parameters']['device'])) {
            $device = $ctx['parameters']['device'];
            break;
        }
    }
}

// ==== ตรวจสอบความครบถ้วน ====
$missing = [];
if (!$nickname) $missing[] = "ชื่อเล่น";
if (!$serial)   $missing[] = "หมายเลขเครื่อง";
if (!$phone)    $missing[] = "เบอร์โทร";
if (!$device)   $missing[] = "อุปกรณ์";
if (!$issue)    $missing[] = "ปัญหา";
if (!$floor)    $missing[] = "เลขห้อง";

if (!empty($missing)) {
    echo json_encode([
        "fulfillmentText" => "ข้อมูลไม่ครบ: " . implode(", ", $missing) . " กรุณากรอกให้ครบครับ"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==== เชื่อมต่อฐานข้อมูล ====
$conn = new mysqli("localhost", "root", "123456", "techfix");
if ($conn->connect_error) {
    echo json_encode(["fulfillmentText" => "เชื่อมต่อฐานข้อมูลไม่ได้"], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==== สร้างเลขคิว ====
$dateForQueue = date("j/n/y");  // เช่น 11/9/25
$queuePrefix = $dateForQueue . "/";

// ดึง queue ล่าสุดของวันนี้
$sqlQueue = "SELECT queue_number FROM device_reports 
             WHERE DATE(report_date) = CURDATE() 
             AND queue_number LIKE CONCAT(?, '%') 
             ORDER BY report_date DESC LIMIT 1";

$stmtQueue = $conn->prepare($sqlQueue);
$stmtQueue->bind_param("s", $queuePrefix);
$stmtQueue->execute();
$result = $stmtQueue->get_result();
$latestQueue = $result->fetch_assoc()['queue_number'] ?? null;
$stmtQueue->close();

// ==== ตรวจสอบ queue ล่าสุด และสร้าง queue ใหม่ ====
if ($latestQueue) {
    // ดึงแค่ A5 หรือ B10 ออกมา
    preg_match('/([A-Z])(\d+)$/', $latestQueue, $matches);
    $prefix = $matches[1];
    $number = (int)$matches[2];

    if ($number < 10) {
        $newPrefix = $prefix;
        $newNumber = $number + 1;
    } else {
        $newPrefix = chr(ord($prefix) + 1);  // A -> B
        $newNumber = 1;
    }
} else {
    // ไม่มีคิวของวันนี้
    $newPrefix = 'A';
    $newNumber = 1;
}

$queueCode = $queuePrefix . $newPrefix . $newNumber;

// ==== บันทึกข้อมูล ====
$sql = "INSERT INTO device_reports 
        (username, phone_number, serial_number, device_type, floor, issue_description, report_date, queue_number)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssss", $nickname, $phone, $serial, $device, $floor, $issue, $queueCode);
$stmt->execute();
$stmt->close();
$conn->close();

// ==== ตอบกลับ ====
$response = [
    "fulfillmentText" => 
        "รับการแจ้งซ่อมครับ คุณ $nickname\n" .
        "📌 คิวของคุณคือ: \"$queueCode\"\n" .
        "🔧 อุปกรณ์: \"$device\"\n" .
        "🔢 หมายเลขเครื่อง: \"$serial\"\n" .
        "🏢 ห้อง: \"$floor\"\n" .
        "❗ ปัญหา: \"$issue\"\n" .
        "📞 ทีมงานจะติดต่อกลับที่เบอร์: $phone ครับ",
    "outputContexts" => []
];

header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
