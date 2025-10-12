<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix"); // ตรวจสอบรหัสผ่าน DB อีกครั้ง
if ($conn->connect_error) { die("DB Error"); }
$conn->set_charset("utf8");

$sql = "
    SELECT
        t.id,
        t.fullname,
        t.username,
        t.phone_number,
        t.created_at,
        t.last_login,
        COUNT(dr.id) AS total_jobs,
        SUM(CASE WHEN dr.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_jobs,
        SUM(CASE WHEN dr.status = 'done' THEN 1 ELSE 0 END) AS done_jobs
    FROM
        technicians t
    LEFT JOIN
        device_reports dr ON t.id = dr.technician_id
    GROUP BY
        t.id
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

$total_technicians = count($technician_stats);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function format_thai_datetime($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '<span style="color:#999;">ยังไม่เคยเข้าระบบ</span>';
    }
    $ts = strtotime($datetime);
    return date('d/m/Y H:i', $ts);
}

// ข้อความแจ้งเตือน (จาก session)
$successMsg = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
$errorMsg = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการช่างเทคนิค - TechFix Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    /* CSS ส่วนใหญ่เหมือนเดิม */
    :root{--navy:#0b2440; --blue:#1e88e5; --bg:#f5f9ff; --card:#ffffff; --line:#e6effa; --text:#1f2937;--green:#2e7d32; --red:#c62828; --blue-strong:#0b63c8;--shadow:0 16px 40px rgba(10,37,64,.12);--radius:20px;--container:1680px;}
    *{box-sizing:border-box} html,body{margin:0}
    body{font-family:system-ui,Segoe UI,Roboto,"TH Sarabun New",Tahoma,sans-serif;color:var(--text);background: radial-gradient(1200px 600px at 50% -240px,#eaf3ff 0,transparent 60%),linear-gradient(180deg,#fbfdff 0,var(--bg) 100%);}
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

    /* เพิ่ม CSS สำหรับปุ่มลบ และจัด Action Cell */
    .action-cell { display: flex; flex-direction:column; gap: 8px; justify-content:center; align-items: center;} /* จัดกลางทั้งแนวตั้งและแนวนอน */
    .btn-details, .btn-delete {
        font-family:inherit; font-size:13px; font-weight:700; padding:6px 12px;
        border:1px solid var(--line); border-radius:10px; cursor:pointer;
        transition:all .18s ease; margin: 0; min-width: 80px; /* เพิ่ม min-width เพื่อให้ปุ่มขนาดเท่ากัน */
    }
    .btn-details{ background:var(--blue); color:#fff; border-color:var(--blue); }
    .btn-details:hover{ background:#0b63c8; border-color:#0b63c8; }
    .btn-delete{ background:#fff; color:var(--red); border-color:var(--red); }
    .btn-delete:hover{ background:var(--red); color:#fff; }
    .alert-box {
        padding: 14px 18px;
        margin-bottom: 20px;
        border-radius: 14px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: fadeInDown .4s ease;
    }
    @keyframes fadeInDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .alert-box.success {
        background-color: #e9f9ec;
        border: 1px solid #d1f3d8;
        color: #2e7d32;
    }
    .alert-box.error {
        background-color: #ffecec;
        border: 1px solid #ffd6d6;
        color: #c62828;
    }
    .alert-box svg { flex: 0 0 20px; }
    /* Modal CSS เหมือนเดิม */
    .modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,40,80,.6);backdrop-filter:blur(5px);z-index:9998;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s ease}
    .modal-overlay.show{opacity:1;pointer-events:auto}
    .modal-content{background:#fff;border-radius:var(--radius);box-shadow:0 20px 50px rgba(0,0,0,.2);max-width:90vw;width:600px;max-height:85vh;display:flex;flex-direction:column;transform:scale(.95);transition:transform .25s ease}
    .modal-overlay.show .modal-content{transform:scale(1)}
    .modal-header{display:flex;justify-content:space-between;align-items:center;padding:16px 22px;border-bottom:1px solid var(--line)}
    .modal-title{margin:0;color:var(--navy);font-size:18px}
    .modal-close{background:transparent;border:none;font-size:24px;line-height:1;cursor:pointer;color:#999}
    .modal-body{padding:24px;overflow-y:auto;display:grid;grid-template-columns:150px 1fr;gap:14px}
    .modal-body .label{font-weight:800;color:var(--navy)}
    .modal-body .value{word-break:break-word;white-space:pre-wrap}

    /* Responsive */
    @media (max-width:960px){
        thead{display:none} 
        tbody tr{
            display:block;
            border:1px solid var(--line); border-radius:14px;
            margin:12px; padding: 8px;
            box-shadow:0 8px 18px rgba(15,40,80,.06);
            overflow:hidden;
        }
        tbody td{
            display:flex; gap:10px; justify-content:space-between; align-items:center;
            border-top:1px solid var(--line); padding:10px;
        }
        tbody tr td:first-child{border-top:none}
        tbody td::before{
            content:attr(data-label);
            font-weight:800; color:#0f3a66;
        }
        .action-cell{flex-direction:row; justify-content:center; flex-wrap: wrap;}
    }

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
                    <span class="menu-icon"><svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"></path><path d="M5 10v10h14V10"></path><path d="M9 20v-6h6v6"></path></svg></span>
                    หน้าหลัก
                </a>
                <a href="manage_technicians.php" class="menu-item" role="menuitem">
                    <span class="menu-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
                    จัดการช่าง
                </a>
                <a href="admin_create_technician.php" class="menu-item" role="menuitem">
                    <span class="menu-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="23" y1="11" x2="17" y2="11"></line><line x1="20" y1="8" x2="20" y2="14"></line></svg></span>
                    เพิ่มช่างใหม่
                </a>
                <a href="logout.php" class="menu-item logout" role="menuitem">
                    <span class="menu-icon"><svg viewBox="0 0 24 24"><path d="M15 12H3"></path><path d="M11 8l-4 4 4 4"></path><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path></svg></span>
                    ออกจากระบบ
                </a>
            </div>
        </div>
    </nav>
</header>

<div class="shell">
    <div class="container">
        <?php if (!empty($successMsg)): ?>
            <div class="alert-box success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span><?= htmlspecialchars($successMsg) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($errorMsg)): ?>
            <div class="alert-box error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span><?= htmlspecialchars($errorMsg) ?></span>
            </div>
        <?php endif; ?>

        <section class="panel">
            <header class="panel-head"><h1 class="title">ภาพรวมและจัดการช่างเทคนิค</h1></header>
            
            <div class="kpis" style="grid-template-columns: 1fr;">
                <div class="kpi total">
                    <h4>ช่างเทคนิคทั้งหมดในระบบ</h4>
                    <div class="num"><?= $total_technicians ?> คน</div>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ชื่อ-สกุล ช่างเทคนิค</th>
                            <th class="tc">งานทั้งหมด</th>
                            <th class="tc">เข้าสู่ระบบล่าสุด</th>
                            <th class="tc">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($technician_stats)): ?>
                        <tr><td colspan="4" class="empty">ยังไม่มีข้อมูลช่างในระบบ</td></tr>
                    <?php else: ?>
                        <?php foreach($technician_stats as $tech): ?>
                            <tr
                                data-id="<?= h($tech['id']) ?>"
                                data-fullname="<?= h($tech['fullname']) ?>"
                                data-username="<?= h($tech['username']) ?>"
                                data-phone_number="<?= h($tech['phone_number']) ?>"
                                data-created_at="<?= h(format_thai_datetime($tech['created_at'])) ?>"
                                data-last_login="<?= h(format_thai_datetime($tech['last_login'])) ?>"
                                data-total_jobs="<?= (int)$tech['total_jobs'] ?>"
                                data-in_progress_jobs="<?= (int)$tech['in_progress_jobs'] ?>"
                                data-done_jobs="<?= (int)$tech['done_jobs'] ?>"
                            >
                                <td data-label="ชื่อ-สกุล ช่างเทคนิค"><strong><?= h($tech['fullname']) ?></strong></td>
                                <td class="tc" data-label="งานทั้งหมด"><?= (int)$tech['total_jobs'] ?> งาน</td>
                                <td class="tc" data-label="เข้าสู่ระบบล่าสุด"><?= format_thai_datetime($tech['last_login']) ?></td>
                                <td class="tc" data-label="จัดการ">
                                    <div class="action-cell">
                                        <button class="btn-details">ดูข้อมูล</button>
                                        <form method="POST" action="delete_technician.php" 
                                              onsubmit="return confirm('คุณต้องการลบช่าง: <?= h($tech['fullname']) ?> (ID: <?= (int)$tech['id'] ?>) ใช่หรือไม่?\\n\\nข้อควรระวัง: หากมีงานที่ยังไม่เสร็จ ช่างคนนี้จะไม่สามารถถูกลบได้');">
                                            <input type="hidden" name="id" value="<?= (int)$tech['id'] ?>">
                                            <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">
                                            <button type="submit" class="btn-delete">ลบ</button>
                                        </form>
                                    </div>
                                </td>
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

<div id="detailsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-content">
        <header class="modal-header">
            <h2 id="modalTitle" class="modal-title">ข้อมูลช่างเทคนิค</h2>
            <button class="modal-close" aria-label="ปิด">&times;</button>
        </header>
        <main id="modalBody" class="modal-body"></main>
    </div>
</div>

<script>
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
    if (menu && !menu.contains(e.target) && !btn.contains(e.target)) {
        menu.classList.remove('show'); btn.classList.remove('active');
        btn.setAttribute('aria-expanded','false'); menu.setAttribute('aria-hidden','true');
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const modalOverlay = document.getElementById('detailsModal');
    const modalBody = document.getElementById('modalBody');
    const modalTitle = document.getElementById('modalTitle');
    const table = document.querySelector('.table-wrap');

    const openModal = (data) => {
        modalTitle.textContent = `ข้อมูลช่าง: ${data.fullname}`;
        modalBody.innerHTML = `
            <span class="label">ID:</span><span class="value">${data.id}</span>
            <span class="label">ชื่อ-สกุล:</span><span class="value">${data.fullname}</span>
            <span class="label">เบอร์โทร:</span><span class="value">${data.phone_number || '-'}</span>
            <span class="label">Username:</span><span class="value">${data.username}</span>
            <span class="label">วันที่สมัคร:</span><span class="value">${data.created_at}</span>
            <span class="label">เข้าระบบล่าสุด:</span><span class="value">${data.last_login}</span>
            <hr style="grid-column: 1 / -1; border: none; border-top: 1px solid #eee; margin: 5px 0;">
            <span class="label">งานทั้งหมด:</span><span class="value"><b>${data.total_jobs}</b> งาน</span>
            <span class="label">กำลังซ่อม:</span><span class="value" style="color:var(--blue-strong);">${data.in_progress_jobs} งาน</span>
            <span class="label">ซ่อมเสร็จ:</span><span class="value" style="color:var(--green);">${data.done_jobs} งาน</span>
        `;
        modalOverlay.classList.add('show');
    };

    const closeModal = () => modalOverlay.classList.remove('show');

    if (table) {
        table.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-details')) {
                const row = e.target.closest('tr');
                if (row) {
                    openModal(row.dataset);
                }
            }
        });
    }

    modalOverlay.addEventListener('click', e => {
        if (e.target === modalOverlay || e.target.classList.contains('modal-close')) {
            closeModal();
        }
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modalOverlay.classList.contains('show')) {
            closeModal();
        }
    });
});
</script>
</body>
</html>