<?php
// /update_status.php — แก้ไขให้รับ technician_id และอัปเดตพร้อมกัน

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

// 1. ‼️‼️‼️ รับค่า (เพิ่ม technician_id) ‼️‼️‼️
$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = $_POST['status'] ?? '';
$tech_id = isset($_POST['technician_id']) && $_POST['technician_id'] !== '' ? (int)$_POST['technician_id'] : null;

$allowed = ['new','in_progress','done'];
$job = null; // เตรียมตัวแปร job ไว้

if ($id > 0 && in_array($status, $allowed, true)) {

  // 2. ‼️‼️‼️ อัปเดต DB (แบบใหม่) ‼️‼️‼️
  // ถ้ามีการส่ง tech_id มา (และสถานะเป็น in_progress) ให้อัปเดตช่างด้วย
  if ($status === 'in_progress' && $tech_id !== null && $tech_id > 0) {
      
      // อัปเดตสถานะ + ID ช่าง + ชื่อช่าง (ดึงจากตาราง technicians) ในคราวเดียว
      // *** ตรวจสอบให้แน่ใจว่าคุณมีตาราง 'technicians' และคอลัมน์ 'name' ***
      $stmt = $conn->prepare(
          "UPDATE device_reports SET
              status = ?,
              technician_id = ?,
              assigned_technician = (SELECT name FROM technicians WHERE id = ?)
           WHERE id = ?"
      );
      $stmt->bind_param("siii", $status, $tech_id, $tech_id, $id);
  
  } else {
      // อัปเดตเฉพาะสถานะ (กรณีเป็น 'done' หรือ 'new' หรือไม่เลือกช่าง)
      $stmt = $conn->prepare("UPDATE device_reports SET status = ? WHERE id = ?");
      $stmt->bind_param("si", $status, $id);
  }
  $stmt->execute();
  $stmt->close();

  // 3. ดึงข้อมูล (ทำหลัง UPDATE)
  // ตอนนี้ข้อมูล 'assigned_technician' จะเป็นข้อมูลล่าสุดที่เพิ่งอัปเดตไป
  $msg = null;
  $line_user_id = null;

  if ($status === 'in_progress' || $status === 'done') {
    
    // เราต้อง SELECT ข้อมูลทั้งหมดอีกครั้งเพื่อเอา line_user_id และข้อมูลอื่นๆ
    $q = $conn->prepare("SELECT username, device_type, serial_number, floor, issue_description,
                         queue_number, line_user_id, assigned_technician
                         FROM device_reports WHERE id = ?");
    $q->bind_param("i", $id);
    $q->execute();
    $job = $q->get_result()->fetch_assoc();
    $q->close();

    if (!empty($job) && !empty($job['line_user_id'])) {
      $line_user_id = $job['line_user_id'];
      $queue = $job['queue_number'] ?? '-';
      
      if ($status === 'in_progress') {
        // ‼️‼️‼️ จุดที่แก้ไข ‼️‼️‼️
        // $job['assigned_technician'] จะมีค่าแล้ว เพราะเรา UPDATE ไปในขั้นตอนที่ 2
        $tech_name = $job['assigned_technician'] ?? 'ไม่ระบุ'; 
        
        // กันไม่ส่งถ้าไม่มีชื่อช่าง
        if ($tech_name !== 'ไม่ระบุ' && $tech_name !== null) {
            $msg = "คิว {$queue} ของคุณ\n"
                 . "สถานะ: 🔧 กำลังดำเนินการซ่อม\n"
                 . "รับการซ่อมโดยช่าง: {$tech_name}";
        }

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

  // 4. ส่ง LINE (ถ้ามี $msg และ $line_user_id)
  if ($msg && $line_user_id) {
    
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

    @file_put_contents(__DIR__ . "/line_push_log.txt",
      date("Y-m-d H:i:s")." id=$id status=$status http=$http err=$err res=$res\n", FILE_APPEND);
  }
}

$conn->close();

// กลับหน้าเดิม
$back = $_SERVER['HTTP_REFERER'] ?? '/';
header("Location: {$back}");
exit;
?>