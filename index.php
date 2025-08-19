<?php
$pageTitle = 'Peringatan Dini Banjir';
$bodyClass = 'index-page';
include 'header_public.php';
?>

<main class="page-container">
  <!-- HERO -->
  <div class="hero">
    <div class="hero-text">
      <h1>Peringatan <span style="color:#00c6ff;">Dini Banjir</span></h1>
      <p>Pantau ketinggian air otomatis, dapatkan notifikasi Telegram, dan akses dashboard petugas.</p>
    </div>
  </div>

  <!-- STATUS CARDS -->
  <div class="index-status-cards">
    <div class="status-card" id="status-card">
      <div class="status-card-icon"><i class="fa fa-water"></i></div>
      <div>
        <div>Status</div>
        <b id="status-level">Loading...</b>
      </div>
    </div>
    <div class="status-card" id="tinggi-card">
      <div class="status-card-icon"><i class="fa fa-chart-line"></i></div>
      <div>
        <div>Tinggi Air</div>
        <b id="tinggi-terbaru">0</b> <span style="font-size:0.92em;">cm</span>
      </div>
    </div>
    <div class="status-card" id="sensor-card">
      <div class="status-card-icon"><i class="fa fa-microchip"></i></div>
      <div>
        <div>Sensor</div>
        <b id="sensor-status">Memeriksa...</b>
      </div>
    </div>
  </div>

  <!-- CHART -->
  <div class="chart-responsive">
    <canvas id="chartTinggi"></canvas>
  </div>
  <p class="update-label"><span id="last-update">Update: -</span></p>
  <div id="toast"></div>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let statusColorMap = { Normal:'green', Siaga:'orange', Bahaya:'red', Evakuasi:'darkred' };
fetch('includes/status_colors.php').then(r=>r.json()).then(m=>statusColorMap=m).catch(()=>{});
const sc = s => statusColorMap[s] || 'black';

function updatePublicStatus() {
  fetch('get_data.php').then(r=>r.json()).then(data=>{
    if (!Array.isArray(data) || !data.length) return;
    const latest = data[0];
    document.getElementById('tinggi-terbaru').innerText = parseFloat(latest.tinggi).toFixed(2);
    const el = document.getElementById('status-level');
    el.innerText = latest.status; el.style.color = sc(latest.status);
    const ss = document.getElementById('sensor-status');
    ss.innerText='Aktif'; ss.style.color='green';
    document.getElementById('status-card').style.borderColor = sc(latest.status);
    document.getElementById('last-update').innerText = 'Update: ' + latest.waktu;
  }).catch(()=>{
    document.getElementById('status-level').innerText='Gagal load data';
    const ss = document.getElementById('sensor-status');
    ss.innerText='Tidak Aktif'; ss.style.color='red';
  });
}
setInterval(updatePublicStatus, 3000); updatePublicStatus();

const ctx = document.getElementById('chartTinggi').getContext('2d');
let chart = new Chart(ctx, {
  type:'line',
  data:{ labels:[], datasets:[{ label:'Tinggi Air (cm)', data:[], borderWidth:2, borderColor:'blue', backgroundColor:'rgba(0,0,255,0.08)', tension:.35, pointBackgroundColor:[], pointRadius:4, pointHoverRadius:7 }] },
  options:{
    responsive:true, maintainAspectRatio:false, animation:{duration:700,easing:'easeOutQuad'},
    plugins:{ legend:{ labels:{ font:{ size:14 } } }, tooltip:{enabled:true} },
    scales:{ x:{ ticks:{ font:{size:12}, autoSkip:true, maxTicksLimit:window.innerWidth<700?4:10 } }, y:{ beginAtZero:true, ticks:{ font:{size:12} } } }
  }
});
function updateChart(){
  fetch('get_data.php').then(r=>r.json()).then(data=>{
    if(!Array.isArray(data)||!data.length) return;
    chart.data.labels = data.map(i=>i.waktu);
    chart.data.datasets[0].data = data.map(i=>parseFloat(i.tinggi));
    chart.data.datasets[0].pointBackgroundColor = data.map(i=>sc(i.status));
    chart.options.scales.x.maxTicksLimit = window.innerWidth<700?4:10;
    chart.update();
  });
}
setInterval(updateChart, 4000); updateChart();
window.addEventListener('resize', ()=>{ chart.options.scales.x.maxTicksLimit = window.innerWidth<700?4:10; chart.update(); });
</script>
