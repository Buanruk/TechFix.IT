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
</body>
</html>