<?php
function getStatusLevel($tinggi) {
    if ($tinggi < 6) return "Normal";
    if ($tinggi < 10) return "Siaga";
    if ($tinggi < 16) return "Bahaya";
    return "Evakuasi";
}

function getStatusColor($status) {
    switch ($status) {
        case "Normal": return "green";
        case "Siaga": return "orange";
        case "Bahaya": return "red";
        case "Evakuasi": return "darkred";
        default: return "gray";
    }
}
?>
