<?php
// config.php — Hostinger + Composer (phpdotenv)
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/vendor/autoload.php';

// Cari .env: 1) ../.env (di atas public_html)  2) ./.env (fallback)
$dotenvLoaded = false;
foreach ([dirname(__DIR__), __DIR__] as $path) {
  if (is_readable($path . '/.env')) {
    Dotenv\Dotenv::createImmutable($path)->safeLoad();
    $dotenvLoaded = true;
    break;
  }
}
if (!$dotenvLoaded) {
  error_log('[config.php] .env tidak ditemukan. Letakkan di: ' . dirname(__DIR__) . '/.env (disarankan) atau ' . __DIR__ . '/.env');
}

// helper ambil env
function envv(string $key, $default = null) {
  $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
  return ($v === false || $v === null) ? $default : $v;
}

date_default_timezone_set(envv('APP_TIMEZONE', 'Asia/Jakarta'));

// DB utama
$host_main = envv('DB_MAIN_HOST', 'localhost');
$user_main = envv('DB_MAIN_USER', '');
$pass_main = envv('DB_MAIN_PASS', '');
$db_main   = envv('DB_MAIN_NAME', '');

$conn = @new mysqli($host_main, $user_main, $pass_main, $db_main);
if ($conn->connect_error) {
  die('Koneksi gagal (main): ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// DB arsip (opsional)
$host_arch = envv('DB_ARCH_HOST', $host_main);
$user_arch = envv('DB_ARCH_USER', '');
$pass_arch = envv('DB_ARCH_PASS', '');
$db_arch   = envv('DB_ARCH_NAME', '');
$conn_arch = null;

if ($db_arch && $user_arch) {
  $conn_arch = @new mysqli($host_arch, $user_arch, $pass_arch, $db_arch);
  if ($conn_arch->connect_error) {
    error_log('Koneksi gagal (archive): ' . $conn_arch->connect_error);
    $conn_arch = null;
  } else {
    $conn_arch->set_charset('utf8mb4');
  }
}

// Telegram
if (!defined('TELEGRAM_TOKEN'))   define('TELEGRAM_TOKEN',   (string)envv('TELEGRAM_TOKEN', ''));
if (!defined('TELEGRAM_CHAT_ID')) define('TELEGRAM_CHAT_ID', (string)envv('TELEGRAM_CHAT_ID', ''));
