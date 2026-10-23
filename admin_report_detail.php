<?php
// เปิดการแสดง Error เพื่อตรวจสอบจุดติดขัด
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// 1. เช็คสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); exit();
}

// 2. รับค่า ID รายงาน
if (!isset($_GET['id']) || empty($_GET['id'])) { 
    header("Location: admin_manage_reports.php"); exit(); 
}

$report_id = (int)$_GET['id'];

// 3. ส่วนการอัปเดตสถานะ (Update Status Only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_report'])) {
    $new_status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $report_id);
    
    if ($stmt->execute()) {
        header("Location: admin_report_detail.php?id=$report_id&msg=success");
        exit();
    }
}

// 4. ดึงข้อมูลรายงานมาแสดงผล
$res = $conn->query("SELECT * FROM reports WHERE id = $report_id");
if (!$res || $res->num_rows == 0) {
    die("ไม่พบข้อมูลรายงาน ID: $report_id");
}
$report = $res->fetch_assoc();

// ดึงชื่อผู้แจ้งจากตาราง users
$u_phone = $report['phone'];
$u_res = $conn->query("SELECT firstname, lastname FROM users WHERE phone = '$u_phone' LIMIT 1");
$user = ($u_res && $u_res->num_rows > 0) ? $u_res->fetch_assoc() : ['firstname' => 'ไม่พบข้อมูลชื่อ', 'lastname' => ''];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดการแจ้งปัญหา #<?php echo $report_id; ?> - Admin iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #104A33; /* Dark Green */
            --sidebar-hover: #196D4C;
            --main-bg: #F4F9F6; /* Light Eco Green Background */
        }

        body { background-color: var(--main-bg); font-family: 'Sarabun', sans-serif; color: #333; overflow-x: hidden; }

        /* Layout Structure */
        .wrapper { display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 260px; background-color: var(--sidebar-bg); background-image: linear-gradient(180deg, #0e3b29 0%, #176142 100%); color: white; display: flex; flex-direction: column; transition: all 0.3s; z-index: 1000; }
        .sidebar-brand { padding: 1.5rem 1.5rem; font-size: 1.4rem; font-weight: 700; border-bottom: 1px solid rgba(255, 255, 255, 0.1); display: flex; align-items: center; gap: 10px; }
        .sidebar-menu { padding: 1rem 0; overflow-y: auto; flex-grow: 1; }
        .nav-item { margin: 0.3rem 1rem; }
        .nav-link { color: rgba(255, 255, 255, 0.7); padding: 0.8rem 1rem; border-radius: 10px; display: flex; align-items: center; gap: 12px; font-weight: 600; transition: 0.3s; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background-color: var(--sidebar-hover); color: white; transform: translateX(5px); }
        .sidebar-footer { padding: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .main-content { flex-grow: 1; padding: 2rem; overflow-y: auto; height: 100vh; }

        /* Card Styles */
        .card-detail { border: none; border-radius: 20px; box-shadow: 0 8px 25px rgba(0,0,0,0.04); }
        .label-text { color: #78909C; font-size: 0.9rem; margin-bottom: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .value-text { font-weight: 700; color: #2C3E50; margin-bottom: 20px; font-size: 1.1rem; }
        .img-evidence { max-width: 100%; border-radius: 12px; border: 2px dashed #cfd8dc; max-height: 400px; object-fit: contain; padding: 5px; background: #fff; }
        
        .btn-main { background: #198754; color: white; transition: 0.3s; border: none; }
        .btn-main:hover { background: #146c43; color: white; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3); }
        .form-select:focus { border-color: #198754; box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25); }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- ===== Sidebar ===== -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-leaf text-success bg-white rounded-circle p-2"></i>
            <div>
                <div class="lh-1">iWasteBank</div>
                <small class="fw-light fs-6 opacity-75">Admin System</small>
            </div>
        </div>

        <div class="sidebar-menu">
            <div class="px-4 text-uppercase small fw-bold mb-2 opacity-50 mt-2">เมนูหลัก</div>
            <div class="nav-item"><a href="admin.php" class="nav-link"><i class="fas fa-chart-pie"></i> Dashboard</a></div>
            <!-- ตั้งค่าให้แถบ แจ้งปัญหา เป็น Active -->
            <div class="nav-item"><a href="admin_manage_reports.php" class="nav-link active"><i class="fas fa-tools"></i> แจ้งปัญหา</a></div>
            <div class="nav-item"><a href="admin_manage_redeems.php" class="nav-link"><i class="fas fa-gift"></i> รายการแลกของรางวัล</a></div>
            <div class="nav-item"><a href="admin_manage_users.php" class="nav-link"><i class="fas fa-users"></i> จัดการสมาชิก</a></div>
            <div class="nav-item"><a href="admin_waste_logs.php" class="nav-link"><i class="fas fa-list-ul"></i> ประวัติทั้งหมด</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-file-alt"></i> รายงานข้อมูล</a></div>
            <div class="nav-item"><a href="trends.php" class="nav-link"><i class="fas fa-chart-line"></i> แนวโน้มข้อมูล</a></div>
            <div class="nav-item"><a href="telegram_logs.php" class="nav-link"><i class="fas fa-bell"></i> ประวัติ Telegram</a></div>

            <div class="px-4 text-uppercase small fw-bold mb-2 opacity-50 mt-4">ตั้งค่าระบบ</div>
            <div class="nav-item"><a href="admin_manage_waste_types.php" class="nav-link"><i class="fas fa-coins"></i> ตั้งค่าคะแนน</a></div>
            <div class="nav-item"><a href="admin_manage_prices.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> ตั้งราคาขยะ</a></div>
            <div class="nav-item"><a href="admin_manage_rewards.php" class="nav-link"><i class="fas fa-box-open"></i> จัดการคลังของรางวัล</a></div>
        </div>

        <div class="sidebar-footer">
            <div class="d-flex align-items-center gap-3 bg-white bg-opacity-10 p-3 rounded-3">
                <div class="bg-white text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div>
                    <div class="fw-bold fs-6">Admin User</div>
                    <a href="logout.php" class="text-white text-decoration-none small opacity-75 hover-opacity-100" onclick="return confirm('ยืนยันออกจากระบบ?');">ออกจากระบบ</a>
                </div>
            </div>
        </div>
    </aside>

    <!-- ===== Main Content ===== -->
    <main class="main-content">
        
        <!-- Header กลับหน้ารายการ -->
        <div class="page-header d-flex align-items-center gap-3 mb-4">
            <a href="admin_manage_reports.php" class="btn btn-white bg-white text-dark rounded-circle shadow-sm" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; transition: 0.3s;">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h3 class="fw-bold mb-1 text-dark">รายละเอียดการแจ้งปัญหา #<?php echo $report_id; ?></h3>
                <p class="text-muted mb-0">ตรวจสอบข้อมูลและอัปเดตสถานะปัญหาของผู้ใช้งาน</p>
            </div>
        </div>

        <!-- แจ้งเตือนเมื่ออัปเดตสำเร็จ -->
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
            <div class="alert alert-success border-0 shadow-sm mb-4 rounded-4 fw-bold">
                <i class="fas fa-check-circle me-2 fs-5 align-middle"></i>อัปเดตสถานะการดำเนินการเรียบร้อยแล้ว!
            </div>
        <?php endif; ?>

        <!-- เนื้อหารายละเอียด (รูปแบบเดียวกับที่ปรับให้ก่อนหน้านี้) -->
        <div class="row g-4">
            <!-- ฝั่งข้อมูลการแจ้งปัญหา -->
            <div class="col-lg-7">
                <div class="card card-detail p-4 p-md-5 bg-white h-100">
                    <h5 class="fw-bold text-success mb-4 border-bottom pb-3"><i class="fas fa-clipboard-list me-2"></i>ข้อมูลจากผู้ใช้งาน</h5>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="label-text"><i class="fas fa-user me-1"></i> ผู้แจ้งปัญหา</div>
                            <div class="value-text"><?php echo htmlspecialchars($user['firstname']." ".$user['lastname']); ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="label-text"><i class="fas fa-phone-alt me-1"></i> เบอร์โทรศัพท์</div>
                            <div class="value-text text-primary"><?php echo $report['phone']; ?></div>
                        </div>
                    </div>

                    <div class="label-text"><i class="fas fa-tag me-1"></i> หัวข้อเรื่อง</div>
                    <div class="value-text"><?php echo htmlspecialchars($report['subject']); ?></div>

                    <div class="label-text"><i class="fas fa-align-left me-1"></i> รายละเอียดปัญหา</div>
                    <div class="bg-light p-3 p-md-4 rounded-4 mb-4 text-dark shadow-sm" style="min-height: 120px; border-left: 4px solid #198754;">
                        <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                    </div>

                    <div class="label-text mb-3"><i class="fas fa-image me-1"></i> ภาพหลักฐานประกอบ:</div>
                    <div class="text-center bg-light rounded-4 p-3">
                        <?php if(!empty($report['evidence_image'])): ?>
                            <img src="uploads/<?php echo $report['evidence_image']; ?>" class="img-evidence shadow-sm">
                        <?php else: ?>
                            <div class="py-5 text-muted opacity-50">
                                <i class="fas fa-image fa-3x mb-3 d-block"></i>
                                ไม่มีภาพหลักฐานประกอบ
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ฝั่งอัปเดตสถานะ -->
            <div class="col-lg-5">
                <div class="card card-detail p-4 p-md-5 bg-white border-top border-success border-5 h-100">
                    <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-tools text-success me-2"></i>สถานะการดำเนินการ</h5>
                    <form action="" method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">อัปเดตสถานะปัจจุบัน</label>
                            <select name="status" class="form-select form-select-lg rounded-4 bg-light fw-bold text-dark border-0 shadow-sm">
                                <option value="กำลังดำเนินการ" <?php echo ($report['status'] == 'กำลังดำเนินการ') ? 'selected' : ''; ?>>⏳ กำลังดำเนินการ (In Progress)</option>
                                <option value="แก้ไขเสร็จสิ้น" <?php echo ($report['status'] == 'แก้ไขเสร็จสิ้น') ? 'selected' : ''; ?>>✅ แก้ไขเสร็จสิ้น (Resolved)</option>
                            </select>
                        </div>

                        <div class="p-3 bg-success bg-opacity-10 rounded-4 mb-4 small text-success fw-semibold">
                            <i class="fas fa-info-circle me-1"></i> เมื่อบันทึกแล้ว สถานะจะถูกเปลี่ยนในหน้าแดชบอร์ดของแอดมินและประวัติของผู้ใช้ทันที
                        </div>

                        <button type="submit" name="update_report" class="btn btn-main btn-lg w-100 rounded-pill shadow-sm fw-bold mt-2">
                            <i class="fas fa-save me-2"></i> บันทึกสถานะใหม่
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>