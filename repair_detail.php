<?php
// ===== DB =====
$conn = new mysqli("localhost", "techfixuser", "StrongPass!234", "techfix");
if ($conn->connect_error) { die("DB Error"); }
$conn->set_charset("utf8");

/* ====== Device filter map (slug -> display, regex) ====== */
$dtypes = [
  'all'     => 'ทั้งหมด',
  'pc'      => 'ปัญหาเกี่ยวกับคอมพิวเตอร์',
  'printer' => 'ปัญหาเกี่ยวกับปริ้นเตอร์',
  'laptop'  => 'ปัญหาเกี่ยวกับโน๊ตบุ๊ค',
  'network' => 'ปัญหาเกี่ยวกับเครือข่าย',
  'tv'      => 'ปัญหาเกี่ยวกับ TV',
];
// ใช้ LOWER(device_type) REGEXP ? ให้รองรับหลายคำ (ไทย/อังกฤษ)
$regexMap = [
  'pc'      => '(คอม|computer|pc|desktop)',
  'printer' => '(ปริ้น|พรินท์|printer|พิมพ์)',
  'laptop'  => '(โน๊ตบุ๊ค|โน้ตบุ๊ก|laptop|notebook)',
  'network' => '(เครือข่าย|network|lan|wifi|router|switch)',
  'tv'      => '(tv|ทีวี|monitor|จอภาพ)',
];

/* ===== Helper ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function build_where_and_params($status, $dtype, $regexMap){
  $wheres = [];
  $types  = '';
  $vals   = [];

  // กรองสถานะ
  if ($status !== 'all'){
    $wheres[] = "status = ?";
    $types   .= "s";
    $vals[]   = $status;
  }
  // กรองประเภทอุปกรณ์ (ด้วย REGEXP แบบยืดหยุ่น)
  if ($dtype !== 'all' && isset($regexMap[$dtype])) {
    $wheres[] = "LOWER(device_type) REGEXP ?";
    $types   .= "s";
    $vals[]   = strtolower($regexMap[$dtype]);
  }

  $whereSQL = $wheres ? ("WHERE ".implode(" AND ", $wheres)) : "";
  return [$whereSQL, $types, $vals];
}

/* ===== รับตัวกรอง ===== */
$filterStatusAllowed = ['new','in_progress','done'];
$filterStatus = $_GET['status'] ?? 'all';
if (!in_array($filterStatus, $filterStatusAllowed, true)) $filterStatus = 'all';

$filterDtypeAllowed = array_keys($dtypes);
$filterDtype = $_GET['dtype'] ?? 'all';
if (!in_array($filterDtype, $filterDtypeAllowed, true)) $filterDtype = 'all';

/* === โหมดตอบ JSON สำหรับรีเฟรชสถานะ (AJAX) ===
   จะเคารพตัวกรอง status + dtype ตามพารามิเตอร์ใน URL ปัจจุบัน
*/
if (isset($_GET['poll']) && $_GET['poll'] === 'status') {
  header('Content-Type: application/json; charset=utf-8');
  if (!isset($conn) || $conn->connect_error) { echo json_encode([]); exit; }

  [$whereSQL, $types, $vals] = build_where_and_params($filterStatus, $filterDtype, $regexMap);

  $sql = "SELECT id, status FROM device_reports $whereSQL ORDER BY id DESC";
  $stmt = $conn->prepare($sql);
  if ($types) { $stmt->bind_param($types, ...$vals); }
  $stmt->execute();
  $res = $stmt->get_result();

  $rows = [];
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $rows[] = ['id' => (int)$r['id'], 'status' => $r['status']];
    }
  }
  $stmt->close();
  echo json_encode($rows, JSON_UNESCAPED_UNICODE);
  exit;
}

/* ===== ตั้งค่าการแบ่งหน้า ===== */
$perPage  = 10;
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset   = ($page - 1) * $perPage;

