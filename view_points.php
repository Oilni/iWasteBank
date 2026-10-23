<?php
// --- ข้อมูลการเชื่อมต่อ DB ---
$db_host = "localhost";
$db_user = "root";
$db_pass = ""; 
$db_name = "iwaste_bank";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("ขออภัย! ระบบขัดข้องชั่วคราว");
}

// 1. รับ Token จาก URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("<div style='text-align:center; padding:50px; font-family:Sarabun,sans-serif;'>
            <h2 style='color:#e74c3c;'>ไม่พบรหัสเข้าถึง</h2>
            <p>กรุณาสแกน QR Code ใหม่จากหน้าตู้ iWasteBank</p>
         </div>");
}

// 2. ตรวจสอบ Token ในตาราง users
$sql_user = "SELECT * FROM users WHERE view_token = '$token' LIMIT 1";
$res_user = $conn->query($sql_user);
$user = $res_user->fetch_assoc();

if (!$user) {
    die("<div style='text-align:center; padding:50px; font-family:Sarabun,sans-serif;'>
            <h2 style='color:#e74c3c;'>รหัสเข้าถึงไม่ถูกต้องหรือหมดอายุ</h2>
            <p>กรุณาสแกน QR Code ใหม่จากหน้าตู้ iWasteBank</p>
         </div>");
}

$phone = $user['phone'];

// 3. คำนวณคะแนนปัจจุบัน
$total = $conn->query("SELECT SUM(points) as pts FROM waste_logs WHERE phone = '$phone'")->fetch_assoc()['pts'] ?? 0;
$used = $conn->query("SELECT SUM(points_used) as pts FROM redemptions WHERE phone = '$phone' AND status != 'ยกเลิก'")->fetch_assoc()['pts'] ?? 0;
$current_points = $total - $used;

// 4. ดึงประวัติการทิ้งขยะล่าสุด
$logs = $conn->query("SELECT * FROM waste_logs WHERE phone = '$phone' ORDER BY created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ทิ้งขยะสำเร็จ! - iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <!-- สคริปต์สำหรับเอฟเฟกต์พลุกระดาษ -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <style>
        body { 
            background: #f4f7f6; 
            font-family: 'Sarabun', sans-serif; 
            color: #2C3E50;
            overflow-x: hidden;
        }
        .header-section {
            background: linear-gradient(135deg, #104A33 0%, #2ecc71 100%);
            padding: 50px 20px 60px 20px;
            border-radius: 0 0 40px 40px;
            color: white;
            text-align: center;
            box-shadow: 0 10px 30px rgba(46, 204, 113, 0.3);
            position: relative;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: white;
            color: #2ecc71;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 15px auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            animation: popIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        .score-display {
            font-size: 4.5rem;
            font-weight: 800;
            line-height: 1;
            margin: 10px 0;
            text-shadow: 2px 4px 10px rgba(0,0,0,0.1);
        }
        .card-custom {
            border: none;
            border-radius: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.04);
            margin-top: -40px;
            background: white;
            padding: 30px 20px;
        }
        .history-item {
            padding: 15px;
            background: #fdfdfd;
            border: 1px solid #f1f1f1;
            border-radius: 15px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.3s;
        }
        .history-item:first-child {
            border-left: 5px solid #2ecc71;
            background: #f2fcf5;
        }
        .btn-main {
            border-radius: 50px;
            padding: 16px;
            font-weight: 700;
            font-size: 1.1rem;
            background: linear-gradient(135deg, #104A33 0%, #198754 100%);
            color: white;
            text-decoration: none;
            display: block;
            margin-top: 25px;
            box-shadow: 0 10px 20px rgba(25, 135, 84, 0.25);
            transition: all 0.3s;
        }
        .btn-main:hover {
            transform: translateY(-3px);
            color: white;
            box-shadow: 0 15px 25px rgba(25, 135, 84, 0.35);
        }
        @keyframes popIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>

<div class="header-section">
    <div class="success-icon"><i class="fas fa-check"></i></div>
    <h3 class="fw-bold mb-1">ทำรายการสำเร็จ!</h3>
    <p class="mb-2 opacity-75 fs-6">ขอบคุณที่ช่วยแยกขยะนะ, <?php echo htmlspecialchars($user['firstname']); ?></p>
    
    <div class="score-display"><?php echo number_format($current_points); ?></div>
    <p class="mb-0 fw-semibold text-uppercase letter-spacing-1">แต้มสะสมปัจจุบัน</p>
</div>

<div class="container pb-5" style="max-width: 600px;">
    <div class="card card-custom">
        <h6 class="fw-bold mb-4 text-dark"><i class="fas fa-history text-success me-2"></i>ประวัติล่าสุดของคุณ</h6>
        
        <?php if ($logs->num_rows > 0): ?>
            <?php while($row = $logs->fetch_assoc()): 
                // กำหนดสีตามประเภทขยะ
                $iconColor = 'text-secondary';
                $type = strtolower($row['waste_type']);
                if(strpos($type, 'plastic') !== false || strpos($type, 'พลาสติก') !== false) $iconColor = 'text-info';
                if(strpos($type, 'paper') !== false || strpos($type, 'กระดาษ') !== false) $iconColor = 'text-warning';
                if(strpos($type, 'metal') !== false || strpos($type, 'โลหะ') !== false) $iconColor = 'text-dark';
            ?>
                <div class="history-item shadow-sm">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-trash-alt <?php echo $iconColor; ?> fs-4"></i>
                        <div>
                            <div class="fw-bold text-dark text-uppercase"><?php echo htmlspecialchars($row['waste_type']); ?></div>
                            <div class="small text-muted"><i class="far fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></div>
                        </div>
                    </div>
                    <div class="text-success fw-bold fs-5">
                        +<?php echo number_format($row['points']); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-4 text-muted">ยังไม่พบประวัติการสะสมแต้ม</div>
        <?php endif; ?>

        <a href="login.php" class="btn-main text-center">
            <i class="fas fa-gift me-2"></i> เข้าสู่ระบบเพื่อแลกรางวัล
        </a>
        <div class="text-center mt-4">
            <small class="text-muted fw-bold">iWasteBank - Smart Waste Management</small>
        </div>
    </div>
</div>

<script>
    // สคริปต์จุดพลุ (Confetti) เมื่อเปิดหน้าเว็บ
    window.onload = function() {
        var duration = 3000;
        var end = Date.now() + duration;

        (function frame() {
            confetti({
                particleCount: 5,
                angle: 60,
                spread: 55,
                origin: { x: 0 },
                colors: ['#2ecc71', '#f1c40f', '#3498db']
            });
            confetti({
                particleCount: 5,
                angle: 120,
                spread: 55,
                origin: { x: 1 },
                colors: ['#2ecc71', '#f1c40f', '#3498db']
            });

            if (Date.now() < end) {
                requestAnimationFrame(frame);
            }
        }());
    };
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>