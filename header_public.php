<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = $pageTitle ?? 'Peringatan Dini Banjir';
$bodyClass = $bodyClass ?? 'index-page';
$curr      = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" href="assets/img/unbin.png">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/public.css?v=<?= time() ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">

<!-- HEADER (top bar) -->
<header class="header">
  <a href="index.php" class="logo">
    <img src="assets/img/unbin.png" alt="Logo" />
    <span class="sitename">Peringatan Dini Banjir</span>
  </a>

  <button class="hamburger" id="menu-toggle" aria-label="Buka Menu">&#9776;</button>

  <nav class="navmenu" id="main-menu">
    <a href="index.php"  class="nav-link<?= $curr==='index.php'  ? ' active' : '' ?>">Beranda</a>
    <a href="banjir.php" class="nav-link<?= $curr==='banjir.php' ? ' active' : '' ?>">Banjir</a>
    <?php if (!empty($_SESSION['logged_in'])): ?>
      <a href="admin/dashboard.php" class="btn-getstarted">Dashboard</a>
      <a href="admin/logout.php" class="btn-getstarted" style="background:#ff4d4d;color:#fff;">Logout</a>
    <?php else: ?>
      <a href="admin/login.php" class="btn-getstarted">Login</a>
    <?php endif; ?>
  </nav>
</header>

<script>
// Hamburger menu (public)
document.addEventListener("DOMContentLoaded", function() {
  const menuToggle = document.getElementById('menu-toggle');
  const mainMenu   = document.getElementById('main-menu');
  if (!menuToggle || !mainMenu) return;

  menuToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    mainMenu.classList.toggle('open');
  });

  document.addEventListener('click', function(e){
    if (window.innerWidth <= 650 && !mainMenu.contains(e.target) && e.target !== menuToggle) {
      mainMenu.classList.remove('open');
    }
  });

  window.addEventListener('resize', function() {
    if (window.innerWidth > 650) mainMenu.classList.remove('open');
  });
});
</script>
