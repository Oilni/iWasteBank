<?php
require_once 'config.php';

// 1. เช็คสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 2. รับค่า ID และตรวจสอบข้อมูล
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admin_waste_logs.php");
    exit();
}

$id = $conn->real_escape_string($_GET['id']);
$msg = ""; 

// --- ส่วนของการบันทึกข้อมูล (Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_log'])) {
    $new_type = $conn->real_escape_string($_POST['waste_type']);
    $new_points = (int)$_POST['points'];

    $update_sql = "UPDATE waste_logs SET waste_type = '$new_type', points = '$new_points' WHERE id = '$id'";
    
    if ($conn->query($update_sql)) {
        $msg = "<div class='alert alert-success shadow-sm rounded-4 animate__animated animate__fadeIn'><i class='fas fa-check-circle me-2'></i>บันทึกการแก้ไขเรียบร้อยแล้ว</div>";
    } else {
        $msg = "<div class='alert alert-danger shadow-sm rounded-4'><i class='fas fa-times-circle me-2'></i>เกิดข้อผิดพลาด: " . $conn->error . "</div>";
    }
}

// 3. ดึงข้อมูลรายการปัจจุบัน (Join users เพื่อโชว์ข้อมูลเหมือนหน้ารายละเอียด)
$sql = "SELECT w.*, u.firstname, u.lastname, u.email 
        FROM waste_logs w 
        LEFT JOIN users u ON w.phone = u.phone 
        WHERE w.id = '$id'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    header("Location: admin_waste_logs.php");
    exit();
}
$data = $result->fetch_assoc();

// 4. ดึงรายการประเภทขยะจากตาราง waste_types
$waste_types_res = $conn->query("SELECT * FROM waste_types ORDER BY id ASC");
$type_points = [];

