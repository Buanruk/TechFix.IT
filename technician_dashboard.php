<?php
// ✅ 1. เพิ่มระบบตรวจสอบการ Login (สำคัญที่สุด)
session_start();

// ตรวจสอบว่ามี session ของช่างอยู่หรือไม่ ถ้าไม่มีให้เด้งกลับไปหน้า login
if (!isset($_SESSION['technician_id'])) {
    header('Location: technician_login.php');
    exit();
}

// เก็บ ID และชื่อของช่างที่ login อยู่ไว้ในตัวแปรเพื่อใช้งาน
$logged_in_technician_id = (int)$_SESSION['technician_id'];
$logged_in_technician_fullname = $_SESSION['technician_fullname'];


// ===== DB =====
$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
if ($conn->connect_error) { die("DB Error"); }
$conn->set_charset("utf8");

// ===== Map ประเภทอุปกรณ์ และ regex (เหมือนเดิม) =====
$dtypes = [
    'all'     => 'อุปกรณ์ทั้งหมด',
    'pc'      => 'ปัญหาเกี่ยวกับคอมพิวเตอร์',
    'printer' => 'ปัญหาเกี่ยวกับปริ้นเตอร์',
    'laptop'  => 'ปัญหาเกี่ยวกับโน๊ตบุ๊ค',
    'network' => 'ปัญหาเกี่ยวกับเครือข่าย',
    'tv'      => 'ปัญหาเกี่ยวกับ TV',
];
$regexMap = [
    'pc'      => '(คอม|computer|pc|desktop)',
    'printer' => '(ปริ้น|พรินท์|printer|พิมพ์)',
    'laptop'  => '(โน๊ตบุ๊ค|โน้ตบุ๊ก|laptop|notebook)',
    'network' => '(เครือข่าย|network|lan|wifi|router|switch)',
    'tv'      => '(tv|ทีวี|monitor|จอภาพ)',
];

// ===== รับค่า Filter (เหมือนเดิม) =====
$filterStatus = $_GET['status'] ?? 'all';
if (!in_array($filterStatus, ['all','new','in_progress','done'], true)) $filterStatus = 'all';

$filterDtype = $_GET['dtype'] ?? 'all';
if (!in_array($filterDtype, array_keys($dtypes), true)) $filterDtype = 'all';

// ===== ตั้งค่าการแบ่งหน้า (เหมือนเดิม) =====
$perPage = 10;
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset  = ($page - 1) * $perPage;

// ===== ฟังก์ชันช่วย (เหมือนเดิม) =====
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function statusText($s){
    return match($s){
        'new'         => 'แจ้งซ่อม',
        'in_progress' => 'กำลังซ่อม',
        'done'        => 'ซ่อมเสร็จ',
        default       => 'ไม่ทราบ',
    };
}
function statusIcon($s){
    return match($s){
        'new'         => '❌',
        'in_progress' => '🔧',
        'done'        => '✅',
        default       => '❓',
    };
}
function pageUrl($p, $status, $dtype){
    $p = max(1,(int)$p);
    return '?status='.urlencode($status).'&dtype='.urlencode($dtype).'&page='.$p;
}

/** ✅ 2. แก้ไขฟังก์ชัน SQL ให้รับ ID ของช่างมาด้วย */
function build_where_and_params($status, $dtype, $regexMap, $tech_id = null){
    $wheres = []; $types = ''; $vals = [];

    // เพิ่มเงื่อนไขสำหรับ technician_id เป็นอันดับแรก
    if ($tech_id !== null) {
        $wheres[] = "technician_id = ?";
        $types .= "i";
        $vals[] = $tech_id;
    }

    if ($status !== 'all'){ $wheres[] = "status = ?"; $types .= "s"; $vals[] = $status; }
    if ($dtype  !== 'all' && isset($regexMap[$dtype])){
        $wheres[] = "LOWER(device_type) REGEXP ?";
        $types   .= "s";
        $vals[]   = strtolower($regexMap[$dtype]);
    }

    $whereSQL = $wheres ? ("WHERE ".implode(" AND ", $wheres)) : "";
    return [$whereSQL, $types, $vals];
}

