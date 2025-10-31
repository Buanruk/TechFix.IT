<?php
// /update_status.php — แก้ไข Error 500, ลบโค้ด PDF, และเปิดใช้งาน LINE Push

// ‼️ 1. ต้องรวมไฟล์ฟังก์ชัน line_push.php ‼️
// ตรวจสอบว่าไฟล์นี้อยู่ในโฟลเดอร์เดียวกัน
require_once __DIR__ . '/line_push.php'; 

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Bangkok');

// === DB ===
// ตรวจสอบความถูกต้องของข้อมูลเชื่อมต่อฐานข้อมูล
$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
$conn->set_charset("utf8mb4");

// รับเฉพาะ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: /");
  exit;
}

// 2. รับค่า (เพิ่ม technician_id)
$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = $_POST['status'] ?? '';
// รับ technician_id จากฟอร์ม
$tech_id = isset($_POST['technician_id']) && $_POST['technician_id'] !== '' ? (int)$_POST['technician_id'] : null;

$allowed = ['new','in_progress','done'];
$job = null; // เตรียมตัวแปร job ไว้

if ($id > 0 && in_array($status, $allowed, true)) {

  // 3. อัปเดต DB: อัปเดตสถานะและชื่อช่างในคราวเดียว
  if ($status === 'in_progress' && $tech_id !== null && $tech_id > 0) {
      
      // อัปเดตสถานะ + ID ช่าง + ชื่อช่าง (ดึงจากตาราง technicians)
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

  // 4. ดึงข้อมูลหลังอัปเดต (เพื่อเอา line_user_id และข้อมูลงานซ่อม)
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
             . "หมายเลขเครื่อง: {$job['serial_number']}\n"
             . "ชั้น: {$job['floor']}\n"
             . "ปัญหา: {$job['issue_description']}";
      }
    }
  }

  // 5. ส่ง LINE Push (ถ้ามีข้อความและ User ID)
  if ($msg && $line_user_id) {
    
    // เรียกใช้ฟังก์ชันจาก line_push.php
    list($http, $res, $err) = line_push($line_user_id, $msg);

    // Log ตรวจสอบผล (สำคัญ)
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