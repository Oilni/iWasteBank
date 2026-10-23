<?php
session_start();
require_once 'config.php';

// 1. เช็คสิทธิ์ผู้ใช้งาน
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

$user_phone = $_SESSION['phone'];
$msg = "";
$error = "";

// 2. คำนวณแต้มคงเหลือปัจจุบัน
function getCurrentPoints($conn, $phone) {
    $sql_total = "SELECT SUM(points) as total FROM waste_logs WHERE phone = '$phone'";
    $total_points = ($conn->query($sql_total)->fetch_assoc()['total']) ?? 0;

    $sql_redeemed = "SELECT SUM(points_used) as redeemed FROM redemptions WHERE phone = '$phone' AND status != 'ยกเลิก'";
    $redeemed_points = ($conn->query($sql_redeemed)->fetch_assoc()['redeemed']) ?? 0;

    return $total_points - $redeemed_points;
}

$current_points = getCurrentPoints($conn, $user_phone);

// 3. จัดการเมื่อผู้ใช้กดยืนยันแลกของรางวัล
if (isset($_POST['redeem_reward'])) {
    $reward_id = (int)$_POST['reward_id'];
    
    // ดึงข้อมูลของรางวัลที่เลือก
    $res_rw = $conn->query("SELECT * FROM rewards WHERE id = $reward_id AND status = 'พร้อมแลก' AND stock > 0");
    
    if ($res_rw && $res_rw->num_rows > 0) {
        $reward = $res_rw->fetch_assoc();
        $pts_req = (int)$reward['points_required'];
        
        // เช็คว่าแต้มพอไหม (ป้องกันการ Hack ดัดแปลงปุ่ม)
        if ($current_points >= $pts_req) {
            
            // เริ่ม Transaction เพื่อป้องกันข้อมูลรวน
            $conn->begin_transaction();
            try {
                // บันทึกประวัติการแลก (สถานะ รอรับของ)
                $stmt = $conn->prepare("INSERT INTO redemptions (phone, reward_id, points_used, status) VALUES (?, ?, ?, 'รอรับของ')");
                $stmt->bind_param("sii", $user_phone, $reward_id, $pts_req);
                $stmt->execute();
                
                // หักสต๊อกของรางวัล
                $new_stock = $reward['stock'] - 1;
                $new_status = ($new_stock > 0) ? 'พร้อมแลก' : 'ของหมด';
                $conn->query("UPDATE rewards SET stock = $new_stock, status = '$new_status' WHERE id = $reward_id");
                
                $conn->commit();
                $msg = "แลกรางวัลสำเร็จ! กรุณาติดต่อแอดมินเพื่อรับของ";
                
                // อัปเดตแต้มคงเหลือใหม่เพื่อแสดงผล
                $current_points -= $pts_req;
            } catch (Exception $e) {
                $conn->rollback();
                $error = "เกิดข้อผิดพลาดในการแลกรางวัล: " . $e->getMessage();
            }
            
        } else {
            $error = "แต้มของคุณไม่เพียงพอสำหรับของรางวัลนี้";
        }
    } else {
        $error = "ขออภัย ของรางวัลนี้หมด หรือถูกยกเลิกไปแล้ว";
    }
}

