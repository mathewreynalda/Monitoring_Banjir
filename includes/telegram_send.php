<?php
require_once '../config.php';
require_once '../includes/telegram_helper.php';

header('Content-Type: application/json; charset=utf-8');

$message = '';
if (isset($_POST['message'])) {
  $message = trim($_POST['message']);
} elseif (isset($_GET['message'])) {
  $message = trim($_GET['message']);
}

if ($message === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Pesan tidak boleh kosong.']);
  exit;
}

$ok = sendTelegramMessage($message);

if ($ok) {
  echo json_encode(['success' => true, 'message' => 'Pesan berhasil dikirim ke Telegram.']);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Gagal mengirim pesan ke Telegram.']);
}