// ===== ✅ 2. แก้ไขส่วนสรุปยอด (KPI) ให้นับเฉพาะงานของช่างคนนี้ =====
$stat = ['new'=>0,'in_progress'=>0,'done'=>0,'all'=>0];
$statSql = "SELECT status, COUNT(*) AS c FROM device_reports WHERE technician_id = ? GROUP BY status";
$statStmt = $conn->prepare($statSql);
$statStmt->bind_param('i', $logged_in_technician_id);
$statStmt->execute();
$qr = $statStmt->get_result();
if ($qr) {
    while($r = $qr->fetch_assoc()){
        $key = $r['status'];
        if (isset($stat[$key])) $stat[$key] = (int)$r['c'];
        $stat['all'] += (int)$r['c'];
    }
}

// ===== ✅ 2. แก้ไขการนับจำนวนหน้า ให้กรองเฉพาะงานของช่างคนนี้ =====
[$whereCnt, $typesCnt, $valsCnt] = build_where_and_params($filterStatus, $filterDtype, $regexMap, $logged_in_technician_id);
$countSql  = "SELECT COUNT(*) AS total FROM device_reports $whereCnt";
$countStmt = $conn->prepare($countSql);
if ($typesCnt) $countStmt->bind_param($typesCnt, ...$valsCnt);
$countStmt->execute();
$countRes  = $countStmt->get_result();
$totalRows = (int)($countRes->fetch_assoc()['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// ===== ✅ 2. แก้ไขการโหลดรายการ ให้กรองเฉพาะงานของช่างคนนี้ =====
[$whereSel, $typesSel, $valsSel] = build_where_and_params($filterStatus, $filterDtype, $regexMap, $logged_in_technician_id);
$selSql = "SELECT * FROM device_reports $whereSel ORDER BY id DESC LIMIT ? OFFSET ?";
$typesSel .= "ii";
$valsSel[] = $perPage;
$valsSel[] = $offset;

$stmt = $conn->prepare($selSql);
$stmt->bind_param($typesSel, ...$valsSel);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>งานซ่อมของฉัน - TechFix</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    /* CSS ทั้งหมดเหมือนเดิม ไม่ต้องแก้ไข */
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
    .hb-btn{display:flex;flex-direction:column;gap:5px;padding:10px;border:none;border-radius:10px;background:linear-gradient(135deg,#2aa2ff,#0a66b5);cursor:pointer;transition:transform .18s ease,filter .18s ease,box-shadow .18s ease;box-shadow:0 8px 20px rgba(42,162,255,.28)}
    .hb-btn:hover{filter:brightness(1.06);transform:translateY(-1px)}
    .hb-btn:active{transform:translateY(0)}
    .hb-btn span{width:24px;height:3px;background:#fff;border-radius:3px;transition:.25s}
    .hb-btn.active span:nth-child(1){transform:translateY(8px) rotate(45deg)}
    .hb-btn.active span:nth-child(2){opacity:0}
    .hb-btn.active span:nth-child(3){transform:translateY(-8px) rotate(-45deg)}
    .nav-menu{position:absolute;right:20px;top:60px;background:#fff;border:1px solid #e0e6ef;border-radius:12px;box-shadow:0 10px 28px rgba(15,40,80,.16);min-width:220px;overflow:hidden;opacity:0;transform:translateY(-8px) scale(.98);max-height:0;pointer-events:none;transition:opacity .22s ease,transform .22s ease,max-height .26s cubic-bezier(.2,.8,.2,1)}
    .nav-menu.show{opacity:1;transform:translateY(0) scale(1);max-height:260px;pointer-events:auto}
    .menu-item{display:flex;align-items:center;gap:12px;padding:12px 16px;text-decoration:none;font-weight:800;color:#0b2440;letter-spacing:.2px;transition:background .15s ease,color .15s ease}
    .menu-item:hover{background:#f3f8ff;color:#1e88e5}
    .menu-item.logout{color:#c62828}
    .menu-item.logout:hover{background:#ffecec;color:#b71c1c}
    .menu-icon{width:18px;height:18px;display:inline-block;flex:0 0 18px}
    .menu-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
    .shell{padding:20px}
    .container{max-width:min(96vw,var(--container));margin:24px auto 40px;padding:0 24px}
    .panel{border-radius:var(--radius);border:1px solid var(--line);background:var(--card);box-shadow:var(--shadow);overflow:hidden}
    .panel-head{padding:18px 22px;background:linear-gradient(180deg,rgba(78,169,255,.16),rgba(30,136,229,.10))}
    .title{margin:0;text-align:center;color:#0b2440;font-weight:900}
    .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:16px 20px 8px}
    .kpi{border:1px solid var(--line);border-radius:16px;padding:12px 14px;background:#fff;box-shadow:0 10px 24px rgba(15,40,80,.06)}
    .kpi h4{margin:0 0 4px 0;font-size:13px;color:#0a2540}
    .kpi .num{font-size:26px;font-weight:900}
    .kpi.new .num{color:var(--red)} .kpi.progress .num{color:var(--blue-strong)} .kpi.done .num{color:var(--green)}
    .toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 18px;color:#667085;flex-wrap:wrap}
    .group{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .label{display:flex;align-items:center;gap:8px;font-weight:800;color:#0a2540;letter-spacing:.2px}
    .select{-webkit-appearance:none;-moz-appearance:none;appearance:none;height:42px;line-height:42px;padding:0 42px 0 14px;min-width:240px;border:1px solid var(--line);border-radius:12px;background:#fff;font-size:15px;font-weight:700;color:#1f2937;outline:none;box-shadow:0 8px 18px rgba(10,37,64,.06),inset 0 1px 0 rgba(255,255,255,.6);transition:box-shadow .18s ease,border-color .18s ease,transform .06s ease;background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="%231e293b" viewBox="0 0 16 16"><path d="M4.646 6.646a.5.5 0 0 1 .708 0L8 9.293l2.646-2.647a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 0 1 0-.708z"/></svg>');background-repeat:no-repeat;background-position:right 12px center;background-size:18px}
    .select:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(10,37,64,.10)}
    .select:focus{border-color:#1e88e5;box-shadow:0 0 0 3px rgba(30,136,229,.18)}
    .table-wrap{background:#fff;border-top:1px solid var(--line);overflow-x:auto}
    table{width:100%;border-collapse:separate;border-spacing:0;font-size:14.5px}
    thead th{position:sticky;top:0;z-index:2;background:linear-gradient(180deg,#f7fbff 0,#eef6ff 100%);color:#0f3a66;font-weight:800;letter-spacing:.2px;padding:14px 16px;border-bottom:1px solid var(--line);text-align:left}
    tbody td{padding:12px 16px;border-top:1px solid var(--line);vertical-align:middle;background:#fff}
    tbody tr:nth-child(even) td{background:#fbfdff}
    tbody tr:hover td{background:#f3f8ff}
    .tc{text-align:center}
    .ellipsis{max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .badge{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;font-size:13px;font-weight:800;border:1px solid transparent}
    .badge.new{background:#ffecec;color:var(--red);border-color:#ffd6d6}
    .badge.in_progress{background:#eef5ff;color:#0b63c8;border-color:#d6eaff}
    .badge.done{background:#e9f9ec;color:#2e7d32;border-color:#d1f3d8}
    .status-select{padding:6px 10px;border:1px solid var(--line);border-radius:10px;background:#fff;font-weight:700;cursor:pointer;max-width:150px}
    .status-select:focus{border-color:#1e88e5;box-shadow:0 0 0 3px rgba(30,136,229,.15)}
    .select-new{color:var(--red)} .select-progress{color:#0b63c8} .select-done{color:#2e7d32}
    .btn-del,.btn-details{font-family:inherit;font-size:13px;font-weight:700;padding:6px 12px;border:1px solid var(--line);border-radius:10px;cursor:pointer;transition:all .18s ease;margin:0}
    .btn-del{background:#fff;color:var(--red)}
    .btn-del:hover{background:var(--red);color:#fff;border-color:var(--red)}
    .btn-details{background:var(--blue);color:#fff;border-color:var(--blue)}
    .btn-details:hover{background:#0b63c8;border-color:#0b63c8}
    .action-cell{display:flex;flex-direction:column;align-items:center;gap:8px;justify-content:center}
    .action-cell form{margin:0}
    .empty{padding:28px;text-align:center;color:#667085}
    .pager{display:flex;align-items:center;justify-content:center;gap:8px;padding:16px;background:#fff;border-top:1px solid var(--line);flex-wrap:wrap}
    .pager a,.pager span{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 12px;border:1px solid var(--line);border-radius:10px;text-decoration:none;color:#0b2440;font-weight:800;background:#fff}
    .pager a:hover{background:#f3f8ff;border-color:#d6eaff}
    .pager .active{background:#e8f2ff;border-color:#b9dcff;color:#0b63c8}
    .pager .disabled{opacity:.45;pointer-events:none}
    @media (max-width:960px){thead{display:none} tbody tr{display:block;border:1px solid var(--line);border-radius:14px;margin:12px;padding:8px;box-shadow:0 8px 18px rgba(15,40,80,.06);overflow:hidden} tbody td{display:flex;gap:10px;justify-content:space-between;align-items:center;border-top:1px solid var(--line);padding:10px} tbody tr td:first-child{border-top:none} tbody td::before{content:attr(data-label);font-weight:800;color:#0f3a66} .ellipsis{max-width:180px} .toolbar{flex-direction:column;align-items:stretch;gap:10px} .select{min-width:unset;width:100%} .action-cell{flex-direction:row;justify-content:center}}
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
</style>
</head>
<body>

<header class="site-header">
    <nav class="navbar">
        <a class="brand" href="#">
            <span class="brand-mark">🛠️</span>
            <span>
                <span class="brand-title">TechFix.it</span><br>
                <small class="brand-sub">Dashboard ช่างเทคนิค</small>
            </span>
        </a>
        <div class="nav-actions">
            <button class="hb-btn" aria-label="เปิดเมนู" aria-expanded="false" onclick="toggleNavMenu(this)">
                <span></span><span></span><span></span>
            </button>
            <div id="navMenu" class="nav-menu" role="menu" aria-hidden="true">
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
            <header class="panel-head"><h1 class="title">งานซ่อมของฉัน (<?= h($logged_in_technician_fullname) ?>)</h1></header>
            <div class="kpis">
                <div class="kpi total"><h4>ทั้งหมด</h4><div class="num"><?= (int)$stat['all'] ?></div></div>
                <div class="kpi new"><h4>งานใหม่</h4><div class="num"><?= (int)$stat['new'] ?></div></div>
                <div class="kpi progress"><h4>กำลังทำ</h4><div class="num"><?= (int)$stat['in_progress'] ?></div></div>
                <div class="kpi done"><h4>ทำเสร็จ</h4><div class="num"><?= (int)$stat['done'] ?></div></div>
            </div>
            <form class="toolbar" method="get">
                <div class="group">
                    <label class="label" for="dtype">กรองอุปกรณ์:</label>
                    <select class="select" id="dtype" name="dtype" onchange="this.form.page.value=1; this.form.submit()">
                        <?php foreach($dtypes as $slug=>$label): ?>
                            <option value="<?= h($slug) ?>" <?= $filterDtype===$slug?'selected':'' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="group">
                    <label class="label" for="status">กรองสถานะ:</label>
                    <select class="select" id="status" name="status" onchange="this.form.page.value=1; this.form.submit()">
                        <option value="all"         <?= $filterStatus==='all' ? 'selected' : '' ?>>ทั้งหมด</option>
                        <option value="new"         <?= $filterStatus==='new' ? 'selected' : '' ?>>❌ แจ้งซ่อม</option>
                        <option value="in_progress" <?= $filterStatus==='in_progress' ? 'selected' : '' ?>>🔧 กำลังซ่อม</option>
                        <option value="done"        <?= $filterStatus==='done' ? 'selected' : '' ?>>✅ ซ่อมเสร็จ</option>
                    </select>
                </div>
                <input type="hidden" name="page" value="<?= (int)$page ?>">
            </form>

            <div class="table-wrap">
                <table>
                    <colgroup>
                        <col style="width: 8%;">
                        <col style="width: 25%;">
                        <col style="width: 22%;">
                        <col style="width: 25%;">
                        <col style="width: 20%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="tc">คิว</th>
                            <th>ชื่อผู้แจ้ง</th>
                            <th class="tc">สถานะ</th>
                            <th class="tc">เปลี่ยนสถานะ</th>
                            <th class="tc">รายละเอียด</th>
                            </tr>
                    </thead>
                    <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr><td colspan="5" class="empty">ยังไม่มีงานที่ได้รับมอบหมาย</td></tr>
                    <?php else: ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <?php
                                $room = $row['room'] ?? ($row['floor'] ?? '');
                                $s = in_array($row['status'], ['new','in_progress','done']) ? $row['status'] : 'new';
                                $reportTime = h(@date('d/m/Y H:i', strtotime($row['report_date'])) ?: $row['report_date']);
                            ?>
                            <tr
                                data-queue="<?= h($row['queue_number']) ?>"
                                data-username="<?= h($row['username']) ?>"
                                data-device="<?= h($row['device_type']) ?>"
                                data-serial="<?= h($row['serial_number']) ?>"
                                data-room="<?= h($room) ?>"
                                data-phone="<?= h($row['phone_number']) ?>"
                                data-time="<?= $reportTime ?>"
                                data-issue="<?= h($row['issue_description']) ?>"
                            >
                                <td class="tc" data-label="คิว"><?= h($row['queue_number']) ?></td>
                                <td class="ellipsis" data-label="ชื่อผู้แจ้ง" title="<?= h($row['username']) ?>"><?= h($row['username']) ?></td>
                                <td class="tc" data-label="สถานะ">
                                    <span class="badge <?= $s ?>"><?= statusIcon($s) ?> <?= h(statusText($s)) ?></span>
                                </td>
                                <td data-label="จัดการ">
                                    <div class="action-cell">
                                        <form method="POST" action="update_status.php">
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">
                                            <select name="status" class="status-select" onchange="this.form.submit()">
                                                <option value="new"         <?= $s==='new'?'selected':'' ?> class="select-new">❌ แจ้งซ่อม</option>
                                                <option value="in_progress" <?= $s==='in_progress'?'selected':'' ?> class="select-progress">🔧 กำลังซ่อม</option>
                                                <option value="done"        <?= $s==='done'?'selected':'' ?> class="select-done">✅ ซ่อมเสร็จ</option>
                                            </select>
                                        </form>
                                        </div>
                                </td>
                                <td class="tc" data-label="รายละเอียด">
                                    <button class="btn-details">รายละเอียดเพิ่มเติม</button>
                                </td>
                                </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <nav class="pager" aria-label="เปลี่ยนหน้า">
                <?php $prev = $page - 1; $next = $page + 1; ?>
                <a class="<?= $page<=1 ? 'disabled':'' ?>" href="<?= $page<=1 ? '#' : h(pageUrl($prev,$filterStatus,$filterDtype)) ?>" aria-label="ก่อนหน้า">«</a>
                <?php
                    $window = 2;
                    $start = max(1, $page - $window);
                    $end   = min($totalPages, $page + $window);

                    if ($start > 1){
                        echo '<a href="'.h(pageUrl(1,$filterStatus,$filterDtype)).'">1</a>';
                        if ($start > 2) echo '<span class="disabled">…</span>';
                    }
                    for($p=$start; $p<=$end; $p++){
                        if ($p == $page) echo '<span class="active">'.$p.'</span>';
                        else echo '<a href="'.h(pageUrl($p,$filterStatus,$filterDtype)).'">'.$p.'</a>';
                    }
                    if ($end < $totalPages){
                        if ($end < $totalPages-1) echo '<span class="disabled">…</span>';
                        echo '<a href="'.h(pageUrl($totalPages,$filterStatus,$filterDtype)).'">'.$totalPages.'</a>';
                    }
                ?>
                <a class="<?= $page>=$totalPages ? 'disabled':'' ?>" href="<?= $page>=$totalPages ? '#' : h(pageUrl($next,$filterStatus,$filterDtype)) ?>" aria-label="ถัดไป">»</a>
                <span class="disabled" style="border:none">หน้า <?= $page ?> / <?= $totalPages ?> • ทั้งหมด <?= number_format($totalRows) ?> รายการ</span>
            </nav>

        </section>

        <div class="footer" style="text-align:center;color:#667085;margin-top:18px">
            © <?= date('Y') ?> TechFix — ระบบแจ้งซ่อมคอมพิวเตอร์
        </div>
    </div>
</div>

<div id="detailsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-content">
        <header class="modal-header">
            <h2 id="modalTitle" class="modal-title">รายละเอียดการแจ้งซ่อม</h2>
            <button class="modal-close" aria-label="ปิด">&times;</button>
        </header>
        <main id="modalBody" class="modal-body"></main>
    </div>
</div>

<div id="liveNotice" class="live-notice" role="status" aria-live="polite">
    มีการอัปเดตใหม่ กำลังโหลดข้อมูล...
</div>

<script>
function toggleNavMenu(btn){const menu=document.getElementById('navMenu');const show=!menu.classList.contains('show');menu.classList.toggle('show',show);btn.classList.toggle('active',show);btn.setAttribute('aria-expanded',show?'true':'false');menu.setAttribute('aria-hidden',show?'false':'true')}
document.addEventListener('click',e=>{const menu=document.getElementById('navMenu');const btn=document.querySelector('.hb-btn');if(!menu)return;if(!menu.contains(e.target)&&!btn.contains(e.target)){menu.classList.remove('show');btn.classList.remove('active');btn.setAttribute('aria-expanded','false');menu.setAttribute('aria-hidden','true')}});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){const menu=document.getElementById('navMenu');const btn=document.querySelector('.hb-btn');if(menu&&menu.classList.contains('show')){menu.classList.remove('show');btn.classList.remove('active');btn.setAttribute('aria-expanded','false');menu.setAttribute('aria-hidden','true')}}});
document.addEventListener('DOMContentLoaded',()=>{const modalOverlay=document.getElementById('detailsModal');const modalBody=document.getElementById('modalBody');const modalTitle=document.getElementById('modalTitle');const table=document.querySelector('.table-wrap');const openModal=data=>{modalTitle.textContent=`รายละเอียดคิว: ${data.queue} (คุณ ${data.username})`;modalBody.innerHTML=`
 <span class="label">อุปกรณ์:</span><span class="value">${data.device||'-'}</span>
 <span class="label">หมายเลขเครื่อง:</span><span class="value">${data.serial||'-'}</span>
 <span class="label">ห้อง/ชั้น:</span><span class="value">${data.room||'-'}</span>
 <span class="label">เบอร์โทร:</span><span class="value">${data.phone||'-'}</span>
 <span class="label">เวลาแจ้ง:</span><span class="value">${data.time||'-'}</span>
 <span class="label" style="grid-column: 1 / -1; margin-top: 8px;"><b>ปัญหาที่แจ้ง:</b></span>
 <span class="value" style="grid-column: 1 / -1; margin-top: -10px; background: #f5f9ff; padding: 10px; border-radius: 8px;">${data.issue||'-'}</span>`;modalOverlay.classList.add('show')};const closeModal=()=>{modalOverlay.classList.remove('show')};if(table){table.addEventListener('click',e=>{if(e.target.classList.contains('btn-details')){const row=e.target.closest('tr');if(!row)return;const reportData={queue:row.dataset.queue,username:row.dataset.username,device:row.dataset.device,serial:row.dataset.serial,room:row.dataset.room,phone:row.dataset.phone,time:row.dataset.time,issue:row.dataset.issue,};openModal(reportData)}})}
modalOverlay.addEventListener('click',e=>{if(e.target===modalOverlay||e.target.classList.contains('modal-close')){closeModal()}});document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modalOverlay.classList.contains('show')){closeModal()}})});
</script>
</body>
</html>