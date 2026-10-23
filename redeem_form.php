<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_phone = $_SESSION['phone'];

// 1. ดึงอัตราแลกเปลี่ยน (แต้ม -> บาท) จากหน้าตั้งค่า Admin
$rate_query = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'baht_per_point'");
$point_rate = $rate_query->fetch_assoc()['setting_value'] ?? 0.1;

// 2. คำนวณแต้มคงเหลือ (แต้มที่ได้รับทั้งหมด - แต้มที่แลกไปแล้วทั้งหมดที่สถานะไม่ใช่ 'ยกเลิก')
$total_earned = $conn->query("SELECT SUM(points) as total FROM waste_logs WHERE phone = '$user_phone'")->fetch_assoc()['total'] ?? 0;
$total_used = $conn->query("SELECT SUM(points_used) as used FROM redemptions WHERE phone = '$user_phone' AND status != 'ยกเลิก'")->fetch_assoc()['used'] ?? 0;
$current_points = $total_earned - $total_used;

// 3. ดึงบัญชีธนาคารของผู้ใช้
$banks = $conn->query("SELECT * FROM user_banks WHERE phone = '$user_phone'");

// 4. ประมวลผลเมื่อกดปุ่มแลกแต้ม
if (isset($_POST['confirm_redeem'])) {
    $points_to_use = intval($_POST['points_to_use']);
    $method = $conn->real_escape_string($_POST['payment_method']);
    $money = $points_to_use * $point_rate;

    if ($points_to_use > 0 && $points_to_use <= $current_points) {
        $sql = "INSERT INTO redemptions (phone, points_used, amount_money, payment_method, status, created_at) 
                VALUES ('$user_phone', $points_to_use, $money, '$method', 'รอการตรวจสอบ', NOW())";
        if ($conn->query($sql)) {
            header("Location: redeem_list.php?status=success");
            exit();
        }
    } else {
        $error = "จำนวนแต้มไม่ถูกต้อง หรือแต้มของคุณไม่เพียงพอ";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แลกแต้มสะสม - iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; font-family: 'Sarabun', sans-serif; }
        .card-redeem { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .points-box { background: linear-gradient(135deg, #0d6efd, #0043a8); color: white; padding: 30px; border-radius: 15px; }
        .method-item { cursor: pointer; transition: 0.2s; border: 2px solid #eee !important; margin-bottom: 10px; }
        .method-item:hover { border-color: #0d6efd !important; background-color: #f8f9ff; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger border-0 shadow-sm mb-3"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card card-redeem shadow-lg">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <a href="user_dashboard.php" class="text-dark me-3"><i class="fas fa-chevron-left fa-lg"></i></a>
                        <h4 class="fw-bold mb-0">แลกแต้มสะสม</h4>
                    </div>

                    <div class="points-box text-center mb-4">
                        <small class="opacity-75 text-uppercase">แต้มที่ใช้แลกได้</small>
                        <h1 class="display-4 fw-bold mb-0"><?php echo number_format($current_points); ?></h1>
                        <span>แต้ม</span>
                    </div>

                    <form method="POST">
                        <div class="mb-4 text-center">
                            <label class="form-label fw-bold">ระบุจำนวนแต้ม</label>
                            <input type="number" name="points_to_use" id="pointsInput" 
                                   class="form-control form-control-lg text-center rounded-pill" 
                                   max="<?php echo $current_points; ?>" min="1" required 
                                   oninput="calculateMoney(this.value)">
                            <div class="mt-2 fw-bold text-primary">เงินที่จะได้รับ: ฿<span id="moneyResult">0.00</span></div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">เลือกช่องทางการรับเงิน</label>
                            <div class="list-group">
                                <label class="list-group-item method-item d-flex gap-3 rounded-4 p-3 border shadow-sm">
                                    <input class="form-check-input flex-shrink-0" type="radio" name="payment_method" value="เงินสด (รับที่สำนักงาน)" checked>
                                    <div>
                                        <strong class="d-block">รับเงินสด</strong>
                                        <small class="text-muted small">ติดต่อรับที่สำนักงาน iWasteBank</small>
                                    </div>
                                </label>

                                <?php while($b = $banks->fetch_assoc()): ?>
                                <label class="list-group-item method-item d-flex gap-3 rounded-4 p-3 border shadow-sm">
                                    <input class="form-check-input flex-shrink-0" type="radio" name="payment_method" 
                                           value="<?php echo $b['bank_name'].' : '.$b['account_number']; ?>">
                                    <div>
                                        <strong class="d-block"><?php echo $b['bank_name']; ?></strong>
                                        <small class="text-muted small"><?php echo $b['account_number']; ?> - <?php echo $b['account_name']; ?></small>
                                    </div>
                                </label>
                                <?php endwhile; ?>
                            </div>
                        </div>

                        <button type="submit" name="confirm_redeem" class="btn btn-primary btn-lg w-100 rounded-pill shadow py-3 fw-bold">
                            ยืนยันการแลกแต้ม
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const rate = <?php echo $point_rate; ?>;
    function calculateMoney(pts) {
        let val = parseFloat(pts) || 0;
        document.getElementById('moneyResult').innerText = (val * rate).toLocaleString(undefined, {minimumFractionDigits: 2});
    }
</script>
</body>
</html>