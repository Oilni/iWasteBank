<?php
session_start(); // เริ่มต้น session เพื่อให้รู้จักว่าใครล็อกอินอยู่

// 1. ล้างค่าตัวแปร session ทั้งหมด
$_SESSION = array();

// 2. ถ้ามีการใช้ cookie สำหรับ session ให้ลบทิ้งด้วย (เพื่อความสะอาดของระบบ)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. ทำลาย session ทั้งหมดในระบบ
session_destroy();

// 4. ส่งผู้ใช้งานกลับไปที่หน้าล็อกอิน หรือหน้าแรก
header("Location: login.php");
exit();
?>