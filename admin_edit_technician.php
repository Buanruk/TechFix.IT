<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

$tech_id = (int)($_GET['id'] ?? 0);
if ($tech_id === 0) {
    $_SESSION['error'] = 'ID ช่างไม่ถูกต้อง';
    header('Location: manage_technicians.php');
    exit;
}

$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
if ($conn->connect_error) { die("DB Error"); }
$conn->set_charset("utf8");

$stmt = $conn->prepare("SELECT * FROM technicians WHERE id = ?");
$stmt->bind_param("i", $tech_id);
$stmt->execute();
$tech = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tech) {
    $_SESSION['error'] = 'ไม่พบข้อมูลช่าง';
    header('Location: manage_technicians.php');
    exit;
}

$errorMsg = $_SESSION['error'] ?? ''; unset($_SESSION['error']);
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>แก้ไขข้อมูลช่าง - TechFix Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    /* (คัดลอก CSS ทั้งหมดจาก `manage_technicians.php` มาใส่ตรงนี้ได้เลย) */
    :root{--navy:#0b2440; --blue:#1e88e5; --bg:#f5f9ff; --card:#ffffff; --line:#e6effa; --text:#1f2937;--green:#2e7d32; --red:#c62828; --blue-strong:#0b63c8;--shadow:0 16px 40px rgba(10,37,64,.12);--radius:20px;--container:1680px;}
    *{box-sizing:border-box} html,body{margin:0}
    body{font-family:system-ui,Segoe UI,Roboto,"TH Sarabun New",Tahoma,sans-serif;color:var(--text);background: radial-gradient(1200px 600px at 50% -240px,#eaf3ff 0,transparent 60%),linear-gradient(180deg,#fbfdff 0,var(--bg) 100%);}
    .site-header{position:sticky;top:0;z-index:1000;background:linear-gradient(90deg,#0b3a6b 0,#1366b3 100%);color:#fff;box-shadow:0 6px 18px rgba(0,0,0,.12)}
    .navbar{height:60px;display:flex;align-items:center;justify-content:space-between;padding:0 30px;position:relative}
    .brand{display:flex;align-items:center;gap:12px;color:#fff;text-decoration:none}
    .brand-mark{display:grid;place-items:center;width:36px;height:36px;border-radius:999px;background:rgba(255,255,255,.15)}
    .brand-title{font-weight:800}
    .brand-sub{opacity:.85;font-size:12px;display:block}
    .shell{padding:20px}
    .container{max-width:min(96vw,var(--container)); margin:24px auto 40px; padding:0 24px;}
    .panel{border-radius:var(--radius);border:1px solid var(--line);background:var(--card);box-shadow:var(--shadow);overflow:hidden}
    .panel-head{padding:18px 22px;background:linear-gradient(180deg,rgba(78,169,255,.16),rgba(30,136,229,.10))}
    .title{margin:0;text-align:center;color:#0b2440;font-weight:900}
    .panel-body{padding:24px 30px;}
    .form-grid{max-width:700px; margin:0 auto;}
    .field{margin-bottom:16px;}
    .field label{display:block; font-weight:700; color:var(--navy); margin-bottom:6px;}
    .field input[type="text"], .field input[type="password"]{
        width:100%; box-sizing:border-box; padding:10px 14px; font-size:1rem;
        border:1px solid var(--line); border-radius:12px; background:#fff;
        box-shadow:0 8px 18px rgba(10,37,64,.06);
    }
    .field small{color:#667085; font-size:13px; margin-top:4px; display:block;}
    .btn-submit{
        font-family:inherit; font-size:15px; font-weight:700; padding:10px 18px;
        border:1px solid var(--green); border-radius:10px; cursor:pointer;
        transition:all .18s ease; background:var(--green); color:#fff;
    }
    .btn-submit:hover{background:#1b5e20; border-color:#1b5e20}
    .alert-box.error {background-color: #ffecec;border: 1px solid #ffd6d6;color: #c62828; padding: 14px 18px;margin-bottom: 20px;border-radius: 14px;font-weight: 700;}
</style>
</head>
<body>

<header class="site-header">
    <nav class="navbar"><a class="brand" href="admin_dashboard.php"><span class="brand-mark">🛠️</span><span><span class="brand-title">TechFix.it</span><br><small class="brand-sub">ระบบแจ้งซ่อมคอมพิวเตอร์</small></span></a></nav>
</header>

<div class="shell">
    <div class="container" style="max-width: 900px;">
        <section class="panel">
            <header class="panel-head"><h1 class="title">แก้ไขข้อมูลช่าง: <?= h($tech['fullname']) ?></h1></header>
            <div class="panel-body">
                <?php if (!empty($errorMsg)): ?><div class="alert-box error"><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>

                <form class="form-grid" method="POST" action="admin_edit_technician_process.php">
                    <input type="hidden" name="id" value="<?= (int)$tech['id'] ?>">
                    
                    <div class="field">
                        <label for="fullname">ชื่อ-สกุล ช่างเทคนิค</label>
                        <input type="text" id="fullname" name="fullname" value="<?= h($tech['fullname']) ?>" required>
                    </div>
                    
                    <div class="field">
                        <label for="username">Username (สำหรับ Login)</label>
                        <input type="text" id="username" name="username" value="<?= h($tech['username']) ?>" required>
                    </div>

                    <div class="field">
                        <label for="phone_number">เบอร์โทรศัพท์</label>
                        <input type="text" id="phone_number" name="phone_number" value="<?= h($tech['phone_number']) ?>">
                    </div>

                    <div class="field">
                        <label for="password">รหัสผ่านใหม่</label>
                        <input type="password" id="password" name="password">
                        <small>เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน</small>
                    </div>

                    <div style="margin-top:20px; display:flex; gap: 12px; align-items:center;">
                        <button type="submit" class="btn-submit">บันทึกการเปลี่ยนแปลง</button>
                        <a href="manage_technicians.php">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

</body>
</html>