<?php
require_once '../includes/db.php';

if (!isset($_SESSION['teacher_id'])) {
  header("Location: login.php");
  exit;
}