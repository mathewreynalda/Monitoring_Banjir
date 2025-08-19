<?php
session_start();
include '../config.php';
date_default_timezone_set('Asia/Jakarta');
$now = date('Y-m-d H:i:s');

$error = "";
if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Log pakai prepared statement juga
            $log_user = $user['username'];
            $log_stmt = $conn->prepare("INSERT INTO user_logs (user, activity, log_time) VALUES (?, 'Login', ?)");
            $log_stmt->bind_param("ss", $log_user, $now);
            $log_stmt->execute();

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Username atau password salah!";
        }
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log In | Peringatan Dini Banjir</title>
  <link rel="icon" href="../assets/img/unbin.png">
  <link rel="stylesheet" href="../assets/css/style.css?v=<?=time()?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <style>
    body.login-page {
      background: #f6fbff;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-container {
      max-width: 350px;
      width: 96vw;
      margin: 0 auto;
      background: #fff;
      border-radius: 17px;
      box-shadow: 0 6px 32px rgba(0,60,180,0.10);
      padding: 38px 25px 26px 25px;
      animation: fadeIn .8s;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .login-container img {
      width: 54px; height: 54px; border-radius: 50%; margin-bottom: 12px;
    }
    .login-container h1 {
      color: #007bff;
      font-size: 2.1em;
      margin-bottom: 16px;
      text-align: center;
      font-weight: 700;
      letter-spacing: 0.8px;
    }
    .login-form {
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 15px;
    }
    .input-group {
      position: relative;
      width: 100%;
    }
    .input-group input {
      width: 100%;
      padding: 12px 13px 12px 40px;
      border: 1.5px solid #c5d3ea;
      border-radius: 8px;
      font-size: 1.08em;
      background: #f8fbff;
      transition: border 0.18s, box-shadow 0.18s;
      outline: none;
    }
    .input-group input:focus {
      border: 1.6px solid #007bff;
      box-shadow: 0 0 0 2px #007bff25;
    }
    .input-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #007bff;
      font-size: 1.15em;
      opacity: 0.85;
    }
    .login-form button {
      background: linear-gradient(135deg, #007bff, #00c6ff);
      color: #fff;
      font-weight: bold;
      border: none;
      border-radius: 9px;
      padding: 11px 0;
      font-size: 1.11em;
      margin-top: 5px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.07);
      transition: background 0.19s;
      cursor: pointer;
    }
    .login-form button:hover {
      background: #0056b3;
    }
    .back-btn {
      display: inline-block;
      margin-top: 16px;
      color: #007bff;
      font-weight: 500;
      text-align: center;
      width: 100%;
      text-decoration: none;
      font-size: 1em;
      letter-spacing: 0.2px;
    }
    .back-btn:hover { text-decoration: underline; }
    .error-msg {
      color: #b80000;
      background: #ffd5d5;
      border-radius: 7px;
      margin-bottom: 12px;
      padding: 8px 13px;
      font-size: 1em;
      width: 100%;
      text-align: left;
      box-shadow: 0 2px 7px #ffeaea40;
      border-left: 4px solid #ff3d3d;
    }
    @media (max-width:500px){
      .login-container { padding: 20px 3vw 20px 3vw;}
      .login-container h1 { font-size: 1.32em; }
    }
  </style>
</head>
<body class="login-page">
  <div class="login-container">
    <img src="../assets/img/unbin.png" alt="Logo">
    <h1>Log In</h1>
    <?php if ($error): ?>
      <div class="error-msg"><i class="fa fa-triangle-exclamation"></i> <?= $error ?></div>
    <?php endif; ?>
    <form method="POST" class="login-form" autocomplete="off">
      <div class="input-group">
        <span class="input-icon"><i class="fa fa-user"></i></span>
        <input type="text" name="username" placeholder="Username" required autocomplete="username" autofocus>
      </div>
      <div class="input-group">
        <span class="input-icon"><i class="fa fa-lock"></i></span>
        <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
      </div>
      <button type="submit">Log In</button>
      <a href="../index.php" class="back-btn"><i class="fa fa-arrow-left"></i> Kembali ke Home</a>
    </form>
  </div>
</body>
</html>
