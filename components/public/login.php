<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../components/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim((string)($_POST['username'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($username === '' || $password === '') {
    $error = 'Please enter username and password.';
  } else {
    $stmt = $pdo->prepare("SELECT user_id, username, password_hash, role FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
      $error = 'Invalid username or password.';
    } else {
      // Store minimal session info
      $_SESSION['user'] = [
        'user_id' => $row['user_id'],
        'username' => $row['username'],
        'role' => $row['role'],
      ];

      header('Location: /swap/swaps/stakeholder/dashboard.php');
      exit;
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
  <h2>EMWS Login</h2>

  <?php if ($error !== ''): ?>
    <p style="color:red;"><?= e($error) ?></p>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <label>Username</label><br>
    <input name="username" required><br><br>

    <label>Password</label><br>
    <input name="password" type="password" required><br><br>

    <button type="submit">Login</button>
  </form>

  <p><a href="/swap/swaps/index.php">Back to Home</a></p>
</body>
</html>
