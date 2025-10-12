<?php
session_start();
// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$err = $_GET['err'] ?? '';
// ดึงข้อความ error จาก session (ถ้ามี)
$errorMsg = $_SESSION['error'] ?? '';
unset($_SESSION['error']); // ลบออกหลังแสดง
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>เข้าสู่ระบบช่าง • TechFix.it</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    /* CSS ทั้งหมดเหมือนเดิม ไม่มีการแก้ไข */
    :root{
      --bg:#0b1623;
      --bg2:#09111b;
      --card:rgba(19,34,56,.72);
      --stroke:rgba(255,255,255,.08);
      --input:#0f1c2c;
      --text:#eaf2ff;
      --muted:#98a9bf;
      --accent1:#2aa2ff;
      --accent2:#0a66b5;
      --danger:#ff5d73;
      --glow:0 14px 40px rgba(42,162,255,.30);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0; font-family:'Montserrat',sans-serif; color:var(--text);
      background:
        radial-gradient(1200px 700px at 15% 10%, rgba(42,162,255,.12), transparent 60%),
        radial-gradient(900px 600px at 85% 90%, rgba(10,102,181,.14), transparent 60%),
        linear-gradient(180deg, var(--bg), var(--bg2));
      display:grid; place-items:center; padding:28px;
    }
    .blob{position:fixed; filter:blur(60px); opacity:.45; z-index:0; pointer-events:none}
    .b1{width:360px;height:360px;top:6%;left:8%; background:#1b3a57}
    .b2{width:420px;height:420px;bottom:8%;right:10%; background:#0a3b6e}
    @media (max-width:560px){ .b1,.b2{display:none} }

    .wrap{ width:min(100%, 480px); position:relative; z-index:1; }
    .card{
      background:var(--card); backdrop-filter: blur(16px);
      border:1px solid var(--stroke);
      border-radius:20px; padding:30px 28px 24px;
      box-shadow:var(--glow);
      animation: pop .35s ease;
    }
    @keyframes pop{ from{ transform:translateY(10px); opacity:0 } to{ transform:none; opacity:1 } }

    .brand{display:flex; flex-direction:column; align-items:center; text-align:center;
      gap:16px; margin-bottom:22px;}
    .brand-img{width:220px; max-width:90%; height:auto; border-radius:14px;
      border:1px solid rgba(255,255,255,.14);
      box-shadow:0 12px 30px rgba(0,0,0,.45),0 0 14px rgba(42,162,255,.35);}
    .title-grad{font-size:1.7rem; font-weight:700; margin:0 0 2px;
      background:linear-gradient(135deg,var(--accent1),var(--accent2));
      -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent;
      text-shadow:0 2px 6px rgba(0,0,0,.35);}
    @media (max-width:560px){.brand-img{width:170px}.title-grad{font-size:1.5rem}}

    .alert{
      background:rgba(255,93,115,.1); border:1px solid rgba(255,93,115,.35);
      color:#ffdbe1; padding:10px 12px; border-radius:12px; font-size:.92rem;
      margin-bottom:14px; display:flex; gap:10px; align-items:center;
      animation: shake .35s ease;
    }
    .alert svg{flex:0 0 18px}
    @keyframes shake{
      10%,90% {transform:translateX(-1px)}
      20%,80% {transform:translateX(2px)}
      30%,50%,70% {transform:translateX(-3px)}
      40%,60% {transform:translateX(3px)}
    }

    .field{margin:12px 0}
    .input{position:relative; display:flex; align-items:center;
      border:1px solid #1f3047; background:var(--input); border-radius:12px;
      padding:12px 12px; transition:.18s;}
    .input:focus-within{ border-color:#2a86ff; box-shadow:0 0 0 4px rgba(42,134,255,.18) }
    .input svg{opacity:.8}
    .input input{flex:1; border:0; outline:none; background:transparent; color:var(--text);
      font-size:1rem; padding:2px 8px;}
    .toggle{border:0; background:transparent; color:var(--muted);
      cursor:pointer; padding:6px; border-radius:8px;}
    .toggle:hover{ color:#cfe2ff; }

    .btn{width:100%; margin-top:16px; padding:12px 16px;
      border:0; border-radius:12px; cursor:pointer; color:#fff;
      font-weight:700; letter-spacing:.2px;
      background:linear-gradient(135deg,var(--accent1),var(--accent2));
      box-shadow:var(--glow); transition:transform .12s ease, filter .12s ease;}
    .btn:hover{ transform:translateY(-1px); filter:brightness(1.05) }
    .btn:active{ transform:translateY(0) }

    .foot{margin-top:18px; text-align:center; color:var(--muted); font-size:.88rem}
    .foot a{color:#cfe2ff}
    .sr-only{position:absolute;left:-9999px}
  </style>
</head>
<body>
  <div class="blob b1"></div>
  <div class="blob b2"></div>

  <div class="wrap">
    <div class="card">
      <div class="brand">
        <img class="brand-img" src="image/logo2.png" alt="TechFix.it">
        <h1 class="title-grad">เข้าสู่ระบบช่างเทคนิค</h1>
      </div>

      <?php if (!empty($errorMsg)): ?>
        <div class="alert" role="alert" aria-live="assertive">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span><?= htmlspecialchars($errorMsg) ?></span>
        </div>
      <?php endif; ?>
      
      <form method="post" action="technician_login_process.php" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="field">
          <label class="sr-only" for="username">ชื่อผู้ใช้</label>
          <div class="input">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M20 21a8 8 0 1 0-16 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <input id="username" name="username" type="text" placeholder="ชื่อผู้ใช้" required autofocus>
          </div>
        </div>

        <div class="field">
          <label class="sr-only" for="password">รหัสผ่าน</label>
          <div class="input">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="11" width="18" height="10" rx="2" stroke="currentColor" stroke-width="1.6"/>
              <path d="M7 11V8a5 5 0 0 1 10 0v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
            <input id="password" name="password" type="password" placeholder="รหัสผ่าน" required>
            <button type="button" class="toggle" aria-label="สลับแสดงรหัสผ่าน" onclick="togglePassword()">
              <svg id="eye" width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
              </svg>
            </button>
          </div>
        </div>

        <button class="btn" type="submit">เข้าสู่ระบบ</button>
      </form>
  </div>

  <script>
    function togglePassword(){
      const input = document.getElementById('password');
      const eye = document.getElementById('eye');
      const isText = input.type === 'text';
      input.type = isText ? 'password' : 'text';
      eye.innerHTML = isText
        ? '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>'
        : '<path d="M17.94 17.94A10.2 10.2 0 0 1 12 19c-7 0-11-7-11-7a20.7 20.7 0 0 1 5.22-5.53M9.88 4.2A10.3 10.3 0 0 1 12 4c7 0 11 8 11 8a20.9 20.9 0 0 1-3.2 4.31" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><line x1="3" y1="3" x2="21" y2="21" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>';
    }
  </script>
</body>
</html>