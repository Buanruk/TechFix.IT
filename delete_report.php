<?php
// delete_report.php — ลบรายการแจ้งซ่อมแบบปลอดภัย + กลับหน้าเดิม

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Bangkok');

// DB
$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
$conn->set_charset("utf8mb4");

// รับเฉพาะ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: /");
  exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$redirect = $_POST['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '/');

// ลบแถว
if ($id > 0) {
  $stmt = $conn->prepare("DELETE FROM device_reports WHERE id = ? LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
}

$conn->close();

// กัน open redirect: อนุญาตเฉพาะ path ในโดเมนเดียวกัน
if (!preg_match('/^\/[A-Za-z0-9\/\-\_\.\?\=&%]*$/', $redirect)) {
  $redirect = '/';
}
header("Location: {$redirect}");
exit;
