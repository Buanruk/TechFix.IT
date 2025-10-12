<?php
session_start();

// 1. ตรวจสอบสิทธิ์ Admin (สำคัญที่สุด)
// ถ้าไม่มี session ของ admin ให้เด้งกลับไปหน้า login ทันที
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// ดึงข้อความ error จาก session (ถ้ามี)
$errorMsg = $_SESSION['error'] ?? '';
unset($_SESSION['error']); // ลบออกหลังแสดง
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เพิ่มช่างใหม่ • TechFix Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
      /* ใช้ CSS สไตล์เดียวกับหน้า Login เพื่อความสวยงาม */
      :root{--bg:#0b1623;--bg2:#09111b;--card:rgba(19,34,56,.72);--stroke:rgba(255,255,255,.08);--input:#0f1c2c;--text:#eaf2ff;--muted:#98a9bf;--accent1:#2aa2ff;--accent2:#0a66b5;--danger:#ff5d73;--glow:0 14px 40px rgba(42,162,255,.30);}
      *{box-sizing:border-box}html,body{height:100%}body{margin:0;font-family:'Montserrat',sans-serif;color:var(--text);background:radial-gradient(1200px 700px at 15% 10%,rgba(42,162,255,.12),transparent 60%),radial-gradient(900px 600px at 85% 90%,rgba(10,102,181,.14),transparent 60%),linear-gradient(180deg,var(--bg),var(--bg2));display:grid;place-items:center;padding:28px}.wrap{width:min(100%,480px);position:relative;z-index:1}.card{background:var(--card);backdrop-filter:blur(16px);border:1px solid var(--stroke);border-radius:20px;padding:30px 28px 24px;box-shadow:var(--glow);animation:pop .35s ease}@keyframes pop{from{transform:translateY(10px);opacity:0}to{transform:none;opacity:1}}.brand{display:flex;flex-direction:column;align-items:center;text-align:center;gap:16px;margin-bottom:22px}.title-grad{font-size:1.7rem;font-weight:700;margin:0 0 2px;background:linear-gradient(135deg,var(--accent1),var(--accent2));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;text-shadow:0 2px 6px rgba(0,0,0,.35)}@media (max-width:560px){.title-grad{font-size:1.5rem}}.alert{background:rgba(255,93,115,.1);border:1px solid rgba(255,93,115,.35);color:#ffdbe1;padding:10px 12px;border-radius:12px;font-size:.92rem;margin-bottom:14px;display:flex;gap:10px;align-items:center;animation:shake .35s ease}.alert svg{flex:0 0 18px}@keyframes shake{10%,90%{transform:translateX(-1px)}20%,80%{transform:translateX(2px)}30%,50%,70%{transform:translateX(-3px)}40%,60%{transform:translateX(3px)}}.field{margin:12px 0}.input{position:relative;display:flex;align-items:center;border:1px solid #1f3047;background:var(--input);border-radius:12px;padding:12px 12px;transition:.18s}.input:focus-within{border-color:#2a86ff;box-shadow:0 0 0 4px rgba(42,134,255,.18)}.input svg{opacity:.8}.input input{flex:1;border:0;outline:none;background:transparent;color:var(--text);font-size:1rem;padding:2px 8px}.btn{width:100%;margin-top:16px;padding:12px 16px;border:0;border-radius:12px;cursor:pointer;color:#fff;font-weight:700;letter-spacing:.2px;background:linear-gradient(135deg,var(--accent1),var(--accent2));box-shadow:var(--glow);transition:transform .12s ease,filter .12s ease}.btn:hover{transform:translateY(-1px);filter:brightness(1.05)}.btn:active{transform:translateY(0)}.foot{margin-top:18px;text-align:center;color:var(--muted);font-size:.88rem}.foot a{color:#cfe2ff}.sr-only{position:absolute;left:-9999px}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="brand">
                <h1 class="title-grad">ลงทะเบียนช่างใหม่</h1>
            </div>

            <?php if (!empty($errorMsg)): ?>
            <div class="alert" role="alert">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span><?= htmlspecialchars($errorMsg) ?></span>
            </div>
            <?php endif; ?>

            <form method="post" action="register_process.php">
                <div class="field">
                    <div class="input">
                        <input name="fullname" type="text" placeholder="ชื่อ-สกุล ของช่าง" required autofocus>
                    </div>
                </div>

                <div class="field">
                    <div class="input">
                        <input name="phone_number" type="tel" placeholder="เบอร์โทรศัพท์">
                    </div>
                </div>

                <div class="field">
                    <div class="input">
                        <input name="username" type="text" placeholder="ตั้งชื่อผู้ใช้ (สำหรับ Login)" required>
                    </div>
                </div>

                <div class="field">
                    <div class="input">
                        <input name="password" type="password" placeholder="ตั้งรหัสผ่าน" required>
                    </div>
                </div>

                <button class="btn" type="submit">สร้างบัญชีช่าง</button>
            </form>

            <div class="foot">
                <a href="admin_dashboard.php">ยกเลิกและกลับสู่ Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>