<?php
header('Content-Type: application/json');
error_reporting(0); // ปิด Error เพื่อไม่ให้ JSON พัง

// --- ข้อมูลการเชื่อมต่อ DB ---
$db_host = "localhost";
$db_user = "root";
$db_pass = ""; 
$db_name = "iwaste_bank";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB Connection Failed"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $conn->real_escape_string($_POST['phone']);
    $action = $_POST['action'] ?? ''; // รับค่า action (ถ้ามี)

    // --- 1. ตรวจสอบว่ามีสมาชิกในระบบหรือไม่ ---
    $user_res = $conn->query("SELECT firstname FROM users WHERE phone = '$phone' LIMIT 1");
    if ($user_res->num_rows == 0) {
        echo json_encode(["status" => "error", "message" => "ไม่พบเบอร์สมาชิกในระบบ"]);
        exit;
    }
    $user_data = $user_res->fetch_assoc();
    $firstname = $user_data['firstname'];

    // --- 2. สร้าง Token สำหรับการดูคะแนนผ่าน QR Code (เพื่อความปลอดภัย) ---
    $view_token = bin2hex(random_bytes(16));
    $conn->query("UPDATE users SET view_token = '$view_token' WHERE phone = '$phone'");

    // ฟังก์ชันคำนวณแต้มคงเหลือปัจจุบัน
    function getCurrentPoints($conn, $phone) {
        $total = $conn->query("SELECT SUM(points) as pts FROM waste_logs WHERE phone = '$phone'")->fetch_assoc()['pts'] ?? 0;
        $used = $conn->query("SELECT SUM(points_used) as pts FROM redemptions WHERE phone = '$phone' AND status != 'ยกเลิก'")->fetch_assoc()['pts'] ?? 0;
        return $total - $used;
    }

    // --- 3. กรณี: ตรวจสอบคะแนนอย่างเดียว (ไม่ต้องบันทึกขยะ) ---
    if ($action === 'check_points') {
        echo json_encode([
            "status" => "success",
            "name" => $firstname,
            "points" => getCurrentPoints($conn, $phone),
            "token" => $view_token
        ]);
        exit;
    }

    // --- 4. กรณี: บันทึกข้อมูลขยะสะสมแต้ม ---
    $type = $conn->real_escape_string($_POST['type']); 
    $image_name = "";

    // ดึงแต้ม Snapshot จากตาราง waste_types
    $res_type = $conn->query("SELECT points_per_unit FROM waste_types WHERE waste_name = '$type'");
    $type_row = $res_type->fetch_assoc();
    $points_to_save = intval($type_row['points_per_unit'] ?? 0);

    // จัดการรูปภาพ
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        $image_name = "waste_" . time() . "_" . $phone . ".jpg";
        move_uploaded_file($_FILES["image"]["tmp_name"], $target_dir . $image_name);
    }

    // บันทึกข้อมูลลงฐานข้อมูล
    $conn->begin_transaction();
    try {
        // บันทึกประวัติทิ้งขยะ
        $q1 = $conn->query("INSERT INTO waste_logs (phone, waste_type, points, image_path, created_at) 
                           VALUES ('$phone', '$type', $points_to_save, '$image_name', NOW())");
        
        // อัปเดตตาราง user_points (ถ้าคุณยังต้องการเก็บตารางสรุปไว้)
        $q2 = $conn->query("INSERT INTO user_points (phone, total_points) 
                           VALUES ('$phone', $points_to_save) 
                           ON DUPLICATE KEY UPDATE total_points = total_points + $points_to_save");

        if ($q1 && $q2) {
            $conn->commit();
            // ส่งค่ากลับไปให้ IoT เพื่อแสดงหน้าสรุป (ที่มี QR Code)
            echo json_encode([
                "status" => "success",
                "name" => $firstname,
                "points" => getCurrentPoints($conn, $phone), // ส่งแต้มรวมล่าสุดกลับไป
                "token" => $view_token
            ]);
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }

} else {
    echo json_encode(["status" => "error", "message" => "Invalid Method"]);
}

$conn->close();
?>