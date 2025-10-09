<?php
// assign_technician.php

// ===== ★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★ =====
// ===== กรุณากรอก LINE NOTIFY ACCESS TOKEN ของคุณที่นี่ =====
// =====                                                         =====
define('7f0rLD4oN4UjV/DY535T4LbemrH+s7OT2lCxMk1dMJdWymlDgLvc89XZvvG/qBNg19e9/HvpKHsgxBFEHkXQlDQN5B8w3L0yhcKCSR51vfvTvUm0o5GQcq+jRlT+4TiQNN0DbIL2jI+adHfOz44YRQdB04t89/1O/w1cDnyilFU=');
// =====                                                         =====
// ===== ★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★ =====


/**
 * ฟังก์ชันสำหรับส่งข้อความแจ้งเตือนไปยัง LINE Notify
 * @param string $message ข้อความที่ต้องการส่ง
 * @return void
 */
function sendLineNotify($message) {
    if (!defined('LINE_NOTIFY_TOKEN') || LINE_NOTIFY_TOKEN === '7f0rLD4oN4UjV/DY535T4LbemrH+s7OT2lCxMk1dMJdWymlDgLvc89XZvvG/qBNg19e9/HvpKHsgxBFEHkXQlDQN5B8w3L0yhcKCSR51vfvTvUm0o5GQcq+jRlT+4TiQNN0DbIL2jI+adHfOz44YRQdB04t89/1O/w1cDnyilFU=') {
        return; // ไม่ต้องทำอะไรถ้ายังไม่ได้ตั้งค่า Token
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
    // เพิ่มการตั้งค่าสำหรับ SSL (อาจจำเป็นสำหรับบางโฮสต์)
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);

    $result = curl_exec($ch);
    curl_close($ch);
    // สามารถ log $result เพื่อตรวจสอบการทำงานได้หากต้องการ
}

// ตรวจสอบว่าเป็นการส่งข้อมูลมาแบบ POST และมีข้อมูลครบถ้วน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['technician'])) {

    // เชื่อมต่อฐานข้อมูล
    $conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
    if ($conn->connect_error) {
        die("DB Error");
    }
    $conn->set_charset("utf8");

    $reportId = (int)$_POST['id'];
    $technicianName = trim($_POST['technician']);

    // อัปเดตฐานข้อมูล: เปลี่ยนสถานะเป็น 'กำลังซ่อม' และบันทึกชื่อช่าง
    $updateSql = "UPDATE device_reports SET status = 'in_progress', assigned_technician = ? WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    if ($stmt) {
        $stmt->bind_param('si', $technicianName, $reportId);
        $stmt->execute();
        $stmt->close();
    }

    // ดึงข้อมูลคิวเพื่อใช้ในการแจ้งเตือน
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

    // สร้างข้อความและส่งแจ้งเตือน
    $message = "คิว {$queueNumber} มอบหมายให้ช่าง: {$technicianName} (กำลังดำเนินการซ่อม)";
    sendLineNotify($message);

    // กลับไปยังหน้าเดิม
    $redirectUrl = $_POST['redirect'] ?? 'admin_dashboard.php';
    header('Location: ' . $redirectUrl);
    exit();

} else {
    // ถ้าไม่มีข้อมูลส่งมา ให้กลับไปหน้าหลัก
    header('Location: admin_dashboard.php');
    exit();
}