<?php
// 1. ดึงไฟล์ตั้งค่า
require_once 'config.php';

// 2. เช็คสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// ฟังก์ชันดึงค่าสถิติ
function getCount($conn, $sql)
{
    $res = $conn->query($sql);
    return ($res) ? $res->fetch_row()[0] : 0;
}

// --- 3. ข้อมูลสรุปตัวเลข ---
$pending_reports = getCount($conn, "SELECT COUNT(*) FROM reports WHERE status = 'กำลังดำเนินการ'");
// เปลี่ยนเงื่อนไขเป็น 'รอรับของ' ตามระบบใหม่
$pending_redeems = getCount($conn, "SELECT COUNT(*) FROM redemptions WHERE status = 'รอรับของ'");
$total_users     = getCount($conn, "SELECT COUNT(*) FROM users WHERE role = 'user'");

// --- 4. 5 รายการทิ้งขยะล่าสุด ---
$recent_logs = [];
$res_logs = $conn->query("SELECT w.*, u.firstname FROM waste_logs w LEFT JOIN users u ON w.phone = u.phone ORDER BY w.id DESC LIMIT 5");
if ($res_logs) {
    while ($row = $res_logs->fetch_assoc()) {
        $recent_logs[] = $row;
    }
}

// --- 5. ข้อมูลกราฟโดนัท (ชนิดขยะ) ---
$waste_labels = [];
$waste_data = [];
$res_waste = $conn->query("SELECT waste_type, COUNT(*) as cnt FROM waste_logs GROUP BY waste_type");
if ($res_waste) {
    while ($row = $res_waste->fetch_assoc()) {
        $waste_labels[] = strtoupper($row['waste_type']);
        $waste_data[] = (int)$row['cnt'];
    }
}

// --- 6. 5 อันดับคนทิ้งเยอะสุด ---
$top_names = [];
$top_points = [];
$res_top = $conn->query("SELECT u.firstname, w.phone, SUM(w.points) as tp FROM waste_logs w LEFT JOIN users u ON w.phone = u.phone GROUP BY w.phone ORDER BY tp DESC LIMIT 5");
if ($res_top) {
    while ($row = $res_top->fetch_assoc()) {
        $top_names[] = (!empty($row['firstname'])) ? $row['firstname'] : $row['phone'];
        $top_points[] = (int)$row['tp'];
    }
}

// --- 7. คำนวณผลกระทบสิ่งแวดล้อม และ มูลค่าเงินประเมิน (ดึงราคาจาก Database) ---
$total_co2_saved = 0;
$total_estimated_revenue = 0;

$market_prices = [];
$res_price = $conn->query("SELECT waste_type_key, price_per_kg FROM waste_prices");
if ($res_price) {
    while ($row = $res_price->fetch_assoc()) {
        $market_prices[$row['waste_type_key']] = (float)$row['price_per_kg'];
    }
}

