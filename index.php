<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TechFix.it</title>
  <!-- Fonts & Styles -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="style.css?v=3.0" />

  <!-- Font Awesome (สำหรับไอคอนต่าง ๆ) -->
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>

  <!-- ===== Navigation Bar ===== -->
  <header class="navbar" role="navigation" aria-label="เมนูหลัก">
    <div class="logo" aria-label="TechFix.it">🛠️ TechFix.it</div>

    <nav class="nav-actions">
      <!-- ปุ่มรูปคนไปหน้า admin -->
      <a href="admin_login.php" class="profile-btn avatar" aria-label="เข้าสู่ระบบแอดมิน" title="เข้าสู่ระบบแอดมิน">
        <svg viewBox="0 0 64 64" width="100%" height="100%" aria-hidden="true">
          <circle cx="32" cy="32" r="30" fill="#B7DBF2"/>
          <circle cx="32" cy="26" r="10" fill="#9CA3AF"/>
          <path d="M12 52c4-10 12-16 20-16s16 6 20 16" fill="#9CA3AF"/>
        </svg>
      </a>
    </nav>
  </header>

  <main>
    <!-- ===== Hero Section ===== -->
    <section class="hero" aria-label="แนะนำบริการ">
      <!-- ซ้าย: ข้อความ -->
      <div class="hero-text">
        <h1>บริการซ่อมคอมพิวเตอร์ <span>TechFix.it</span></h1>
        <p>
          แจ้งซ่อมอุปกรณ์ไอทีผ่าน LINE Bot ได้ง่าย ๆ พร้อมระบบแจ้งเตือนอัตโนมัติ
          และสามารถติดตามสถานะงานซ่อมได้แบบเรียลไทม์ สะดวกทั้งในคอมพิวเตอร์และมือถือ
        </p>

        <div class="hero-buttons">
          <a href="repair_detail.php" class="cta" aria-label="ไปหน้ารายละเอียดแจ้งซ่อม">
            <i class="fas fa-clipboard-list" aria-hidden="true"></i>
            <span class="label">รายละเอียดแจ้งซ่อม</span>
          </a>
        </div>
      </div>

      <!-- ขวา: กล่อง QR -->
      <aside class="hero-qr-box" aria-label="สแกนเพิ่ม LINE แจ้งซ่อม">
        <h3>สแกนเพื่อแจ้งซ่อม</h3>
        <img src="image/qr.jpg" alt="QR Code เพิ่มเพื่อน LINE @429fxsnw" loading="lazy" decoding="async" />
        <p>@429fxsnw</p>
      </aside>
    </section>

    <!-- ===== Features ===== -->
    <section class="features" aria-label="คุณสมบัติเด่น">
      <div class="feature">
        <i class="fas fa-tools"></i>
        <h3>ซ่อมครบ จบทุกปัญหา</h3>
        <p>รับทุกอาการ คอมเปิดไม่ติด เครื่องพิมพ์เสีย จอไม่ขึ้นภาพ ฯลฯ</p>
      </div>

      <div class="feature">
        <i class="fas fa-clock"></i>
        <h3>บริการ 24 ชั่วโมง</h3>
        <p>แจ้งซ่อมและติดตามงานได้ทุกที่ ทุกเวลา ตลอด 24 ชั่วโมง</p>
      </div>

      <div class="feature">
        <i class="fas fa-bell"></i>
        <h3>แจ้งเตือนสถานะ</h3>
        <p>อัปเดตสถานะแบบเรียลไทม์ ผ่านหน้าเว็บและไลน์แชท</p>
      </div>
    </section>
  </main>

  <!-- ===== Footer ===== -->
  <footer class="footer">
    <p>© <?php echo date('Y'); ?> TechFix.it — บริการซ่อมอุปกรณ์ไฟฟ้าครบวงจร</p>
  </footer>


 <!-- ===== Live update notice + auto refresh (วางไว้เหนือ </body>) ===== -->
<style>
  .live-notice{
    position: fixed; left: 50%; bottom: 20px; transform: translateX(-50%);
    background: #0b63c8; color: #fff; padding: 10px 14px; border-radius: 12px;
    box-shadow: 0 10px 24px rgba(15,40,80,.25); font-weight: 800;
    display: none; z-index: 2000;
  }
</style>

<div id="liveNotice" class="live-notice" role="status" aria-live="polite">
  มีการอัปเดตใหม่ กำลังโหลดข้อมูล...
</div>

<script>
  // === ตั้งค่าเส้นทางไฟล์ ping ===
  const PING_URL = '/changes_ping.php';          // <-- ถ้าไฟล์อยู่รากเว็บ
  // const PING_URL = '/techfix/changes_ping.php'; // <-- ถ้าไฟล์อยู่ในโฟลเดอร์โปรเจกต์

  const POLL_MS  = 5000;   // ยิงเช็คทุก 5 วินาที
  let lastSig = null;      // เก็บลายเซ็นรอบก่อน

  async function pingChanges() {
    try {
      const res = await fetch(PING_URL, { cache: 'no-store' });
      if (!res.ok) return;
      const j = await res.json();
      if (!j || !j.sig) return;

      if (lastSig === null) {
        // ครั้งแรก: ตั้งต้นด้วยค่าล่าสุด ไม่รีหน้า
        lastSig = j.sig;
        return;
      }

      if (j.sig !== lastSig) {
        // มีการเปลี่ยนแปลง: โชว์แถบแจ้งเตือน แล้วรีเฟรช
        lastSig = j.sig;
        const n = document.getElementById('liveNotice');
        if (n) n.style.display = 'inline-flex';
        setTimeout(() => location.reload(), 800);
      }
    } catch (e) {
      // เงียบ ๆ ไป ไม่ต้องเตือนผู้ใช้
    }
  }

  // ยิงทันทีเมื่อโหลดหน้า / กลับมาโฟกัส / และโพลลิ่งทุก POLL_MS
  let pollTimer = setInterval(pingChanges, POLL_MS);
  window.addEventListener('load', pingChanges);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') pingChanges();
  });
</script>
<!-- ===== End live update ===== -->
 
</body>
</html>