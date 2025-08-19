<?php
// ========= BOOTSTRAP =========
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/status_level.php';
date_default_timezone_set('Asia/Jakarta');

// (Opsional) pastikan user login
if (!isset($_SESSION['username'])) {
  header('Location: ../login.php');
  exit;
}

// Util keluaran JSON
function json_out($arr, $code = 200) {
  if (!headers_sent()) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode($arr);
  exit;
}

// Pastikan koneksi tersedia
if (!isset($conn) || !($conn instanceof mysqli)) {
  json_out(['ok'=>false, 'msg'=>'Koneksi database tidak tersedia'], 500);
}

// ========= ENDPOINTS AJAX (HARUS DI ATAS SEBELUM OUTPUT HTML) =========

// GET: notifikasi telegram terakhir
if (isset($_GET['action']) && $_GET['action'] === 'get_last_telegram') {
  $res = $conn->query("SELECT value, updated_at FROM notify_status WHERE id = 1");
  if ($res && $row = $res->fetch_assoc()) {
    $statusV = $row['value'];
    $time    = $row['updated_at'];

    if ($statusV === 'Sensor Mati') {
      $msg = "🚨 Sensor tidak aktif sejak pukul " . date('H:i', strtotime($time)) . "!";
    } elseif ($statusV === 'Normal') {
      $msg = "✅ Status kembali Normal.";
    } elseif ($statusV === 'Sensor kembali aktif') {
      $msg = "✅ Sensor kembali aktif pada pukul " . date('H:i', strtotime($time)) . ".";
    } else {
      $msg = "⚠️ Peringatan Banjir: Status {$statusV}!";
    }
    json_out(['ok'=>true, 'msg'=>"Kirim Telegram: $msg", 'time'=>$time]);
  }
  json_out(['ok'=>false, 'msg'=>'Belum ada notifikasi peringatan.', 'time'=>'-']);
}

// POST: ubah nama
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_name'], $_POST['new_name'])) {
  $username = $_SESSION['username'] ?? '';
  $newname  = ucfirst(strtolower(trim($_POST['new_name'] ?? '')));
  if ($newname === '') json_out(['ok'=>false, 'msg'=>'Nama tidak boleh kosong'], 400);

  $stmt = $conn->prepare("SELECT username FROM users WHERE LOWER(username)=LOWER(?) AND username!=?");
  $stmt->bind_param("ss", $newname, $username);
  $stmt->execute();
  $cek = $stmt->get_result();
  if ($cek && $cek->num_rows > 0) json_out(['ok'=>false, 'msg'=>'Nama sudah digunakan'], 400);

  $stmt2 = $conn->prepare("UPDATE users SET username=? WHERE username=?");
  $stmt2->bind_param("ss", $newname, $username);
  if ($stmt2->execute()) {
    $_SESSION['username'] = $newname;
    $log = $conn->prepare("INSERT INTO user_logs (user, activity, log_time) VALUES (?, 'Ubah nama user', NOW())");
    $log->bind_param('s', $newname);
    $log->execute();
    json_out(['ok'=>true, 'msg'=>'Nama berhasil diubah']);
  }
  json_out(['ok'=>false, 'msg'=>'Gagal mengubah nama'], 500);
}

// POST: ganti password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pass'], $_POST['old_pass'], $_POST['new_pass'])) {
  $username = $_SESSION['username'] ?? '';
  $old = (string)($_POST['old_pass'] ?? '');
  $new = (string)($_POST['new_pass'] ?? '');
  if (strlen($new) < 4) json_out(['ok'=>false, 'msg'=>'Password minimal 4 karakter'], 400);

  $stmt = $conn->prepare("SELECT password FROM users WHERE username=?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res && $row = $res->fetch_assoc()) {
    if (!password_verify($old, $row['password'])) {
      json_out(['ok'=>false, 'msg'=>'Password lama salah'], 400);
    }
    $hashed = password_hash($new, PASSWORD_DEFAULT);
    $stmt2 = $conn->prepare("UPDATE users SET password=? WHERE username=?");
    $stmt2->bind_param("ss", $hashed, $username);
    if ($stmt2->execute()) {
      $log = $conn->prepare("INSERT INTO user_logs (user, activity, log_time) VALUES (?, 'Ganti password', NOW())");
      $log->bind_param('s', $username);
      $log->execute();
      json_out(['ok'=>true, 'msg'=>'Password berhasil diubah']);
    }
    json_out(['ok'=>false, 'msg'=>'Gagal mengubah password'], 500);
  }
  json_out(['ok'=>false, 'msg'=>'Pengguna tidak ditemukan'], 400);
}

// ====== DATA HALAMAN (sesudah endpoint) ======
$stRow = $conn->query("SELECT status FROM control_status WHERE id=1");
$sensorStatus = ($stRow && $stRow->num_rows > 0) ? $stRow->fetch_assoc()['status'] : 'OFF';

