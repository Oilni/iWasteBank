<?php
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php'; // ต้องรัน composer require mpdf/mpdf ก่อน

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

$monthNamesTh = [1=>'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                 'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];

// ===== ดึงข้อมูลเหมือนกับหน้า reports.php ทุกประการ =====
$sql = "SELECT waste_type, COUNT(*) as cnt, SUM(points) as total_points
        FROM waste_logs
        WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?
        GROUP BY waste_type";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $year, $month);
$stmt->execute();
$res = $stmt->get_result();
$summary = [];
$totalCount = 0;
$totalPoints = 0;
while ($row = $res->fetch_assoc()) {
    $summary[] = $row;
    $totalCount += (int)$row['cnt'];
    $totalPoints += (int)$row['total_points'];
}

$sqlActive = "SELECT COUNT(DISTINCT phone) as active_users FROM waste_logs 
              WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?";
$stmtA = $conn->prepare($sqlActive);
$stmtA->bind_param("ii", $year, $month);
$stmtA->execute();
$activeUsers = $stmtA->get_result()->fetch_assoc()['active_users'] ?? 0;

$sqlTop = "SELECT u.firstname, w.phone, SUM(w.points) as tp 
           FROM waste_logs w LEFT JOIN users u ON w.phone = u.phone
           WHERE YEAR(w.created_at) = ? AND MONTH(w.created_at) = ?
           GROUP BY w.phone ORDER BY tp DESC LIMIT 5";
$stmtT = $conn->prepare($sqlTop);
$stmtT->bind_param("ii", $year, $month);
$stmtT->execute();
$topUsers = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);

// ===== สร้าง HTML สำหรับแปลงเป็น PDF (mPDF รับ HTML/CSS ธรรมดา) =====
$html = '
<style>
    body { font-family: sans-serif; }
    h2 { color: #198754; margin-bottom: 4px; }
    .sub { color: #6c757d; font-size: 11px; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { border: 1px solid #ddd; padding: 6px 8px; font-size: 12px; }
    th { background: #f8f9fa; text-align: left; }
    .summary-box { display: inline-block; width: 30%; text-align: center; padding: 10px; border: 1px solid #eee; margin-right: 1%; }
    .summary-val { font-size: 18px; font-weight: bold; color: #198754; }
</style>
<h2>รายงานสรุป iWasteBank</h2>
<div class="sub">ประจำเดือน ' . $monthNamesTh[$month] . ' ' . $year . ' | ออกรายงานเมื่อ ' . date('d/m/Y H:i') . ' น.</div>

<div>
    <div class="summary-box">จำนวนรายการทั้งหมด<br><span class="summary-val">' . number_format($totalCount) . '</span></div>
    <div class="summary-box">แต้มที่แจกไปทั้งหมด<br><span class="summary-val">' . number_format($totalPoints) . '</span></div>
    <div class="summary-box">สมาชิกที่ใช้งาน<br><span class="summary-val">' . number_format($activeUsers) . '</span></div>
</div>

<br><br>
<h3>สรุปตามประเภทขยะ</h3>
<table>
    <tr><th>ประเภท</th><th>จำนวนครั้ง</th><th>แต้มรวม</th></tr>';

if (empty($summary)) {
    $html .= '<tr><td colspan="3" style="text-align:center;">ไม่มีข้อมูลในเดือนนี้</td></tr>';
} else {
    foreach ($summary as $row) {
        $html .= '<tr><td>' . strtoupper($row['waste_type']) . '</td><td>' . number_format($row['cnt']) . '</td><td>' . number_format($row['total_points']) . '</td></tr>';
    }
}

$html .= '</table>

<h3>5 อันดับผู้สะสมแต้มสูงสุด</h3>
<table>
    <tr><th>อันดับ</th><th>ชื่อสมาชิก</th><th>แต้มสะสม</th></tr>';

if (empty($topUsers)) {
    $html .= '<tr><td colspan="3" style="text-align:center;">ไม่มีข้อมูลในเดือนนี้</td></tr>';
} else {
    foreach ($topUsers as $i => $u) {
        $name = !empty($u['firstname']) ? $u['firstname'] : $u['phone'];
        $html .= '<tr><td>' . ($i + 1) . '</td><td>' . htmlspecialchars($name) . '</td><td>' . number_format($u['tp']) . '</td></tr>';
    }
}

$html .= '</table>';

// ===== สร้าง PDF ด้วย mPDF (ตั้งค่าให้เลือกฟอนต์อัตโนมัติตามภาษา รองรับไทย) =====
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'autoScriptToLang' => true,
    'autoLangToFont' => true,
]);
$mpdf->WriteHTML($html);
$mpdf->Output('รายงาน_' . $monthNamesTh[$month] . '_' . $year . '.pdf', 'D'); // 'D' = บังคับดาวน์โหลด