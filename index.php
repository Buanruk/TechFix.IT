<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TechFix.it</title>
  <link rel="stylesheet" href="style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
  <div class="container">  
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="logo">TechFix.it</div>
      <ul class="nav-index">
        <!-- ปุ่ม เข้าสู่ระบบแอดมิน -->
        <li>
          <a href="admin_login.php" class="btn-admin">
            <i class="fas fa-user-shield"></i> เข้าสู่ระบบแอดมิน
          </a>
        </li>
        <!-- ปุ่ม รายละเอียดแจ้งซ่อม -->
        <li>
          <a href="repair_detail.php" class="btn-detail">
            <i class="fas fa-wrench"></i> รายละเอียดแจ้งซ่อม
          </a>
        </li>
      </ul>
      <div class="socials">
        <a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
      </div>
    </aside>

    <!-- Main Content -->
    <section class="product-showcase">
      <div class="image">
        <img src="image/newhome.png" alt="TechFix.it Poster" />
      </div>
    </section>
  </div>
</body>
</html>