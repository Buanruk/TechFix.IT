<?php
// 1. เรียกใช้งานไฟล์ config
require_once 'line_config.php';
require_once 'db_connect.php'; // เรียกใช้ไฟล์เชื่อมต่อกลาง

// 2. ตรวจสอบว่าเป็นการส่งข้อมูลมาแบบ POST และมีข้อมูลครบถ้วน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['technician_id'])) {

    $reportId = (int)$_POST['id'];
    $technicianId = (int)$_POST['technician_id'];
    $technicianName = ''; // สร้างตัวแปรว่างไว้ก่อน

    // 3. ดึงชื่อเต็มของช่างจาก ID ที่ได้รับมา
    $stmt_tech = $conn->prepare("SELECT fullname FROM technicians WHERE id = ?");
    if ($stmt_tech) {
        $stmt_tech->bind_param('i', $technicianId);
        $stmt_tech->execute();
        $result_tech = $stmt_tech->get_result();
        if ($row_tech = $result_tech->fetch_assoc()) {
            $technicianName = $row_tech['fullname'];
        }
        $stmt_tech->close();
    }

    if (!empty($technicianName)) {
        // 4. อัปเดตฐานข้อมูล: เปลี่ยนสถานะ, บันทึกชื่อช่าง และ technician_id
        $updateSql = "UPDATE device_reports SET status = 'in_progress', assigned_technician = ?, technician_id = ? WHERE id = ?";
        $stmt = $conn->prepare($updateSql);
        if ($stmt) {
            $stmt->bind_param('sii', $technicianName, $technicianId, $reportId);
            $stmt->execute();
            $stmt->close();
        }

        // 5. ดึงข้อมูลที่จำเป็นสำหรับการแจ้งเตือน LINE
        $line_user_id = null;
        $queueNumber = 'N/A';
        $qStmt = $conn->prepare("SELECT queue_number, line_user_id FROM device_reports WHERE id = ?");
        if ($qStmt) {
            $qStmt->bind_param('i', $reportId);
            $qStmt->execute();
            $result = $qStmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $queueNumber = $row['queue_number'];
                $line_user_id = $row['line_user_id'];
            }
            $qStmt->close();
        }

        // 6. สร้างข้อความและส่งแจ้งเตือนผ่าน LINE
        if (!empty($line_user_id)) {
            $messageText = "คิว {$queueNumber} ของคุณ\nรับการซ่อมโดยช่าง: {$technicianName}\nสถานะ: กำลังดำเนินการซ่อม";
            $messages = [['type' => 'text', 'text' => $messageText]];
            line_push($line_user_id, $messages);
        }
    }

    $conn->close();

    // 7. กลับไปยังหน้าเดิม
    header('Location: admin_dashboard.php?assign=success');
    exit();

} else {
    // ถ้าไม่มีข้อมูลส่งมา ให้กลับไปหน้าหลัก
    header('Location: admin_dashboard.php?error=nodata');
    exit();
}