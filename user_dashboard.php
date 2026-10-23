<?php
session_start();
include 'config.php';

// 1. เช็คสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}

$user_phone = $_SESSION['phone'];

// ดึงข้อมูลผู้ใช้ล่าสุดจากฐานข้อมูลเพื่ออัปเดตชื่อให้ตรงกับโปรไฟล์ที่เพิ่งแก้
$query_user = $conn->query("SELECT firstname, lastname FROM users WHERE phone = '$user_phone'");
if ($query_user && $query_user->num_rows > 0) {
    $user_data = $query_user->fetch_assoc();
    $display_name = $user_data['firstname']; // โชว์แค่ชื่อจริง
    // ถ้าอยากให้โชว์ทั้งชื่อและนามสกุล ให้ลบคอมเมนต์บรรทัดล่างนี้แทน
    // $display_name = $user_data['firstname'] . ' ' . $user_data['lastname'];
} else {
    $display_name = $_SESSION['fname'] ?? 'ผู้ใช้งาน';
}

// 2. ดึงคะแนนสะสมทั้งหมดจาก waste_logs
$sql_total = "SELECT SUM(points) as total FROM waste_logs WHERE phone = '$user_phone'";
$res_total = $conn->query($sql_total);
$total_points = ($res_total) ? $res_total->fetch_assoc()['total'] : 0;

// 3. ดึงคะแนนที่แลกแล้วจาก redemptions
$redeemed_points = 0;
$sql_check_table = "SHOW TABLES LIKE 'redemptions'";
$table_exists = $conn->query($sql_check_table);

if ($table_exists->num_rows > 0) {
    $sql_redeemed = "SELECT SUM(points_used) as redeemed FROM redemptions WHERE phone = '$user_phone' AND status != 'ยกเลิก'";
    $res_redeemed = $conn->query($sql_redeemed);
    if ($res_redeemed) {
        $redeemed_points = $res_redeemed->fetch_assoc()['redeemed'] ?? 0;
    }
}

// 4. คำนวณคะแนนคงเหลือ
$current_points = ($total_points ?? 0) - ($redeemed_points ?? 0);

// 5. คำนวณผลกระทบสิ่งแวดล้อมส่วนบุคคล
$user_co2_saved = 0;
$sql_user_env = "SELECT waste_type, SUM(points) as total_pts FROM waste_logs WHERE phone = '$user_phone' GROUP BY waste_type";
$res_user_env = $conn->query($sql_user_env);

if ($res_user_env) {
    while ($row = $res_user_env->fetch_assoc()) {
        $pts = (float)$row['total_pts'];
        $kg = $pts / 10; 
        $type = strtolower(trim($row['waste_type']));
        
        if (strpos($type, 'plastic') !== false || strpos($type, 'พลาสติก') !== false) {
            $user_co2_saved += ($kg * 1.5);
        } elseif (strpos($type, 'paper') !== false || strpos($type, 'กระดาษ') !== false) {
            $user_co2_saved += ($kg * 0.9);
        } elseif (strpos($type, 'metal') !== false || strpos($type, 'โลหะ') !== false) {
            $user_co2_saved += ($kg * 4.0);
        } else {
            $user_co2_saved += ($kg * 0.5);
        }
    }
}
$user_trees = $user_co2_saved / 9;

// 6. ดึงประวัติรายการทิ้งขยะล่าสุด
$sql_history = "SELECT * FROM waste_logs WHERE phone = '$user_phone' ORDER BY created_at DESC";
$history_result = $conn->query($sql_history);

