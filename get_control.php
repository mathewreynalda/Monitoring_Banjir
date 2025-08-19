<?php
require __DIR__ . '/config.php';

$APIKEY_SERVER = envv('API_KEY', 'iotflood1903');
$apikey = $_GET['apikey'] ?? '';

if ($apikey !== $APIKEY_SERVER) {
    http_response_code(403);
    echo "ERR";
    exit;
}

$res = $conn->query("SELECT status FROM control_status WHERE id=1");
if (!$res || !$row = $res->fetch_assoc()) {
    http_response_code(500);
    echo "ERR";
    exit;
}

$out = strtoupper(trim($row['status']));
echo ($out === 'ON') ? 'ON' : 'OFF';