// --- ตั้งค่า Path รูปภาพ ---
$image_folder = "uploads/"; 
$full_image_path = $image_folder . $data['image_path'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขรายการ #<?php echo $id; ?> - iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        :root {
            --sidebar-bg: #104A33;
            --sidebar-hover: #196D4C;
            --main-bg: #F4F9F6;
            --card-radius: 1.25rem;
        }

        body {
            background-color: var(--main-bg);
            font-family: 'Sarabun', sans-serif;
            color: #333;
            overflow-x: hidden;
        }

        .wrapper {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            background-image: linear-gradient(180deg, #0e3b29 0%, #176142 100%);
            color: white;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 1.5rem 1.5rem;
            font-size: 1.4rem;
            font-weight: 700;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-menu {
            padding: 1rem 0;
            overflow-y: auto;
            flex-grow: 1;
        }

        .nav-item {
            margin: 0.3rem 1rem;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.7);
            padding: 0.8rem 1rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none;
        }

        .nav-link:hover, .nav-link.active {
            background-color: var(--sidebar-hover);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Main Content Styling */
        .main-content {
            flex-grow: 1;
            padding: 2rem;
            overflow-y: auto;
            height: 100vh;
        }

        /* Page Specific Styling */
        .card-edit { border: none; border-radius: var(--card-radius); box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; }
        .edit-header { background: linear-gradient(135deg, #198754 0%, #20c997 100%); color: white; padding: 20px; }
        .info-label { color: #6c757d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
        .info-value { font-weight: 700; color: #333; margin-bottom: 20px; }
        .waste-img-container { background: #eaf7f0; border-radius: 15px; overflow: hidden; min-height: 200px; display: flex; align-items: center; justify-content: center; border: 1px dashed #a3d7bc; }
        .waste-img { width: 100%; height: auto; object-fit: contain; max-height: 300px; }
        .form-section { background: #fff; border-radius: 15px; padding: 20px; border: 1px solid #e0ebe4; }
        
        .border-green { border-color: #198754 !important; }
        .text-green { color: #104A33 !important; }

        /* โค้ด CSS สำหรับมือถือ */
        .mobile-toggle-btn { display: none; }
        @media (max-width: 768px) {
            .sidebar { position: fixed !important; left: -300px; top: 0; height: 100vh; z-index: 1050; box-shadow: 5px 0 15px rgba(0,0,0,0.2); transition: left 0.3s ease-in-out !important; }
            .sidebar.show { left: 0 !important; }
            .main-content { padding: 1rem; width: 100%; }
            .mobile-toggle-btn { display: block; } 
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1040; backdrop-filter: blur(2px); }
            .sidebar-overlay.show { display: block; }
        }
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
            <div class="nav-item"><a href="admin_manage_reports.php" class="nav-link"><i class="fas fa-tools"></i> แจ้งปัญหา</a></div>
            <div class="nav-item"><a href="admin_manage_redeems.php" class="nav-link"><i class="fas fa-gift"></i> รายการแลกของรางวัล</a></div>
            <div class="nav-item"><a href="admin_manage_users.php" class="nav-link"><i class="fas fa-users"></i> จัดการสมาชิก</a></div>
            <div class="nav-item"><a href="admin_waste_logs.php" class="nav-link active"><i class="fas fa-list-ul"></i> ประวัติทั้งหมด</a></div>
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
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <div class="mobile-toggle-btn mb-4">
            <div class="d-flex align-items-center gap-3">
                <button id="openSidebar" class="btn btn-white bg-white shadow-sm border-0 px-3 py-2 rounded-3">
                    <i class="fas fa-bars text-success fs-4"></i>
                </button>
                <h5 class="fw-bold mb-0 text-success"><i class="fas fa-leaf me-2"></i>iWasteBank</h5>
            </div>
        </div>

        <div class="container-fluid px-0">
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <a href="admin_waste_logs.php" class="btn btn-light shadow-sm rounded-pill text-secondary fw-bold">
                    <i class="fas fa-arrow-left me-1"></i> ย้อนกลับ
                </a>
                <span class="badge bg-secondary rounded-pill px-3 py-2">ID รายการ: #<?php echo $id; ?></span>
            </div>

            <?php echo $msg; ?>

            <div class="card card-edit bg-white shadow-sm">
                <div class="edit-header d-flex align-items-center gap-2">
                    <i class="fas fa-edit fs-4"></i>
                    <h4 class="mb-0 fw-bold">แก้ไขและตรวจสอบรายการ</h4>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <div class="row g-5">
                        <div class="col-md-5 border-end-md pe-md-4">
                            <h6 class="fw-bold text-green mb-4 border-bottom pb-2">
                                <i class="fas fa-user-circle me-2 text-success"></i> ข้อมูลสมาชิก
                            </h6>
                            
                            <div class="info-label">ชื่อ-นามสกุล</div>
                            <div class="info-value"><?php echo htmlspecialchars(($data['firstname'] ?? 'ไม่ระบุ') . ' ' . ($data['lastname'] ?? '')); ?></div>
                            
                            <div class="info-label">เบอร์โทรศัพท์</div>
                            <div class="info-value text-success"><i class="fas fa-phone-alt me-1 small"></i> <?php echo $data['phone']; ?></div>

                            <div class="info-label mb-2 mt-4">ภาพหลักฐาน</div>
                            <div class="waste-img-container mb-3 shadow-sm">
                                <?php if(!empty($data['image_path']) && file_exists($full_image_path)): ?>
                                    <img src="<?php echo $full_image_path; ?>" class="waste-img" alt="Waste Image">
                                <?php else: ?>
                                    <div class="text-center text-muted p-4">
                                        <i class="fas fa-image fa-3x mb-2 opacity-50"></i>
                                        <p class="small mb-0">ไม่พบไฟล์ภาพจากระบบ</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded-3 mt-4">
                                <span class="info-label mb-0">เวลาทำรายการ</span>
                                <span class="fw-bold text-dark"><i class="far fa-clock text-secondary me-1"></i> <?php echo date('d/m/Y H:i', strtotime($data['created_at'])); ?></span>
                            </div>
                        </div>

                        <div class="col-md-7 ps-md-4">
                            <form method="POST">
                                <div class="form-section shadow-sm mb-4">
                                    <h6 class="fw-bold text-green mb-4 border-bottom pb-2">
                                        <i class="fas fa-tools me-2 text-success"></i> ส่วนการปรับปรุงข้อมูล
                                    </h6>

                                    <div class="mb-4">
                                        <label class="form-label fw-bold text-dark">1. ปรับเปลี่ยนประเภทขยะ</label>
                                        <select name="waste_type" id="waste_type" class="form-select form-select-lg border-green" onchange="updatePoints()" required>
                                            <?php 
                                            while($wt = $waste_types_res->fetch_assoc()) {
                                                $selected = ($data['waste_type'] == $wt['waste_name']) ? "selected" : "";
                                                $type_points[$wt['waste_name']] = $wt['points_per_unit'];
                                                echo "<option value='".$wt['waste_name']."' $selected>".strtoupper($wt['waste_name'])." (".$wt['points_per_unit']." แต้ม/ชิ้น)</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-bold text-dark">2. คะแนนที่ได้รับ (แก้ไขได้)</label>
                                        <div class="input-group input-group-lg">
                                            <span class="input-group-text bg-success text-white border-success"><i class="fas fa-star"></i></span>
                                            <input type="number" name="points" id="points" class="form-control border-success fw-bold text-success" value="<?php echo $data['points']; ?>" required min="0">
                                        </div>
                                        <div class="form-text mt-2 text-muted small">
                                            <i class="fas fa-info-circle me-1 text-primary"></i> ระบุคะแนนสุทธิที่ต้องการให้สมาชิก (จะคำนวณจากประเภทขยะให้อัตโนมัติเมื่อเปลี่ยน)
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <button type="submit" name="update_log" class="btn btn-success w-100 py-3 rounded-pill fw-bold shadow-sm">
                                            <i class="fas fa-save me-2"></i> บันทึกการเปลี่ยนแปลง
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <a href="admin_waste_logs.php" class="btn btn-light border w-100 py-3 rounded-pill text-secondary fw-bold">ยกเลิก</a>
                                    </div>
                                </div>
                            </form>
                            
                            <div class="card mt-4 border border-warning bg-warning bg-opacity-10 rounded-4">
                                <div class="card-body small text-dark">
                                    <strong class="text-warning-emphasis"><i class="fas fa-exclamation-triangle me-1"></i> หมายเหตุระบบ:</strong><br>
                                    การแก้ไขข้อมูลในหน้านี้จะไปอัปเดตประวัติการทิ้งขยะ แต่จะ<strong>ไม่</strong>ส่งผลย้อนหลังต่อยอดแต้มรวมปัจจุบันของสมาชิกโดยอัตโนมัติ หากต้องการปรับยอดแต้มรวม (Balance) กรุณาปรับปรุงที่หน้า <a href="admin_manage_users.php" class="fw-bold text-success">จัดการสมาชิก</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const pointsData = <?php echo json_encode($type_points); ?>;

    function updatePoints() {
        const typeSelect = document.getElementById('waste_type');
        const pointsInput = document.getElementById('points');
        const selectedType = typeSelect.value;

        if (pointsData[selectedType] !== undefined) {
            pointsInput.value = pointsData[selectedType];
            
            // เพิ่ม Effect ให้รู้ว่าตัวเลขเปลี่ยน (โทนสีเขียว)
            pointsInput.style.backgroundColor = "#eaf7f0";
            pointsInput.style.transition = "background-color 0.3s";
            setTimeout(() => { pointsInput.style.backgroundColor = "#fff"; }, 500);
        }
    }

    // JavaScript สำหรับเมนูมือถือ
    document.addEventListener('DOMContentLoaded', function() {
        const openBtn = document.getElementById('openSidebar');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        if (openBtn && sidebar && overlay) {
            openBtn.addEventListener('click', function() {
                sidebar.classList.add('show');
                overlay.classList.add('show');
            });

            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }
    });
</script>

</body>
</html>