// log admin (10 terbaru)
$logs = null;
if (($_SESSION['role'] ?? '') === 'admin') {
  $logs = $conn->query("SELECT * FROM user_logs ORDER BY log_time DESC LIMIT 10");
}

// HEADER (berisi <html>, sidebar, buka .main-content, dsb.)
include __DIR__ . '/header_admin.php';
?>
<!-- ======= DASHBOARD ======= -->
<div class="dashboard-cards">
  <div class="status-card" id="status-card">
    <div class="status-card-icon"><i class="fa fa-bell"></i></div>
    <div><div>Status</div><b id="status-level">Loading...</b></div>
  </div>

  <div class="status-card" id="tinggi-card">
    <div class="status-card-icon"><i class="fa fa-water"></i></div>
    <div><div>Tinggi Air</div><b id="tinggi-terbaru">0</b> <span style="font-size:0.92em;">cm</span></div>
  </div>

  <div class="status-card" id="sensor-card">
    <div class="status-card-icon"><i class="fa fa-microchip"></i></div>
    <div>
      <div>Sensor</div>
      <b id="sensor-status" style="color:<?= ($sensorStatus==='ON' ? 'green' : 'red') ?>;">
        <?= $sensorStatus === 'ON' ? 'Aktif' : 'Tidak Aktif' ?>
      </b>
      <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
      <div id="sensor-controls" style="margin-top:8px;display:flex;gap:8px;">
        <button id="btnOn"  class="btn-action" style="display:none;">ON</button>
        <button id="btnOff" class="btn-action" style="display:none;background:#ff4d4d;">OFF</button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ======= CHART ======= -->
<div class="chart-container">
  <canvas id="chartTinggi"></canvas>
</div>

<!-- ======= DATA SENSOR ======= -->
<section id="data">
  <div class="export-section">
    <label for="start_date">Dari:</label>
    <input type="date" id="start_date">
    <label for="end_date">Sampai:</label>
    <input type="date" id="end_date">
    <button onclick="exportData('excel')" class="btn-action"><i class="fa fa-file-excel"></i> Export Excel</button>
    <button onclick="exportData('pdf')" class="btn-action"><i class="fa fa-file-pdf"></i> Export PDF</button>

    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
      <select id="daysRange">
        <option value="1">1 hari</option>
        <option value="7">7 hari</option>
        <option value="14">14 hari</option>
        <option value="30" selected>30 hari</option>
      </select>
      <button onclick="showPopupArchive()" class="btn-action btn-archive"><i class="fa fa-archive"></i> Arsipkan</button>
    <?php endif; ?>
  </div>

  <div class="responsive-table">
    <table>
      <thead><tr><th>ID</th><th>Tinggi</th><th>Status</th><th>Waktu</th></tr></thead>
      <tbody id="sensor-table"></tbody>
    </table>
  </div>
</section>

<!-- ======= TELEGRAM GRID ======= -->
<section class="telegram-grid">
  <div class="tg-card">
    <h2>Notifikasi Telegram Terakhir</h2>
    <p class="tg-meta" id="last-telegram-msg" style="color:#333;">Memuat data...</p>
    <p class="tg-meta"><small>Waktu: <span id="last-telegram-time">-</span></small></p>
  </div>

  <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
  <div class="tg-card">
    <h2>Kirim Notifikasi Telegram</h2>
    <form class="telegram-form tg-form" id="tgForm">
      <input type="text" id="tgMessage" name="telegram_msg" placeholder="Isi pesan Telegram..." required>
      <button type="submit" class="btn-action">Kirim</button>
    </form>
    <p id="tgResult" class="tg-meta"></p>
  </div>
  <?php endif; ?>
</section>

<!-- ======= LOG (Admin) ======= -->
<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
<section style="margin-top:24px;">
  <h2>Log Aktivitas</h2>
  <div class="log-activity" style="max-height:150px;overflow-y:auto;">
    <ul id="log-list" style="font-size:0.97em;">
      <?php if ($logs) while ($log = $logs->fetch_assoc()) {
        $log_user = ucfirst(strtolower($log['user']));
        echo "<li>{$log['log_time']} - {$log_user}: {$log['activity']}</li>";
      } ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<!-- ======= MODAL EDIT PROFIL ======= -->
