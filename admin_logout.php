<?php
require_once 'includes/db.php';
session_destroy();
header("Location: admin_login.php");
exit;
