<?php
/**
 * admin/telegram_notify.php
 * Kirim Telegram saat:
 *  - Sensor dinyalakan / dimatikan
 *  - Status ketinggian air berubah (Normal/Siaga/Bahaya/Evakuasi) pada data BARU
 *
 * Anti-spam:
 *  - Hanya kirim jika waktu data terbaru (data_air.waktu) LEBIH BARU
 *    dari notify_status.updated_at, dan statusnya BERBEDA dengan notify_status.value.
 *  - Fresh boot dikirim sekali (setelah sensor ON), lalu matikan flag.
 *
 * Catatan: Tidak lagi include get_data.php (yang menutup $conn).
 *          Kita query langsung 1 baris terbaru dari data_air.
 */

require_once '../config.php';
require_once '../includes/status_level.php';
require_once '../includes/telegram_helper.php';
date_default_timezone_set('Asia/Jakarta');

// ---- util keluar diam-diam, log ke error_log untuk debug ----
function soft_exit($why=''){ if($why) error_log('[telegram_notify] '.$why); exit; }

// ---- status sensor saat ini ----
$st = $conn->query("SELECT status FROM control_status WHERE id=1");
if(!$st || $st->num_rows===0) soft_exit('control_status kosong');
$currentSensorStatus = $st->fetch_assoc()['status'] ?? 'OFF';

// ---- state notifikasi terakhir ----
$res = $conn->query("SELECT * FROM notify_status WHERE id=1");
if(!$res || $res->num_rows===0) soft_exit('notify_status kosong');
$notify = $res->fetch_assoc();

$last_status        = (string)($notify['value'] ?? '');
$last_sensor_status = (string)($notify['sensor_status'] ?? 'OFF');
$last_notif_time    = strtotime($notify['last_notif_sent_at'] ?? '1970-01-01');
$updated_at_prev_ts = strtotime($notify['updated_at'] ?? '1970-01-01'); // patokan anti-spam
$is_fresh_boot      = (int)($notify['is_fresh_boot'] ?? 0);

// ---- ambil 1 data terbaru langsung dari DB ----
$q = $conn->query("SELECT id, tinggi, status, waktu FROM data_air ORDER BY waktu DESC LIMIT 1");
if(!$q || $q->num_rows===0) soft_exit('data_air kosong');

$latest = $q->fetch_assoc();
if(!isset($latest['tinggi'], $latest['waktu'])) soft_exit('kolom wajib kosong');

$tinggi = (float)$latest['tinggi'];
$waktu  = (string)$latest['waktu'];
$latest_ts = strtotime($waktu);

// fallback status bila null/kosong
$status_now = isset($latest['status']) && $latest['status'] !== null && $latest['status'] !== ''
  ? (string)$latest['status']
  : getStatusLevel($tinggi);

// ---- 1) SENSOR ON ----
if($last_sensor_status==='OFF' && $currentSensorStatus==='ON'){
  @sendTelegramMessage("✅ Sensor telah dinyalakan");
  $conn->query("UPDATE notify_status
                SET sensor_status='ON', is_fresh_boot=1, updated_at=NOW()
                WHERE id=1");
  soft_exit('Notify: sensor ON');
}

// ---- 2) SENSOR OFF ----
if($last_sensor_status==='ON' && $currentSensorStatus==='OFF'){
  @sendTelegramMessage("⚠️ Sensor telah dimatikan");
  $conn->query("UPDATE notify_status
                SET sensor_status='OFF', is_fresh_boot=0, updated_at=NOW()
                WHERE id=1");
  soft_exit('Notify: sensor OFF');
}

// ---- 3) FRESH BOOT ----
// Kirim satu ringkasan ketika sensor baru diaktifkan, hanya jika ada data BARU.
if($is_fresh_boot===1 && $currentSensorStatus==='ON'){
  if($latest_ts > $updated_at_prev_ts){
    $msg = "ℹ️ Status saat sensor aktif:\n".
           "Status: {$status_now}\n".
           "Ketinggian air: {$tinggi} cm\n".
           "Pukul: {$waktu}";
    @sendTelegramMessage($msg);

    $safe_status = $conn->real_escape_string($status_now);
    $safe_waktu  = $conn->real_escape_string($waktu);
    $conn->query("UPDATE notify_status
                  SET value='{$safe_status}',
                      is_fresh_boot=0,
                      updated_at='{$safe_waktu}',
                      last_notif_sent_at=NOW()
                  WHERE id=1");
  }
  soft_exit('Notify: fresh boot (sekali)');
}

// ---- 4) STATUS BERUBAH pada data BARU ----
// Kunci anti-spam: pastikan data terbaru lebih BARU dari catatan sebelumnya
// dan status memang BERUBAH. Debounce 2 detik antar kirim.
if($latest_ts > $updated_at_prev_ts && $status_now !== $last_status){
  $now = time();
  if(($now - $last_notif_time) >= 2){
    $msg = ($status_now==='Normal')
      ? "✅ Status kembali Normal.\nKetinggian air: {$tinggi} cm (pukul {$waktu})."
      : "⚠️ Peringatan Banjir: Status {$status_now}!\nKetinggian air: {$tinggi} cm (pukul {$waktu}).";

    @sendTelegramMessage($msg);

    $safe_status = $conn->real_escape_string($status_now);
    $safe_waktu  = $conn->real_escape_string($waktu);
    $conn->query("UPDATE notify_status
                  SET value='{$safe_status}',
                      updated_at='{$safe_waktu}',
                      last_notif_sent_at=NOW(),
                      is_fresh_boot=0
                  WHERE id=1");
    soft_exit('Notify: status berubah → '.$status_now);
  } else {
    soft_exit('Debounce: jeda notifikasi terlalu cepat');
  }
}

// Tidak ada kondisi terpenuhi (status sama / belum ada data lebih baru).
soft_exit('No change');
