<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "uni_admission";   // your database name

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

/*
|--------------------------------------------------------------------------
| BASE URL (IMPORTANT FOR SUBFOLDERS LIKE /teacher/)
|--------------------------------------------------------------------------
*/
if (!defined('BASE_URL')) {
    define('BASE_URL', '/admission/');  // change if your project folder name is different
}