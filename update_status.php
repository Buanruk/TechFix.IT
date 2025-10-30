<?php
// /update_status.php — ฉบับแก้ไข เรียกใช้ line_push.php

// ‼️‼️‼️ 1. ต้อง include ไฟล์ฟังก์ชันที่สร้างขึ้นมาใหม่ ‼️‼️‼️
require_once __DIR__ . 'line_push.php'; // หรือระบุ path ให้ถูก

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Bangkok');

// === DB ===
$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
$conn->set_charset("utf8mb4");

// รับเฉพาะ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: /");
  exit;
}

// 2. รับค่า (เหมือนเดิม)
$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = $_POST['status'] ?? '';
$tech_id = isset($_POST['technician_id']) && $_POST['technician_id'] !== '' ? (int)$_POST['technician_id'] : null;

$allowed = ['new','in_progress','done'];
$job = null;

if ($id > 0 && in_array($status, $allowed, true)) {

  // 3. อัปเดต DB (เหมือนเดิม)
  if ($status === 'in_progress' && $tech_id !== null && $tech_id > 0) {
      // ตรวจสอบว่าตาราง technicians และคอลัมน์ name ถูกต้อง
      $stmt = $conn->prepare(
          "UPDATE device_reports SET
              status = ?,
              technician_id = ?,
              assigned_technician = (SELECT name FROM technicians WHERE id = ?)
           WHERE id = ?"
      );
      $stmt->bind_param("siii", $status, $tech_id, $tech_id, $id);
  } else {
      $stmt = $conn->prepare("UPDATE device_reports SET status = ? WHERE id = ?");
      $stmt->bind_param("si", $status, $id);
  }
  $stmt->execute();
  $stmt->close();

  // 4. ดึงข้อมูล (เหมือนเดิม)
  $msg = null;
  $line_user_id = null;

  if ($status === 'in_progress' || $status === 'done') {
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
        $tech_name = $job['assigned_technician'] ?? 'ไม่ระบุ';
        
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
             // ... (ข้อความอื่นๆ) ...
             . "ปัญหา: {$job['issue_description']}";
      }
    }
  }

  // 5. ‼️‼️‼️ เปลี่ยนมาเรียกใช้ฟังก์ชัน line_push() ‼️‼️‼️
  if ($msg && $line_user_id) {
    
    // บล็อก cURL เก่าถูกลบออก และแทนที่ด้วยบรรทัดนี้:
    list($http, $res, $err) = line_push($line_user_id, $msg);

    // Log ตรวจสอบผล (สำคัญมาก)
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