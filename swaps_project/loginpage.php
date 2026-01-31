<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 1rem; }
    form { margin-bottom: 1rem; }
    label { display: inline-block; width: 120px; }
    input { margin-bottom: 0.5rem; }
    .msg { padding: 0.5rem; border-radius: 4px; }
    .error { color: #a00; }
    .success { color: #060; }
  </style>
</head>
<body>

<form id="loginForm" method="post" action="">
  <div>
    <label for="username">Username:</label>
    <input type="text" id="username" name="username" required>
  </div>
  <div>
    <label for="password">Password:</label>
    <input type="password" id="password" name="password" required>
  </div>
  <button type="submit">Login</button>
  <button type="reset">Reset</button>
</form>
<div id="msg" class="msg" style="display:none;"></div>
<hr>

<?php

// Start session (matches secure settings from config.php)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Redirect to main page if already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: /swaps_project/machine_page.php');
    exit('Already logged in');
}

// Load config for database connection
require_once __DIR__ . '/config.php';

/*
 * Connect and allow simple login verification against users table.
 */

// Escape helper for outputting HTML-safe values
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// State holders
$error = null;
$tables = [];
$loginMsg = null;

// Connect using config
try{
  $pdo = getPDOConnection();
}catch(Exception $e){
  error_log('Database connection error: ' . $e->getMessage());
  $error = 'Database connection failed.';
}

// Handle login on POST
if (!isset($error) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
  $loginUsername = trim((string)$_POST['username']);
  $loginPassword = (string)$_POST['password'];
  try {
    // Use prepared statement with parameterized query to prevent SQL injection
    $stmt = $pdo->prepare('SELECT user_id, username, password_hash, role FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $loginUsername]);
    $row = $stmt->fetch();
    if ($row) {
      $stored = (string)$row['password_hash'];
      // Always use password_verify for production; plaintext comparison is for dev only
      $isHashed = str_starts_with($stored, '$');
      $ok = $isHashed ? password_verify($loginPassword, $stored) : ($loginPassword === $stored);
      if ($ok) {
        // Set session variables on successful login
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['last_activity'] = time();
        
        // Redirect to search page after successful login
        header('Location: /swaps_project/search.php');
        exit('Login successful, redirecting...');
      } else {
        $loginMsg = 'Invalid username or password.';
      }
    } else {
      $loginMsg = 'Invalid username or password.';
    }
  } catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    $loginMsg = 'A login error occurred. Please try again.';
  }
}

// List tables (optional, for dev)
if (!isset($error)) {
  try{
    $stmt = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema ORDER BY TABLE_NAME");
    $stmt->execute([':schema' => $db]);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
  }catch(Exception $e){
    $error = $e->getMessage();
  }
}

// Output messages
if ($loginMsg) {
  $cls = (str_starts_with($loginMsg, 'Login successful')) ? 'success' : 'error';
  echo '<script>document.getElementById("msg").textContent = ' . json_encode($loginMsg) . '; document.getElementById("msg").classList.add(' . json_encode($cls) . '); document.getElementById("msg").style.display = "block";</script>';
}
if ($error) {
  echo '<script>document.getElementById("msg").textContent = ' . json_encode('Error: ' . $error) . '; document.getElementById("msg").classList.add("error"); document.getElementById("msg").style.display = "block";</script>';
}
?>

<script>
  const form = document.getElementById('loginForm');
  const msg = document.getElementById('msg');
  // Clear message when the form is reset
  form.addEventListener('reset', () => {
    msg.textContent = '';
    msg.className = 'msg';
    msg.style.display = 'none';
  });
  // Clear previous message immediately on submit (new attempt)
  form.addEventListener('submit', () => {
    msg.textContent = '';
    msg.className = 'msg';
    msg.style.display = 'none';
  });
</script>

</body>
</html>