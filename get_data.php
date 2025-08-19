<?php
include 'config.php';
include 'includes/status_level.php';
date_default_timezone_set('Asia/Jakarta');

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$sql = "SELECT id, tinggi, status, waktu FROM data_air ORDER BY waktu DESC LIMIT 20";
$res = $conn->query($sql);

$data = [];
if ($res && $res->num_rows > 0) {
  while ($row = $res->fetch_assoc()) {
    if ($row['status'] === null || $row['status'] === '') {
      // fallback hitung status utk data lama
      $row['status'] = getStatusLevel((float)$row['tinggi']);
    }
    $data[] = $row;
  }
  echo json_encode($data);
} else {
  echo json_encode(["error"=>"Tidak ada data sensor"]);
}
$conn->close();
