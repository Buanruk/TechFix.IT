<?php
// assign_technician.php (เวอร์ชันใหม่ ใช้ระบบ LINE Push Message ที่มีอยู่แล้ว)

// 1. เรียกใช้งานไฟล์ config ที่มีฟังก์ชัน line_push() และ Token อยู่แล้ว
require_once 'line_config.php';

// 2. ตรวจสอบว่าเป็นการส่งข้อมูลมาแบบ POST และมีข้อมูลครบถ้วน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['technician'])) {

    // 3. เชื่อมต่อฐานข้อมูล
    $conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
    if ($conn->connect_error) { die("DB Error"); }
    $conn->set_charset("utf8");

    $reportId = (int)$_POST['id'];
    $technicianName = trim($_POST['technician']);

    // 4. อัปเดตฐานข้อมูล: เปลี่ยนสถานะเป็น 'กำลังซ่อม' และบันทึกชื่อช่าง
    $updateSql = "UPDATE device_reports SET status = 'in_progress', assigned_technician = ? WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    if ($stmt) {
        $stmt->bind_param('si', $technicianName, $reportId);
        $stmt->execute();
        $stmt->close();
    }

    // 5. ดึงข้อมูลที่จำเป็นสำหรับการแจ้งเตือน (รวมถึง line_user_id)
    // *** หากคอลัมน์ที่เก็บ ID ผู้ใช้ LINE ของคุณไม่ใช่ชื่อ 'line_user_id' กรุณาแก้ไขตรงนี้ ***
    $line_user_id = null;
    $queueNumber = 'N/A';
    $qStmt = $conn->prepare("SELECT queue_number, line_user_id FROM device_reports WHERE id = ?");
    if ($qStmt) {
        $qStmt->bind_param('i', $reportId);
        $qStmt->execute();
        $result = $qStmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $queueNumber = $row['queue_number'];
            $line_user_id = $row['line_user_id']; // ดึง User ID ของ LINE จากฐานข้อมูล
        }
        $qStmt->close();
    }

    $conn->close();

    // 6. สร้างข้อความและส่งแจ้งเตือนผ่านฟังก์ชัน line_push() ที่มีอยู่แล้ว
    if (!empty($technicianName) && !empty($line_user_id)) {
        
        // จัดรูปแบบข้อความตามที่ฟังก์ชัน line_push() ต้องการ
        $messageText = "คิว {$queueNumber} ของคุณ\nมอบหมายให้ช่าง: {$technicianName}\nสถานะ: กำลังดำเนินการซ่อม";
        
        $messages = [
            [
                'type' => 'text',
                'text' => $messageText
            ]
        ];

        // เรียกใช้ฟังก์ชัน line_push() จากไฟล์ line_config.php
        line_push($line_user_id, $messages);
    }
    
    // 7. กลับไปยังหน้าเดิม
    $redirectUrl = $_POST['redirect'] ?? 'admin_dashboard.php';
    $cacheBuster = (strpos($redirectUrl, '?') === false) ? '?' : '&';
    $redirectUrl .= $cacheBuster . 't=' . time();

    header('Location: ' . $redirectUrl);
    exit();

} else {
    // ถ้าไม่มีข้อมูลส่งมา ให้กลับไปหน้าหลัก
    header('Location: admin_dashboard.php');
    exit();
}