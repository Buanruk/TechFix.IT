<?php
// assign_technician.php (เวอร์ชันสำหรับตรวจสอบปัญหา)

// เปิดการแสดงผล Error เพื่อช่วยในการตรวจสอบ (ควรปิดหลังจากแก้ไขปัญหาเสร็จ)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// ===== กรุณาตรวจสอบว่า LINE NOTIFY ACCESS TOKEN ของคุณถูกต้อง 100% =====
define('LINE_NOTIFY_TOKEN', '7f0rLD4oN4UjV/DY535T4LbemrH+s7OT2lCxMk1dMJdWymlDgLvc89XZvvG/qBNg19e9/HvpKHsgxBFEHkXQlDQN5B8w3L0yhcKCSR51vfvTvUm0o5GQcq+jRlT+4TiQNN0DbIL2jI+adHfOz44YRQdB04t89/1O/w1cDnyilFU=');


/**
 * ฟังก์ชันสำหรับส่งข้อความแจ้งเตือนไปยัง LINE Notify
 * @param string $message ข้อความที่ต้องการส่ง
 * @return void
 */
function sendLineNotify($message) {
    if (!defined('LINE_NOTIFY_TOKEN') || LINE_NOTIFY_TOKEN === 'YOUR_LINE_NOTIFY_ACCESS_TOKEN' || empty(LINE_NOTIFY_TOKEN)) {
        // บันทึก Log ว่า Token ไม่ได้ตั้งค่า
        file_put_contents('line_notify_error.log', date('[Y-m-d H:i:s]') . " Error: LINE Notify Token is not configured." . PHP_EOL, FILE_APPEND);
        return;
    }

    $url = 'https://notify-api.line.me/api/notify';
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Bearer ' . LINE_NOTIFY_TOKEN,
    ];
    $data = ['message' => $message];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // ตั้งเวลา Timeout

    $result = curl_exec($ch);

    // ===== ส่วนตรวจสอบ Error ที่เพิ่มเข้ามา =====
    if (curl_errno($ch)) {
        $error_message = date('[Y-m-d H:i:s]') . ' cURL Error: ' . curl_error($ch);
        // บันทึก Error ลงในไฟล์ line_notify_error.log
        file_put_contents('line_notify_error.log', $error_message . PHP_EOL, FILE_APPEND);
    } else {
        // ตรวจสอบ HTTP Status Code จาก LINE
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code != 200) {
            $error_message = date('[Y-m-d H:i:s]') . " LINE Notify API Error: HTTP Status Code " . $http_code . ". Response: " . $result;
            file_put_contents('line_notify_error.log', $error_message . PHP_EOL, FILE_APPEND);
        }
    }
    // =======================================
    
    curl_close($ch);
}


// --- ส่วนที่เหลือของไฟล์เหมือนเดิม ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['technician'])) {

    $conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
    if ($conn->connect_error) { die("DB Error"); }
    $conn->set_charset("utf8");

    $reportId = (int)$_POST['id'];
    $technicianName = trim($_POST['technician']);

    $updateSql = "UPDATE device_reports SET status = 'in_progress', assigned_technician = ? WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    if ($stmt) {
        $stmt->bind_param('si', $technicianName, $reportId);
        $stmt->execute();
        $stmt->close();
    }

    $queueNumber = 'N/A';
    $qStmt = $conn->prepare("SELECT queue_number FROM device_reports WHERE id = ?");
    if ($qStmt) {
        $qStmt->bind_param('i', $reportId);
        $qStmt->execute();
        $result = $qStmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $queueNumber = $row['queue_number'];
        }
        $qStmt->close();
    }

    $conn->close();

    if (!empty($technicianName)) {
        $message = "คิว {$queueNumber} มอบหมายให้ช่าง: {$technicianName} (กำลังดำเนินการซ่อม)";
        sendLineNotify($message);
    }
    
    $redirectUrl = $_POST['redirect'] ?? 'admin_dashboard.php';
    $cacheBuster = (strpos($redirectUrl, '?') === false) ? '?' : '&';
    $redirectUrl .= $cacheBuster . 't=' . time();

    header('Location: ' . $redirectUrl);
    exit();

} else {
    header('Location: admin_dashboard.php');
    exit();
}