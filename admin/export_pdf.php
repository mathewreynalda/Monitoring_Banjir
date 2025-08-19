<?php
/**
 * export_pdf.php
 * Generate PDF dengan Dompdf + footer canvas (halaman 1 ikut), filter tanggal & status
 */

session_start();
date_default_timezone_set('Asia/Jakarta');

// ------------ Debug hanya saat pengembangan (MATIKAN di production) ------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

// ------------ Helper NULL-safe htmlspecialchars ------------
function h($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

// ------------ Resolve path aman ------------
$base = __DIR__;
$auto = $base . '/../vendor/autoload.php';
$conf = $base . '/../config.php';
$stat = $base . '/../includes/status_level.php';

if (!file_exists($auto)) $auto = $base . '/vendor/autoload.php';
if (!file_exists($conf)) $conf = $base . '/config.php';
if (!file_exists($stat)) $stat = $base . '/includes/status_level.php';

foreach ([$auto, $conf, $stat] as $f) {
    if (!file_exists($f)) {
        http_response_code(500);
        die("File hilang: " . h($f));
    }
}

require_once $auto;
require_once $conf;
require_once $stat;

if (!isset($conn)) {
    http_response_code(500);
    die('Koneksi database ($conn) tidak tersedia. Cek config.php');
}

// ------------ Dompdf ------------
use Dompdf\Dompdf;
use Dompdf\Options;

// ------------ Input & validasi ------------
$start_date    = $_GET['start']  ?? '';
$end_date      = $_GET['end']    ?? '';
$filter_status = $_GET['status'] ?? '';

if ($start_date === '' || $end_date === '') {
    http_response_code(400);
    die('Rentang tanggal tidak valid!');
}
if (strtotime($start_date) > strtotime($end_date)) {
    http_response_code(400);
    die('Start date harus <= end date');
}

$user     = ucfirst(strtolower($_SESSION['username'] ?? 'Guest'));
$tglCetak = date('d-m-Y H:i');

// ------------ Query (prepared statement) ------------
$where  = " WHERE DATE(waktu) BETWEEN ? AND ? ";
$params = [$start_date, $end_date];
$types  = "ss";

if ($filter_status !== '') {
    $where   .= " AND status = ? ";
    $params[] = $filter_status;
    $types   .= "s";
}

$sql  = "SELECT id, tinggi, status, waktu FROM data_air {$where} ORDER BY waktu DESC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    die('Gagal prepare statement: ' . h($conn->error));
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ------------ HTML tabel ------------
$html = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    @page { margin: 20mm 15mm; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; margin-bottom: 18mm; }
    h2 { text-align: center; margin: 0 0 6px 0; }
    p  { text-align: center; margin: 0 0 10px 0; }
    table { border-collapse: collapse; width: 100%; margin-top: 10px; }
    thead { background: #f2f2f2; }
    th, td { border: 1px solid #999; padding: 6px 8px; text-align: center; }
    .status-Normal   { color: green;   font-weight: bold; }
    .status-Siaga    { color: orange;  font-weight: bold; }
    .status-Bahaya   { color: red;     font-weight: bold; }
    .status-Evakuasi { color: darkred; font-weight: bold; }
  </style>
</head>
<body>

<h2>Data Ketinggian Air</h2>
<p>
  Dari <b>' . h($start_date) . '</b> sampai <b>' . h($end_date) . '</b>' .
  ($filter_status !== '' ? ' | Status: <b>' . h($filter_status) . '</b>' : '') .
'</p>

<table>
  <thead>
    <tr>
      <th>ID</th><th>Tinggi (cm)</th><th>Status</th><th>Waktu</th>
    </tr>
  </thead>
  <tbody>';

if ($result && $result->num_rows > 0) {
    $allowStatus = ['Normal','Siaga','Bahaya','Evakuasi'];
    while ($row = $result->fetch_assoc()) {
        $statusClass = 'status-' . (in_array($row['status'], $allowStatus, true) ? $row['status'] : 'Normal');
        $html .= "<tr>
            <td>".h($row['id'])."</td>
            <td>".h($row['tinggi'])."</td>
            <td class='{$statusClass}'>".h($row['status'])."</td>
            <td>".h($row['waktu'])."</td>
        </tr>";
    }
} else {
    $html .= '<tr><td colspan="4">Tidak ada data.</td></tr>';
}

$html .= '
  </tbody>
</table>

</body>
</html>';

$stmt->close();

// ------------ Render Dompdf ------------
// Bersihkan buffer & matikan error output biar PDF tidak corrupt
if (ob_get_length()) ob_end_clean();
ini_set('display_errors', 0);
error_reporting(0);

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$options->setIsRemoteEnabled(true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// ------------ Footer via Canvas (versi lama & baru) ------------
$mm = 2.83465;
$leftMargin  = 15 * $mm;
$rightMargin = 15 * $mm;
$bottomPad   = 12 * $mm;

$canvas      = $dompdf->get_canvas();
$fontMetrics = $dompdf->getFontMetrics();
$font        = $fontMetrics->get_font('helvetica', '');
$sizeLeft    = 9.5;
$sizeRight   = 10;

$w = $canvas->get_width();
$h = $canvas->get_height();

$yLine = $h - ($bottomPad + 10);
$lineThickness = 0.8;
$lineWidth     = $w - $leftMargin - $rightMargin;

$ref = new ReflectionMethod($canvas, 'rectangle');
if ($ref->getNumberOfParameters() >= 6) {
    $canvas->rectangle($leftMargin, $yLine - ($lineThickness/2), $lineWidth, $lineThickness, 0, [0,0,0]);
} else {
    $canvas->rectangle($leftMargin, $yLine - ($lineThickness/2), $lineWidth, $lineThickness, 'F');
}

$leftText1 = "Dicetak oleh: {$user} | {$tglCetak}";
$leftText2 = "© " . date('Y') . " Mathew Reynalda Rusli - Sistem Peringatan Dini Banjir";
$canvas->page_text($leftMargin, $yLine + 6,  $leftText1, $font, $sizeLeft, [0,0,0]);
$canvas->page_text($leftMargin, $yLine + 18, $leftText2, $font, $sizeLeft, [0,0,0]);

$rightText  = "Halaman {PAGE_NUM} / {PAGE_COUNT}";
$textWidth  = $fontMetrics->getTextWidth($rightText, $font, $sizeRight);
$xRight     = $w - $rightMargin - $textWidth;
$yRight     = $yLine + 12;
$canvas->page_text($xRight, $yRight, $rightText, $font, $sizeRight, [0,0,0]);

// ------------ Output PDF dengan nama file sesuai tanggal export ------------
$filename = 'data_ketinggian_air_' . date('Y-m-d_H-i') . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]);
exit;
