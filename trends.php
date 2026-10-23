<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// เตรียมประเภทขยะสำหรับการแสดงกราฟจาก Database
$typeSummary = ['metal' => 0, 'paper' => 0, 'plastic' => 0, 'other' => 0];

// ดึงจำนวนแต่ละประเภท (เพื่อวาดกราฟและไฮไลต์)
$sqlType = "SELECT LOWER(waste_type) AS waste_type, COUNT(*) AS total
            FROM waste_logs GROUP BY LOWER(waste_type)";
$resType = $conn->query($sqlType);
if ($resType) {
    while ($row = $resType->fetch_assoc()) {
        $type = strtolower(trim($row['waste_type']));
        if (isset($typeSummary[$type])) {
            $typeSummary[$type] = (int)$row['total'];
        }
    }
}

$totalWaste = array_sum($typeSummary);

// กราฟ 30 วัน
$days = 30;
$startDate = date('Y-m-d', strtotime("-{$days} days"));
$dateLabels = [];
for ($i = $days; $i >= 0; $i--) {
    $dateLabels[] = date('Y-m-d', strtotime("-{$i} days"));
}
$wasteTypes = ['metal', 'paper', 'plastic', 'other'];
$trendData = [];
foreach ($wasteTypes as $type) {
    foreach ($dateLabels as $d) { $trendData[$type][$d] = 0; }
}
$sql = "SELECT DATE(created_at) AS d, LOWER(waste_type) AS waste_type, COUNT(*) AS cnt FROM waste_logs WHERE created_at >= ? GROUP BY DATE(created_at), LOWER(waste_type)";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("s", $startDate);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $type = strtolower(trim($row['waste_type']));
        $date = $row['d'];
        if (isset($trendData[$type]) && isset($trendData[$type][$date])) {
            $trendData[$type][$date] = (int)$row['cnt'];
        }
    }
    $stmt->close();
}
$trendSeries = [];
foreach ($wasteTypes as $type) { $trendSeries[$type] = array_values($trendData[$type]); }

// Heatmap (แก้ไขคำสั่ง SQL ตรงนี้ให้ดึง hr มาด้วย)
$dayNamesTh = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสฯ', 'ศุกร์', 'เสาร์'];
$heatmapGrid = [];
for ($dow = 1; $dow <= 7; $dow++) { for ($hr = 0; $hr < 24; $hr++) { $heatmapGrid[$dow][$hr] = 0; } }
$sqlHeat = "SELECT DAYOFWEEK(created_at) AS dow, HOUR(created_at) AS hr, COUNT(*) AS cnt FROM waste_logs GROUP BY DAYOFWEEK(created_at), HOUR(created_at)";
$resHeat = $conn->query($sqlHeat);
$maxCount = 1;
if ($resHeat) {
    while ($row =$resHeat->fetch_assoc()) {
        $dow = (int)$row['dow']; 
        $hr  = (int)$row['hr']; 
        $cnt = (int)$row['cnt'];
        if (isset($heatmapGrid[$dow]) && isset($heatmapGrid[$dow][$hr])) { $heatmapGrid[$dow][$hr] =$cnt; }
        if ($cnt >$maxCount) { $maxCount =$cnt; }
    }
}

// Pie Chart
$pieLabels = ['โลหะ', 'กระดาษ', 'พลาสติก', 'ทั่วไป'];
$pieData = [$typeSummary['metal'],$typeSummary['paper'], $typeSummary['plastic'],$typeSummary['other']];

