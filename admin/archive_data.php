<?php
/**
 * admin/archive_data.php
 * Pindahkan data lama dari u944297177_iotflood.data_air (DB utama) ke u944297177_flood_archive.data_air_archive (DB arsip).
 * - 2 koneksi: $conn (utama) dari config.php, dan $conn_arch (arsip).
 * - Status selalu dihitung ulang via getStatusLevel().
 * - Ber-batch untuk dataset besar.
 *
 * Pakai:
 *   Browser: /admin/archive_data.php?days=30
 *   CLI    : php admin/archive_data.php 30
 */

session_start();
require_once __DIR__ . '/../config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(120);          // jaga-jaga jika datanya banyak
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set('Asia/Jakarta');
$now = date('Y-m-d H:i:s');

// Muat fungsi getStatusLevel()
$levelHelper = __DIR__ . '/../includes/status_level.php';
if (file_exists($levelHelper)) require_once $levelHelper;
if (!function_exists('getStatusLevel')) {
  die("Fungsi getStatusLevel() tidak ditemukan. Pastikan includes/status_level.php ada.");
}

// Validasi koneksi & nama DB dari config.php
if (!isset($conn) || !($conn instanceof mysqli)) {
  die("config.php tidak menyediakan \$conn (mysqli) ke DB utama.");
}
if (!isset($db_main, $host_arch, $user_arch, $pass_arch, $db_arch)) {
  die("config.php harus punya \$db_main, \$host_arch, \$user_arch, \$pass_arch, \$db_arch.");
}

// Buat koneksi ke DB arsip
$conn_arch = new mysqli($host_arch, $user_arch, $pass_arch, $db_arch);
if ($conn_arch->connect_error) die("Koneksi gagal (archive): " . $conn_arch->connect_error);

$conn->set_charset('utf8mb4');
$conn_arch->set_charset('utf8mb4');

// Parameter hari
$days = 30;
if (PHP_SAPI === 'cli' && isset($argv[1])) $days = (int)$argv[1];
if (PHP_SAPI !== 'cli' && isset($_GET['days'])) $days = (int)$_GET['days'];
if ($days < 1 || $days > 365) die("Rentang hari tidak valid. Harus 1..365");

