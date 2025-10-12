<?php /* TechFix.it — Parallax Scroll + Apple-style motion (single file) */ ?>
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

    /* ===== NAVBAR ===== */
    .navbar{
      position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;
      padding:14px clamp(16px,4vw,40px);background:rgba(14,17,27,.6);backdrop-filter:blur(10px);
      border-bottom:1px solid rgba(255,255,255,.06)
    }
    .logo{font-weight:800;letter-spacing:.3px;display:flex;align-items:center;gap:10px}
    .logo .dot{width:10px;height:10px;background:var(--accent);border-radius:50%}
    .nav-actions{display:flex;align-items:center;gap:10px}

    /* ปุ่มหลัก */
    .btn{
      display:inline-flex;align-items:center;justify-content:center;gap:10px;
      padding:12px 16px;border-radius:14px;text-decoration:none;
      color:var(--text);background:linear-gradient(180deg,#4F9DFF,#3C86E7);
      box-shadow:0 8px 18px rgba(79,157,255,.35);font-weight:700;transition:.25s ease;
    }
    .btn i{font-size:1rem}
    .btn:hover{transform:translateY(-2px);box-shadow:0 12px 22px rgba(79,157,255,.45)}
    .btn:active{transform:scale(.97)}
    .btn.outline{background:transparent;border:1px solid rgba(230,237,247,.18);box-shadow:none}
    .btn.ghost{background:rgba(79,157,255,.12);border:1px solid rgba(79,157,255,.35);color:#CFE4FF}
    .btn.ghost:hover{background:rgba(79,157,255,.18);border-color:rgba(79,157,255,.55)}

    /* ปุ่มแอดมินบนแถบบน */
    .btn-admin{
      background:linear-gradient(180deg,#5ea4ff,#3d86e7);
      border:1px solid rgba(255,255,255,.14);
      padding:10px 16px;border-radius:999px;
    }

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
    .hero-cta .btn{min-width:180px}

    /* QR badge */
    .qr-badge{position:absolute;right:clamp(10px,4vw,40px);bottom:clamp(10px,3vw,40px);z-index:2}
    .qr-card{
      display:flex;flex-direction:column;align-items:center;gap:10px;background:linear-gradient(160deg,#141B2B,#0F1522);
      border:1px solid rgba(255,255,255,.06);border-radius:16px;padding:14px 16px;box-shadow:var(--shadow)
    }

    /* ===== SCROLL STORY ===== */
    .story{position:relative}
    .frame{position:sticky;top:0;min-height:100vh;display:grid;grid-template-columns:1.1fr .9fr;gap:24px;align-items:center}
    .frame+.spacer{height:50vh}
    .glass{background:linear-gradient(145deg,#182033,#121826);border:1px solid rgba(255,255,255,.06);border-radius:var(--radius);padding:26px}
    .ph{width:100%;aspect-ratio:4/3;border-radius:16px;background:#0f172a;display:grid;place-items:center;color:#94A3B8;border:1px solid rgba(255,255,255,.06)}

    /* Reveal anim */
    .reveal{opacity:0;transform:translateY(24px);transition:opacity .7s ease, transform .7s ease}
    .reveal.in{opacity:1;transform:none}

    /* ===== Masonry showcase ===== */
    .grid-title{margin:40px 0 12px;font-size:22px;font-weight:800;color:#CFE4FF}
    .masonry{columns:1;column-gap:14px}
    @media(min-width:600px){.masonry{columns:2}}
    @media(min-width:900px){.masonry{columns:3}}
    .card{break-inside:avoid;margin:0 0 14px;background:var(--card);border:1px solid rgba(255,255,255,.06);border-radius:18px;overflow:hidden;box-shadow:var(--shadow)}
    .ph-img{width:100%;display:block;aspect-ratio:4/3;background:linear-gradient(180deg,#1C2436,#121827);display:grid;place-items:center;color:#94A3B8;font-size:12px}
    .card-body{padding:14px 16px 16px}

    .footer{margin-top:50px;padding:24px;text-align:center;color:var(--muted);border-top:1px solid rgba(255,255,255,.06);background:linear-gradient(180deg,rgba(20,26,40,.3),rgba(14,17,27,.6))}

    /* Mobile adjustments */
    @media(max-width:980px){
      .frame{grid-template-columns:1fr}
      .qr-badge{position:static;margin-top:16px;display:flex;justify-content:center}
    }
    @media(max-width:680px){
      .navbar{padding:12px 16px}
      .glass{padding:18px}
      .hero-cta .btn{flex:1 1 46%;min-width:140px;padding:12px 14px}
    }
    @media(max-width:400px){
      .hero-cta .btn{flex-basis:100%}
    }

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
    <a href="technician_login.php" class="btn btn-admin outline" aria-label="เข้าสู่ระบบช่างเทคนิค">
        <i class="fas fa-wrench" aria-hidden="true"></i>
        TECHNICIAN
    </a>

    <a href="admin_login.php" class="btn btn-admin" aria-label="เข้าสู่ระบบแอดมิน">
        <i class="fas fa-user-shield" aria-hidden="true"></i>
        👤ADMIN
    </a>
</nav>
  </header>

  <!-- HERO with parallax layers -->
  <section class="hero" aria-label="หน้าแรก">
    <div class="hero-bg"></div>

    <!-- Parallax decorative layers -->
    <img class="layer" data-parallax data-speed="-0.25" src="image/logo2.png" alt="" style="top:10%;left:-4%;width:28vw;max-width:360px;opacity:.35" />
    <img class="layer floating" data-parallax data-speed="0.12" src="image/logo2.png" alt="" style="top:18%;right:-6%;width:30vw;max-width:400px;opacity:.28" />
    <img class="layer" data-parallax data-speed="-0.15" alt="" style="bottom:-6%;left:8%;width:22vw;max-width:300px;opacity:.22" />

    <div class="hero-inner wrap">
      <h1 class="reveal">บริการซ่อมคอมพิวเตอร์ <span>TechFix.it</span></h1>
      <p class="lead reveal" style="transition-delay:.08s">แจ้งซ่อมผ่าน LINE Bot • ติดตามสถานะแบบเรียลไทม์ • รองรับมือถือเต็มรูปแบบ</p>

      <!-- ปุ่ม 2 อัน ข้างกัน -->
      <div class="hero-cta reveal" style="transition-delay:.15s">
        <a href="repair_detail.php" class="btn ghost" aria-label="คิวแจ้งซ่อม">
          <i class="fas fa-clipboard-list" aria-hidden="true"></i> คิวแจ้งซ่อม
          </a>
        <a href="#story" class="btn outline">
          <i class="fas fa-arrow-down" aria-hidden="true"></i> ดูรายละเอียด
        </a>
      </div>
    </div>

    <!-- QR floating badge -->
    <aside class="qr-badge" aria-label="สแกนเพิ่มเพื่อน LINE">
      <div class="qr-card reveal" style="transition-delay:.25s">
        <img src="image/qr.jpg" alt="QR LINE" style="width:120px;aspect-ratio:1/1;border-radius:10px;display:block">
        <small style="color:var(--muted)">@429fxsnw</small>
      </div>
    </aside>
  </section>

<main id="story" class="wrap story" aria-label="ลำดับคุณสมบัติ">

    <section class="frame">
        <div class="glass reveal">
            <h2 style="margin:0 0 8px;font-size:clamp(22px,3.5vw,34px)"><span style="color:var(--brand)">แจ้งซ่อมง่ายๆ</span> ไม่กี่ขั้นตอน</h2>
            <p class="lead">สแกน QR ผ่าน LINE กรอกข้อมูลแจ้งซ่อมเพียงไม่กี่ขั้นตอน <br> ระบบจะรับข้อมูลการแจ้งซ่อมและส่งคิวให้ทันที</p>
            <ul style="margin:12px 0 0;color:var(--muted);line-height:1.9">
                <li>รองรับอุปกรณ์ IT ทุกชนิด</li>
                <li>แจ้งเตือนอัตโนมัติในไลน์</li>
                <li>ติดตามสถานะได้ตลอดเวลา</li>
            </ul>
        </div>
        <div class="reveal">
            <img src="image/how2.png" alt="หน้าจอตัวอย่าง" style="width:100%;height:auto;border-radius:16px">
        </div>
    </section>

    <section class="frame">
        <div class="reveal">
            <img src="image/ai.png" alt="หน้าจอตัวอย่าง" style="width:100%;height:auto;border-radius:16px">
        </div>
        <div class="glass reveal">
            <h2 style="margin:0 0 8px;font-size:clamp(22px,3.5vw,34px)">อัปเดต <span style="color:var(--accent)">เรียลไทม์</span></h2>
            <p class="lead">สถานะ “แจ้งซ่อม → กำลังซ่อม → เสร็จสิ้น” ถูกซิงก์ทั้งหน้าเว็บและ LINE พร้อม timestamp</p>
            <ul style="margin:12px 0 0;color:var(--muted);line-height:1.9">
                <li>การ์ดสรุปงาน + ค้นหาตามสถานะ</li>
                <li>แจ้งเตือนเมื่อมีการเปลี่ยนแปลง</li>
                <li>ส่งการแจ้งเตือนเข้า LINE หลังซ่อมเสร็จ</li>
            </ul>
        </div>
    </section>

    <section class="frame">
        <div class="glass reveal">
            <h2 style="margin:0 0 8px;font-size:clamp(22px,3.5vw,34px)">พร้อมใช้งานทุก <span style="color:var(--brand)">อุปกรณ์</span></h2>
            <p class="lead">Smartphone , PC , Laptop , Tablet</p>
            <ul style="margin:12px 0 0;color:var(--muted);line-height:1.9">
                <li>1 LINE ID แจ้งได้หลายอุปกรณ์ (โครงสร้าง <code>one-to-many</code> + <code>user token</code>)</li>
                <li>ช่วยตรวจคำผิดด้วย AI</li>
                <li>คำนึงถึงความปลอดภัยของข้อมูลส่วนบุคคล</li>
                <li>การเก็บข้อมูลเพื่อพัฒนาความเสถียร</li>
            </ul>
        </div>
        <div class="reveal">
            <img src="image/device.png" alt="หน้าจอตัวอย่าง" style="width:100%;height:auto;border-radius:16px">
        </div>
    </section>

</main>

  <!-- Masonry showcase -->
  <section class="wrap">
  <h3 class="grid-title">ตัวอย่างหมวดอุปกรณ์ที่รับซ่อม</h3>
  <div class="masonry">

    <!-- ปัญหาด้านคอมพิวเตอร์ (4:3) -->
    <article class="card reveal">
      <div class="ph-img" data-parallax data-speed="0.06" style="aspect-ratio:4/3; overflow:hidden; border-radius:16px;">
        <img 
          src="image/9.jpg" 
          alt="ปัญหาด้านคอมพิวเตอร์" 
          loading="lazy"
          style="width:100%; height:100%; object-fit:cover; display:block; transition:transform .4s ease;"
        >
      </div>
      <div class="card-body" style="font-weight:600; text-align:center;">ปัญหาด้านคอมพิวเตอร์</div>
    </article>

    <!-- ปัญหาด้านโน๊ตบุ๊ค (4:5) -->
    <article class="card reveal">
      <div class="ph-img" data-parallax data-speed="-0.04" style="aspect-ratio:4/5; overflow:hidden; border-radius:16px;">
        <img 
          src="image/10.jpg" 
          alt="ปัญหาด้านโน๊ตบุ๊ค" 
          loading="lazy"
          style="width:100%; height:100%; object-fit:cover; display:block; transition:transform .4s ease;"
        >
      </div>
      <div class="card-body" style="font-weight:600; text-align:center;">ปัญหาด้านโน๊ตบุ๊ค</div>
    </article>

    <!-- ปัญหาด้านปริ้นเตอร์ (4:3) -->
    <article class="card reveal">
      <div class="ph-img" data-parallax data-speed="0.08" style="aspect-ratio:4/3; overflow:hidden; border-radius:16px;">
        <img 
          src="image/11.jpg" 
          alt="ปัญหาด้านปริ้นเตอร์" 
          loading="lazy"
          style="width:100%; height:100%; object-fit:cover; display:block; transition:transform .4s ease;"
        >
      </div>
      <div class="card-body" style="font-weight:600; text-align:center;">ปัญหาด้านปริ้นเตอร์</div>
    </article>

    <!-- อุปกรณ์เครือข่าย (4:3) -->
    <article class="card reveal">
      <div class="ph-img" data-parallax data-speed="-0.05" style="aspect-ratio:4/3; overflow:hidden; border-radius:16px;">
        <img 
          src="image/12.jpg" 
          alt="อุปกรณ์เครือข่าย" 
          loading="lazy"
          style="width:100%; height:100%; object-fit:cover; display:block; transition:transform .4s ease;"
        >
      </div>
      <div class="card-body" style="font-weight:600; text-align:center;">อุปกรณ์เครือข่าย</div>
    </article>

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

// ===== ✅ JavaScript (เวอร์ชันอัปเดต) สำหรับเอฟเฟกต์ Sticky Fade ที่ช้าลง =====
function handleStickyFade() {
    const frames = document.querySelectorAll('.story > .frame');
    const story = document.querySelector('.story');
    if (!story || frames.length === 0) return;

    const scrollRect = story.getBoundingClientRect();
    const scrollPercent = Math.max(0, -scrollRect.top) / (story.scrollHeight - window.innerHeight);
    const progressValue = scrollPercent * frames.length;
    const lastFrameIndex = frames.length - 1;

    frames.forEach((frame, index) => {
        const distance = Math.abs(index - progressValue);
        let opacity;

        // เพิ่มเงื่อนไขพิเศษสำหรับ frame สุดท้าย (เหมือนเดิม)
        if (index === lastFrameIndex) {
            // ถ้าเป็น frame สุดท้าย: จะ fade in เข้ามา แต่จะไม่ fade out ออกไป
            opacity = Math.max(0, Math.min(1, 1 - (index - progressValue)));
        } else {
            // ✅ ถ้าเป็น frame อื่นๆ: ใช้ตรรกะใหม่ที่ fade ช้าลง
            // การคำนวณแบบ (1 - distance^2) จะทำให้ opacity คงที่ที่ 1 นานขึ้น
            // ทำให้ section จางหายไปช้ากว่าเดิม
            const adjustedDistance = Math.min(1, distance);
            opacity = Math.max(0, 1 - (adjustedDistance * adjustedDistance));
        }

        frame.style.opacity = opacity;
    });
}

// เรียกใช้ฟังก์ชันนี้เมื่อมีการ scroll (เหมือนเดิม)
window.addEventListener('scroll', handleStickyFade, { passive: true });

// เรียกใช้ครั้งแรกตอนโหลดหน้าเว็บ (เหมือนเดิม)
handleStickyFade();
  </script>
</body>
</html>