// หาประเภทที่มากสุด
$maxWasteType = 'ไม่มีข้อมูล';
$maxWasteCount = 0;
foreach ($typeSummary as $type =>$count) {
    if ($count >$maxWasteCount) {
        $maxWasteCount =$count;
        switch ($type) {
            case 'metal': $maxWasteType = 'โลหะ'; break;
            case 'paper': $maxWasteType = 'กระดาษ'; break;
            case 'plastic': $maxWasteType = 'พลาสติก'; break;
            case 'other': $maxWasteType = 'ทั่วไป'; break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>วิเคราะห์ข้อมูลขยะ - Admin iWasteBank</title>
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
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }

        .summary-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .summary-card:hover { transform: translateY(-3px); box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.10); }
        .summary-number { font-size: 2.5rem; font-weight: 700; }
        
        /* สไตล์การ์ดเซนเซอร์ตลอดกาล */
        .sensor-card { background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border: 1px solid #e9ecef; }
        .sensor-card .icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }

        .heatmap-table { border-collapse: collapse; width: 100%; font-size: 0.75rem; }
        .heatmap-table th, .heatmap-table td { text-align: center; padding: 4px; border: 1px solid #f0f0f0; }
        .heatmap-table th { background: #fcfcfc; color: #6c757d; font-weight: 600; }
        .heatmap-cell { border-radius: 4px; width: 28px; height: 28px; display: inline-block; line-height: 28px; }
        .chart-container { position: relative; height: 320px; width: 100%; }
        .pie-container { position: relative; height: 330px; width: 100%; }
        .highlight-box { background: linear-gradient(135deg, #e8f5e9, #ffffff); border-radius: 1rem; padding: 20px; }

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
                <div class="nav-item"><a href="admin_waste_logs.php" class="nav-link"><i class="fas fa-list-ul"></i> ประวัติทั้งหมด</a></div>
                <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-file-alt"></i> รายงานข้อมูล</a></div>
                <div class="nav-item"><a href="trends.php" class="nav-link active"><i class="fas fa-chart-line"></i> แนวโน้มข้อมูล</a></div>
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

            <div class="d-flex align-items-center gap-3 mb-4">
                <i class="fas fa-chart-line text-success fs-1"></i>
                <div>
                    <h3 class="fw-bold mb-0 text-dark">การวิเคราะห์ข้อมูลขยะ</h3>
                    <p class="text-muted mb-0">แสดงกราฟแนวโน้มและสถิติจากเซนเซอร์บนตู้ขยะอัจฉริยะแบบสะสม (Lifetime)</p>
                </div>
            </div>

            <!-- ===== การ์ดสถิติดึงจาก ESP32 (ใช้ค่า lifetime ที่ไม่มีวันรีเซ็ต) ===== -->
            <h5 class="fw-bold mb-3 mt-4"><i class="fas fa-microchip text-primary me-2"></i> ยอดเซนเซอร์สะสมตลอดกาล (Real-time)</h5>
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card sensor-card p-4 rounded-4 h-100 shadow-sm border-top border-success border-4">
                        <div class="d-flex align-items-center">
                            <div class="icon-box bg-success bg-opacity-10 text-success me-3"><i class="fas fa-list-ol"></i></div>
                            <div>
                                <h6 class="text-muted mb-0">ยอดรวมที่เซนเซอร์นับได้</h6>
                                <h3 class="fw-bold mb-0 text-success"><span id="stat-total-life">0</span> <small class="fs-6 text-muted">ชิ้น</small></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card sensor-card p-4 rounded-4 h-100 shadow-sm border-top border-info border-4">
                        <div class="d-flex align-items-center">
                            <div class="icon-box bg-info bg-opacity-10 text-info me-3"><i class="fas fa-bottle-water"></i></div>
                            <div>
                                <h6 class="text-muted mb-0">พลาสติก สะสม</h6>
                                <h3 class="fw-bold mb-0 text-info"><span id="stat-plastic-life">0</span> <small class="fs-6 text-muted">ชิ้น</small></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card sensor-card p-4 rounded-4 h-100 shadow-sm border-top border-warning border-4">
                        <div class="d-flex align-items-center">
                            <div class="icon-box bg-warning bg-opacity-10 text-warning me-3"><i class="fas fa-box"></i></div>
                            <div>
                                <h6 class="text-muted mb-0">กระดาษ สะสม</h6>
                                <h3 class="fw-bold mb-0 text-warning"><span id="stat-paper-life">0</span> <small class="fs-6 text-muted">ชิ้น</small></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card sensor-card p-4 rounded-4 h-100 shadow-sm border-top border-secondary border-4">
                        <div class="d-flex align-items-center">
                            <div class="icon-box bg-secondary bg-opacity-10 text-secondary me-3"><i class="fas fa-cube"></i></div>
                            <div>
                                <h6 class="text-muted mb-0">โลหะ/อื่นๆ สะสม</h6>
                                <h3 class="fw-bold mb-0 text-secondary"><span id="stat-metal-life">0</span> <small class="fs-6 text-muted">ชิ้น</small></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- HIGHLIGHT -->
            <div class="card card-panel p-4 mb-4">
                <div class="highlight-box">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="text-muted small mb-1">ประเภทขยะที่มีการถูกรับเข้าระบบ (Database) มากที่สุด</div>
                            <div class="fs-3 fw-bold text-success">
                                <i class="fas fa-chart-pie me-2"></i> <?php echo htmlspecialchars($maxWasteType); ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <div class="text-muted small">จำนวนที่มีการสแกนรับแต้ม</div>
                            <div class="fs-3 fw-bold"><?php echo number_format($maxWasteCount); ?> <span class="fs-6 text-muted">ชิ้น</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TREND CHART -->
            <div class="card card-panel p-4 mb-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-chart-line me-2 text-success"></i> ปริมาณขยะที่รับเข้าระบบรายวันย้อนหลัง <?php echo $days; ?> วัน
                </h6>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- PIE CHART & TABLE -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card card-panel p-4 h-100">
                        <h6 class="fw-bold mb-3"><i class="fas fa-chart-pie me-2 text-success"></i> สัดส่วนประเภทขยะ (จากระบบ)</h6>
                        <div class="pie-container">
                            <canvas id="wastePieChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card card-panel p-4 h-100">
                        <h6 class="fw-bold mb-3"><i class="fas fa-table me-2 text-success"></i> สรุปประเภทขยะที่บันทึกแล้ว</h6>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>ประเภท</th>
                                        <th class="text-end">จำนวน (ชิ้น)</th>
                                        <th class="text-end">สัดส่วน</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $totalDbWaste = array_sum($typeSummary);$typeNames = ['metal' => 'โลหะ', 'paper' => 'กระดาษ', 'plastic' => 'พลาสติก', 'other' => 'ทั่วไป'];
                                    foreach ($typeSummary as $type =>$count):
                                        $percent =$totalDbWaste > 0 ? ($count / $totalDbWaste) * 100 : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo $typeNames[$type]; ?></td>
                                            <td class="text-end fw-bold"><?php echo number_format($count); ?></td>
                                            <td class="text-end"><?php echo number_format($percent, 1); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- HEATMAP -->
            <div class="card card-panel p-4">
                <h6 class="fw-bold mb-1"><i class="fas fa-fire me-2 text-danger"></i> ช่วงเวลาที่มีการทำรายการบ่อยที่สุด</h6>
                <p class="text-muted small mb-3">สีเข้มขึ้น = มีการรับขยะเข้าระบบในช่วงเวลานั้นบ่อยขึ้น (รวมทุกประเภท)</p>
                <div class="table-responsive">
                    <table class="heatmap-table">
                        <thead>
                            <tr>
                                <th>วัน \ ชม.</th>
                                <?php for ($h = 0; $h < 24; $h++): ?>
                                    <th><?php echo $h; ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($dow = 1; $dow <= 7; $dow++): ?>
                                <tr>
                                    <td class="fw-bold text-start"><?php echo $dayNamesTh[$dow - 1]; ?></td>
                                    <?php for ($h = 0; $h < 24; $h++):
                                        $count =$heatmapGrid[$dow][$h];
                                        $intensity =$count > 0 ? min(1, $count / $maxCount) : 0;
                                        $bg = $intensity > 0 ? "rgba(25,135,84," . (0.15 + $intensity * 0.85) . ")" : "#f8f9fa";
                                        $textColor =$intensity > 0.5 ? "#fff" : "#adb5bd";
                                    ?>
                                        <td>
                                            <span class="heatmap-cell" style="background: <?php echo $bg; ?>; color: <?php echo$textColor; ?>;"
                                                title="<?php echo $dayNamesTh[$dow - 1] . ' ' . $h . ':00 - ' .$count . ' ชิ้น'; ?>">
                                                <?php echo $count > 0 ?$count : ''; ?>
                                            </span>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- Chart.js Setup -->
    <script>
        Chart.defaults.font.family = "'Sarabun', sans-serif";
        Chart.defaults.color = '#6c757d';

        const dateLabels = <?php echo json_encode(array_map(fn($d) => date('d/m', strtotime($d)),$dateLabels)); ?>;

        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: dateLabels,
                datasets: [{
                        label: 'โลหะ',
                        data: <?php echo json_encode($trendSeries['metal']); ?>,
                        borderColor: '#6c757d',
                        backgroundColor: 'rgba(108,117,125,0.1)',
                        tension: 0.3, fill: true
                    },
                    {
                        label: 'กระดาษ',
                        data: <?php echo json_encode($trendSeries['paper']); ?>,
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255,193,7,0.1)',
                        tension: 0.3, fill: true
                    },
                    {
                        label: 'พลาสติก',
                        data: <?php echo json_encode($trendSeries['plastic']); ?>,
                        borderColor: '#0dcaf0',
                        backgroundColor: 'rgba(13,202,240,0.1)',
                        tension: 0.3, fill: true
                    },
                    {
                        label: 'ทั่วไป',
                        data: <?php echo json_encode($trendSeries['other']); ?>,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25,135,84,0.1)',
                        tension: 0.3, fill: true
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

        new Chart(document.getElementById('wastePieChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($pieLabels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    data: <?php echo json_encode($pieData); ?>,
                    backgroundColor: ['#6c757d', '#ffc107', '#0dcaf0', '#198754'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } } }
            }
        });
    </script>

    <!-- ===== JS ดึงข้อมูลเซนเซอร์แบบ Lifetime ตลอดกาลจาก ESP32 ===== -->
    <script>
        const ESP32_IP = "10.119.108.144"; // IP ของ ESP32

        async function fetchLifetimeSensorData() {
            try {
                const res = await fetch(`http://${ESP32_IP}/data`);
                const data = await res.json();
                
                // ดึงค่า lifetime ที่ไม่ถูกรีเซ็ต (life_ir5 ถึง life_ir8) ที่เราเพิ่งเพิ่มใน Arduino
                const lifePlastic = parseInt(data.life_ir5) || 0; 
                const lifeMetal   = parseInt(data.life_ir6) || 0;   
                const lifePaper   = parseInt(data.life_ir7) || 0;   
                const lifeOther   = parseInt(data.life_ir8) || 0;   
                
                const lifeTotal = lifePlastic + lifeMetal + lifePaper + lifeOther;
                
                document.getElementById('stat-total-life').textContent = lifeTotal;
                document.getElementById('stat-plastic-life').textContent = lifePlastic;
                document.getElementById('stat-paper-life').textContent = lifePaper;
                document.getElementById('stat-metal-life').textContent = lifeMetal + lifeOther;
                
            } catch (err) {
                console.error("เชื่อมต่อ ESP32 ไม่ได้: ", err);
            }
        }

        fetchLifetimeSensorData();
        setInterval(fetchLifetimeSensorData, 2000);
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const openBtn = document.getElementById('openSidebar');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            if (openBtn && sidebar && overlay) {
                openBtn.addEventListener('click', function() {
                    sidebar.classList.add('show'); overlay.classList.add('show');
                });
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('show'); overlay.classList.remove('show');
                });
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>