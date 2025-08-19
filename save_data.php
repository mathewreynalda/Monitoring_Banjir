<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/status_level.php';

// --- Konfigurasi ---
$APIKEY_SERVER = envv('API_KEY', 'iotflood1903');   // Tambahkan API_KEY=... di .env
date_default_timezone_set(envv('APP_TIMEZONE', 'Asia/Jakarta'));

// --- Ambil input (form-urlenc / query / JSON) ---
$raw = file_get_contents('php://input');
$maybeJson = json_decode($raw, true);

$apikey = $_POST['apikey'] ?? $_GET['apikey'] ?? ($maybeJson['apikey'] ?? '');
$tinggi = $_POST['tinggi'] ?? $_GET['tinggi'] ?? ($maybeJson['tinggi'] ?? null);

// --- Auth sederhana ---
if ($apikey !== $APIKEY_SERVER) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// --- Validasi ---
$tinggi = is_numeric($tinggi) ? (float)$tinggi : null;
if ($tinggi === null || $tinggi < 0) {
    http_response_code(400);
    echo "Bad Request";
    exit;
}

// --- Hitung status ---
$status = getStatusLevel($tinggi);

// --- Insert ---
$stmt = $conn->prepare("INSERT INTO data_air (tinggi, status, waktu) VALUES (?, ?, NOW())");
$stmt->bind_param("ds", $tinggi, $status);
$ok = $stmt->execute();

if ($ok) {
    echo "OK";   // Balasan singkat untuk ESP32
} else {
    http_response_code(500);
    echo "ERR";
}
