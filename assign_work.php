<?php
// assign_work.php
require_once __DIR__.'/line_config.php';

$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
if ($conn->connect_error) { die("DB Error"); }
$conn->set_charset("utf8");

// รายชื่อช่าง (ตั้งค่าตามจริง)
$TECHS = [
  'tong' => ['name' => 'ช่างโต้ง', 'phone' => '081-111-1111'],
  'chai' => ['name' => 'ช่างชาย', 'phone' => '082-222-2222'],
  'bew'  => ['name' => 'ช่างบิว',  'phone' => '083-333-3333'],
];

$id   = isset($_POST['id'])   ? (int)$_POST['id']   : 0;
$tech = isset($_POST['tech']) ? $_POST['tech']      : '';
$redir= isset($_POST['redirect']) ? $_POST['redirect'] : 'index.php';

if (!$id || !isset($TECHS[$tech])) {
  header("Location: ".$redir); exit;
}

$tk   = $TECHS[$tech];
$name = $tk['name'];
$tel  = $tk['phone'];

// ดึงข้อมูลงาน
$stmt = $conn->prepare("SELECT id, username, phone_number, device_type, serial_number, issue_description, line_user_id, status FROM device_reports WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) { header("Location: ".$redir); exit; }

// อัพเดตเป็นกำลังซ่อม + บันทึกช่าง
// หมายเหตุ: ถ้าอยากกันไม่ให้แก้ซ้ำ เพิ่มเงื่อนไข AND assigned_tech IS NULL
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

// ส่ง LINE push (ถ้ามี line_user_id)
if (!empty($job['line_user_id'])) {
  $to = $job['line_user_id'];

  $title = "งานซ่อมของคุณกำลังดำเนินการ";
  $detail = "อุปกรณ์: ".$job['device_type'].($job['serial_number']? " (".$job['serial_number'].")" : "");
  $techInfo = $name." • โทร ".$tel;

  $msgText = $title."\n"
           . $detail."\n"
           . "รับงานโดย: ".$techInfo."\n"
           . "หมายเหตุ: ".$job['issue_description'];

  @line_push($to, [
    ['type'=>'text','text'=>$msgText]
  ]);
}

$conn->close();

// กลับหน้าเดิม
header("Location: ".$redir);
exit;
