<?php
/**
 * config.php — Hostinger + Composer + phpdotenv
 * Ekspektasi: file .env berada di public_html/.env
 */

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* =========================
   Composer Autoload
========================= */
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
  error_log('[config.php] vendor/autoload.php tidak ditemukan. Jalankan "composer install" di server.');
  http_response_code(500);
  exit('Autoload tidak ditemukan. Jalankan "composer install" di server.');
}
require_once $autoload;

/* =========================
   Load .env dari public_html
   (gunakan createMutable supaya nilai .env override env server)
========================= */
$envFile = __DIR__ . '/.env';
if (is_readable($envFile)) {
  Dotenv\Dotenv::createMutable(__DIR__)->safeLoad();
  error_log("[config.php] .env loaded from: {$envFile}");
} else {
  error_log("[config.php] .env TIDAK ditemukan di: {$envFile}");
}

/* =========================
   Helper ambil env
========================= */
if (!function_exists('envv')) {
  function envv(string $key, $default = null)
  {
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : $v;
  }
}

/* =========================
   Error reporting (APP_DEBUG)
========================= */
$debug = filter_var(envv('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

/* =========================
   Timezone
========================= */
date_default_timezone_set(envv('APP_TIMEZONE', 'Asia/Jakarta'));

/* =========================
   DB Utama (wajib jika digunakan)
========================= */
$host_main = envv('DB_MAIN_HOST', 'localhost');
$user_main = envv('DB_MAIN_USER', '');
$pass_main = envv('DB_MAIN_PASS', '');
$db_main = envv('DB_MAIN_NAME', '');

$conn = null;
if ($db_main !== '' && $user_main !== '') {
  $conn = @new mysqli($host_main, $user_main, $pass_main, $db_main);
  if ($conn->connect_error) {
    if ($debug) {
      die('Koneksi gagal (main): ' . $conn->connect_error);
    }
    error_log('Koneksi gagal (main): ' . $conn->connect_error);
    http_response_code(500);
    exit('Terjadi masalah koneksi database.');
  }
  $conn->set_charset('utf8mb4');
} else {
  error_log('[config.php] DB_MAIN_NAME atau DB_MAIN_USER belum di-set di .env; koneksi utama dilewati.');
}

/* =========================
   DB Arsip (opsional)
========================= */
$host_arch = envv('DB_ARCH_HOST', $host_main);
$user_arch = envv('DB_ARCH_USER', '');
$pass_arch = envv('DB_ARCH_PASS', '');
$db_arch = envv('DB_ARCH_NAME', '');
$conn_arch = null;

if ($db_arch !== '' && $user_arch !== '') {
  $conn_arch = @new mysqli($host_arch, $user_arch, $pass_arch, $db_arch);
  if ($conn_arch->connect_error) {
    error_log('Koneksi gagal (archive): ' . $conn_arch->connect_error);
    $conn_arch = null;
  } else {
    $conn_arch->set_charset('utf8mb4');
  }
}

/* =========================
   Telegram (opsional)
========================= */
if (!defined('TELEGRAM_TOKEN')) {
  define('TELEGRAM_TOKEN', (string) envv('TELEGRAM_TOKEN', ''));
}
if (!defined('TELEGRAM_CHAT_ID')) {
  define('TELEGRAM_CHAT_ID', (string) envv('TELEGRAM_CHAT_ID', ''));
}

/* =========================
   Util: kirim pesan Telegram (opsional)
========================= */
if (!function_exists('tg_notify')) {
  function tg_notify(string $message): bool
  {
    if (TELEGRAM_TOKEN === '' || TELEGRAM_CHAT_ID === '')
      return false;
    $url = 'https://api.telegram.org/bot' . TELEGRAM_TOKEN . '/sendMessage';
    $ctx = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query([
          'chat_id' => TELEGRAM_CHAT_ID,
          'text' => $message,
          'parse_mode' => 'HTML',
          'disable_web_page_preview' => true,
        ]),
        'timeout' => 5,
      ]
    ]);
    $res = @file_get_contents($url, false, $ctx);
    return $res !== false;
  }
}

/* =========================
   Penanda bootstrap
========================= */
if (!defined('APP_BOOTSTRAPPED')) {
  define('APP_BOOTSTRAPPED', true);
}
