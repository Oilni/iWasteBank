<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = "";
$error = "";

// 1. จัดการการกดบันทึกราคาใหม่
if (isset($_POST['update_prices'])) {
    $prices = $_POST['prices'] ?? [];
    
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE waste_prices SET price_per_kg = ?, updated_at = CURRENT_TIMESTAMP WHERE waste_type_key = ?");
        foreach ($prices as $key => $price) {
            $p = (float)$price;
            $k = $conn->real_escape_string($key);
            $stmt->bind_param("ds", $p, $k);
            $stmt->execute();
        }
        $conn->commit();
        $msg = "บันทึกราคาอ้างอิงใหม่เรียบร้อยแล้ว";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
    }
}

// 2. ดึงราคาปัจจุบันมาแสดง
$market_prices = [];
$updated_times = [];
$res = $conn->query("SELECT * FROM waste_prices");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $market_prices[$row['waste_type_key']] = (float)$row['price_per_kg'];
        $updated_times[$row['waste_type_key']] = $row['updated_at'];
    }
}

// ข้อมูลเริ่มต้นกรณีตารางว่าง
$default_keys = ['plastic', 'paper', 'metal', 'other'];
foreach ($default_keys as $key) {
    if (!isset($market_prices[$key])) {
        $market_prices[$key] = 0.00;
        $updated_times[$key] = date('Y-m-d H:i:s');
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งราคาขยะ - Admin iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
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

        .wrapper { display: flex; height: 100vh; overflow: hidden; }

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

        .sidebar-menu { padding: 1rem 0; overflow-y: auto; flex-grow: 1; }
        .nav-item { margin: 0.3rem 1rem; }
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

        .sidebar-footer { padding: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .main-content { flex-grow: 1; padding: 2rem; overflow-y: auto; height: 100vh; }
        
        .panel-card {
            background: white;
            border: none;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .table-custom th {
            border-top: none;
            color: #95a5a6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            background: #f8f9fa;
        }
        .table-custom td {
            vertical-align: middle;
            border-bottom: 1px solid #f1f2f6;
            padding: 15px 10px;
        }

        .type-icon {
            width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px;
        }
        .icon-plastic { background: #e0f7fa; color: #00bcd4; }
        .icon-paper { background: #fff8e1; color: #ffc107; }
        .icon-metal { background: #eceff1; color: #607d8b; }
        .icon-other { background: #f5f5f5; color: #9e9e9e; }

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
            <!-- เมนูของระบบใหม่ -->
            <div class="nav-item"><a href="admin_manage_redeems.php" class="nav-link"><i class="fas fa-gift"></i> รายการแลกของรางวัล</a></div>
            <div class="nav-item"><a href="admin_manage_users.php" class="nav-link"><i class="fas fa-users"></i> จัดการสมาชิก</a></div>
            <div class="nav-item"><a href="admin_waste_logs.php" class="nav-link"><i class="fas fa-list-ul"></i> ประวัติทั้งหมด</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-file-alt"></i> รายงานข้อมูล</a></div>
            <div class="nav-item"><a href="trends.php" class="nav-link"><i class="fas fa-chart-line"></i> แนวโน้มข้อมูล</a></div>
            <div class="nav-item"><a href="telegram_logs.php" class="nav-link"><i class="fas fa-bell"></i> ประวัติ Telegram</a></div>

            <div class="px-4 text-uppercase small fw-bold mb-2 opacity-50 mt-4">ตั้งค่าระบบ</div>
            <div class="nav-item"><a href="admin_manage_waste_types.php" class="nav-link"><i class="fas fa-coins"></i> ตั้งค่าคะแนน</a></div>
            <div class="nav-item"><a href="admin_manage_prices.php" class="nav-link active"><i class="fas fa-money-bill-wave"></i> ตั้งราคาขยะ</a></div>
            <!-- เมนูคลังของรางวัล (แทนเรทแลกเงิน) -->
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

        <div class="d-flex align-items-center gap-3 mb-4">
            <i class="fas fa-money-bill-wave text-success fs-1"></i>
            <div>
                <h3 class="fw-bold mb-0 text-dark">ตั้งราคาขยะ (Market Price)</h3>
                <p class="text-muted mb-0">ราคาที่ตั้งไว้ที่นี่จะถูกนำไปใช้อ้างอิงในการคำนวณประเมินรายได้ของระบบ (หน้า Dashboard)</p>
            </div>
        </div>

        <?php if($msg): ?>
            <div class="alert alert-success rounded-4 shadow-sm border-0"><i class="fas fa-check-circle me-2"></i><?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger rounded-4 shadow-sm border-0"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="panel-card border-top border-warning border-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-money-bill-wave text-warning me-2"></i>อัปเดตราคาตามประเภทขยะ</h5>
            </div>

            <form method="POST">
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th>ประเภทขยะ</th>
                                <th>ราคาต่อกิโลกรัม (บาท)</th>
                                <th class="text-end">อัปเดตล่าสุด</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Plastic -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="type-icon icon-plastic"><i class="fas fa-bottle-water"></i></div>
                                        <div>
                                            <div class="fw-bold text-dark">พลาสติก</div>
                                            <div class="small text-muted">KEY: PLASTIC</div>
                                        </div>
                                    </div>
                                </td>
                                <td style="width: 30%;">
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="prices[plastic]" class="form-control text-success fw-bold" value="<?php echo number_format($market_prices['plastic'], 2, '.', ''); ?>" required>
                                        <span class="input-group-text bg-light">฿</span>
                                    </div>
                                </td>
                                <td class="text-end text-muted small">
                                    <i class="far fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($updated_times['plastic'])); ?>
                                </td>
                            </tr>
                            
                            <!-- Paper -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="type-icon icon-paper"><i class="fas fa-box"></i></div>
                                        <div>
                                            <div class="fw-bold text-dark">กระดาษ</div>
                                            <div class="small text-muted">KEY: PAPER</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="prices[paper]" class="form-control text-success fw-bold" value="<?php echo number_format($market_prices['paper'], 2, '.', ''); ?>" required>
                                        <span class="input-group-text bg-light">฿</span>
                                    </div>
                                </td>
                                <td class="text-end text-muted small">
                                    <i class="far fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($updated_times['paper'])); ?>
                                </td>
                            </tr>

                            <!-- Metal -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="type-icon icon-metal"><i class="fas fa-cube"></i></div>
                                        <div>
                                            <div class="fw-bold text-dark">โลหะ/กระป๋อง</div>
                                            <div class="small text-muted">KEY: METAL</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="prices[metal]" class="form-control text-success fw-bold" value="<?php echo number_format($market_prices['metal'], 2, '.', ''); ?>" required>
                                        <span class="input-group-text bg-light">฿</span>
                                    </div>
                                </td>
                                <td class="text-end text-muted small">
                                    <i class="far fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($updated_times['metal'])); ?>
                                </td>
                            </tr>

                            <!-- Other -->
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="type-icon icon-other"><i class="fas fa-trash"></i></div>
                                        <div>
                                            <div class="fw-bold text-dark">ขยะทั่วไป</div>
                                            <div class="small text-muted">KEY: OTHER</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="prices[other]" class="form-control text-success fw-bold" value="<?php echo number_format($market_prices['other'], 2, '.', ''); ?>" required>
                                        <span class="input-group-text bg-light">฿</span>
                                    </div>
                                </td>
                                <td class="text-end text-muted small">
                                    <i class="far fa-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($updated_times['other'])); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <span class="small text-muted"><i class="fas fa-info-circle text-primary me-1"></i>มูลค่าที่ถูกประเมินจะแสดงผลในหน้า Dashboard</span>
                    <button type="submit" name="update_prices" class="btn btn-warning fw-bold rounded-pill px-4 shadow-sm">
                        <i class="fas fa-save me-2"></i>บันทึกราคาใหม่
                    </button>
                </div>
            </form>

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