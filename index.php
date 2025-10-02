<?php /* TechFix.it — Parallax Scroll + Apple‑style motion (single file) */ ?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TechFix.it</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
  <style>
    :root{
      --bg:#0E111B; --bg-soft:#111627; --card:#151B27; --brand:#4F9DFF; --accent:#FDB913;
      --text:#E6EDF7; --muted:#A6B2C8; --maxw:1160px; --radius:18px; --shadow:0 10px 30px rgba(0,0,0,.25);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0; font-family:'Montserrat',sans-serif; color:var(--text); background:var(--bg);
      -webkit-font-smoothing:antialiased; overflow-x:hidden;
    }
    .navbar{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;
      padding:14px clamp(16px,4vw,40px);background:rgba(14,17,27,.6);backdrop-filter:blur(10px);
      border-bottom:1px solid rgba(255,255,255,.06)}
    .logo{font-weight:800;letter-spacing:.3px;display:flex;align-items:center;gap:10px}
    .logo .dot{width:10px;height:10px;background:var(--accent);border-radius:50%}
    .nav-actions{display:flex;align-items:center;gap:10px}
    .btn{display:inline-flex;align-items:center;gap:10px;padding:12px 16px;border-radius:14px;text-decoration:none;
      color:var(--text);background:linear-gradient(180deg,#4F9DFF,#3C86E7);box-shadow:0 8px 18px rgba(79,157,255,.35);font-weight:700}
    .btn.outline{background:transparent;border:1px solid rgba(230,237,247,.15);box-shadow:none}

    .wrap{width:min(100%,var(--maxw));margin:0 auto;padding:0 clamp(16px,4vw,40px)}

    /* ===== HERO (Parallax Layers) ===== */
    .hero{position:relative;min-height:86vh;display:grid;place-items:center;overflow:hidden}
    .hero-bg{
      position:absolute;inset:0;background:
        radial-gradient(1200px 600px at 15% -10%, rgba(79,157,255,.25), transparent 60%),
        radial-gradient(900px 500px at 110% 20%, rgba(253,185,19,.18), transparent 55%),
        linear-gradient(180deg, #0B1020, #0E111B 30%, #0E111B 100%);
      z-index:0
    }
    .layer{position:absolute;inset:auto;will-change:transform;filter:drop-shadow(0 10px 25px rgba(0,0,0,.25))}
    .floating{animation:float 6s ease-in-out infinite}
    @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}

    .hero-inner{position:relative;z-index:1;display:grid;gap:14px;text-align:center}
    h1{margin:0;font-size:clamp(28px,5.2vw,56px);line-height:1.12;font-weight:800}
    h1 span{color:var(--brand);text-shadow:0 6px 24px rgba(79,157,255,.3)}
    .lead{color:var(--muted);font-size:clamp(14px,1.3vw,18px)}
    .hero-cta{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:8px}

    /* QR badge */
    .qr-badge{position:absolute;right:clamp(10px,4vw,40px);bottom:clamp(10px,3vw,40px);z-index:2}
    .qr-card{display:flex;flex-direction:column;align-items:center;gap:10px;background:linear-gradient(160deg,#141B2B,#0F1522);
      border:1px solid rgba(255,255,255,.06);border-radius:16px;padding:14px 16px;box-shadow:var(--shadow)}
    .qr-box{width:120px;aspect-ratio:1/1;border-radius:10px;background:repeating-linear-gradient(45deg,#1E293B 0 12px,#0F172A 12px 24px);
      display:grid;place-items:center;color:#94A3B8;font-size:12px;border:1px dashed rgba(255,255,255,.18)}

    /* ===== SCROLL STORY (Apple‑like sticky frames) ===== */
    .story{position:relative}
    .frame{position:sticky;top:0;min-height:100vh;display:grid;grid-template-columns:1.1fr .9fr;gap:24px;align-items:center}
    .frame+.spacer{height:50vh}
    .glass{background:linear-gradient(145deg,#182033,#121826);border:1px solid rgba(255,255,255,.06);border-radius:var(--radius);padding:26px}
    .ph{width:100%;aspect-ratio:4/3;border-radius:16px;background:#0f172a;display:grid;place-items:center;color:#94A3B8;border:1px solid rgba(255,255,255,.06)}

    /* Reveal anim */
    .reveal{opacity:0;transform:translateY(24px);transition:opacity .7s ease, transform .7s ease}
    .reveal.in{opacity:1;transform:none}

    /* ===== Masonry showcase (light parallax on scroll) ===== */
    .grid-title{margin:40px 0 12px;font-size:22px;font-weight:800;color:#CFE4FF}
    .masonry{columns:1;column-gap:14px}
    @media(min-width:600px){.masonry{columns:2}}
    @media(min-width:900px){.masonry{columns:3}}
    .card{break-inside:avoid;margin:0 0 14px;background:var(--card);border:1px solid rgba(255,255,255,.06);border-radius:18px;overflow:hidden;box-shadow:var(--shadow)}
    .ph-img{width:100%;display:block;aspect-ratio:4/3;background:linear-gradient(180deg,#1C2436,#121827);display:grid;place-items:center;color:#94A3B8;font-size:12px}
    .card-body{padding:14px 16px 16px}

    .footer{margin-top:50px;padding:24px;text-align:center;color:var(--muted);border-top:1px solid rgba(255,255,255,.06);background:linear-gradient(180deg,rgba(20,26,40,.3),rgba(14,17,27,.6))}

    /* Mobile adjustments */
    @media(max-width:980px){.frame{grid-template-columns:1fr}.qr-badge{position:static;margin-top:16px;display:flex;justify-content:center}}
    @media(max-width:680px){.navbar{padding:12px 16px}.glass{padding:18px}}

    /* Respect reduced motion */
    @media (prefers-reduced-motion: reduce){
      .layer,.floating{animation:none;transform:none !important}
      .reveal{transition:none}
    }
  </style>
</head>
<body>
  <!-- NAVBAR -->
  <header class="navbar" role="navigation" aria-label="เมนูหลัก">
    <div class="logo"><span class="dot"></span> TechFix.it</div>
    <nav class="nav-actions">
      <a href="repair_detail.php" class="btn outline" aria-label="รายละเอียดแจ้งซ่อม"><i class="fas fa-clipboard-list"></i> รายละเอียดแจ้งซ่อม</a>
      <a href="admin_login.php" class="btn" aria-label="เข้าสู่ระบบแอดมิน"><i class="fas fa-user-shield"></i> สำหรับแอดมิน</a>
    </nav>
  </header>

  <!-- HERO with parallax layers -->
  <section class="hero" aria-label="หน้าแรก">
    <div class="hero-bg"></div>

    <!-- Parallax decorative layers (replace with PNG/SVG later) -->
    <img class="layer" data-parallax data-speed="-0.25" src="image/shape1.png" alt="" style="top:10%;left:-4%;width:28vw;max-width:360px;opacity:.35" />
    <img class="layer floating" data-parallax data-speed="0.12" src="image/shape2.png" alt="" style="top:18%;right:-6%;width:30vw;max-width:400px;opacity:.28" />
    <img class="layer" data-parallax data-speed="-0.15" src="image/shape3.png" alt="" style="bottom:-6%;left:8%;width:22vw;max-width:300px;opacity:.22" />

    <div class="hero-inner wrap">
      <h1 class="reveal">บริการซ่อมคอมพิวเตอร์ <span>TechFix.it</span></h1>
      <p class="lead reveal" style="transition-delay:.08s">แจ้งซ่อมผ่าน LINE Bot • ติดตามสถานะแบบเรียลไทม์ • รองรับมือถือเต็มรูปแบบ</p>
      <div class="hero-cta reveal" style="transition-delay:.15s">
        <a href="repair_detail.php" class="btn"><i class="fas fa-paper-plane"></i> เริ่มแจ้งซ่อม</a>
        <a href="#story" class="btn outline"><i class="fas fa-arrow-down"></i> ดูรายละเอียด</a>
      </div>
    </div>

    <!-- QR floating badge -->
    <aside class="qr-badge">
      <div class="qr-card reveal" style="transition-delay:.25s">
        <img src="image/qr.jpg" alt="QR LINE"style="width:120px;aspect-ratio:1/1;border-radius:10px;display:block">
        <small style="color:var(--muted)">@429fxsnw</small>
      </div>
    </aside>
  </section>

  <!-- STICKY STORY: Apple-like step scroll -->
  <main id="story" class="wrap story" aria-label="ลำดับคุณสมบัติ">
    <!-- Frame 1 -->
    <section class="frame">
      <div class="glass reveal">
        <h2 style="margin:0 0 8px;font-size:clamp(22px,3.5vw,34px)"><span style="color:var(--brand)">แจ้งซ่อม</span> ง่ายสุด ๆ</h2>
        <p class="lead">สแกน QR ผ่าน LINE กรอกข้อมูลแจ้งซ่อมเพียงไม่กี่ขั้นตอน <br> ระบบจะรับข้อมูลการแจ้งซ่อมและส่งคิวให้ทันที</p>
        <ul style="margin:12px 0 0;color:var(--muted);line-height:1.9">
          <li>รองรับอุปกรณ์ IT หรือ อุปกรณ์ไฟฟ้าทุกชนิด</li>
          <li>แจ้งเตือนอัตโนมัติในไลน์</li>
          <li>ติดตามสถานะได้ตลอดเวลา</li>
        </ul>
      </div>
      <div class="reveal">
        <img src="image/home.png" alt="home" style="width:100%;height:auto;border-radius:16px">
      </div>
    </section>
    <div class="spacer"></div>

    <!-- Frame 2 -->
    <section class="frame">
      <div class="glass reveal">
        <h2 style="margin:0 0 8px;font-size:clamp(22px,3.5vw,34px)">อัปเดต <span style="color:var(--accent)">เรียลไทม์</span></h2>
        <p class="lead">สถานะ "แจ้งซ่อม → กำลังซ่อม → เสร็จสิ้น" ถูกซิงก์ทั้งหน้าเว็บและ LINE พร้อม timestamp</p>
        <ul style="margin:12px 0 0;color:var(--muted);line-height:1.9">
          <li>การ์ดสรุปงาน + ค้นหาตามสถานะ</li>
          <li>แจ้งเตือนเมื่อมีการเปลี่ยนแปลง</li>
          <li>ส่งการแจ้งเตือนเข้า Line หลังซ่อมเสร็จ</li>
        </ul>
      </div>
      <div class="reveal">
        <img src="image/home.png" alt="home" style="width:100%;height:auto;border-radius:16px">
      </div>
    </section>
    <div class="spacer"></div>

    <!-- Frame 3 -->
    <section class="frame">
      <div class="glass reveal">
        <h2 style="margin:0 0 8px;font-size:clamp(22px,3.5vw,34px)">พร้อมใช้งานทุก <span style="color:var(--brand)">อุปกรณ์</span></h2>
        <p class="lead">SmartPhone , PC , Laptop ,TabLet</p>
        <ul style="margin:12px 0 0;color:var(--muted);line-height:1.9">
          <li>line 1 id สามารถแจ้งได้หลายอุปกรณ์ด้วยการเก็บข้อมูลแบบ <code>one to many</code> + <cod>user token</code></li>
          <li>ตรวจจับคำผิดด้วยเทคโนโลยี AI</li>
          <li>ปลอดภัยต่อข้อมูลส่วนตัว</li>
        </ul>
      </div>
      <div class="reveal">
        <img src="image/home.png" alt="home" style="width:100%;height:auto;border-radius:16px">
      </div>
    </section>
  </main>

  <!-- Masonry showcase with light parallax -->
  <section class="wrap">
    <h3 class="grid-title">ตัวอย่างงาน/บริการ</h3>
    <div class="masonry">
      <article class="card reveal"><div class="ph-img" data-parallax data-speed="0.06">รูป 4:3</div><div class="card-body">อัปเกรด SSD + ลง Windows</div></article>
      <article class="card reveal"><div class="ph-img" data-parallax data-speed="-0.04" style="aspect-ratio:4/5">รูป 4:5</div><div class="card-body">ซ่อมจอภาพไม่ติด</div></article>
      <article class="card reveal"><div class="ph-img" data-parallax data-speed="0.08">รูป 4:3</div><div class="card-body">ตั้งค่าปริ้นเตอร์สำนักงาน</div></article>
      <article class="card reveal"><div class="ph-img" data-parallax data-speed="-0.05">รูป 4:3</div><div class="card-body">ทำความสะอาดพีซี + เปลี่ยนซิลิโคน</div></article>
    </div>
  </section>

  <footer class="footer">
    <p>© <?php echo date('Y'); ?> TechFix.it — บริการซ่อมอุปกรณ์ไอทีครบวงจร</p>
  </footer>

  <script>
    // ===== IntersectionObserver: reveal on scroll =====
    const io = new IntersectionObserver((entries)=>{
      entries.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target);} });
    },{threshold:0.2});
    document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

    // ===== Parallax on scroll (requestAnimationFrame + speed factor) =====
    const layers = [...document.querySelectorAll('[data-parallax]')];
    let ticking = false;
    function updateParallax(){
      const vpH = window.innerHeight;
      layers.forEach(el=>{
        const rect = el.getBoundingClientRect();
        const speed = parseFloat(el.dataset.speed||0.1);
        // progress: -1 (above) -> 1 (below)
        const p = (rect.top + rect.height/2 - vpH/2) / vpH;
        const translate = p * speed * 100; // px
        el.style.transform = `translate3d(0, ${translate}px, 0)`;
      });
      ticking = false;
    }
    function onScroll(){ if(!ticking){ ticking = true; requestAnimationFrame(updateParallax);} }
    window.addEventListener('scroll', onScroll, {passive:true});
    window.addEventListener('resize', updateParallax);
    updateParallax();

    // Smooth scroll for in-page links
    document.querySelectorAll('a[href^="#"]').forEach(a=>{
      a.addEventListener('click', e=>{
        const id = a.getAttribute('href');
        if(id.length>1){ e.preventDefault(); document.querySelector(id)?.scrollIntoView({behavior:'smooth'}); }
      });
    });
  </script>
</body>
</html>
