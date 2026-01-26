<?php
declare(strict_types=1);

require_once __DIR__ . '/../components/auth.php';

$_SESSION = [];
session_destroy();

header('Location: /swap/swaps/public/login.php');
exit;
