<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = $pageTitle ?? 'Dashboard - Peringatan Dini Banjir';
$bodyClass = $bodyClass ?? 'dashboard-page';
$curr      = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>

  <!-- Favicon -->
  <link rel="icon" href="../assets/img/unbin.png">

  <!-- CSS umum & dashboard -->
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?= time() ?>">

  <!-- Icon & Chart -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">

<!-- ============ SIDEBAR ============ -->
<div class="sidebar">
  <div class="sidebar-logo">
    <img src="../assets/img/unbin.png" alt="Logo" width="40" />
    <span>Peringatan Banjir</span>
  </div>

  <nav>
    <a href="dashboard.php" class="nav-link <?= $curr==='dashboard.php' ? 'active' : '' ?>">
      <i class="fa fa-home"></i> Dashboard
    </a>
    <a href="#data" class="nav-link">
      <i class="fa fa-database"></i> Data Sensor
    </a>
    <a href="#" onclick="openProfileModal();return false;" class="nav-link">
      <i class="fa fa-user-edit"></i> Edit Profil
    </a>
    <a href="logout.php" class="nav-link" onclick="logoutConfirm();return false;">
      <i class="fa fa-sign-out"></i> Logout
    </a>
  </nav>
</div>

<!-- ============ MAIN WRAPPER (dibuka di sini, ditutup di file konten) ============ -->
<div class="main-content">
  <header class="main-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
    <button id="toggleSidebar" class="hamburger" style="display:none;"><i class="fa fa-bars"></i></button>
    <h1 style="margin:0;">Dashboard</h1>
    <div>
      <b id="displayName"><?= ucfirst($_SESSION['username'] ?? 'User') ?></b>
      <span class="role"><?= $_SESSION['role'] ?? '' ?></span>
    </div>
  </header>

<script>
// Toggle sidebar (mobile)
document.getElementById('toggleSidebar')?.addEventListener('click', () => {
  document.querySelector('.sidebar')?.classList.toggle('open');
});

/**
 * Logout dengan dukungan modal custom:
 * - Jika halaman punya showCustomConfirm() -> dipakai untuk konfirmasi.
 * - Jika halaman punya showSuccessModal()   -> tampilkan pesan sukses lalu redirect.
 * - Jika tidak ada keduanya -> fallback confirm() & redirect biasa.
 */
function logoutConfirm(){
  const doLogout = () => {
    fetch('logout.php?ajax=1', { credentials: 'same-origin' })
      .then(r => r.ok ? r.json() : { ok:false })
      .then(() => {
        if (typeof showSuccessModal === 'function') {
          showSuccessModal('Berhasil logout');
          setTimeout(() => { window.location.href = 'login.php'; }, 800);
        } else {
          window.location.href = 'login.php';
        }
      })
      .catch(() => { window.location.href = 'login.php'; });
  };

  if (typeof showCustomConfirm === 'function') {
    showCustomConfirm('Logout sekarang?', doLogout);
  } else if (confirm('Logout sekarang?')) {
    doLogout();
  }
}

// Stub aman jika halaman belum menyediakan modal Edit Profil
function openProfileModal(){
  // Override fungsi ini di halaman yang punya modal sebenarnya
  alert('Form Edit Profil belum tersedia pada halaman ini.');
}
</script>
