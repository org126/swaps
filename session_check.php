<?php
// Simple session check for protected pages
session_start();
if (empty($_SESSION['user_id'])) {
  header('Location: loginpage.php');
  exit;
}
