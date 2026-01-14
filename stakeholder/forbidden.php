<?php
declare(strict_types=1);

require_once __DIR__ . '/../components/auth.php';
http_response_code(403);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>403 Forbidden</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
  <h1>403 — Access Denied</h1>
  <p>You are not authorized to view this page.</p>
  <p><a href="/index.php">Back to Home</a></p>
</body>
</html>