// Pastikan tabel arsip ada (di DB arsip)
$sqlCreateArchive = "
  CREATE TABLE IF NOT EXISTS `data_air_archive` (
    `id` BIGINT UNSIGNED NOT NULL,
    `tinggi` DECIMAL(10,2) NOT NULL,
    `status` VARCHAR(20) DEFAULT NULL,
    `waktu` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_waktu` (`waktu`),
    KEY `idx_status` (`status`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if (!$conn_arch->query($sqlCreateArchive)) {
  die("Gagal membuat/cek tabel arsip: " . $conn_arch->error);
}

// Hitung kandidat (info)
$kandidat = 0;
$sqlCount = sprintf(
  "SELECT COUNT(*) c FROM `%s`.`data_air` WHERE waktu < (NOW() - INTERVAL %d DAY)",
  $conn->real_escape_string($db_main), $days
);
if ($rs = $conn->query($sqlCount)) { $kandidat = (int)$rs->fetch_assoc()['c']; $rs->free(); }

// Siapkan prepared statements
$insArch = $conn_arch->prepare("
  INSERT INTO `data_air_archive` (id, tinggi, status, waktu)
  VALUES (?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    tinggi=VALUES(tinggi), status=VALUES(status), waktu=VALUES(waktu)
");
if (!$insArch) die("Prepare INSERT arsip gagal: " . $conn_arch->error);

// Untuk hapus dari DB utama (per-chunk id)
$delTpl = "DELETE FROM `%s`.`data_air` WHERE id IN (%s)";

// Loop ber-batch dari DB utama
$BATCH    = 2000;   // ambil 2000 baris per iterasi
$DEL_CHK  = 1000;   // hapus id per 1000
$lastId   = 0;
$moved    = 0;
$deleted  = 0;

try {
  while (true) {
    // Ambil batch kandidat dari DB utama
    $sqlBatch = sprintf(
      "SELECT id, tinggi, status, waktu
       FROM `%s`.`data_air`
       WHERE waktu < (NOW() - INTERVAL %d DAY) AND id > %d
       ORDER BY id ASC
       LIMIT %d",
      $conn->real_escape_string($db_main), $days, $lastId, $BATCH
    );
    if (!$res = $conn->query($sqlBatch, MYSQLI_USE_RESULT)) {
      throw new Exception("Query batch (main) gagal: " . $conn->error);
    }

    $idsToDelete = [];
    $rowsThis = 0;

    // Mulai transaksi kecil di DB arsip (untuk batch ini)
    $conn_arch->begin_transaction();
    try {
      while ($row = $res->fetch_assoc()) {
        $rowsThis++;
        $id     = (int)$row['id'];
        $tinggi = (float)$row['tinggi'];
        $waktu  = $row['waktu'];

        // Selalu sinkron status dengan getStatusLevel()
        $status = getStatusLevel($tinggi);

        $insArch->bind_param('idss', $id, $tinggi, $status, $waktu);
        if (!$insArch->execute()) throw new Exception("Insert arsip gagal: " . $insArch->error);

        $idsToDelete[] = $id;
        $lastId = $id;
      }
      $res->free();

      // Commit insert batch ke DB arsip
      $conn_arch->commit();

    } catch (Throwable $e) {
      $conn_arch->rollback();
      if (isset($res) && $res instanceof mysqli_result) $res->free();
      throw $e;
    }

    if ($rowsThis === 0) break; // selesai

    // Hapus baris yang sudah dipindahkan dari DB utama (per chunk)
    foreach (array_chunk($idsToDelete, $DEL_CHK) as $ck) {
      $placeholders = implode(',', array_fill(0, count($ck), '?'));
      $sqlDel = sprintf($delTpl, $conn->real_escape_string($db_main), $placeholders);
      $stmtDel = $conn->prepare($sqlDel);
      if (!$stmtDel) throw new Exception("Prepare DELETE gagal: " . $conn->error);

      $types = str_repeat('i', count($ck));
      $stmtDel->bind_param($types, ...$ck);
      if (!$stmtDel->execute()) throw new Exception("DELETE gagal: " . $stmtDel->error);

      $deleted += $stmtDel->affected_rows;
      $stmtDel->close();
    }

    $moved += $rowsThis;
  }

  // Catat log di DB utama (jika tabel ada)
  $user = isset($_SESSION['username']) ? ucfirst(strtolower($_SESSION['username'])) : 'SYSTEM';
  $desc = sprintf("Arsip > %d hari ke %s.data_air_archive (kandidat:%d, moved:%d, deleted:%d)",
                  $days, $db_arch, $kandidat, $moved, $deleted);
  $stmtLog = $conn->prepare(sprintf(
    "INSERT INTO `%s`.`user_logs` (user, activity, log_time) VALUES (?, ?, ?)",
    $conn->real_escape_string($db_main)
  ));
  if ($stmtLog) {
    $stmtLog->bind_param('sss', $user, $desc, $now);
    $stmtLog->execute();
    $stmtLog->close();
  }

  // Output
  $msg = "✅ Arsip sukses. Kandidat:$kandidat | Dipindah:$moved | Dihapus:$deleted | Cutoff:$days hari | Target: {$db_arch}.data_air_archive";
  if (PHP_SAPI === 'cli') {
    echo $msg . PHP_EOL;
  } else {
    if (isset($_SESSION['username'])) {
      header("Location: dashboard.php?message=arsip_sukses");
      exit;
    } else {
      echo $msg;
    }
  }

} catch (Throwable $e) {
  $err = "❌ Gagal arsip: " . $e->getMessage();
  if (PHP_SAPI === 'cli') fwrite(STDERR, $err . PHP_EOL); else echo $err;
} finally {
  if (isset($insArch) && $insArch instanceof mysqli_stmt) $insArch->close();
  if (isset($conn_arch) && $conn_arch instanceof mysqli) $conn_arch->close();
}
