<?php
// ไฟล์สำหรับทดสอบและบังคับรีเซ็ตรหัสผ่าน

echo "<h1>Password Reset & Verification Test</h1>";

// 1. เชื่อมต่อฐานข้อมูล
require_once __DIR__ . '/db_connect.php';
if ($conn) {
    echo "✅ Database connection successful.<br>";
} else {
    die("❌ Database connection failed.");
}

// 2. กำหนดค่าที่ต้องการทดสอบ
$usernameToTest = 'peerapat';
$passwordToTest = 'tech1234';

echo "Testing for user: <strong>" . htmlspecialchars($usernameToTest) . "</strong><br>";
echo "Testing with password: <strong>" . htmlspecialchars($passwordToTest) . "</strong><br>";
echo "<hr>";

// 3. สร้าง Hash ใหม่บน Server นี้
$newHash = password_hash($passwordToTest, PASSWORD_DEFAULT);

echo "Generated new hash: <pre>" . htmlspecialchars($newHash) . "</pre>";
echo "Hash length: " . strlen($newHash) . " characters.<br>";
echo "<hr>";

// 4. บังคับอัปเดต Hash ใหม่นี้ลงในฐานข้อมูล
$sql = "UPDATE technicians SET password_hash = ? WHERE username = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("❌ SQL PREPARE FAILED: " . htmlspecialchars($conn->error));
}

$stmt->bind_param('ss', $newHash, $usernameToTest);
if ($stmt->execute()) {
    echo "✅ Successfully updated the password hash in the database for user '" . htmlspecialchars($usernameToTest) . "'.<br>";
} else {
    echo "❌ FAILED to update the password hash in the database. Error: " . htmlspecialchars($stmt->error) . "<br>";
}
$stmt->close();
echo "<hr>";

// 5. ทดสอบการ Verify ทันทีด้วยค่าที่เพิ่งสร้างและอัปเดตไป
echo "Now, attempting to verify the password '<strong>" . htmlspecialchars($passwordToTest) . "</strong>' against the new hash...<br>";

$isPasswordCorrect = password_verify($passwordToTest, $newHash);

echo "Verification result: ";
if ($isPasswordCorrect) {
    echo "<strong style='color:green;'>TRUE (The password is correct!)</strong><br>";
    echo "<h2>Test PASSED. Please try logging in now.</h2>";
} else {
    echo "<strong style='color:red;'>FALSE (The password did NOT match!)</strong><br>";
    echo "<h2>Test FAILED. There is a problem with the password_verify function on your server.</h2>";
}

$conn->close();

?>