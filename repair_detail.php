<?php
// ===== DB =====
$conn = new mysqli("localhost", "root", "123456", "techfix");
if ($conn->connect_error) { die("DB Error"); }
$conn->set_charset("utf8");

/* === โหมดตอบ JSON สำหรับรีเฟรชสถานะ (AJAX) === */
if (isset($_GET['poll']) && $_GET['poll'] === 'status') {
  header('Content-Type: application/json; charset=utf-8');
  require_once __DIR__.'/db_connect.php';
  if (!isset($conn) || $conn->connect_error) { echo json_encode([]); exit; }

  $filter  = $_GET['status'] ?? 'all';
  $allowed = ['new','in_progress','done'];
  if (!in_array($filter, $allowed, true)) $filter = 'all';

  if ($filter === 'all') {
    $stmt = $conn->prepare("SELECT id, status FROM device_reports ORDER BY id DESC");
  } else {
    $stmt = $conn->prepare("SELECT id, status FROM device_reports WHERE status = ? ORDER BY id DESC");
    $stmt->bind_param("s", $filter);
  }
  $stmt->execute();
  $res = $stmt->get_result();

  $rows = [];
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $rows[] = ['id' => (int)$r['id'], 'status' => $r['status']];
    }
  }
  $stmt->close();
  $conn->close();

  echo json_encode($rows, JSON_UNESCAPED_UNICODE);
  exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>หน้าหลักการแจ้งซ่อม</title>
  <style>
    :root{
      --header-h: 64px;         /* ความสูง header */
      --pad-top: 24px;          /* ระยะเว้นเหนือคอนเทนต์หลัง header */
    }

    body{
      font-family:'Tahoma',sans-serif;margin:0;min-height:100vh;
      /* ดันคอนเทนต์ลงให้พ้น header ที่ fixed */
      padding: calc(var(--header-h) + var(--pad-top)) 20px 32px;
      display:flex;justify-content:center;align-items:flex-start;

      background:
        radial-gradient(1200px 600px at 80% -10%, rgba(255,255,255,.35), rgba(255,255,255,0) 60%),
        linear-gradient(135deg,#0b1e33 0%, #0e2a4a 45%, #113761 100%);
    }
    body::after{content:"";position:fixed;inset:0;background-image:radial-gradient(rgba(255,255,255,0.06) 1px, transparent 1px);background-size:18px 18px;pointer-events:none;}

    /* ===== Header/Navbar: ลอยติดบนสุด ===== */
    .site-header{
      position:fixed; inset:0 0 auto 0; height:var(--header-h);
      z-index:1200;
      background:linear-gradient(90deg,#0b3a6b 0,#1366b3 100%);
      color:#fff; box-shadow:0 6px 18px rgba(0,0,0,.18);
      border-bottom:1px solid rgba(255,255,255,.25);
    }
    .navbar{height:100%;display:flex;align-items:center;justify-content:space-between;padding:0 24px}
    .brand{display:flex;align-items:center;gap:12px;color:#fff;text-decoration:none}
    .brand-mark{display:grid;place-items:center;width:36px;height:36px;border-radius:999px;background:rgba(255,255,255,.15)}
    .brand-title{font-weight:800}
    .brand-sub{opacity:.85;font-size:12px;display:block}

    /* ===== ปุ่มกลับหน้าหลัก: วางใต้ header อย่างสวยงาม ===== */
    .back-left{
      position:fixed; left:22px; top: calc(var(--header-h) + 12px); z-index:1100;
      display:inline-flex; align-items:center; gap:10px;
      padding:10px 16px; border-radius:999px;
      background: linear-gradient(90deg,#1976d2,#0d47a1);
      color:#fff; text-decoration:none; font-weight:700;
      border:1px solid rgba(255,255,255,.22);
      box-shadow:0 10px 22px rgba(0,0,0,.25);
      backdrop-filter: blur(6px);
      transition: transform .12s ease, box-shadow .12s ease, opacity .12s ease;
    }
    .back-left:hover{ transform: translateY(-1px); box-shadow: 0 12px 24px rgba(0,0,0,.3); opacity:.96; }
    .back-left:focus-visible{ outline: 3px solid rgba(25,118,210,.4); outline-offset: 2px; }
    .back-left svg{ width:18px; height:18px; }

    .page{ width:min(1200px,100%); }
    .card{background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); border-radius:16px; box-shadow:0 12px 28px rgba(25,118,210,.4); overflow:hidden;}
    .card-header{padding:18px 20px;background: linear-gradient(90deg,#0d47a1,#1976d2);color:#fff; font-weight:700; font-size:20px; letter-spacing:.3px; text-align:center;}
    .table-wrap{ padding: 10px 14px 18px; }
    table{ width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; }
    th,td{ padding:12px 15px; text-align:center; }
    th{ background: linear-gradient(90deg,#0d47a1,#1976d2); color:#fff; font-weight:600; font-size:16px; letter-spacing:.4px; }
    td{ font-size:15px; color:#2d2d2d; border-bottom:1px solid rgba(25,118,210,.4); }
    tr:nth-child(even){ background:#f6fbff; }
    tr:hover{ background:#e7f1ff; transition:.25s; }

    .status-cell{ display:flex; align-items:center; gap:8px; justify-content:center; }
    .status-dot{ display:inline-block; width:16px; height:16px; border-radius:50%; border:1px solid #aaa; }
    .red{ background:#f44336; } .blue{ background:#2196f3; } .green{ background:#4caf50; } .gray{ background:#9e9e9e; }

    .status-label{ font-weight:700; }
    .txt-red  { color:#f44336; }
    .txt-blue { color:#2196f3; }
    .txt-green{ color:#4caf50; }
    .txt-gray { color:#9e9e9e; }

    /* toolbar filter */
    .toolbar{display:flex;gap:12px;justify-content:center;align-items:center;padding:12px 18px;}
    .toolbar .label{font-weight:800;color:#0a2540;}
    .toolbar .select{
      appearance:none;height:42px;line-height:42px;padding:0 42px 0 14px;min-width:240px;
      border:1px solid #e6effa;border-radius:12px;background:#fff;font-size:15px;font-weight:700;color:#1f2937;outline:none;
      box-shadow:0 8px 18px rgba(10,37,64,.06), inset 0 1px 0 rgba(255,255,255,.6);
      background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="%231e293b" viewBox="0 0 16 16"><path d="M4.646 6.646a.5.5 0 0 1 .708 0L8 9.293l2.646-2.647a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 0 1 0-.708z"/></svg>');
      background-repeat:no-repeat;background-position:right 12px center;background-size:18px;
    }

    @media (max-width: 640px){
      :root{ --header-h: 56px; }
      .back-left{ left:12px; top: calc(var(--header-h) + 10px); }
      .navbar{ padding:0 14px; }
      .brand-sub{ display:none; }
    }
  </style>
</head>
<body>

<!-- ===== Header ลอยบนสุด ===== -->
<header class="site-header">
  <nav class="navbar">
    <a class="brand" href="#">
      <span class="brand-mark">🛠️</span>
      <span>
        <span class="brand-title">TechFix.it</span><br>
        <small class="brand-sub">ระบบแจ้งซ่อมคอมพิวเตอร์</small>
      </span>
    </a>
    <div></div>
  </nav>
</header>

<?php
  $homeUrl = 'index.php';
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

  require_once __DIR__.'/db_connect.php';
  if (!isset($conn) || $conn->connect_error) {
    die('<pre>เชื่อมต่อฐานข้อมูลไม่สำเร็จ: '.h($conn->connect_error ?? 'no $conn').'</pre>');
  }

  $filter  = $_GET['status'] ?? 'all';
  $allowed = ['new','in_progress','done'];
  if (!in_array($filter, $allowed, true)) $filter = 'all';

  $baseSelect = "
    SELECT id, username AS fullname, device_type AS device, floor,
           serial_number AS device_no, status
    FROM device_reports
  ";

  if ($filter === 'all') {
    $stmt = $conn->prepare("$baseSelect ORDER BY id DESC");
  } else {
    $stmt = $conn->prepare("$baseSelect WHERE status = ? ORDER BY id DESC");
    $stmt->bind_param("s", $filter);
  }
  $stmt->execute();
  $result = $stmt->get_result();
  if (!$result) { die('<pre>SQL Error: '.h($conn->error)."</pre>"); }

  $status_map = [
    'new'         => ['label'=>'รอดำเนินการ','color'=>'red'],
    'in_progress' => ['label'=>'กำลังซ่อม','color'=>'blue'],
    'done'        => ['label'=>'ซ่อมเสร็จแล้ว','color'=>'green'],
  ];
?>

<!-- ปุ่มกลับหน้าหลัก: อยู่ใต้ header -->
<a class="back-left" href="<?= h($homeUrl) ?>" title="กลับหน้าหลัก">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <polyline points="15 18 9 12 15 6"></polyline>
  </svg>
  <span class="label">กลับหน้าหลัก</span>
</a>

<main class="page">
  <section class="card">
    <div class="card-header">รายการแจ้งซ่อม</div>

    <form class="toolbar" method="get">
      <label class="label" for="status">กรองสถานะ:</label>
      <select class="select" id="status" name="status" onchange="this.form.submit()">
        <option value="all"         <?= $filter==='all' ? 'selected' : '' ?>>ทั้งหมด</option>
        <option value="new"         <?= $filter==='new' ? 'selected' : '' ?>>❌ ยังไม่ซ่อม</option>
        <option value="in_progress" <?= $filter==='in_progress' ? 'selected' : '' ?>>🔧 กำลังซ่อม</option>
        <option value="done"        <?= $filter==='done' ? 'selected' : '' ?>>✅ ซ่อมเสร็จ</option>
      </select>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ลำดับ</th>
            <th>ชื่อ-สกุล</th>
            <th>อุปกรณ์</th>
            <th>ชั้นที่</th>
            <th>หมายเลขเครื่อง</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody id="ticket-body">
        <?php if ($result->num_rows > 0): ?>
          <?php while($row = $result->fetch_assoc()): ?>
            <?php $st = $status_map[$row['status']] ?? ['label'=>'ไม่ทราบ','color'=>'gray']; ?>
            <tr data-id="<?= (int)$row['id'] ?>">
              <td data-label="ลำดับ"><?= (int)$row['id'] ?></td>
              <td data-label="ชื่อ-สกุล"><?= h($row['fullname']) ?></td>
              <td data-label="อุปกรณ์"><?= h($row['device']) ?></td>
              <td data-label="ชั้นที่"><?= h($row['floor']) ?></td>
              <td data-label="หมายเลขเครื่อง"><?= h($row['device_no']) ?></td>
              <td data-label="สถานะ" class="status-cell">
                <span class="status-dot <?= h($st['color']) ?>"></span>
                <span class="status-label txt-<?= h($st['color']) ?>"><?= h($st['label']) ?></span>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6">ไม่มีข้อมูลการแจ้งซ่อม</td></tr>
        <?php endif; ?>
        <?php $stmt->close(); $result->free(); $conn->close(); ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<script>
const statusMap = {
  new:         {label: "รอดำเนินการ",  color: "red"},
  in_progress: {label: "กำลังซ่อม",    color: "blue"},
  done:        {label: "ซ่อมเสร็จแล้ว", color: "green"},
};

function renderStatusCell(st){
  const color = st?.color || "gray";
  const label = st?.label || "ไม่ทราบ";
  return `<span class="status-dot ${color}"></span> <span class="status-label txt-${color}">${label}</span>`;
}

async function refreshTickets(){
  try{
    const url = new URL(window.location.href);
    url.searchParams.set('poll', 'status');
    const res = await fetch(url, {cache:"no-store"});
    if(!res.ok) return;
    const data = await res.json();
    if(!Array.isArray(data)) return;

    data.forEach(row => {
      const tr = document.querySelector(`#ticket-body tr[data-id="${row.id}"]`);
      if(tr){
        const st = statusMap[row.status] || {label:"ไม่ทราบ", color:"gray"};
        const cell = tr.querySelector(".status-cell");
        if (cell) cell.innerHTML = renderStatusCell(st);
      }
    });
  }catch(e){
    console.error("Refresh error", e);
  }
}
refreshTickets();
setInterval(refreshTickets, 10000);
</script>

</body>
</html>