<div id="profileModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;">
  <div style="background:white;padding:25px;border-radius:12px;max-width:400px;width:90%;">
    <h2>Edit Profil</h2>

    <form id="formUpdateName" style="margin-bottom:18px;">
      <label>Nama Baru:</label>
      <input type="text" name="new_name" value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>" required>
      <input type="hidden" name="update_name" value="1">
      <button type="submit" class="btn-action">Ubah Nama</button>
    </form>

    <form id="formUpdatePass">
      <label>Password Lama:</label>
      <div style="position:relative;">
        <input type="password" name="old_pass" id="old_pass" required style="width:100%;padding-right:38px;">
        <span onclick="togglePw('old_pass', this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;"><i class="fa fa-eye"></i></span>
      </div>
      <label>Password Baru:</label>
      <div style="position:relative;">
        <input type="password" name="new_pass" id="new_pass" required style="width:100%;padding-right:38px;">
        <span onclick="togglePw('new_pass', this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;"><i class="fa fa-eye"></i></span>
      </div>
      <input type="hidden" name="update_pass" value="1">
      <button type="submit" class="btn-action" style="margin-top:8px;">Ganti Password</button>
    </form>

    <button onclick="closeProfileModal()" class="btn-action" style="background:#bbb;margin-top:12px;">Tutup</button>
  </div>
</div>

<!-- ======= MODAL KONFIRMASI UMUM ======= -->
<div id="customConfirmModal" class="custom-modal">
  <div class="custom-modal-content">
    <p id="customConfirmMessage">Yakin?</p>
    <div class="modal-buttons">
      <button id="confirmYes" class="btn-action">Ya</button>
      <button id="confirmNo"  class="btn-action btn-cancel">Batal</button>
    </div>
  </div>
</div>

<!-- ======= MODAL SUKSES ======= -->
<div id="successModal" class="custom-modal">
  <div class="custom-modal-content">
    <p id="successMessage">Berhasil!</p>
    <div class="modal-buttons">
      <button onclick="closeSuccessModal()" class="btn-action">Tutup</button>
    </div>
  </div>
</div>

<script>
// ===== Util =====
const statusColor = s => ({Normal:'green',Siaga:'orange',Bahaya:'red',Evakuasi:'darkred'})[s] || 'black';
function openProfileModal(){ document.getElementById('profileModal').style.display='flex'; }
function closeProfileModal(){ document.getElementById('profileModal').style.display='none'; }
function togglePw(id, el){ const i=document.getElementById(id); if(!i) return; i.type = i.type==='password'?'text':'password'; el.querySelector('i').classList.toggle('fa-eye'); el.querySelector('i').classList.toggle('fa-eye-slash'); }
function showCustomConfirm(message, cbYes){
  const m=document.getElementById('customConfirmModal'), y=document.getElementById('confirmYes'), n=document.getElementById('confirmNo');
  document.getElementById('customConfirmMessage').innerText = message;
  m.style.display='flex';
  const off=()=>{ y.onclick=null; n.onclick=null; m.style.display='none'; };
  y.onclick=()=>{ try{ cbYes(); }finally{ off(); } };
  n.onclick=off;
}
function showSuccessModal(msg){ document.getElementById('successMessage').innerText=msg; document.getElementById('successModal').style.display='flex'; }
function closeSuccessModal(){ document.getElementById('successModal').style.display='none'; }

// ===== Sensor buttons =====
function refreshSensorButtons(){
  const st=(document.getElementById('sensor-status')?.innerText || '').trim();
  const on=document.getElementById('btnOn'), off=document.getElementById('btnOff');
  if(!on||!off) return;
  if(st==='Aktif'){ on.style.display='none'; off.style.display='inline-block'; }
  else { on.style.display='inline-block'; off.style.display='none'; }
}
document.getElementById('btnOn')?.addEventListener('click', ()=>{
  showCustomConfirm('Yakin ingin mengaktifkan sensor?', ()=>{
    fetch('../control.php?action=ON').then(r=>r.text()).then(msg=>{
      const el=document.getElementById('sensor-status');
      if (el){ el.innerText='Aktif'; el.style.color='green'; }
      refreshSensorButtons(); showSuccessModal(msg||'Sensor diaktifkan');
    });
  });
});
document.getElementById('btnOff')?.addEventListener('click', ()=>{
  showCustomConfirm('Yakin ingin menonaktifkan sensor?', ()=>{
    fetch('../control.php?action=OFF').then(r=>r.text()).then(msg=>{
      const el=document.getElementById('sensor-status');
      if (el){ el.innerText='Tidak Aktif'; el.style.color='red'; }
      refreshSensorButtons(); showSuccessModal(msg||'Sensor dinonaktifkan');
    });
  });
});
refreshSensorButtons();

// ===== Chart =====
(function initChart(){
  const el = document.getElementById('chartTinggi');
  if (!el || !window.Chart) return;
  const ctx = el.getContext('2d');
  window._chartTinggi = new Chart(ctx, {
    type:'line',
    data:{ labels:[], datasets:[{ label:'Tinggi Air (cm)', data:[], pointBackgroundColor:[], borderColor:'blue', backgroundColor:'rgba(0,0,255,0.1)', fill:true, tension:0.3, pointRadius:5, pointHoverRadius:7 }] },
    options:{ responsive:true, scales:{ y:{beginAtZero:true}, x:{ticks:{maxRotation:45,minRotation:45,autoSkip:true}} } }
  });
})();

