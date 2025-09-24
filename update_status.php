<?php
// update_status.php — แบบง่าย (ไม่อัปเดต updated_at)
// วางที่: C:\xampp\htdocs\techfix\end\update_status.php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ชื่อโฟลเดอร์โปรเจกต์ใต้ htdocs (มี / นำหน้า)
$APP_BASE = '/techfix';

// DB
$conn = new mysqli("localhost", "phpadmin", "2547", "techfix");
$conn->set_charset("utf8mb4");

// รับเฉพาะ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: {$APP_BASE}/index.php");
  exit;
}

// รับค่า
$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = $_POST['status'] ?? '';
$allowed = ['new','in_progress','done'];

if ($id > 0 && in_array($status, $allowed, true)) {
  $stmt = $conn->prepare("UPDATE device_reports SET status = ? WHERE id = ?");
  $stmt->bind_param("si", $status, $id);
  $stmt->execute();
  $stmt->close();
}

$conn->close();

// กลับหน้าเดิม ถ้าไม่มี referer ให้กลับหน้าหลัก
$back = $_SERVER['HTTP_REFERER'] ?? "{$APP_BASE}/index.php";
header("Location: {$back}");
exit;
