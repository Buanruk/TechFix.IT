<?php
session_start();
require_once __DIR__ . '/db_connect.php'; // ตรวจสอบว่า db_connect.php อยู่ในที่ที่ถูกต้อง

// ตรวจสอบว่า Admin ได้ล็อกอินแล้ว
if (!isset($_SESSION['admin_id'])) {
    // หากไม่ได้ล็อกอิน ให้เปลี่ยนเส้นทางไปยังหน้าล็อกอินของ Admin
    $_SESSION['error'] = "คุณต้องล็อกอินในฐานะผู้ดูแลระบบเพื่อดำเนินการนี้";
    header('Location: admin_login.php');
    exit();
}

// ตรวจสอบว่ามีการส่ง technician_id มาหรือไม่
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    $_SESSION['error'] = "ไม่พบ ID ช่างที่ต้องการลบ";
    header('Location: manage_technicians.php'); // หรือหน้าเดิมที่ผู้ใช้มาจาก
    exit();
}

$technician_id = (int)$_POST['id'];
$redirect_url = $_POST['redirect'] ?? 'manage_technicians.php'; // URL ที่จะกลับไปหลังลบ

// ตรวจสอบว่าช่างคนนี้มีงานที่ยังไม่เสร็จหรือกำลังซ่อมอยู่หรือไม่
// หากมี ควรจะเตือนหรือไม่อนุญาตให้ลบ เพื่อป้องกันข้อมูลค้าง
$check_jobs_stmt = $conn->prepare("SELECT COUNT(*) FROM device_reports WHERE technician_id = ? AND status IN ('new', 'in_progress')");
$check_jobs_stmt->bind_param("i", $technician_id);
$check_jobs_stmt->execute();
$check_jobs_stmt->bind_result($active_jobs_count);
$check_jobs_stmt->fetch();
$check_jobs_stmt->close();

if ($active_jobs_count > 0) {
    $_SESSION['error'] = "ไม่สามารถลบช่างได้ เนื่องจากมีงานที่ยังไม่เสร็จหรือกำลังซ่อมอยู่ " . $active_jobs_count . " งาน";
    header('Location: ' . $redirect_url);
    exit();
}

// เริ่ม Transaction เพื่อให้การลบข้อมูลทั้งหมดที่เกี่ยวข้องสำเร็จพร้อมกัน
$conn->begin_transaction();

try {
    // 1. ลบงานที่ช่างคนนี้เคยได้รับมอบหมาย (สถานะ 'done' หรือ 'new' ที่ไม่ได้ถูก assign แล้ว)
    // หรืออาจจะตั้งค่า technician_id ในตาราง device_reports ให้เป็น NULL แทนการลบ
    // สำหรับการลบช่าง เราอาจจะต้องการ 'reset' งานที่เคยถูก assign ให้เป็น NULL ก่อนลบช่าง
    $update_report_stmt = $conn->prepare("UPDATE device_reports SET technician_id = NULL, assigned_technician = NULL WHERE technician_id = ?");
    $update_report_stmt->bind_param("i", $technician_id);
    $update_report_stmt->execute();
    $update_report_stmt->close();

    // 2. ลบข้อมูลช่างออกจากตาราง technicians
    $delete_technician_stmt = $conn->prepare("DELETE FROM technicians WHERE id = ?");
    $delete_technician_stmt->bind_param("i", $technician_id);
    $delete_technician_stmt->execute();

    if ($delete_technician_stmt->affected_rows > 0) {
        $conn->commit(); // ยืนยันการเปลี่ยนแปลง
        $_SESSION['success'] = "ลบข้อมูลช่างเรียบร้อยแล้ว";
    } else {
        $conn->rollback(); // ยกเลิกการเปลี่ยนแปลงหากไม่มีอะไรถูกลบ
        $_SESSION['error'] = "ไม่พบช่าง ID " . $technician_id . " หรือไม่สามารถลบได้";
    }
    $delete_technician_stmt->close();

} catch (Exception $e) {
    $conn->rollback(); // ยกเลิกการเปลี่ยนแปลงหากมีข้อผิดพลาด
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบช่าง: " . $e->getMessage();
}

$conn->close();
header('Location: ' . $redirect_url);
exit();