// 4. ดึงข้อมูลรายการของรางวัลทั้งหมดมาแสดง
$rewards = [];
$res_rewards = $conn->query("SELECT * FROM rewards WHERE status != 'ยกเลิก' ORDER BY points_required ASC");
if ($res_rewards) {
    while ($row = $res_rewards->fetch_assoc()) {
        $rewards[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แลกของรางวัล - iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { 
            background-color: #f7f9fc; 
            font-family: 'Sarabun', sans-serif; 
        }

        /* Navbar & Header */
        .navbar {
            background-color: #ffffff;
            box-shadow: 0 2px 15px rgba(0,0,0,0.04);
        }
        
        .points-header {
            background: linear-gradient(135deg, #104A33 0%, #2ecc71 100%);
            border-radius: 20px;
            color: white;
            padding: 25px 20px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(46, 204, 113, 0.25);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .points-header::after {
            content: '\f06b';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            font-size: 100px;
            right: -20px;
            bottom: -20px;
            opacity: 0.1;
            transform: rotate(-15deg);
        }

        /* Reward Cards */
        .reward-card {
            background: white;
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.04);
            transition: all 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .reward-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .reward-img-container {
            width: 100%;
            aspect-ratio: 1 / 1;
            background: #f8f9fa;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .reward-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: 0.3s;
        }
        .reward-card:hover .reward-img { transform: scale(1.05); }
        
        .stock-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            backdrop-filter: blur(5px);
        }

        .reward-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .reward-title {
            font-weight: 700;
            color: #2C3E50;
            font-size: 1.1rem;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .reward-points {
            color: #f39c12;
            font-weight: 800;
            font-size: 1.3rem;
            margin-bottom: 15px;
        }

        /* Buttons */
        .btn-redeem {
            background: linear-gradient(45deg, #FF512F 0%, #DD2476 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: bold;
            padding: 10px;
            margin-top: auto;
            transition: 0.3s;
        }
        .btn-redeem:hover {
            transform: scale(1.03);
            color: white;
            box-shadow: 0 5px 15px rgba(221, 36, 118, 0.3);
        }
        .btn-disabled {
            background: #e9ecef;
            color: #adb5bd;
            border: none;
            border-radius: 50px;
            font-weight: bold;
            padding: 10px;
            margin-top: auto;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg sticky-top mb-4">
    <div class="container">
        <a href="user_dashboard.php" class="btn btn-light rounded-pill px-3 fw-bold text-dark shadow-sm border">
            <i class="fas fa-arrow-left me-1"></i> กลับ
        </a>
        <span class="navbar-brand fw-bold text-success mx-auto">
            <i class="fas fa-gift me-2"></i>แคตตาล็อกของรางวัล
        </span>
        <div style="width: 75px;"></div> <!-- Spacer -->
    </div>
</nav>

<div class="container pb-5">

    <!-- แต้มปัจจุบัน -->
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="points-header">
                <p class="mb-1 opacity-75 small fw-bold text-uppercase">แต้มคงเหลือของคุณ</p>
                <div class="d-flex justify-content-center align-items-baseline gap-2">
                    <h1 class="display-3 fw-bolder mb-0"><?php echo number_format($current_points); ?></h1>
                    <span class="fs-5 opacity-75">แต้ม</span>
                </div>
            </div>

            <?php if($msg): ?>
                <div class="alert alert-success rounded-4 shadow-sm border-0 text-center"><i class="fas fa-check-circle me-2"></i><?php echo $msg; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-danger rounded-4 shadow-sm border-0 text-center"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- รายการของรางวัล -->
    <h5 class="fw-bold text-dark mb-4 mt-2"><i class="fas fa-shopping-bag text-primary me-2"></i>เลือกของรางวัลที่ต้องการ</h5>
    
    <div class="row g-4">
        <?php if(empty($rewards)): ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-box-open fa-4x text-muted mb-3 opacity-50"></i>
                <h5 class="text-muted fw-bold">ยังไม่มีของรางวัลในแคตตาล็อก</h5>
                <p class="small text-muted">กรุณารอแอดมินเพิ่มรายการของรางวัลเร็วๆ นี้</p>
            </div>
        <?php else: ?>
            <?php foreach($rewards as $item): ?>
                <?php 
                    $pts_req = (int)$item['points_required'];
                    $stock = (int)$item['stock'];
                    
                    // เช็คสถานะปุ่ม
                    $can_redeem = true;
                    $btn_text = "แลกรางวัลนี้";
                    $btn_class = "btn-redeem";
                    
                    if($stock <= 0) {
                        $can_redeem = false;
                        $btn_text = "ของหมดชั่วคราว";
                        $btn_class = "btn-disabled";
                    } elseif($current_points < $pts_req) {
                        $can_redeem = false;
                        $btn_text = "แต้มไม่พอ (ขาดอีก " . ($pts_req - $current_points) . ")";
                        $btn_class = "btn-disabled";
                    }
                ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="reward-card">
                        <div class="reward-img-container">
                            <!-- ถ้าไม่มีรูป ให้โชว์ไอคอนกล่องของขวัญแทน -->
                            <?php if(!empty($item['image_path'])): ?>
                                <img src="uploads/rewards/<?php echo htmlspecialchars($item['image_path']); ?>" class="reward-img" alt="reward">
                            <?php else: ?>
                                <i class="fas fa-gift fa-4x text-muted opacity-25"></i>
                            <?php endif; ?>
                            
                            <!-- ป้ายบอกสต๊อก -->
                            <?php if($stock > 0): ?>
                                <span class="stock-badge bg-white text-dark">เหลือ <?php echo $stock; ?> ชิ้น</span>
                            <?php else: ?>
                                <span class="stock-badge bg-danger text-white">SOLD OUT</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="reward-body">
                            <h6 class="reward-title"><?php echo htmlspecialchars($item['reward_name']); ?></h6>
                            <div class="reward-points"><i class="fas fa-star text-warning me-1"></i><?php echo number_format($pts_req); ?> แต้ม</div>
                            
                            <?php if($can_redeem): ?>
                                <button type="button" class="btn <?php echo $btn_class; ?> w-100" 
                                        onclick="confirmRedeem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['reward_name']); ?>', <?php echo $pts_req; ?>)">
                                    <i class="fas fa-hand-pointer me-1"></i> <?php echo $btn_text; ?>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn <?php echo $btn_class; ?> w-100" disabled>
                                    <?php echo $btn_text; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Modal ยืนยันการแลก -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-body p-4 text-center">
                <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                    <i class="fas fa-question fa-3x"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">ยืนยันการแลกรางวัล?</h4>
                <p class="text-muted mb-4">
                    คุณต้องการใช้ <strong class="text-warning fs-5" id="modalPoints">0</strong> แต้ม <br>
                    เพื่อแลก <strong class="text-dark" id="modalRewardName">ชื่อของรางวัล</strong> ใช่หรือไม่?
                </p>
                <form method="POST">
                    <input type="hidden" name="reward_id" id="modalRewardId">
                    <div class="row g-2">
                        <div class="col-6">
                            <button type="button" class="btn btn-light w-100 rounded-pill fw-bold border py-2" data-bs-dismiss="modal">ยกเลิก</button>
                        </div>
                        <div class="col-6">
                            <button type="submit" name="redeem_reward" class="btn btn-success w-100 rounded-pill fw-bold py-2 shadow-sm">
                                ยืนยันการแลก
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ฟังก์ชันส่งข้อมูลเข้าไปใน Modal ยืนยัน
    function confirmRedeem(id, name, points) {
        document.getElementById('modalRewardId').value = id;
        document.getElementById('modalRewardName').textContent = name;
        document.getElementById('modalPoints').textContent = points.toLocaleString();
        
        var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        confirmModal.show();
    }
</script>
</body>
</html>