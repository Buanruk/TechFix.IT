<?php
// ===== DB =====
$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
if ($conn->connect_error) { die("DB Error"); }
$conn->set_charset("utf8mb4");

// ===== รับค่าสถานะ =====
$filter = $_GET['status'] ?? 'all';
$allowFilter = ['all','new','in_progress','done'];
if (!in_array($filter, $allowFilter, true)) $filter = 'all';

// ===== สรุปจำนวนแต่ละสถานะ (การ์ดสรุป) =====
$stat = ['new'=>0,'in_progress'=>0,'done'=>0,'all'=>0];
$qr = $conn->query("SELECT status, COUNT(*) AS c FROM device_reports GROUP BY status");
if ($qr) {
  while($r = $qr->fetch_assoc()){
    $key = $r['status'];
    if (isset($stat[$key])) $stat[$key] = (int)$r['c'];
    $stat['all'] += (int)$r['c'];
  }
}

// ===== โหลดรายการตามสถานะ =====
if ($filter === 'all') {
  $stmt = $conn->prepare("SELECT * FROM device_reports ORDER BY id DESC");
} else {
  $stmt = $conn->prepare("SELECT * FROM device_reports WHERE status = ? ORDER BY id DESC");
  $stmt->bind_param("s", $filter);
}
$stmt->execute();
$result = $stmt->get_result();

