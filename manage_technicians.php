<?php
// ===== 1. เริ่ม Session และตรวจสอบการ Login ของ Admin (สำคัญที่สุด) =====
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// ===== 2. เชื่อมต่อฐานข้อมูล =====
$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
if ($conn->connect_error) { die("DB Error"); }
$conn->set_charset("utf8");

// ===== 3. เขียน SQL เพื่อดึงข้อมูลสถิติของช่างแต่ละคน =====
// ใช้ LEFT JOIN เพื่อให้ช่างที่ยังไม่มีงานแสดงขึ้นมาด้วย
$sql = "
    SELECT
        t.id,
        t.fullname,
        COUNT(dr.id) AS total_jobs,
        SUM(CASE WHEN dr.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_jobs,
        SUM(CASE WHEN dr.status = 'done' THEN 1 ELSE 0 END) AS done_jobs
    FROM
        technicians t
    LEFT JOIN
        device_reports dr ON t.id = dr.technician_id
    GROUP BY
        t.id, t.fullname
    ORDER BY
        t.fullname ASC;
";

$result = $conn->query($sql);
$technician_stats = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $technician_stats[] = $row;
    }
}

// นับจำนวนช่างทั้งหมด
$total_technicians = count($technician_stats);

// ฟังก์ชัน h() สำหรับป้องกัน XSS
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการช่างเทคนิค - TechFix Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    :root{
        --navy:#0b2440; --blue:#1e88e5; --bg:#f5f9ff; --card:#ffffff; --line:#e6effa; --text:#1f2937;
        --green:#2e7d32; --red:#c62828; --blue-strong:#0b63c8;
        --shadow:0 16px 40px rgba(10,37,64,.12);
        --radius:20px;
        --container:1680px;
    }
    *{box-sizing:border-box} html,body{margin:0}
    body{
        font-family:system-ui,Segoe UI,Roboto,"TH Sarabun New",Tahoma,sans-serif;
        color:var(--text);
        background: radial-gradient(1200px 600px at 50% -240px,#eaf3ff 0,transparent 60%),
                    linear-gradient(180deg,#fbfdff 0,var(--bg) 100%);
    }
    .site-header{position:sticky;top:0;z-index:1000;background:linear-gradient(90deg,#0b3a6b 0,#1366b3 100%);color:#fff;box-shadow:0 6px 18px rgba(0,0,0,.12)}
    .navbar{height:60px;display:flex;align-items:center;justify-content:space-between;padding:0 30px;position:relative}
    .brand{display:flex;align-items:center;gap:12px;color:#fff;text-decoration:none}
    .brand-mark{display:grid;place-items:center;width:36px;height:36px;border-radius:999px;background:rgba(255,255,255,.15)}
    .brand-title{font-weight:800}
    .brand-sub{opacity:.85;font-size:12px;display:block}
    .nav-actions{display:flex;align-items:center}
    .hb-btn{display:flex;flex-direction:column;gap:5px;padding:10px; border:none; border-radius:10px;background:linear-gradient(135deg,#2aa2ff,#0a66b5);cursor:pointer; transition:transform .18s ease, filter .18s ease, box-shadow .18s ease;box-shadow:0 8px 20px rgba(42,162,255,.28);}
    .hb-btn:hover{filter:brightness(1.06); transform:translateY(-1px)}
    .hb-btn:active{transform:translateY(0)}
    .hb-btn span{width:24px;height:3px;background:#fff;border-radius:3px;transition:.25s}
    .hb-btn.active span:nth-child(1){transform:translateY(8px) rotate(45deg)}
    .hb-btn.active span:nth-child(2){opacity:0}
    .hb-btn.active span:nth-child(3){transform:translateY(-8px) rotate(-45deg)}
    .nav-menu{position:absolute; right:20px; top:60px;background:#fff; border:1px solid #e0e6ef;border-radius:12px; box-shadow:0 10px 28px rgba(15,40,80,.16);min-width:220px; overflow:hidden;opacity:0; transform:translateY(-8px) scale(.98);max-height:0; pointer-events:none;transition:opacity .22s ease, transform .22s ease, max-height .26s cubic-bezier(.2,.8,.2,1);}
    .nav-menu.show{opacity:1; transform:translateY(0) scale(1); max-height:260px; pointer-events:auto;}
    .menu-item{display:flex; align-items:center; gap:12px;padding:12px 16px; text-decoration:none; font-weight:800;color:#0b2440; letter-spacing:.2px;transition:background .15s ease, color .15s ease;}
    .menu-item:hover{background:#f3f8ff; color:#1e88e5}
    .menu-item.logout{color:#c62828}
    .menu-item.logout:hover{background:#ffecec; color:#b71c1c}
    .menu-icon{ width:18px; height:18px; display:inline-block; flex:0 0 18px;}
    .menu-icon svg{width:18px; height:18px; fill:none; stroke:currentColor; stroke-width:1.9; stroke-linecap:round; stroke-linejoin:round}
    .shell{padding:20px}
    .container{max-width:min(96vw,var(--container)); margin:24px auto 40px; padding:0 24px;}
    .panel{border-radius:var(--radius);border:1px solid var(--line);background:var(--card);box-shadow:var(--shadow);overflow:hidden}
    .panel-head{padding:18px 22px;background:linear-gradient(180deg,rgba(78,169,255,.16),rgba(30,136,229,.10))}
    .title{margin:0;text-align:center;color:#0b2440;font-weight:900}
    .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:16px 20px 8px}
    .kpi{border:1px solid var(--line);border-radius:16px;padding:12px 14px;background:#fff;box-shadow:0 10px 24px rgba(15,40,80,.06)}
    .kpi h4{margin:0 0 4px 0;font-size:13px;color:#0a2540}
    .kpi .num{font-size:26px;font-weight:900}
    .kpi.progress .num{color:var(--blue-strong)} .kpi.done .num{color:var(--green)}
    .table-wrap{background:#fff;border-top:1px solid var(--line);overflow-x:auto}
    table{ width:100%; border-collapse:separate; border-spacing:0; font-size:14.5px;}
    thead th{position:sticky; top:0; z-index:2; background:linear-gradient(180deg,#f7fbff 0,#eef6ff 100%); color:#0f3a66; font-weight:800; letter-spacing:.2px; padding:14px 16px; border-bottom:1px solid var(--line); text-align:left;}
    tbody td{padding:12px 16px; border-top:1px solid var(--line); vertical-align:middle; background:#fff;}
    tbody tr:nth-child(even) td{background:#fbfdff}
    tbody tr:hover td{background:#f3f8ff}
    .tc{text-align:center}
    .empty{padding:28px;text-align:center;color:#667085}
</style>
</head>
<body>

<header class="site-header">
    <nav class="navbar">
        <a class="brand" href="admin_dashboard.php">
            <span class="brand-mark">🛠️</span>
            <span>
                <span class="brand-title">TechFix.it</span><br>
                <small class="brand-sub">ระบบแจ้งซ่อมคอมพิวเตอร์</small>
            </span>
        </a>
        <div class="nav-actions">
            <button class="hb-btn" aria-label="เปิดเมนู" aria-expanded="false" onclick="toggleNavMenu(this)">
                <span></span><span></span><span></span>
            </button>
            <div id="navMenu" class="nav-menu" role="menu" aria-hidden="true">
                 <a href="admin_dashboard.php" class="menu-item home" role="menuitem">
                    <span class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"></path><path d="M5 10v10h14V10"></path><path d="M9 20v-6h6v6"></path></svg>
                    </span>
                    หน้าหลัก
                </a>
                <a href="manage_technicians.php" class="menu-item" role="menuitem">
                    <span class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </span>
                    จัดการช่าง
                </a>
                <a href="admin_create_technician.php" class="menu-item" role="menuitem">
                    <span class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="23" y1="11" x2="17" y2="11"></line><line x1="20" y1="8" x2="20" y2="14"></line></svg>
                    </span>
                    เพิ่มช่างใหม่
                </a>
                <a href="logout.php" class="menu-item logout" role="menuitem">
                    <span class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M15 12H3"></path><path d="M11 8l-4 4 4 4"></path><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path></svg>
                    </span>
                    ออกจากระบบ
                </a>
            </div>
        </div>
    </nav>
</header>

<div class="shell">
    <div class="container">
        <section class="panel">
            <header class="panel-head"><h1 class="title">ภาพรวมและจัดการช่างเทคนิค</h1></header>
            
            <div class="kpis" style="grid-template-columns: 1fr;"> <div class="kpi total">
                    <h4>ช่างเทคนิคทั้งหมดในระบบ</h4>
                    <div class="num"><?= $total_technicians ?> คน</div>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ชื่อ-สกุล ช่างเทคนิค</th>
                            <th class="tc">งานที่ได้รับมอบหมาย</th>
                            <th class="tc">กำลังซ่อม</th>
                            <th class="tc">ซ่อมเสร็จ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($technician_stats)): ?>
                        <tr><td colspan="4" class="empty">ยังไม่มีข้อมูลช่างในระบบ</td></tr>
                    <?php else: ?>
                        <?php foreach($technician_stats as $tech): ?>
                            <tr>
                                <td><strong><?= h($tech['fullname']) ?></strong></td>
                                <td class="tc"><?= (int)$tech['total_jobs'] ?> งาน</td>
                                <td class="tc" style="color: var(--blue-strong);"><?= (int)$tech['in_progress_jobs'] ?> งาน</td>
                                <td class="tc" style="color: var(--green);"><?= (int)$tech['done_jobs'] ?> งาน</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="footer" style="text-align:center;color:#667085;margin-top:18px">
            © <?= date('Y') ?> TechFix — ระบบแจ้งซ่อมคอมพิวเตอร์
        </div>
    </div>
</div>

<script>
// Script สำหรับเมนู (เหมือนเดิม)
function toggleNavMenu(btn){
    const menu = document.getElementById('navMenu');
    const show = !menu.classList.contains('show');
    menu.classList.toggle('show', show);
    btn.classList.toggle('active', show);
    btn.setAttribute('aria-expanded', show ? 'true' : 'false');
    menu.setAttribute('aria-hidden', show ? 'false' : 'true');
}
document.addEventListener('click', (e)=>{
    const menu = document.getElementById('navMenu');
    const btn = document.querySelector('.hb-btn');
    if (!menu) return;
    if (!menu.contains(e.target) && !btn.contains(e.target)) {
        menu.classList.remove('show'); btn.classList.remove('active');
        btn.setAttribute('aria-expanded','false'); menu.setAttribute('aria-hidden','true');
    }
});
</script>
</body>
</html>