<?php
// /update_status.php — อัปเดตสถานะ + Push LINE (แก้ 415 แล้ว)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Bangkok');

// === ใส่ Channel Access Token ของ LINE OA ===
$LINE_CHANNEL_ACCESS_TOKEN = '7f0rLD4oN4UjV/DY535T4LbemrH+s7OT2lCxMk1dMJdWymlDgLvc89XZvvG/qBNg19e9/HvpKHsgxBFEHkXQlDQN5B8w3L0yhcKCSR51vfvTvUm0o5GQcq+jRlT+4TiQNN0DbIL2jI+adHfOz44YRQdB04t89/1O/w1cDnyilFU=';

// === DB ===
$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
$conn->set_charset("utf8mb4");

// รับเฉพาะ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: /");
  exit;
}

// รับค่า
$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = $_POST['status'] ?? '';
$allowed = ['new','in_progress','done'];

if ($id > 0 && in_array($status, $allowed, true)) {

  // 1. อัปเดตสถานะ (ทำก่อนเสมอ)
  $stmt = $conn->prepare("UPDATE device_reports SET status = ? WHERE id = ?");
  $stmt->bind_param("si", $status, $id);
  $stmt->execute();
  $stmt->close();

  $msg = null; // ตัวแปรเก็บข้อความที่จะส่ง
  $line_user_id = null; // ตัวแปรเก็บ ID ผู้ใช้

  // 2. ถ้าสถานะเป็น "กำลังทำ" หรือ "เสร็จแล้ว" ให้เตรียมส่ง LINE
  if ($status === 'in_progress' || $status === 'done') {

    // ดึงข้อมูลงานซ่อม (รวมถึง line_user_id และชื่อช่าง)
    // *** แก้ 'technician_name' ถ้าชื่อคอลัมน์ของคุณไม่ตรง ***
    $q = $conn->prepare("SELECT username, device_type, serial_number, floor, issue_description,
                         queue_number, line_user_id, technician_name
                         FROM device_reports WHERE id = ?");
    $q->bind_param("i", $id);
    $q->execute();
    $job = $q->get_result()->fetch_assoc();
    $q->close();

    if (!empty($job) && !empty($job['line_user_id'])) {
      $line_user_id = $job['line_user_id'];
      $queue = $job['queue_number'] ?? '-';
      
      // ‼️‼️‼️ ส่วนที่เพิ่มเข้ามา ‼️‼️‼️
      if ($status === 'in_progress') {
        $tech_name = $job['technician_name'] ?? 'ไม่ระบุ';
        $msg = "คิว {$queue} ของคุณ\n"
             . "สถานะ: 🔧 กำลังดำเนินการซ่อม\n"
             . "รับการซ่อมโดยช่าง: {$tech_name}";
      
      // ส่วน "เสร็จแล้ว" ที่คุณมีอยู่แล้ว
      } elseif ($status === 'done') {
        $msg = "แจ้งเตือนจาก techfix.asia\n"
             . "งานซ่อมคิว: {$queue}\n"
             . "สถานะ: ✅ ซ่อมเสร็จแล้ว\n"
             . "อุปกรณ์: {$job['device_type']}\n"
             . "หมายเลขเครื่อง: {$job['serial_number']}\n"
             . "ชั้น: {$job['floor']}\n"
             . "ปัญหา: {$job['issue_description']}";
      }
    }
  }

  // 3. ถ้มี $msg และ $line_user_id ที่ต้องส่ง ให้ส่ง Push
  if ($msg && $line_user_id) {
    
    // === ส่ง LINE Push (สำคัญ: header ต้องเป็นสตริงแบบนี้) ===
    $url = 'https://api.line.me/v2/bot/message/push';
    $headers = [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $LINE_CHANNEL_ACCESS_TOKEN,
    ];
    $payload = json_encode([
      'to' => $line_user_id,
      'messages' => [[ 'type' => 'text', 'text' => $msg ]]
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $res  = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    // Log ตรวจสอบผล push
    @file_put_contents(__DIR__ . "/line_push_log.txt",
      date("Y-m-d H:i:s")." id=$id status=$status http=$http err=$err res=$res\n", FILE_APPEND);
  }
}

$conn->close();

// กลับหน้าเดิม ถ้าไม่มีก็กลับหน้าแรก
$back = $_SERVER['HTTP_REFERER'] ?? '/';
header("Location: {$back}");
exit;
?>