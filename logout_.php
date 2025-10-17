<?php
// 1. เริ่ม session เพื่อที่จะเข้าถึงและทำลายมัน
session_start();

// 2. ล้างค่าตัวแปรใน session ทั้งหมด
session_unset();

// 3. ทำลาย session
session_destroy();

// 4. เด้ง (Redirect) กลับไปหน้า technician_login.php
header('Location: technician_login.php');
exit(); // จบการทำงานทันที
?>