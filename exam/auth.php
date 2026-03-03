<?php
require_once '../includes/db.php';
if (!isset($_SESSION['exam_id'])) {
    header("Location: login.php"); exit;
}