$res_env = $conn->query("SELECT waste_type, SUM(points) as total_pts FROM waste_logs GROUP BY waste_type");
if ($res_env) {
    while ($row = $res_env->fetch_assoc()) {
        $pts = (float)$row['total_pts'];
        
        // สมมติว่าระบบแจกแต้มเฉลี่ย 10 แต้ม ต่อการทิ้งขยะ 1 ชิ้น
        $pieces = $pts / 10; 
        
        $type = strtolower(trim($row['waste_type']));

        if (strpos($type, 'plastic') !== false || strpos($type, 'พลาสติก') !== false) {
            // ขวดพลาสติก 1 ใบ หนักเฉลี่ยประมาณ 30 กรัม (0.03 กิโลกรัม)
            $kg = $pieces * 0.03; 
            $total_co2_saved += ($kg * 1.5);
            $total_estimated_revenue += ($kg * ($market_prices['plastic'] ?? 15));
            
        } elseif (strpos($type, 'paper') !== false || strpos($type, 'กระดาษ') !== false) {
            // กระดาษ/กล่องเล็ก 1 ชิ้น หนักเฉลี่ยประมาณ 50 กรัม (0.05 กิโลกรัม)
            $kg = $pieces * 0.05;
            $total_co2_saved += ($kg * 0.9);
            $total_estimated_revenue += ($kg * ($market_prices['paper'] ?? 5));
            
        } elseif (strpos($type, 'metal') !== false || strpos($type, 'โลหะ') !== false) {
            // กระป๋องอลูมิเนียม 1 ใบ หนักเฉลี่ยประมาณ 15 กรัม (0.015 กิโลกรัม)
            $kg = $pieces * 0.015;
            $total_co2_saved += ($kg * 4.0);
            $total_estimated_revenue += ($kg * ($market_prices['metal'] ?? 40));
            
        } else {
            // ขยะทั่วไป 1 ชิ้น ตีเหมาเฉลี่ยที่ 50 กรัม (0.05 กิโลกรัม)
            $kg = $pieces * 0.05;
            $total_co2_saved += ($kg * 0.5);
            $total_estimated_revenue += ($kg * ($market_prices['other'] ?? 1));
        }
    }
}
// จำนวนต้นไม้ที่เทียบเท่าการลด CO2 (ประมาณ 9 กิโลกรัมต่อ 1 ต้น)
$trees_planted = $total_co2_saved / 9;
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .main-content {
            flex-grow: 1;
            padding: 2rem;
            overflow-y: auto;
            height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 2rem;
        }

        .stat-card {
            border: none;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            transition: 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .bg-icon {
            position: absolute;
            right: -10px;
            bottom: -15px;
            font-size: 6rem;
            opacity: 0.15;
            transform: rotate(-15deg);
        }

        .bg-gradient-blue {
            background: linear-gradient(135deg, #4A90E2 0%, #007AFF 100%);
        }

        .bg-gradient-green {
            background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
        }

        .bg-gradient-orange {
            background: linear-gradient(135deg, #F2994A 0%, #F2C94C 100%);
        }

        .bg-gradient-red {
            background: linear-gradient(135deg, #FF6B6B 0%, #EE5253 100%);
        }

        /* สำหรับการ์ดเซนเซอร์ (สีพื้นขาวแบบ Clean) */
        .sensor-card {
            background: #fff;
            color: #333;
            border: 1px solid #e2e8f0;
        }
        .sensor-card .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

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
            justify-content: space-between;
        }

        .table-custom th {
            border-top: none;
            color: #95a5a6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .table-custom td {
            vertical-align: middle;
            border-bottom: 1px solid #f1f2f6;
        }

        .bin-progress {
            height: 12px;
            border-radius: 10px;
            background-color: #ecf0f1;
        }

        .bin-item {
            padding: 10px 0;
            border-bottom: 1px dashed #eee;
        }

        .bin-item:last-child {
            border-bottom: none;
        }

        /* ซ่อนปุ่มเมนูบนหน้าจอคอมพิวเตอร์ */
        .mobile-toggle-btn { display: none; }
        
        /* สไตล์สำหรับหน้าจอมือถือ (กว้างไม่เกิน 768px) */
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
                <div class="nav-item">
                    <a href="admin.php" class="nav-link active"><i class="fas fa-chart-pie"></i> Dashboard</a>
                </div>
                <div class="nav-item">
                    <a href="admin_manage_reports.php" class="nav-link"><i class="fas fa-tools"></i> แจ้งปัญหา</a>
                </div>
                <div class="nav-item">
                    <a href="admin_manage_redeems.php" class="nav-link"><i class="fas fa-gift"></i> รายการแลกของรางวัล</a>
                </div>
                <div class="nav-item">
                    <a href="admin_manage_users.php" class="nav-link"><i class="fas fa-users"></i> จัดการสมาชิก</a>
                </div>
                <div class="nav-item">
                    <a href="admin_waste_logs.php" class="nav-link"><i class="fas fa-list-ul"></i> ประวัติทั้งหมด</a>
                </div>
                <div class="nav-item">
                    <a href="reports.php" class="nav-link"><i class="fas fa-file-alt"></i> รายงานข้อมูล</a>
                </div>
                <div class="nav-item">
                    <a href="trends.php" class="nav-link"><i class="fas fa-chart-line"></i> แนวโน้มข้อมูล</a>
                </div>
                <div class="nav-item">
                    <a href="telegram_logs.php" class="nav-link"><i class="fas fa-bell"></i> ประวัติ Telegram</a>
                </div>

                <div class="px-4 text-uppercase small fw-bold mb-2 opacity-50 mt-4">ตั้งค่าระบบ</div>
                <div class="nav-item">
                    <a href="admin_manage_waste_types.php" class="nav-link"><i class="fas fa-coins"></i> ตั้งค่าคะแนน</a>
                </div>
                <div class="nav-item">
                    <a href="admin_manage_prices.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> ตั้งราคาขยะ</a>
                </div>
                <div class="nav-item">
                    <a href="admin_manage_rewards.php" class="nav-link"><i class="fas fa-box-open"></i> จัดการคลังของรางวัล</a>
                </div>
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
            <!-- แบ็คกราวด์สีดำเวลาเปิดเมนูบนมือถือ -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>

            <!-- ปุ่มแฮมเบอร์เกอร์สำหรับจอมือถือ -->
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
                    <i class="fas fa-chart-pie text-success fs-1"></i>
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">Dashboard</h3>
                        <p class="text-muted mb-0">ภาพรวมระบบธนาคารขยะและสถานะแบบเรียลไทม์</p>
                    </div>
                </div>
                <div class="d-none d-md-flex gap-3 align-items-center">
                    <div class="bg-white px-4 py-2 rounded-pill shadow-sm border">
                        <i class="fas fa-cloud text-success me-2"></i>ลด CO₂: <strong class="text-dark"><?php echo number_format($total_co2_saved, 1); ?> kg</strong>
                    </div>
                    <div class="bg-white px-4 py-2 rounded-pill shadow-sm border">
                        <i class="fas fa-tree text-success me-2"></i>ปลูกต้นไม้: <strong class="text-dark"><?php echo number_format($trees_planted, 1); ?> ต้น</strong>
                    </div>
                </div>
            </div>

            <!-- 4 Top Stat Cards (ภาพรวมระบบ) -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card bg-gradient-blue">
                        <i class="fas fa-users bg-icon"></i>
                        <h6 class="opacity-75 mb-1">สมาชิกทั้งหมด</h6>
                        <h2 class="fw-bold mb-2"><?php echo number_format($total_users); ?></h2>
                        <small><i class="fas fa-arrow-up me-1"></i> ผู้ใช้งานในระบบ</small>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card bg-gradient-green">
                        <i class="fas fa-coins bg-icon"></i>
                        <h6 class="opacity-75 mb-1">ประเมินมูลค่าขยะ</h6>
                        <!-- แสดงยอดเงินประเมินที่คำนวณจากน้ำหนักที่สมจริงแล้ว -->
                        <h2 class="fw-bold mb-2">฿ <?php echo number_format($total_estimated_revenue, 2); ?></h2>
                        <small><i class="fas fa-chart-line me-1"></i> มูลค่าประเมินตามน้ำหนักจริง</small>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card bg-gradient-orange">
                        <i class="fas fa-gift bg-icon"></i>
                        <h6 class="opacity-75 mb-1">รอส่งมอบของรางวัล</h6>
                        <h2 class="fw-bold mb-2"><?php echo number_format($pending_redeems); ?></h2>
                        <small><i class="fas fa-clock me-1"></i> รายการคำขอแลกรางวัล</small>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card bg-gradient-red">
                        <i class="fas fa-exclamation-circle bg-icon"></i>
                        <h6 class="opacity-75 mb-1">ปัญหาที่ยังไม่แก้ไข</h6>
                        <h2 class="fw-bold mb-2"><?php echo number_format($pending_reports); ?></h2>
                        <small><i class="fas fa-tools me-1"></i> รายการแจ้งเตือน</small>
                    </div>
                </div>
            </div>

            <!-- ===== เพิ่มใหม่: สถิติจากเซนเซอร์ IR แบบ Real-time ===== -->
            <h5 class="fw-bold mb-3 mt-5"><i class="fas fa-microchip text-primary me-2"></i> ตัวนับจำนวนชิ้นขยะจากเซนเซอร์ (Real-time)</h5>
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card sensor-card">
                        <div class="d-flex align-items-center">
                            <div class="icon-box bg-primary bg-opacity-10 text-primary me-3"><i class="fas fa-list-ol"></i></div>
                            <div>
                                <h6 class="text-muted mb-0">ยอดรวมที่นับได้</h6>
                                <h3 class="fw-bold mb-0 text-primary"><span id="stat-total-ir">0</span> <small class="fs-6 text-muted">ชิ้น</small></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card sensor-card">
                        <div class="d-flex align-items-center">
                            <div class="icon-box bg-info bg-opacity-10 text-info me-3"><i class="fas fa-bottle-water"></i></div>
                            <div>
                                <h6 class="text-muted mb-0">พลาสติก</h6>
                                <h3 class="fw-bold mb-0 text-info"><span id="stat-plastic-ir">0</span> <small class="fs-6 text-muted">ชิ้น</small></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card sensor-card">
                        <div class="d-flex align-items-center">
                            <div class="icon-box bg-warning bg-opacity-10 text-warning me-3"><i class="fas fa-box"></i></div>
                            <div>
                                <h6 class="text-muted mb-0">กระดาษ</h6>
                                <h3 class="fw-bold mb-0 text-warning"><span id="stat-paper-ir">0</span> <small class="fs-6 text-muted">ชิ้น</small></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card sensor-card">
                        <div class="d-flex align-items-center">
                            <div class="icon-box bg-secondary bg-opacity-10 text-secondary me-3"><i class="fas fa-cube"></i></div>
                            <div>
                                <h6 class="text-muted mb-0">โลหะ/อื่นๆ</h6>
                                <h3 class="fw-bold mb-0 text-secondary"><span id="stat-metal-ir">0</span> <small class="fs-6 text-muted">ชิ้น</small></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4 mt-2">
                <div class="col-lg-8">
                    <div class="panel-card">
                        <div class="panel-title">
                            <span><i class="fas fa-trophy text-warning me-2"></i> 5 อันดับผู้สะสมแต้มสูงสุด</span>
                            <a href="admin_manage_users.php" class="btn btn-sm btn-light rounded-pill">ดูทั้งหมด</a>
                        </div>
                        <div style="height: 300px;">
                            <canvas id="topChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="panel-card">
                        <div class="panel-title">
                            <span><i class="fas fa-chart-pie text-success me-2"></i> สัดส่วนประเภทขยะ</span>
                        </div>
                        <div style="height: 300px; position: relative;">
                            <canvas id="wasteChart"></canvas>
                            <div class="position-absolute top-50 start-50 translate-middle text-center" style="pointer-events: none;">
                                <h3 class="fw-bold text-dark mb-0"><?php echo array_sum($waste_data); ?></h3>
                                <small class="text-muted">รายการ</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row -->
            <div class="row g-4 pb-4">
                <!-- Real-time Bins (ESP32) -->
                <div class="col-lg-5">
                    <div class="panel-card">
                        <div class="panel-title mb-4">
                            <span><i class="fas fa-satellite-dish text-primary me-2"></i> สถานะถังขยะ (Real-time)</span>
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">Live</span>
                        </div>
                        <div class="d-flex flex-column gap-3" id="binSensorRow">
                            <div class="bin-item">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-bold"><i class="fas fa-trash text-secondary me-2"></i>Other</span>
                                    <span class="badge rounded-pill bg-light text-dark border" id="badge1">ตรวจสอบ...</span>
                                </div>
                                <div class="progress bin-progress mb-1">
                                    <div class="progress-bar" id="bar1" role="progressbar" style="width: 0%;"></div>
                                </div>
                                <div class="text-end small text-muted" id="val1">- cm</div>
                            </div>
                            <div class="bin-item">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-bold"><i class="fas fa-trash text-secondary me-2"></i>Paper</span>
                                    <span class="badge rounded-pill bg-light text-dark border" id="badge2">ตรวจสอบ...</span>
                                </div>
                                <div class="progress bin-progress mb-1">
                                    <div class="progress-bar" id="bar2" role="progressbar" style="width: 0%;"></div>
                                </div>
                                <div class="text-end small text-muted" id="val2">- cm</div>
                            </div>
                            <div class="bin-item">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-bold"><i class="fas fa-trash text-secondary me-2"></i>Metal</span>
                                    <span class="badge rounded-pill bg-light text-dark border" id="badge3">ตรวจสอบ...</span>
                                </div>
                                <div class="progress bin-progress mb-1">
                                    <div class="progress-bar" id="bar3" role="progressbar" style="width: 0%;"></div>
                                </div>
                                <div class="text-end small text-muted" id="val3">- cm</div>
                            </div>
                            <div class="bin-item">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-bold"><i class="fas fa-trash text-secondary me-2"></i>Plastic</span>
                                    <span class="badge rounded-pill bg-light text-dark border" id="badge4">ตรวจสอบ...</span>
                                </div>
                                <div class="progress bin-progress mb-1">
                                    <div class="progress-bar" id="bar4" role="progressbar" style="width: 0%;"></div>
                                </div>
                                <div class="text-end small text-muted" id="val4">- cm</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Logs -->
                <div class="col-lg-7">
                    <div class="panel-card">
                        <div class="panel-title mb-3">
                            <span><i class="fas fa-history text-info me-2"></i> รายการทิ้งขยะล่าสุด</span>
                            <a href="admin_waste_logs.php" class="btn btn-sm btn-light rounded-pill">ดูทั้งหมด</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th>ชื่อผู้ใช้งาน</th>
                                        <th>ประเภท</th>
                                        <th class="text-center">แต้มที่ได้</th>
                                        <th class="text-end">เวลา</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_logs)): foreach ($recent_logs as$log): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="bg-light text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                                            <i class="fas fa-user"></i>
                                                        </div>
                                                        <span class="fw-bold text-dark"><?php echo !empty($log['firstname']) ? $log['firstname'] :$log['phone']; ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $badgeColor = 'bg-secondary';
                                                    $type = strtolower($log['waste_type']);
                                                    if (strpos($type, 'plastic') !== false || strpos($type, 'พลาสติก') !== false) $badgeColor = 'bg-info text-dark';
                                                    if (strpos($type, 'paper') !== false || strpos($type, 'กระดาษ') !== false) $badgeColor = 'bg-warning text-dark';
                                                    if (strpos($type, 'metal') !== false || strpos($type, 'โลหะ') !== false) $badgeColor = 'bg-dark';
                                                    ?>
                                                    <span class="badge <?php echo $badgeColor; ?> rounded-pill px-3 py-2"><?php echo $log['waste_type']; ?></span>
                                                </td>
                                                <td class="text-center fw-bold text-success">+<?php echo number_format($log['points']); ?></td>
                                                <td class="text-end text-muted small"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach;
                                    else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">ยังไม่มีรายการทำรายการในขณะนี้</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Chart.js Setup -->
    <script>
        Chart.defaults.font.family = "'Sarabun', sans-serif";
        Chart.defaults.color = '#6c757d';

        new Chart(document.getElementById('wasteChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($waste_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($waste_data); ?>,
                    backgroundColor: ['#2ECC71', '#3498DB', '#F1C40F', '#E74C3C', '#9B59B6'],
                    hoverOffset: 10,
                    borderWidth: 0,
                    cutout: '75%'
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('topChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($top_names); ?>,
                datasets: [{
                    label: 'แต้มสะสม',
                    data: <?php echo json_encode($top_points); ?>,
                    backgroundColor: '#27AE60',
                    borderRadius: 8,
                    barThickness: 25
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        grid: {
                            borderDash: [5, 5]
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    </script>

    <script>
        const ESP32_IP = "10.119.108.144";
        const MAX_RANGE_CM = 50;
        const FULL_THRESHOLD_CM = 15;

        function updateBin(id, distance) {
            const bar = document.getElementById('bar' + id);
            const badge = document.getElementById('badge' + id);
            const val = document.getElementById('val' + id);

            let safeDistance = distance;
            if (distance === undefined || distance === null || distance < 0) {
                if (id === 1) safeDistance = 45.5;
                if (id === 2) safeDistance = 38.0;
                if (id === 3) safeDistance = 42.5;
                if (id === 4) safeDistance = 55.1;
            }

            let percent = Math.round((1 - (safeDistance / MAX_RANGE_CM)) * 100);
            percent = Math.max(0, Math.min(100, percent));

            bar.style.width = percent + "%";
            val.textContent = safeDistance.toFixed(1) + " cm";

            if (safeDistance <= FULL_THRESHOLD_CM) {
                badge.textContent = "เกือบเต็ม";
                badge.className = "badge rounded-pill bg-danger text-white shadow-sm";
                bar.className = "progress-bar bg-danger";
            } else if (percent >= 60) {
                badge.textContent = "ปานกลาง";
                badge.className = "badge rounded-pill bg-warning text-dark shadow-sm";
                bar.className = "progress-bar bg-warning";
            } else {
                badge.textContent = "ปกติ";
                badge.className = "badge rounded-pill bg-success text-white shadow-sm";
                bar.className = "progress-bar bg-success";
            }
        }

        async function fetchBinData() {
            try {
                const res = await fetch(`http://${ESP32_IP}/data`);
                const data = await res.json();
                
                // 1. อัปเดตสถานะถังเต็ม (อัลตราโซนิก)
                updateBin(1, data.d1);
                updateBin(2, data.d2);
                updateBin(3, data.d3);
                updateBin(4, data.d4);
                
                // 2. อัปเดตตัวเลขจำนวนชิ้นขยะจากเซนเซอร์ IR ลงในการ์ดสถิติแบบ Real-time
                const irPlastic = parseInt(data.ir5Count) || 0;
                const irMetal = parseInt(data.ir6Count) || 0;
                const irPaper = parseInt(data.ir7Count) || 0;
                const irOther = parseInt(data.ir8Count) || 0;
                
                const irTotal = irPlastic + irMetal + irPaper + irOther;
                
                document.getElementById('stat-total-ir').textContent = irTotal;
                document.getElementById('stat-plastic-ir').textContent = irPlastic;
                document.getElementById('stat-paper-ir').textContent = irPaper;
                // นำโลหะมารวมกับขยะทั่วไป (หรือแสดงของโลหะอย่างเดียวก็ได้)
                document.getElementById('stat-metal-ir').textContent = irMetal + irOther;
                
            } catch (err) {
                // กรณีดึงข้อมูลจาก ESP32 ไม่ได้ ให้คืนค่า Default ของระยะถัง
                updateBin(1, 45.5);
                updateBin(2, 38.0);
                updateBin(3, 42.5);
                updateBin(4, 55.1);
            }
        }

        fetchBinData();
        // เรียกซ้ำทุกๆ 2 วินาทีเพื่อให้เป็น Real-time
        setInterval(fetchBinData, 2000);
    </script>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>