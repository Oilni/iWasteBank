<?php
session_start();
require_once 'config.php';

// เช็คสิทธิ์แอดมิน
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// ตั้งค่า Header สำหรับดาวน์โหลดไฟล์ CSV (ใช้ UTF-8 BOM เพื่อให้ภาษาไทยใน Excel ไม่เพี้ยน)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="Waste_Logs_Report_' . date('Ymd_His') . '.csv"');

// เปิด Output Stream
$output = fopen('php://output', 'w');

// พิมพ์ BOM ให้อ่านภาษาไทยใน Excel ได้
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// พิมพ์หัวตาราง
fputcsv($output, array('รหัสรายการ', 'เบอร์โทรศัพท์', 'ชื่อ-นามสกุล', 'ประเภทขยะ', 'คะแนนที่ได้', 'วันที่และเวลาทำรายการ'));

// ดึงข้อมูล
$sql = "SELECT w.id, w.phone, u.firstname, u.lastname, w.waste_type, w.points, w.created_at 
        FROM waste_logs w 
        LEFT JOIN users u ON w.phone = u.phone 
        ORDER BY w.created_at DESC";
$res = $conn->query($sql);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $fullname = $row['firstname'] . " " . $row['lastname'];
        fputcsv($output, array(
            $row['id'], 
            $row['phone'], 
            $fullname, 
            $row['waste_type'], 
            $row['points'], 
            $row['created_at']
        ));
    }
}

fclose($output);
exit();
?>