<?php
require_once '../includes/db.php';

if (!isset($_SESSION['dean_id'])) {
    header("Location: login.php");
    exit;
}