// 7. ดึงข้อมูลของรางวัลที่ผู้ใช้คนนี้กดแลกไป
$my_rewards = [];
$res_my_rewards = $conn->query("SELECT r.status, r.points_used, r.redeemed_at, rw.reward_name, rw.image_path 
                                FROM redemptions r JOIN rewards rw ON r.reward_id = rw.id 
                                WHERE r.phone = '$user_phone' ORDER BY r.redeemed_at DESC LIMIT 5");
if ($res_my_rewards) {
    while ($row = $res_my_rewards->fetch_assoc()) { $my_rewards[] = $row; }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iWasteBank - แดชบอร์ดผู้ใช้งาน</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { background-color: #f7f9fc; font-family: 'Sarabun', sans-serif; color: #333; }
        .navbar { background-color: #ffffff; box-shadow: 0 2px 15px rgba(0,0,0,0.04); }
        .wallet-card { background: linear-gradient(135deg, #104A33 0%, #2ecc71 100%); border-radius: 24px; color: white; padding: 30px; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(46, 204, 113, 0.3); margin-bottom: 20px; }
        .wallet-card::before { content: ''; position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; }
        .wallet-card::after { content: ''; position: absolute; bottom: -30px; right: 50px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%; }
        
        .action-menu { text-align: center; text-decoration: none; color: #333; display: flex; flex-direction: column; align-items: center; gap: 10px; transition: 0.3s; }
        .action-menu:hover { transform: translateY(-5px); }
        .action-icon { width: 65px; height: 65px; border-radius: 20px; display: flex; justify-content: center; align-items: center; font-size: 24px; box-shadow: 0 8px 15px rgba(0,0,0,0.05); background: white; transition: 0.3s; }
        .action-menu:hover .action-icon { box-shadow: 0 12px 20px rgba(0,0,0,0.1); }
        .icon-redeem { color: #fff; background: linear-gradient(135deg, #FF512F 0%, #DD2476 100%); }
        .icon-report { color: #e74c3c; }
        .icon-rank { color: #f1c40f; } 
        .icon-profile { color: #9b59b6; }
        
        .stat-mini-card { background: white; border-radius: 20px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); height: 100%; display: flex; align-items: center; gap: 15px; }
        .stat-icon-mini { width: 45px; height: 45px; border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 20px; }
        .panel-box { background: white; border-radius: 24px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .reward-ticket { display: flex; align-items: center; padding: 15px; border: 1px solid #eee; border-radius: 15px; margin-bottom: 10px; background: #fff; }
        .reward-ticket-img { width: 60px; height: 60px; object-fit: cover; border-radius: 12px; margin-right: 15px; background: #f8f9fa; }
        .table-custom th { color: #95a5a6; font-weight: 600; border-top: none; background: transparent; }
        .table-custom td { vertical-align: middle; border-bottom: 1px solid #f8f9fa; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold text-success" href="#"><i class="fas fa-leaf me-2"></i>iWasteBank</a>
        <div class="dropdown">
            <button class="btn btn-light rounded-pill border-0 shadow-sm dropdown-toggle px-3 py-2 fw-bold text-dark" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle text-success me-1 fs-5 align-middle"></i> <?php echo htmlspecialchars($display_name); ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-2" style="border-radius: 15px;">
                <li><a class="dropdown-item py-2 fw-bold text-dark" href="profile.php"><i class="fas fa-user-cog text-primary me-2"></i>จัดการโปรไฟล์</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 text-danger fw-bold" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <div class="row g-4 mb-5">
        <!-- 1. Hero Wallet Card -->
        <div class="col-lg-5">
            <div class="wallet-card h-100 d-flex flex-column justify-content-center">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <p class="mb-0 text-white-50 fw-bold">สวัสดี, ฮีโร่รักษ์โลก</p>
                        <h4 class="fw-bold mb-0"><?php echo htmlspecialchars($display_name); ?></h4>
                    </div>
                    <!-- ปุ่มเปิด QR Code บัตรสมาชิก -->
                    <button class="btn btn-light bg-opacity-25 text-white border-0 rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#qrModal">
                        <i class="fas fa-qrcode me-2"></i>บัตรของฉัน
                    </button>
                </div>
                
                <div class="mt-2">
                    <p class="mb-1 text-white-50 small text-uppercase fw-bold">แต้มสะสมที่ใช้ได้</p>
                    <div class="d-flex align-items-baseline gap-2">
                        <h1 class="display-4 fw-bolder mb-0"><?php echo number_format($current_points); ?></h1>
                        <span class="fs-5 opacity-75">แต้ม</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Quick Actions Menu -->
        <div class="col-lg-7">
            <div class="panel-box h-100 d-flex flex-column justify-content-center">
                <h6 class="fw-bold text-dark mb-4">เมนูด่วน (Quick Actions)</h6>
                <div class="row g-3 justify-content-center">
                    <div class="col-6 col-md-3">
                        <a href="redeem_list.php" class="action-menu">
                            <div class="action-icon icon-redeem"><i class="fas fa-gift"></i></div>
                            <span class="fw-bold">แลกของรางวัล</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <!-- ลิงก์ไปหน้า Leaderboard -->
                        <a href="leaderboard.php" class="action-menu">
                            <div class="action-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-trophy"></i></div>
                            <span class="fw-bold">จัดอันดับ</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="report_list.php" class="action-menu">
                            <div class="action-icon icon-report"><i class="fas fa-headset"></i></div>
                            <span class="fw-bold">แจ้งปัญหา</span>
                        </a>
                    </div>
                    <div class="col-6 col-md-3">
                        <a href="profile.php" class="action-menu">
                            <div class="action-icon icon-profile"><i class="fas fa-user-edit"></i></div>
                            <span class="fw-bold">โปรไฟล์</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 3. My Rewards (คูปองรางวัลของฉัน) -->
    <div class="row g-4 mb-5">
        <div class="col-12">
            <div class="panel-box bg-white">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-ticket-alt text-danger me-2"></i>ของรางวัลของฉัน</h5>
                    <a href="redeem_list.php" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold">แคตตาล็อกทั้งหมด</a>
                </div>

                <div class="row g-3">
                    <?php if (empty($my_rewards)): ?>
                        <div class="col-12 text-center py-4 text-muted">
                            <i class="fas fa-box-open fa-3x mb-2 opacity-25"></i>
                            <p class="mb-0">คุณยังไม่มีประวัติการแลกของรางวัล <br>ไปที่แคตตาล็อกเพื่อแลกเลย!</p>
                        </div>
                    <?php else: foreach ($my_rewards as $reward): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="reward-ticket shadow-sm">
                                <?php if (!empty($reward['image_path'])): ?>
                                    <img src="uploads/rewards/<?php echo htmlspecialchars($reward['image_path']); ?>" class="reward-ticket-img">
                                <?php else: ?>
                                    <div class="reward-ticket-img d-flex align-items-center justify-content-center text-muted">
                                        <i class="fas fa-gift fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-1 text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($reward['reward_name']); ?></h6>
                                    <div class="small text-muted mb-2"><i class="far fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($reward['redeemed_at'])); ?></div>
                                    
                                    <?php if ($reward['status'] == 'รอรับของ'): ?>
                                        <span class="badge bg-warning text-dark rounded-pill"><i class="fas fa-clock me-1"></i>รอติดต่อรับของ</span>
                                    <?php elseif ($reward['status'] == 'รับของแล้ว'): ?>
                                        <span class="badge bg-success rounded-pill"><i class="fas fa-check me-1"></i>รับของแล้ว</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger rounded-pill"><i class="fas fa-times me-1"></i>ยกเลิกแล้ว</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Eco Impact & Stats -->
    <h5 class="fw-bold text-dark mb-3"><i class="fas fa-globe-asia text-success me-2"></i>ผลกระทบของคุณ (Eco Impact)</h5>
    <div class="row g-3 mb-5">
        <div class="col-6 col-md-3">
            <div class="stat-mini-card">
                <div class="stat-icon-mini bg-success bg-opacity-10 text-success"><i class="fas fa-cloud"></i></div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark"><?php echo number_format($user_co2_saved, 2); ?> <span class="fs-6 fw-normal text-muted">kg</span></h5>
                    <div class="small text-muted">ลด CO₂</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini-card">
                <div class="stat-icon-mini bg-primary bg-opacity-10 text-primary"><i class="fas fa-tree"></i></div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark"><?php echo number_format($user_trees, 1); ?> <span class="fs-6 fw-normal text-muted">ต้น</span></h5>
                    <div class="small text-muted">ปลูกต้นไม้</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini-card">
                <div class="stat-icon-mini bg-info bg-opacity-10 text-info"><i class="fas fa-star"></i></div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark"><?php echo number_format($total_points); ?></h5>
                    <div class="small text-muted">สะสมทั้งหมด</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini-card">
                <div class="stat-icon-mini bg-warning bg-opacity-10 text-warning"><i class="fas fa-ticket-alt"></i></div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark"><?php echo number_format($redeemed_points); ?></h5>
                    <div class="small text-muted">ใช้แลกของแล้ว</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. History Table -->
    <div id="historySection" class="panel-box">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0 fw-bold"><i class="fas fa-list-ul text-secondary me-2"></i>ประวัติการทิ้งขยะล่าสุด</h5>
        </div>

        <div class="table-responsive">
            <table id="logsTable" class="table table-custom table-hover w-100">
                <thead>
                    <tr>
                        <th>วันที่-เวลา</th>
                        <th>ประเภทขยะ</th>
                        <th>คะแนน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($history_result): ?>
                        <?php while($row = $history_result->fetch_assoc()): ?>
                        <tr>
                            <td class="align-middle text-muted small"><i class="far fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                            <td class="align-middle">
                                <?php 
                                    $badgeColor = 'bg-secondary';
                                    $type = strtolower($row['waste_type']);
                                    if(strpos($type, 'plastic') !== false || strpos($type, 'พลาสติก') !== false) $badgeColor = 'bg-info text-dark';
                                    if(strpos($type, 'paper') !== false || strpos($type, 'กระดาษ') !== false) $badgeColor = 'bg-warning text-dark';
                                    if(strpos($type, 'metal') !== false || strpos($type, 'โลหะ') !== false) $badgeColor = 'bg-dark text-white';
                                ?>
                                <span class="badge <?php echo $badgeColor; ?> bg-opacity-25 border px-3 py-2 rounded-pill fw-bold">
                                    <?php echo strtoupper($row['waste_type']); ?>
                                </span>
                            </td>
                            <td class="align-middle fw-bold text-success fs-5">+<?php echo number_format($row['points']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal แสดงบัตรสมาชิก QR Code -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 shadow-lg text-center p-4">
      <div class="modal-header border-0 pb-0 justify-content-center position-relative">
        <h5 class="modal-title fw-bold text-success"><i class="fas fa-id-card me-2"></i>บัตรสมาชิกดิจิทัล</h5>
        <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pb-2">
        <p class="text-muted small mb-4">โชว์ QR Code นี้ให้แอดมินสแกน หรือใช้สแกนที่ตู้ทิ้งขยะ</p>
        
        <!-- ใช้ API สร้าง QR Code จากเบอร์โทรศัพท์ -->
        <div class="bg-white p-3 rounded-4 shadow-sm d-inline-block border">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=http://192.168.88.238/iWasteBank/admin_manage_redeems.php?search=<?php echo $user_phone; ?>&color=104A33" alt="QR Code" class="img-fluid">
        </div>
        
        <h4 class="fw-bold mt-4 mb-0"><?php echo htmlspecialchars($display_name); ?></h4>
        <div class="text-muted fs-5 tracking-widest mt-1"><i class="fas fa-phone-alt me-2 fs-6"></i><?php echo substr($user_phone, 0, 3)."-".substr($user_phone, 3, 3)."-".substr($user_phone, 6, 4); ?></div>
      </div>
      <div class="modal-footer border-0 justify-content-center pt-0">
        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-4 py-2 fs-6">แต้มสะสม: <?php echo number_format($current_points); ?></span>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#logsTable').DataTable({
            language: { search: "", searchPlaceholder: "ค้นหาประวัติ...", lengthMenu: "แสดง _MENU_ รายการ", info: "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ", paginate: { first: "หน้าแรก", last: "หน้าสุดท้าย", next: "ถัดไป", previous: "ก่อนหน้า" } },
            order: [[0, 'desc']], responsive: true, pageLength: 5,
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
        });
        $('.dataTables_filter input').addClass('form-control rounded-pill px-3').css('width', '200px');
    });
</script>
</body>
</html>