/* ===== นับจำนวนทั้งหมด (ตามตัวกรอง status+dtype) ===== */
[$whereSQLCnt, $typesCnt, $valsCnt] = build_where_and_params($filterStatus, $filterDtype, $regexMap);
$countSql = "SELECT COUNT(*) AS total FROM device_reports $whereSQLCnt";
$countStmt = $conn->prepare($countSql);
if ($typesCnt) { $countStmt->bind_param($typesCnt, ...$valsCnt); }
$countStmt->execute();
$countRes   = $countStmt->get_result();
$totalRows  = (int)($countRes->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

/* ===== ดึงรายการตามตัวกรอง + LIMIT/OFFSET ===== */
$baseSelect = "
  SELECT id, username AS fullname, device_type AS device, floor,
         serial_number AS device_no, status
  FROM device_reports
";
[$whereSQL, $types, $vals] = build_where_and_params($filterStatus, $filterDtype, $regexMap);
$sql = "$baseSelect $whereSQL ORDER BY id DESC LIMIT ? OFFSET ?";
$typesSel = $types . "ii";
$valsSel  = $vals;
$valsSel[] = $perPage;
$valsSel[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($typesSel, ...$valsSel);
$stmt->execute();
$result = $stmt->get_result();

$status_map = [
  'new'         => ['label'=>'รอดำเนินการ','color'=>'red'],
  'in_progress' => ['label'=>'กำลังซ่อม','color'=>'blue'],
  'done'        => ['label'=>'ซ่อมเสร็จแล้ว','color'=>'green'],
];

function page_url($p, $status, $dtype){
  return '?status='.urlencode($status).'&dtype='.urlencode($dtype).'&page='.(int)max(1,$p);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>หน้าหลักการแจ้งซ่อม</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{ --header-h:64px; --pad-top:24px; }
    body{
      font-family:'Tahoma',sans-serif;margin:0;min-height:100vh;
      padding: calc(var(--header-h) + var(--pad-top)) 20px 32px;
      display:flex;justify-content:center;align-items:flex-start;
      background:
        radial-gradient(1200px 600px at 80% -10%, rgba(255,255,255,.35), rgba(255,255,255,0) 60%),
        linear-gradient(135deg,#0b1e33 0%, #0e2a4a 45%, #113761 100%);
    }
    body::after{content:"";position:fixed;inset:0;background-image:radial-gradient(rgba(255,255,255,0.06) 1px, transparent 1px);background-size:18px 18px;pointer-events:none;}

    .site-header{position:fixed;inset:0 0 auto 0;height:var(--header-h);z-index:1200;background:linear-gradient(90deg,#0b3a6b 0,#1366b3 100%);color:#fff;box-shadow:0 6px 18px rgba(0,0,0,.18);border-bottom:1px solid rgba(255,255,255,.25)}
    .navbar{height:100%;display:flex;align-items:center;justify-content:space-between;padding:0 24px}
    .brand{display:flex;align-items:center;gap:12px;color:#fff;text-decoration:none}
    .brand-mark{display:grid;place-items:center;width:36px;height:36px;border-radius:999px;background:rgba(255,255,255,.15)}
    .brand-title{font-weight:800}.brand-sub{opacity:.85;font-size:12px;display:block}
    .back-left{position:fixed;left:22px;top: calc(var(--header-h) + 12px);z-index:1100;display:inline-flex;align-items:center;gap:10px;padding:10px 16px;border-radius:999px;background: linear-gradient(90deg,#1976d2,#0d47a1);color:#fff;text-decoration:none;font-weight:700;border:1px solid rgba(255,255,255,.22);box-shadow:0 10px 22px rgba(0,0,0,.25);backdrop-filter: blur(6px);transition: transform .12s ease, box-shadow .12s ease, opacity .12s ease}
    .back-left:hover{ transform: translateY(-1px); box-shadow: 0 12px 24px rgba(0,0,0,.3); opacity:.96}
    .back-left svg{ width:18px; height:18px }

    .page{ width:min(1200px,100%) }
    .card{background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); border-radius:16px; box-shadow:0 12px 28px rgba(25,118,210,.4); overflow:hidden}
    .card-header{padding:18px 20px;background: linear-gradient(90deg,#0d47a1,#1976d2);color:#fff;font-weight:700;font-size:20px;letter-spacing:.3px;text-align:center}
    .table-wrap{ padding: 10px 14px 4px }
    table{ width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden }
    th,td{ padding:12px 15px; text-align:center }
    th{ background: linear-gradient(90deg,#0d47a1,#1976d2); color:#fff; font-weight:600; font-size:16px; letter-spacing:.4px }
    td{ font-size:15px; color:#2d2d2d; border-bottom:1px solid rgba(25,118,210,.4) }
    tr:nth-child(even){ background:#f6fbff } tr:hover{ background:#e7f1ff; transition:.25s }

    .status-cell{ display:flex; align-items:center; gap:8px; justify-content:center }
    .status-dot{ display:inline-block; width:16px; height:16px; border-radius:50%; border:1px solid #aaa }
    .red{ background:#f44336 } .blue{ background:#2196f3 } .green{ background:#4caf50 } .gray{ background:#9e9e9e }
    .status-label{ font-weight:700 } .txt-red{color:#f44336}.txt-blue{color:#2196f3}.txt-green{color:#4caf50}.txt-gray{color:#9e9e9e}

    /* === Toolbar: ซ้าย (ประเภทอุปกรณ์) | ขวา (สถานะ) === */
    .toolbar{
      display:flex;justify-content:space-between;align-items:center;
      gap:16px;padding:12px 18px;flex-wrap:wrap
    }
    .toolbar .group{
      display:flex;align-items:center;gap:10px;flex-wrap:wrap
    }
    .toolbar .label{font-weight:800;color:#0a2540}
    .toolbar .select{
      appearance:none;height:42px;line-height:42px;padding:0 42px 0 14px;min-width:240px;
      border:1px solid #e6effa;border-radius:12px;background:#fff;font-size:15px;font-weight:700;color:#1f2937;outline:none;
      box-shadow:0 8px 18px rgba(10,37,64,.06), inset 0 1px 0 rgba(255,255,255,.6);
      background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="%231e293b" viewBox="0 0 16 16"><path d="M4.646 6.646a.5.5 0 0 1 .708 0L8 9.293l2.646-2.647a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 0 1 0-.708z"/></svg>');
      background-repeat:no-repeat;background-position:right 12px center;background-size:18px;
    }

    /* Pagination */
    .pager{display:flex;align-items:center;justify-content:center;gap:8px;padding:12px 16px 18px}
    .pager a, .pager span{
      display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 12px;
      border:1px solid #dfe7f3;border-radius:10px;text-decoration:none;color:#0b2440;font-weight:800;background:#fff
    }
    .pager a:hover{background:#f3f8ff;border-color:#cfe2ff}
    .pager .active{background:#e8f2ff;border-color:#b9dcff;color:#0b63c8}
    .pager .disabled{opacity:.45;pointer-events:none}

    @media (max-width:860px){
      .toolbar{justify-content:center}
    }
    @media (max-width:640px){
      :root{ --header-h:56px }
      .back-left{ left:12px; top: calc(var(--header-h) + 10px) }
      .navbar{ padding:0 14px } .brand-sub{ display:none }
      th{ font-size:15px } td{ font-size:14px }

      /* ฟอร์มกรองซ้อนเป็นสองแถวบนจอเล็ก */
      .toolbar{flex-direction:column;align-items:stretch}
      .toolbar .group{width:100%;justify-content:space-between}
      .toolbar .select{min-width:unset;flex:1}

      /* ทำให้ตารางอ่านง่ายบนจอเล็กมาก */
      thead{ display:none }
      table{ border-collapse:separate }
      tbody tr{ display:block; margin:10px; border:1px solid #e6effa; border-radius:12px; overflow:hidden; box-shadow:0 6px 16px rgba(0,0,0,.06) }
      tbody td{ display:flex; gap:10px; align-items:flex-start; border-bottom:1px solid #eef2f7 }
      tbody tr td:first-child{ border-top:none }
      tbody td::before{ content:attr(data-label); flex:0 0 120px; font-weight:800; color:#0f3a66 }
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

<!-- ปุ่มกลับหน้าหลัก -->
<a class="back-left" href="index.php" title="กลับหน้าหลัก">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <polyline points="15 18 9 12 15 6"></polyline>
  </svg>
  <span class="label">กลับหน้าหลัก</span>
</a>

<main class="page">
  <section class="card">
    <div class="card-header">รายการแจ้งซ่อม</div>

    <!-- Filters: ซ้าย = ประเภทอุปกรณ์, ขวา = สถานะ -->
    <form class="toolbar" method="get">
      <div class="group">
        <label class="label" for="dtype">กรองอุปกรณ์:</label>
        <select class="select" id="dtype" name="dtype" onchange="this.form.page.value=1; this.form.submit()">
          <?php foreach ($dtypes as $slug=>$label): ?>
            <option value="<?= h($slug) ?>" <?= $filterDtype===$slug ? 'selected':'' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="group">
        <label class="label" for="status">กรองสถานะ:</label>
        <select class="select" id="status" name="status" onchange="this.form.page.value=1; this.form.submit()">
          <option value="all"         <?= $filterStatus==='all' ? 'selected' : '' ?>>ทั้งหมด</option>
          <option value="new"         <?= $filterStatus==='new' ? 'selected' : '' ?>>❌ ยังไม่ซ่อม</option>
          <option value="in_progress" <?= $filterStatus==='in_progress' ? 'selected' : '' ?>>🔧 กำลังซ่อม</option>
          <option value="done"        <?= $filterStatus==='done' ? 'selected' : '' ?>>✅ ซ่อมเสร็จ</option>
        </select>
      </div>

      <input type="hidden" name="page" value="<?= (int)$page ?>"><!-- เปลี่ยนตัวกรองให้กลับหน้า 1 -->
    </form>

    <!-- Table -->
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
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <nav class="pager" aria-label="เปลี่ยนหน้า">
      <?php
        $prev = $page - 1;
        $next = $page + 1;
      ?>
      <a class="<?= $page<=1 ? 'disabled':'' ?>" href="<?= $page<=1 ? '#' : h(page_url($prev, $filterStatus, $filterDtype)) ?>" aria-label="ก่อนหน้า">«</a>

      <?php
        $window = 2; // จำนวนหน้าก่อน/หลัง
        $start = max(1, $page - $window);
        $end   = min($totalPages, $page + $window);

        if ($start > 1){
          echo '<a href="'.h(page_url(1,$filterStatus,$filterDtype)).'">1</a>';
          if ($start > 2) echo '<span class="disabled">…</span>';
        }
        for($p=$start; $p<=$end; $p++){
          if ($p == $page) echo '<span class="active">'.$p.'</span>';
          else echo '<a href="'.h(page_url($p,$filterStatus,$filterDtype)).'">'.$p.'</a>';
        }
        if ($end < $totalPages){
          if ($end < $totalPages-1) echo '<span class="disabled">…</span>';
          echo '<a href="'.h(page_url($totalPages,$filterStatus,$filterDtype)).'">'.$totalPages.'</a>';
        }
      ?>

      <a class="<?= $page>=$totalPages ? 'disabled':'' ?>" href="<?= $page>=$totalPages ? '#' : h(page_url($next, $filterStatus, $filterDtype)) ?>" aria-label="ถัดไป">»</a>
      <span class="disabled" style="border:none">หน้า <?= $page ?> / <?= $totalPages ?> • ทั้งหมด <?= number_format($totalRows) ?> รายการ</span>
    </nav>

  </section>
</main>

<?php
$stmt->close();
$result->free();
$conn->close();
?>

<script>
  // แผนที่สถานะ -> ป้ายและสี
  const statusMap = {
    new:         {label: "รอดำเนินการ",  color: "red"},
    in_progress: {label: "กำลังซ่อม",    color: "blue"},
    done:        {label: "ซ่อมเสร็จแล้ว", color: "green"},
  };

  // สร้าง HTML ของเซลล์สถานะ
  function renderStatusCell(st){
    const color = (st && st.color) ? st.color : "gray";
    const label = (st && st.label) ? st.label : "ไม่ทราบ";
    return `<span class="status-dot ${color}"></span><span class="status-label txt-${color}">${label}</span>`;
  }

  // ดึงสถานะล่าสุดแล้วอัปเดตเฉพาะแถวที่มีอยู่ในหน้านี้
  async function refreshTickets(){
    try{
      const url = new URL(window.location.href);
      url.searchParams.set('poll', 'status'); // ขอเฉพาะ id/status (ยังคงพารามิเตอร์ dtype/status/page ปัจจุบัน)
      const res = await fetch(url.toString(), { cache: "no-store" });
      if (!res.ok) return;

      const data = await res.json();
      if (!Array.isArray(data)) return;

      for (const row of data) {
        const tr = document.querySelector(`#ticket-body tr[data-id="${row.id}"]`);
        if (!tr) continue;

        const st = statusMap[row.status] || {label: "ไม่ทราบ", color: "gray"};
        const cell = tr.querySelector(".status-cell");
        if (cell) cell.innerHTML = renderStatusCell(st);
      }
    } catch (e) {
      console.error("Refresh error", e);
    }
  }

  // เรียกครั้งแรก และตั้ง interval ให้อัปเดตอัตโนมัติ
  refreshTickets();
  setInterval(refreshTickets, 8000); // ทุก 8 วิ
</script>
</body>
</html>
