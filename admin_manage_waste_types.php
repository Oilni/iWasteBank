<?php
require_once 'config.php';

// 1. เช็คสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = "";

// 2. จัดการบันทึกการแก้ไขคะแนน (Update)
if (isset($_POST['update_type'])) {
    $id = $_POST['type_id'];
    $points = intval($_POST['points']);

    $stmt = $conn->prepare("UPDATE waste_types SET points_per_unit = ? WHERE id = ?");
    $stmt->bind_param("ii", $points, $id);
    if ($stmt->execute()) {
        $msg = "อัปเดตคะแนนเรียบร้อยแล้ว";
    }
}

// 3. จัดการเพิ่มประเภทขยะใหม่ (Insert)
if (isset($_POST['add_type'])) {
    $name = $conn->real_escape_string($_POST['waste_name']);
    $points = intval($_POST['points']);

    $stmt = $conn->prepare("INSERT INTO waste_types (waste_name, points_per_unit) VALUES (?, ?)");
    $stmt->bind_param("si", $name, $points);
    if ($stmt->execute()) {
        $msg = "เพิ่มประเภทขยะใหม่เรียบร้อยแล้ว";
    }
}

// 4. ดึงข้อมูลประเภทขยะทั้งหมด
$result = $conn->query("SELECT * FROM waste_types ORDER BY waste_name ASC");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าคะแนนขยะ - Admin iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #104A33;
            /* Dark Green */
            --sidebar-hover: #196D4C;
            --main-bg: #F4F9F6;
            /* Light Eco Green Background */
            --card-radius: 1.25rem;
        }

        body {
            background-color: var(--main-bg);
            font-family: 'Sarabun', sans-serif;
            color: #333;
            overflow-x: hidden;
        }

        /* Layout Structure */
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

        .nav-link:hover,
        .nav-link.active {
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

        /* Header */
        .page-header {
            margin-bottom: 2rem;
        }

        /* Panels / Tables */
        .panel-card {
            background: white;
            border: none;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            height: 100%;
        }

        .panel-title {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            color: #2C3E50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Custom Table */
        .table-custom th {
            border-top: none;
            color: #95a5a6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            background: #f8f9fa;
        }

        .table-custom td {
            vertical-align: middle;
            border-bottom: 1px solid #f1f2f6;
        }

        /* --- โค้ด CSS สำหรับมือถือ --- */
        .mobile-toggle-btn { display: none; }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed !important;
                left: -300px;
                top: 0;
                height: 100vh;
                z-index: 1050;
                box-shadow: 5px 0 15px rgba(0,0,0,0.2);
                transition: left 0.3s ease-in-out !important;
            }
            .sidebar.show { 
                left: 0 !important;
            }
            
            .main-content { padding: 1rem; width: 100%; }
            .mobile-toggle-btn { display: block; } 
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0; left: 0;
                width: 100vw; height: 100vh;
                background: rgba(0,0,0,0.5);
                z-index: 1040;
                backdrop-filter: blur(2px);
            }
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

                <!-- อัปเดตเมนูการแลกของรางวัล -->
                <div class="nav-item"><a href="admin_manage_redeems.php" class="nav-link"><i class="fas fa-gift"></i> รายการแลกของรางวัล</a></div>

                <div class="nav-item"><a href="admin_manage_users.php" class="nav-link"><i class="fas fa-users"></i> จัดการสมาชิก</a></div>
                <div class="nav-item"><a href="admin_waste_logs.php" class="nav-link"><i class="fas fa-list-ul"></i> ประวัติทั้งหมด</a></div>
                <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-file-alt"></i> รายงานข้อมูล</a></div>
                <div class="nav-item"><a href="trends.php" class="nav-link"><i class="fas fa-chart-line"></i> แนวโน้มข้อมูล</a></div>
                <div class="nav-item"><a href="telegram_logs.php" class="nav-link"><i class="fas fa-bell"></i> ประวัติ Telegram</a></div>

                <div class="px-4 text-uppercase small fw-bold mb-2 opacity-50 mt-4">ตั้งค่าระบบ</div>
                <div class="nav-item"><a href="admin_manage_waste_types.php" class="nav-link active"><i class="fas fa-coins"></i> ตั้งค่าคะแนน</a></div>
                <div class="nav-item"><a href="admin_manage_prices.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> ตั้งราคาขยะ</a></div>

                <!-- เพิ่มเมนูคลังของรางวัล -->
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

            <!-- --- เพิ่ม HTML สำหรับปุ่ม 3 ขีดและพื้นหลังสีดำตรงนี้ --- -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>
            <div class="mobile-toggle-btn mb-4">
                <div class="d-flex align-items-center gap-3">
                    <button id="openSidebar" class="btn btn-white bg-white shadow-sm border-0 px-3 py-2 rounded-3">
                        <i class="fas fa-bars text-success fs-4"></i>
                    </button>
                    <h5 class="fw-bold mb-0 text-success"><i class="fas fa-leaf me-2"></i>iWasteBank</h5>
                </div>
            </div>

            <!-- Header -->
            <div class="page-header">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <i class="fas fa-coins text-warning fs-1"></i>
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">ตั้งค่าคะแนนตามประเภทขยะ</h3>
                        <p class="text-muted mb-0">กำหนดเรทคะแนนที่ผู้ใช้งานจะได้รับจากการทิ้งขยะแต่ละประเภท</p>
                    </div>
                </div>

                <?php if ($msg): ?>
                    <div class="alert alert-success border-0 shadow-sm mb-4 alert-dismissible fade show rounded-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- ตารางแสดงประเภทขยะ -->
                    <div class="col-lg-8">
                        <div class="panel-card border-top border-success border-4">
                            <div class="panel-title">
                                <i class="fas fa-list text-success"></i> รายการประเภทขยะและคะแนน
                            </div>
                            <div class="table-responsive">
                                <table class="table table-custom align-middle">
                                    <thead>
                                        <tr>
                                            <th>ประเภทขยะ (Key)</th>
                                            <th class="text-center" style="width: 200px;">คะแนนต่อชิ้น/ครั้ง</th>
                                            <th class="text-center">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="bg-light text-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                            <?php
                                                            $icon = 'fa-trash';
                                                            if (strpos(strtolower($row['waste_name']), 'plastic') !== false) $icon = 'fa-bottle-water text-info';
                                                            if (strpos(strtolower($row['waste_name']), 'paper') !== false) $icon = 'fa-box text-warning';
                                                            if (strpos(strtolower($row['waste_name']), 'metal') !== false) $icon = 'fa-cube text-secondary';
                                                            ?>
                                                            <i class="fas <?php echo $icon; ?>"></i>
                                                        </div>
                                                        <div>
                                                            <span class="fw-bold text-dark fs-6"><?php echo $row['waste_name']; ?></span>
                                                            <div class="small text-muted">ID: #<?php echo $row['id']; ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <form action="" method="POST">
                                                    <input type="hidden" name="type_id" value="<?php echo $row['id']; ?>">
                                                    <td class="text-center">
                                                        <div class="input-group input-group-sm mx-auto shadow-sm" style="max-width: 130px;">
                                                            <input type="number" name="points" class="form-control text-center fw-bold text-success"
                                                                value="<?php echo $row['points_per_unit']; ?>" min="0">
                                                            <span class="input-group-text bg-white text-muted">แต้ม</span>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="submit" name="update_type" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm fw-bold">
                                                            <i class="fas fa-save me-1"></i> บันทึก
                                                        </button>
                                                    </td>
                                                </form>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4 p-3 bg-light rounded-3 text-muted small border">
                                <i class="fas fa-info-circle text-primary me-2"></i>
                                <b class="text-dark">หมายเหตุ:</b> การแก้ไขคะแนนที่นี่ จะมีผลกับรายการที่ทิ้ง <strong>หลังจากนี้</strong> เท่านั้น ประวัติการทิ้งย้อนหลังจะไม่ถูกเปลี่ยนแปลง
                            </div>
                        </div>
                    </div>

                    <!-- ฟอร์มเพิ่มประเภทขยะใหม่ -->
                    <div class="col-lg-4">
                        <div class="panel-card border-top border-primary border-4">
                            <div class="panel-title">
                                <i class="fas fa-plus-circle text-primary"></i> เพิ่มประเภทขยะใหม่
                            </div>
                            <form action="" method="POST">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-dark">ชื่อประเภท (เช่น paper, metal)</label>
                                    <input type="text" name="waste_name" class="form-control" placeholder="ระบุชื่อภาษาอังกฤษ/ไทย" required>
                                    <div class="form-text small"><i class="fas fa-exclamation-triangle text-warning me-1"></i>ต้องตรงกับที่ QR Code หรือ IoT ส่งมา</div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-dark">คะแนนเริ่มต้นที่จะได้รับ</label>
                                    <div class="input-group shadow-sm">
                                        <input type="number" name="points" class="form-control fw-bold text-primary" value="0" min="0" required>
                                        <span class="input-group-text bg-white">แต้ม</span>
                                    </div>
                                </div>
                                <button type="submit" name="add_type" class="btn btn-primary w-100 rounded-pill shadow-sm py-2 fw-bold">
                                    <i class="fas fa-plus me-1"></i> เพิ่มลงในระบบ
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- --- เพิ่ม JavaScript สำหรับปุ่ม 3 ขีด ตรงนี้ --- -->
    <script>
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