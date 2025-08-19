<?php
session_start();
include 'config.php';
date_default_timezone_set('Asia/Jakarta');
$now = date('Y-m-d H:i:s');

// API key sebagai backup akses
$APIKEY_SERVER = 'iotflood1903';
$apikey = $_GET['apikey'] ?? '';

// Autentikasi: Hanya user login atau API key yang valid
if (!isset($_SESSION['logged_in']) && $apikey !== $APIKEY_SERVER) {
    echo "Akses ditolak.";
    exit;
}

// Ambil nama user (atau IOT_API jika dari API)
$user = isset($_SESSION['username']) ? ucfirst(strtolower($_SESSION['username'])) : 'IOT_API';

// Fungsi kirim pesan ke Telegram
function sendTelegramMessage($message) {
    $token = '8347890114:AAFkjPoz7yuW5uHh5RbinMfjydGjboiGllg';  // Ganti dengan token kamu
    $chat_id = '6181039632';  // Ganti dengan chat ID kamu
    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $post = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response ? true : false;
}

// Jika ada perintah ON atau OFF
if (isset($_GET['action'])) {
    $action = strtoupper($_GET['action']);

    if (!in_array($action, ['ON', 'OFF'])) {
        echo "Action tidak valid.";
        exit;
    }

    // Update status sensor di tabel control_status
    $stmt = $conn->prepare("UPDATE control_status SET status = ? WHERE id = 1");
    $stmt->bind_param("s", $action);

    if ($stmt->execute()) {
        // Update notify_status
        $sensor_status = $action;
        $is_fresh = ($action === 'ON') ? 1 : 0;

        $stmt2 = $conn->prepare("UPDATE notify_status SET sensor_status = ?, is_fresh_boot = ?, updated_at = NOW() WHERE id = 1");
        $stmt2->bind_param("si", $sensor_status, $is_fresh);
        $stmt2->execute();
        $stmt2->close();

        // Catat log user
        $log_activity = "Mengubah status sistem ke $action";
        $conn->query("INSERT INTO user_logs (user, activity, log_time) VALUES ('$user', '$log_activity', '$now')");

        // Kirim Telegram
        $msg = ($action === 'ON')
            ? "✅ Sistem sensor dinyalakan oleh $user (pukul " . date('H:i') . ")."
            : "⚠️ Sistem sensor dimatikan oleh $user (pukul " . date('H:i') . ").";

        if (sendTelegramMessage($msg)) {
            $conn->query("INSERT INTO user_logs (user, activity, log_time) VALUES ('SYSTEM', 'Kirim Telegram: " . strip_tags($msg) . "', '$now')");
        }

        echo "Sistem berhasil diubah ke $action";
    } else {
        echo "Gagal mengubah status.";
    }

    $stmt->close();
} else {
    echo "Parameter action tidak ditemukan.";
}
?>
