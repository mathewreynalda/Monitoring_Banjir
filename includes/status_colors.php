<?php
include 'includes/status_level.php';
header("Content-Type: application/json");

$statuses = ["Normal", "Siaga", "Bahaya", "Evakuasi"];
$colors = [];

foreach ($statuses as $status) {
    $colors[$status] = getStatusColor($status);
}

echo json_encode($colors);
?>
