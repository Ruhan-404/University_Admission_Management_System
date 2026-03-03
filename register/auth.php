<?php
require_once '../includes/db.php';
if (!isset($_SESSION['register_id'])) {
    header("Location: login.php"); exit;
}
