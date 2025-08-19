<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
include_once '../config.php';
include_once '../includes/status_level.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

date_default_timezone_set('Asia/Jakarta');

// Cek login
if (!isset($_SESSION['logged_in'])) {
    die("Akses ditolak!");
}

// Logging aktivitas
$username = ucfirst(strtolower($_SESSION['username']));
$now = date('Y-m-d H:i:s');
$conn->query("INSERT INTO user_logs (user, activity, log_time) VALUES ('$username', 'Export data ke Excel', '$now')");

// Ambil parameter tanggal
$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

$query = "SELECT id, tinggi, status, waktu FROM data_air";
if ($start && $end) {
    $query .= " WHERE DATE(waktu) BETWEEN '$start' AND '$end'";
}
$query .= " ORDER BY waktu DESC";
$result = $conn->query($query);

// Inisialisasi spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Data Sensor Air");

// Judul
$sheet->setCellValue('A1', 'Laporan Data Ketinggian Air');
$sheet->mergeCells('A1:D1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Header tabel
$headers = ['ID', 'Tinggi (cm)', 'Status', 'Waktu'];
$sheet->fromArray($headers, null, 'A3');

// Styling header
$sheet->getStyle('A3:D3')->getFont()->setBold(true);
$sheet->getStyle('A3:D3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A3:D3')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A3:D3')->getFill()->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('E0E0E0');

// Lebar kolom
$sheet->getColumnDimension('A')->setWidth(8);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(25);

// Data isi
$rowNum = 4;
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $id     = $row['id'];
        $tinggi = $row['tinggi'];
        $status = $row['status'] ?: getStatusLevel($tinggi);
        $waktu  = $row['waktu'];

        $sheet->setCellValue("A{$rowNum}", $id);
        $sheet->setCellValue("B{$rowNum}", $tinggi);
        $sheet->setCellValue("C{$rowNum}", $status);
        $sheet->setCellValue("D{$rowNum}", $waktu);

        // Warna teks status
        $statusColor = match ($status) {
            'Normal'   => '228B22',  // Hijau
            'Siaga'    => 'FFA500',  // Oranye
            'Bahaya'   => 'FF0000',  // Merah
            'Evakuasi' => '8B0000',  // Merah Gelap
            default    => '000000',  // Hitam
        };
        $sheet->getStyle("C{$rowNum}")
              ->getFont()
              ->getColor()
              ->setRGB($statusColor);

        // Border tiap baris
        $sheet->getStyle("A{$rowNum}:D{$rowNum}")
              ->getBorders()
              ->getAllBorders()
              ->setBorderStyle(Border::BORDER_THIN);

        $rowNum++;
    }
} else {
    $sheet->setCellValue("A4", "Tidak ada data ditemukan.");
    $sheet->mergeCells("A4:D4");
    $sheet->getStyle("A4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Nama file download sesuai tanggal & jam export
$filename = "data_ketinggian_air_" . date('Y-m-d_H-i') . ".xlsx";

// Output file Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