function updateDashboard(){
  fetch('../get_data.php', {cache:'no-store'})
    .then(r=>r.json())
    .then(data=>{
      if(!Array.isArray(data) || !data.length) return;
      const latest=data[0];
      const tinggiEl=document.getElementById('tinggi-terbaru');
      if (tinggiEl) tinggiEl.innerText=parseFloat(latest.tinggi).toFixed(2);

      const stEl=document.getElementById('status-level');
      if (stEl) {
        stEl.innerText=latest.status;
        stEl.style.color=statusColor(latest.status);
      }
      const card=document.getElementById('status-card');
      if (card) card.style.borderColor=statusColor(latest.status);

      // table
      let html='';
      data.slice(0,20).forEach(it=>{
        const cls={Normal:'status-normal',Siaga:'status-siaga',Bahaya:'status-bahaya',Evakuasi:'status-evakuasi'}[it.status]||'';
        html+=`<tr><td>${it.id}</td><td>${parseFloat(it.tinggi).toFixed(2)}</td><td class="${cls}">${it.status}</td><td>${it.waktu}</td></tr>`;
      });
      const tb=document.getElementById('sensor-table');
      if (tb) tb.innerHTML=html;

      // chart
      if (window._chartTinggi){
        window._chartTinggi.data.labels=data.map(d=>d.waktu);
        window._chartTinggi.data.datasets[0].data=data.map(d=>parseFloat(d.tinggi));
        window._chartTinggi.data.datasets[0].pointBackgroundColor=data.map(d=>statusColor(d.status));
        window._chartTinggi.update();
      }
    }).catch(()=>{});
}
setInterval(updateDashboard, 5000); updateDashboard();

// ===== Last Telegram =====
function updateLastTelegram(){
  fetch('dashboard.php?action=get_last_telegram', {cache:'no-store'})
    .then(r=>r.json())
    .then(d=>{
      const m=document.getElementById('last-telegram-msg');
      const t=document.getElementById('last-telegram-time');
      if (m) m.innerText=d.msg||'-';
      if (t) t.innerText=d.time||'-';
    }).catch(()=>{});
}
setInterval(updateLastTelegram, 5000); updateLastTelegram();

// ===== Kirim Telegram (AJAX) — endpoint terpisah =====
document.getElementById('tgForm')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const inp=document.getElementById('tgMessage');
  const msg=inp?.value.trim();
  if(!msg) return;

  const res = await fetch('../includes/telegram_send.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
    body: new URLSearchParams({ message: msg }).toString()
  });

  let info='Gagal mengirim pesan.';
  try{
    const json=await res.json();
    info = json.success ? 'Pesan dikirim.' : (json.error || info);
  }catch(_){}

  const out=document.getElementById('tgResult');
  if (out) out.innerText = info;
  if(res.ok && inp){ inp.value=''; updateLastTelegram(); }
});

// ===== Export & Arsip =====
function exportData(t){
  const s=document.getElementById('start_date')?.value, e=document.getElementById('end_date')?.value;
  if(!s||!e){ showSuccessModal('Tanggal mulai dan akhir harus diisi.'); return; }
  if (new Date(s) > new Date(e)) { showSuccessModal('Rentang tanggal tidak valid.'); return; }
  window.location.href=`export_${t}.php?start=${encodeURIComponent(s)}&end=${encodeURIComponent(e)}`;
}

function showPopupArchive(){
  const d=document.getElementById('daysRange')?.value || 30;
  showCustomConfirm(`Yakin ingin mengarsipkan data lebih dari ${d} hari lalu?`, ()=>{
    window.location.href=`archive_data.php?days=${encodeURIComponent(d)}`;
  });
}

// ===== Edit Profil via fetch (tanpa reload) =====
document.getElementById('formUpdateName')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd=new FormData(e.target);
  const res=await fetch('dashboard.php',{method:'POST',body:fd});
  const data=await res.json().catch(()=>({ok:false,msg:'Gagal'}));
  showSuccessModal(data.msg || (data.ok?'Berhasil':'Gagal'));
});

document.getElementById('formUpdatePass')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd=new FormData(e.target);
  const res=await fetch('dashboard.php',{method:'POST',body:fd});
  const data=await res.json().catch(()=>({ok:false,msg:'Gagal'}));
  showSuccessModal(data.msg || (data.ok?'Berhasil':'Gagal'));
});

// === PING pengirim Telegram otomatis (status berubah) ===
setInterval(() => {
  fetch('telegram_notify.php?src=dashboard', { cache: 'no-store' })
    .catch(() => {});
}, 4000);
</script>

</div><!-- /main-content -->
<?php include __DIR__ . '/../footer.php'; ?>
