<?php
// เริ่มต้น Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. ตั้งค่า Timezone ของ PHP ให้เป็นเวลาประเทศไทย
date_default_timezone_set('Asia/Bangkok');

$host = "localhost";
$user = "root";
$pass = "";
$db   = "iwaste_bank";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตั้งค่าให้ MySQL รองรับภาษาไทย
$conn->set_charset("utf8mb4");

// 2. ตั้งค่า Timezone ของฐานข้อมูล MySQL ให้เป็นเวลาประเทศไทย (+07:00)
$conn->query("SET time_zone = '+07:00'");
?>