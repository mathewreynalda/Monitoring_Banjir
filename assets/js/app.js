// Chart.js initialization
const ctx = document.getElementById('chartTinggi').getContext('2d');
let chart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: [],
    datasets: [{
      label: 'Tinggi Air (cm)',
      data: [],
      borderWidth: 2,
      borderColor: 'blue',
      backgroundColor: 'rgba(0,0,255,0.08)',
      tension: 0.33,
      pointBackgroundColor: [],
      pointRadius: 4,
      pointHoverRadius: 7,
      fill: true
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 800, easing: 'easeOutQuad' },
    plugins: {
      legend: { labels: { font: { size: 14 } } },
      tooltip: { enabled: true }
    },
    scales: {
      x: {
        ticks: {
          font: { size: 12 },
          autoSkip: true,
          maxTicksLimit: window.innerWidth < 700 ? 4 : 10,
        }
      },
      y: {
        beginAtZero: true,
        ticks: { font: { size: 12 } }
      }
    }
  }
});

// Fungsi update dashboard (card, tabel, chart)
function updateDashboard() {
  fetch('get_data.php')
    .then(res => res.json())
    .then(data => {
      if (!Array.isArray(data) || data.length === 0) return;

      // Ambil labels, tinggi air, status
      const labels = data.map(item => item.waktu);
      const tinggi = data.map(item => parseFloat(item.tinggi));
      const status = data.map(item => item.status);

      // Array warna titik berdasarkan status
      const pointColors = status.map(getStatusColor);

      // Update Chart
      chart.data.labels = labels;
      chart.data.datasets[0].data = tinggi;
      chart.data.datasets[0].pointBackgroundColor = pointColors;
      chart.options.scales.x.maxTicksLimit = window.innerWidth < 700 ? 4 : 10;
      chart.update();

      // Card Status: update angka & status warna
      const latest = data[0];
      const latestTinggi = parseFloat(latest.tinggi) || 0;
      document.getElementById('tinggi-terbaru').innerText = latestTinggi.toFixed(2);
      document.getElementById('status-level').innerText = latest.status;
      document.getElementById('status-level').style.color = getStatusColor(latest.status);
      document.getElementById('status-card').style.borderColor = getStatusColor(latest.status);

      // Update tabel sensor (jika ada #sensor-table)
      if (document.getElementById('sensor-table')) {
        let table = "";
        data.slice(0, 20).forEach(item => {
          let statusClass = "";
          switch(item.status) {
            case "Normal": statusClass = "status-normal"; break;
            case "Siaga": statusClass = "status-siaga"; break;
            case "Bahaya": statusClass = "status-bahaya"; break;
            case "Evakuasi": statusClass = "status-evakuasi"; break;
          }
          table += `<tr>
            <td>${item.id !== undefined ? item.id : '-'}</td>
            <td>${parseFloat(item.tinggi).toFixed(2)}</td>
            <td class="${statusClass}">${item.status}</td>
            <td>${item.waktu}</td>
          </tr>`;
        });
        document.getElementById('sensor-table').innerHTML = table;
      }

      // Update card sensor ON/OFF jika ada #sensor-status
      if (document.getElementById('sensor-status')) {
        document.getElementById('sensor-status').innerText =
          latest.sensor !== undefined ? (latest.sensor === "ON" ? "Aktif" : "Tidak Aktif") : "Aktif";
        document.getElementById('sensor-status').style.color =
          latest.sensor !== undefined ? (latest.sensor === "ON" ? "green" : "red") : "green";
      }
    })
    .catch(error => {
      console.error("Fetch error:", error);
      // Jika ingin tampilkan error di card
      document.getElementById('status-level').innerText = 'Gagal load data';
      document.getElementById('status-level').style.color = 'gray';
      document.getElementById('tinggi-terbaru').innerText = '-';
    });
}

// Fungsi ambil warna berdasarkan status
function getStatusColor(status) {
  switch (status) {
    case "Normal": return "green";
    case "Siaga": return "orange";
    case "Bahaya": return "red";
    case "Evakuasi": return "darkred";
    default: return "blue";
  }
}

// Responsive: update chart jika resize (autoSkip label)
window.addEventListener('resize', () => {
  chart.options.scales.x.maxTicksLimit = window.innerWidth < 700 ? 4 : 10;
  chart.update();
});

// Interval update (tiap 2 detik)
setInterval(updateDashboard, 2000);
updateDashboard();