// ===== Helpers =====
function statusText($s){
  return match($s){
    'new'         => 'ยังไม่ซ่อม',
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
  };
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>รายการแจ้งซ่อมทั้งหมด</title>
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
  .hb-btn{display:flex;flex-direction:column;gap:5px;padding:10px;border:none;border-radius:10px;background:linear-gradient(135deg,#2aa2ff,#0a66b5);cursor:pointer;transition:transform .18s ease, filter .18s ease, box-shadow .18s ease;box-shadow:0 8px 20px rgba(42,162,255,.28)}
  .hb-btn:hover{filter:brightness(1.06); transform:translateY(-1px)}
  .hb-btn:active{transform:translateY(0)}
  .hb-btn span{width:24px;height:3px;background:#fff;border-radius:3px;transition:.25s}
  .hb-btn.active span:nth-child(1){transform:translateY(8px) rotate(45deg)}
  .hb-btn.active span:nth-child(2){opacity:0}
  .hb-btn.active span:nth-child(3){transform:translateY(-8px) rotate(-45deg)}
  .nav-menu{position:absolute; right:20px; top:60px;background:#fff; border:1px solid #e0e6ef;border-radius:12px; box-shadow:0 10px 28px rgba(15,40,80,.16);min-width:220px; overflow:hidden;opacity:0; transform:translateY(-8px) scale(.98);max-height:0; pointer-events:none;transition:opacity .22s ease, transform .22s ease, max-height .26s cubic-bezier(.2,.8,.2,1)}
  .nav-menu.show{opacity:1; transform:translateY(0) scale(1); max-height:260px; pointer-events:auto;}
  .menu-item{display:flex; align-items:center; gap:12px;padding:12px 16px; text-decoration:none; font-weight:800;color:#0b2440; letter-spacing:.2px;transition:background .15s ease, color .15s ease;}
  .menu-item:hover{background:#f3f8ff; color:#1e88e5}
  .menu-item.logout{color:#c62828}
  .menu-item.logout:hover{background:#ffecec; color:#b71c1c}

  .shell{padding:20px}
  .container{max-width:min(96vw,var(--container)); margin:24px auto 40px; padding:0 24px;}
  .panel{border-radius:var(--radius);border:1px solid var(--line);background:var(--card);box-shadow:var(--shadow);overflow:hidden}
  .panel-head{padding:18px 22px;background:linear-gradient(180deg,rgba(78,169,255,.16),rgba(30,136,229,.10))}
  .title{margin:0;text-align:center;color:#0b2440;font-weight:900}

  .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:16px 20px 8px}
  .kpi{border:1px solid var(--line);border-radius:16px;padding:12px 14px;background:#fff;box-shadow:0 10px 24px rgba(15,40,80,.06)}
  .kpi h4{margin:0 0 4px 0;font-size:13px;color:#0a2540}
  .kpi .num{font-size:26px;font-weight:900}
  .kpi.new .num{color:var(--red)} .kpi.progress .num{color:var(--blue-strong)} .kpi.done .num{color:var(--green)}

  .toolbar{display:flex; align-items:center; justify-content:center; gap:12px; padding:12px 18px; color:#667085}
  .label{display:flex; align-items:center; gap:8px; font-weight:800; color:#0a2540; letter-spacing:.2px}
  .select{-webkit-appearance:none; -moz-appearance:none; appearance:none;height:42px; line-height:42px; padding:0 42px 0 14px; min-width:240px;border:1px solid var(--line); border-radius:12px; background:#fff;font-size:15px; font-weight:700; color:#1f2937; outline:none;box-shadow:0 8px 18px rgba(10,37,64,.06), inset 0 1px 0 rgba(255,255,255,.6);transition: box-shadow .18s ease, border-color .18s ease, transform .06s ease;background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="%231e293b" viewBox="0 0 16 16"><path d="M4.646 6.646a.5.5 0 0 1 .708 0L8 9.293l2.646-2.647a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 0 1 0-.708z"/></svg>');background-repeat:no-repeat; background-position:right 12px center; background-size:18px;}
  .select:hover{ transform:translateY(-1px); box-shadow:0 10px 22px rgba(10,37,64,.10) }
  .select:focus{ border-color:#1e88e5; box-shadow:0 0 0 3px rgba(30,136,229,.18) }

  .table-wrap{background:#fff;border-top:1px solid var(--line);overflow-x:auto}
  table{width:100%;border-collapse:separate; border-spacing:0;font-size:14.5px;table-layout:fixed}
  colgroup col.c-queue{width:100px}
  colgroup col.c-name{width:210px}
  colgroup col.c-device{width:180px}
  colgroup col.c-serial{width:160px}
  colgroup col.c-room{width:110px}
  colgroup col.c-issue{width:auto}
  colgroup col.c-phone{width:160px}
  colgroup col.c-time{width:190px}
  colgroup col.c-status{width:140px}
  colgroup col.c-action{width:180px}
  thead th{position:sticky; top:0; z-index:2; background:linear-gradient(180deg,#f7fbff 0,#eef6ff 100%); color:#0f3a66; font-weight:800; letter-spacing:.2px; padding:14px 16px; border-bottom:1px solid var(--line); text-align:left;}
  tbody td{padding:14px 16px; border-top:1px solid var(--line); vertical-align:top; background:#fff;}
  tbody tr:nth-child(even) td{background:#fbfdff}
  tbody tr:hover td{background:#f3f8ff}
  .tc{text-align:center}
  .nowrap{white-space:nowrap}
  .ellipsis{max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;}
  .issue{display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden;line-height:1.55;white-space:normal;word-break:break-word;overflow-wrap:anywhere}

  .badge{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;font-size:13px;font-weight:800;border:1px solid transparent}
  .badge.new{background:#ffecec;color:#c62828;border-color:#ffd6d6}
  .badge.in_progress{background:#eef5ff;color:#0b63c8;border-color:#d6eaff}
  .badge.done{background:#e9f9ec;color:#2e7d32;border-color:#d1f3d8}

  .status-select{padding:6px 10px;border:1px solid var(--line);border-radius:10px;background:#fff;font-weight:700;cursor:pointer}
  .status-select:focus{border-color:#1e88e5;box-shadow:0 0 0 3px rgba(30,136,229,.15)}
  .select-new{color:#c62828} .select-progress{color:#0b63c8} .select-done{color:#2e7d32}

  .empty{padding:28px;text-align:center;color:#667085}
  @media (max-width:920px){
    .brand-sub{display:none}
    thead th, tbody td{padding:10px 12px}
    colgroup col.c-name{width:180px}
    colgroup col.c-serial{width:140px}
    colgroup col.c-phone{width:140px}
    colgroup col.c-time{width:170px}
    .issue{-webkit-line-clamp:3}
  }
</style>
</head>
<body>

<header class="site-header">
  <nav class="navbar">
    <a class="brand" href="#"><span class="brand-mark">🛠️</span><span><span class="brand-title">TechFix.it</span><br><small class="brand-sub">ระบบแจ้งซ่อมคอมพิวเตอร์</small></span></a>
    <div class="nav-actions">
      <button class="hb-btn" aria-label="เปิดเมนู" aria-expanded="false" onclick="toggleNavMenu(this)">
        <span></span><span></span><span></span>
      </button>
      <div id="navMenu" class="nav-menu" role="menu" aria-hidden="true">
        <a href="index.php" class="menu-item home" role="menuitem">หน้าหลัก</a>
        <a href="logout.php" class="menu-item logout" role="menuitem">ออกจากระบบ</a>
      </div>
    </div>
  </nav>
</header>

<div class="shell">
  <div class="container">
    <section class="panel">
      <header class="panel-head"><h1 class="title">รายการแจ้งซ่อมทั้งหมด</h1></header>

      <!-- KPI -->
      <div class="kpis">
        <div class="kpi total"><h4>ทั้งหมด</h4><div class="num"><?= (int)$stat['all'] ?></div></div>
        <div class="kpi new"><h4>ยังไม่ซ่อม</h4><div class="num"><?= (int)$stat['new'] ?></div></div>
        <div class="kpi progress"><h4>กำลังซ่อม</h4><div class="num"><?= (int)$stat['in_progress'] ?></div></div>
        <div class="kpi done"><h4>ซ่อมเสร็จ</h4><div class="num"><?= (int)$stat['done'] ?></div></div>
      </div>

      <!-- Filter -->
      <form class="toolbar" method="get">
        <label class="label" for="status">กรองสถานะ:</label>
        <select class="select" id="status" name="status" onchange="this.form.submit()">
          <option value="all"         <?= $filter==='all' ? 'selected' : '' ?>>ทั้งหมด</option>
          <option value="new"         <?= $filter==='new' ? 'selected' : '' ?>>❌ ยังไม่ซ่อม</option>
          <option value="in_progress" <?= $filter==='in_progress' ? 'selected' : '' ?>>🔧 กำลังซ่อม</option>
          <option value="done"        <?= $filter==='done' ? 'selected' : '' ?>>✅ ซ่อมเสร็จ</option>
        </select>
      </form>

      <!-- Table -->
      <div class="table-wrap">
        <table>
          <colgroup>
            <col class="c-queue"><col class="c-name"><col class="c-device"><col class="c-serial">
            <col class="c-room"><col class="c-issue"><col class="c-phone"><col class="c-time">
            <col class="c-status"><col class="c-action">
          </colgroup>
          <thead>
            <tr>
              <th class="tc">คิว</th>
              <th>ชื่อ-สกุล</th>
              <th>อุปกรณ์</th>
              <th>หมายเลขเครื่อง</th>
              <th class="tc">ห้อง</th>
              <th class="tc">ปัญหา</th>
              <th>เบอร์โทร</th>
              <th>เวลาแจ้ง</th>
              <th class="tc">สถานะ</th>
              <th class="tc">เปลี่ยนสถานะ</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($result->num_rows === 0): ?>
            <tr><td colspan="10" class="empty">ยังไม่มีรายการตามเงื่อนไขที่เลือก</td></tr>
          <?php else: ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <?php
                $room = $row['room'] ?? ($row['floor'] ?? '');
                $s = in_array($row['status'], ['new','in_progress','done']) ? $row['status'] : 'new';
                $selectClass = $s==='new' ? 'select-new' : ($s==='in_progress' ? 'select-progress' : 'select-done');
              ?>
              <tr>
                <td class="tc ellipsis"><?= h($row['queue_number']) ?></td>
                <td class="ellipsis" title="<?= h($row['username']) ?>"><?= h($row['username']) ?></td>
                <td class="ellipsis" title="<?= h($row['device_type']) ?>"><?= h($row['device_type']) ?></td>
                <td class="ellipsis" title="<?= h($row['serial_number']) ?>"><?= h($row['serial_number']) ?></td>
                <td class="tc"><?= h($room) ?></td>
                <td class="issue"><?= nl2br(h($row['issue_description'])) ?></td>
                <td class="nowrap"><?= h($row['phone_number']) ?></td>
                <td class="nowrap" title="<?= h($row['report_date']) ?>">
                  <?= h(@date('d/m/Y H:i', strtotime($row['report_date'])) ?: $row['report_date']) ?>
                </td>
                <td class="tc">
                  <span class="badge <?= $s ?>"><?= statusIcon($s) ?> <?= h(statusText($s)) ?></span>
                </td>
                <td class="tc">
                  <!-- ใช้พาธแบบ absolute ให้ตรงกับตำแหน่งจริงของ update_status.php (รากโดเมน) -->
                  <form method="POST" action="/update_status.php">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <select name="status" class="status-select <?= $selectClass ?>" onchange="this.form.submit()">
                      <option value="new"         <?= $s==='new'?'selected':'' ?>>❌ ยังไม่ซ่อม</option>
                      <option value="in_progress" <?= $s==='in_progress'?'selected':'' ?>>🔧 กำลังซ่อม</option>
                      <option value="done"        <?= $s==='done'?'selected':'' ?>>✅ ซ่อมเสร็จ</option>
                    </select>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
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
      menu.classList.remove('show');
      btn.classList.remove('active');
      btn.setAttribute('aria-expanded','false');
      menu.setAttribute('aria-hidden','true');
    }
  });
  document.addEventListener('keydown',(e)=>{
    if(e.key === 'Escape'){
      const menu = document.getElementById('navMenu');
      const btn = document.querySelector('.hb-btn');
      if(menu && menu.classList.contains('show')){
        menu.classList.remove('show');
        btn.classList.remove('active');
        btn.setAttribute('aria-expanded','false');
        menu.setAttribute('aria-hidden','true');
      }
    }
  });
</script>
</body>
</html>
