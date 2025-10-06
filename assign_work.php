<?php
// assign_work.php – เลือกช่างแล้วอัปเดตเป็นกำลังซ่อม + ส่ง LINE + บันทึกลง DB

// ==== DB (ดึงจากไฟล์เชื่อมต่อหลักของคุณ) ====
require_once __DIR__.'/db_connect.php';   // ให้ได้ตัวแปร $conn ที่ connect แล้ว
if (!isset($conn) || $conn->connect_error) { http_response_code(500); exit('DB Error'); }
$conn->set_charset("utf8");

// ==== รายชื่อช่าง (ปรับเพิ่ม/ลบได้) ====
$TECHS = [
  'tong' => ['name' => 'ช่างโต้ง', 'phone' => '081-111-1111'],
  'chai' => ['name' => 'ช่างชาย',  'phone' => '082-222-2222'],
  'bew'  => ['name' => 'ช่างบิว',   'phone' => '083-333-3333'],
];

// ==== LINE push helper ====
if (is_file(__DIR__.'/line_push.php')) {
  require_once __DIR__.'/line_push.php';   // ควรมี function line_push($to, array $messages)
} else {
  require_once __DIR__.'/line_config.php';
  if (!function_exists('line_push')) {
    function line_push($to, array $messages){
      if (!$to || !defined('LINE_CH_ACCESS_TOKEN')) return false;
      $ch = curl_init('https://api.line.me/v2/bot/message/push');
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
          'Content-Type: application/json',
          'Authorization: Bearer '.LINE_CH_ACCESS_TOKEN
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['to'=>$to,'messages'=>$messages], JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 10,
      ]);
      curl_exec($ch);
      $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      return $http>=200 && $http<300;
    }
  }
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ==== รับค่า ====
$id    = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$tech  = $_POST['tech'] ?? '';
$redir = $_POST['redirect'] ?? 'admin_dashboard.php';

if (!$id || !isset($TECHS[$tech])) { header("Location: ".$redir); exit; }

$name = $TECHS[$tech]['name'];
$tel  = $TECHS[$tech]['phone'];

// ==== ดึงข้อมูลงาน ====
$stmt = $conn->prepare("SELECT * FROM device_reports WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) { header("Location: ".$redir); exit; }

// ==== อัปเดตเป็นกำลังซ่อม + บันทึกช่าง ====
// ถ้าต้องการ “ล็อกไม่ให้เปลี่ยนช่างถ้าถูก assign ไปแล้ว”
// ให้เพิ่ม AND assigned_tech IS NULL ใน WHERE และตรวจ affected_rows
$upd = $conn->prepare("
  UPDATE device_reports
     SET status='in_progress',
         assigned_tech=?,
         assigned_tech_phone=?,
         assigned_at=NOW()
   WHERE id=?");
$upd->bind_param("ssi", $name, $tel, $id);
$upd->execute();
$upd->close();

// ==== ส่ง LINE ให้ผู้ใช้ (ถ้ามี line_user_id) ====
$lineId = $job['line_user_id'] ?? '';
if ($lineId) {
  $title   = "สถานะอัปเดต: กำลังซ่อม";
  $detail  = "อุปกรณ์: ".$job['device_type'].($job['serial_number'] ? " (".$job['serial_number'].")" : "");
  $techStr = "ช่างผู้ดูแล: ".$name." • ".$tel;
  $report  = "แจ้งเมื่อ: ".(@date('d/m/Y H:i', strtotime($job['report_date'])) ?: $job['report_date']);
  $issue   = "ปัญหา: ".$job['issue_description'];
  $msg     = $title."\n".$detail."\n".$techStr."\n".$report."\n".$issue;
  @line_push($lineId, [['type'=>'text','text'=>$msg]]);
}

header("Location: ".$redir);
exit;
