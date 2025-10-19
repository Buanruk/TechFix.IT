<?php
date_default_timezone_set('Asia/Bangkok')
// db_connect.php — เชื่อมต่อฐานข้อมูลครั้งเดียว แล้วให้ไฟล์อื่น include มาใช้

$DB_HOST = "localhost";
$DB_USER = "techfixuser";
$DB_PASS = "StrongPass!234";
$DB_NAME = "techfix";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(["error" => "DB_CONNECTION_FAILED"], JSON_UNESCAPED_UNICODE);
  exit;
}

$conn->set_charset('utf8mb4');

$conn->query("SET time_zone = '+07:00'");

?>