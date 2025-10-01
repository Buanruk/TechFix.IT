<?php
// update_status.php — อัปเดตสถานะ + แจ้ง LINE
// วางที่: C:\xampp\htdocs\techfix\end\update_status.php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ==== CONFIG ====
$APP_BASE = '/techfix';
$LINE_CHANNEL_ACCESS_TOKEN = '7f0rLD4oN4UjV/DY535T4LbemrH+s7OT2lCxMk1dMJdWymlDgLvc89XZvvG/qBNg19e9/HvpKHsgxBFEHkXQlDQN5B8w3L0yhcKCSR51vfvTvUm0o5GQcq+jRlT+4TiQNN0DbIL2jI+adHfOz44YRQdB04t89/1O/w1cDnyilFU='; // <<< ใส่ Token ของคุณ

// ==== DB CONNECT ====
$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
$conn->set_charset("utf8mb4");

// ==== รับเฉพาะ POST ====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: {$APP_BASE}/index.php");
  exit;
}

// ==== รับค่า ====
$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = $_POST['status'] ?? '';
$allowed = ['new','in_progress','done'];

if ($id > 0 && in_array($status, $allowed, true)) {
  // อัปเดตสถานะ
  $stmt = $conn->prepare("UPDATE device_reports SET status = ? WHERE id = ?");
  $stmt->bind_param("si", $status, $id);
  $stmt->execute();
  $stmt->close();

  // ถ้าสถานะ = done → ดึงข้อมูลและส่ง LINE แจ้งลูกค้า
  if ($status === 'done') {
    $q = $conn->prepare("SELECT username, device_type, serial_number, issue_description, queue_number, line_user_id 
                         FROM device_reports WHERE id = ?");
    $q->bind_param("i", $id);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();

    if (!empty($res['line_user_id'])) {
      $msg = "แจ้งเตือนจาก TechFix.it\n"
           . "งานซ่อมคิว: {$res['queue_number']}\n"
           . "สถานะ: ✅ ซ่อมเสร็จแล้ว\n"
           . "อุปกรณ์: {$res['device_type']} | SN: {$res['serial_number']}\n"
           . "ปัญหา: {$res['issue_description']}";

      // ==== ส่ง LINE Push ====
      $url = 'https://api.line.me/v2/bot/message/push';
      $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $LINE_CHANNEL_ACCESS_TOKEN
      ];
      $payload = json_encode([
        'to' => $res['line_user_id'],
        'messages' => [[
          'type' => 'text',
          'text' => $msg
        ]]
      ], JSON_UNESCAPED_UNICODE);

      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $payload,
      ]);
      $result = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);
      curl_close($ch);

      // (Debug) เขียน log ไว้เช็คถ้า push ไม่ผ่าน
      file_put_contents(__DIR__ . "/line_push_log.txt",
        date("Y-m-d H:i:s")." HTTP:$httpCode ERR:$error RES:$result\n",
        FILE_APPEND
      );
    }
  }
}

$conn->close();

// กลับหน้าเดิม ถ้าไม่มี referer ให้กลับหน้าหลัก
$back = $_SERVER['HTTP_REFERER'] ?? "{$APP_BASE}/index.php";
header("Location: {$back}");
exit;
