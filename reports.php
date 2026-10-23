<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

/*
 * reports.php
 * รายงานข้อมูลการทิ้งขยะจากตาราง waste_logs
 * ไม่แก้ไขข้อมูลในฐานข้อมูล
 */

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = date('Y-m-d');
}

if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

/* ใช้ช่วงเวลาแบบ [วันเริ่มต้น, วันถัดจากวันสิ้นสุด] */
$endExclusive = date('Y-m-d', strtotime($endDate . ' +1 day'));

/* ===== สรุปข้อมูล ===== */
$sqlSummary = "SELECT
                    COUNT(*) AS total_records,
                    COALESCE(SUM(points), 0) AS total_points
               FROM waste_logs
               WHERE created_at >= ? AND created_at < ?";

$stmt = $conn->prepare($sqlSummary);
$stmt->bind_param("ss", $startDate, $endExclusive);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc() ?: [
    'total_records' => 0,
    'total_points' => 0
];
$stmt->close();

/* ===== สรุปตามประเภทขยะ ===== */
$sqlTypes = "SELECT waste_type, COUNT(*) AS total, COALESCE(SUM(points),0) AS points
             FROM waste_logs
             WHERE created_at >= ? AND created_at < ?
             GROUP BY waste_type
             ORDER BY total DESC";

$stmt = $conn->prepare($sqlTypes);
$stmt->bind_param("ss", $startDate, $endExclusive);
$stmt->execute();
$typeResult = $stmt->get_result();

$typeRows = [];
while ($row = $typeResult->fetch_assoc()) {
    $typeRows[] = $row;
}
$stmt->close();

/* ===== รายการทิ้งขยะ ===== */
$sqlLogs = "SELECT id, waste_type, points, created_at
            FROM waste_logs
            WHERE created_at >= ? AND created_at < ?
            ORDER BY created_at DESC, id DESC";

$stmt = $conn->prepare($sqlLogs);
$stmt->bind_param("ss", $startDate, $endExclusive);
$stmt->execute();
$logResult = $stmt->get_result();

function wasteName($type)
{
    $type = strtolower(trim((string)$type));

    $map = [
        'metal'   => 'โลหะ',
        'paper'   => 'กระดาษ',
        'plastic' => 'พลาสติก',
        'other'   => 'ทั่วไป'
    ];

    return $map[$type] ?? $type;
}

function thaiDateTime($value)
{
    $ts = strtotime($value);
    if (!$ts) return htmlspecialchars($value);

    return date('d/m/', $ts)
        . ((int)date('Y', $ts) + 543)
        . ' ' . date('H:i:s', $ts);
}

function wasteIcon($type)
{
    $type = strtolower(trim((string)$type));

    if ($type === 'metal') return 'fa-cube';
    if ($type === 'paper') return 'fa-file-lines';
    if ($type === 'plastic') return 'fa-bottle-water';

    return 'fa-trash';
}

$totalRecords = (int)$summary['total_records'];
$totalPoints = (float)$summary['total_points'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานข้อมูลการทิ้งขยะ - Admin iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-bg: #104A33; /* Dark Green */
            --sidebar-hover: #196D4C;
            --main-bg: #F4F9F6; /* Light Eco Green Background */
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
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
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

        /* Report Specific Styles */
        .stat-card {
            border: none;
            border-radius: 1rem;
            background: #fff;
            height: 100%;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .icon-record { background: #e7f7ef; color: #198754; }
        .icon-point { background: #fff3cd; color: #997404; }
        .icon-type { background: #e7f0ff; color: #0d6efd; }

        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 15px;
            border-radius: 50px;
            background: #eaf7f0;
            color: #198754;
            font-weight: 600;
        }

        .empty-state {
            padding: 50px 20px;
            text-align: center;
            color: #6c757d;
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

        .report-title { display: none; }

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

        /* ====== PRINT MEDIA QUERIES ====== */
        @media print {
            body { background: #fff; }
            .sidebar { display: none !important; }
            .main-content { padding: 0 !important; margin: 0 !important; width: 100% !important; overflow: visible !important; height: auto !important; }
            .wrapper { display: block !important; height: auto !important; overflow: visible !important; }
            .no-print, .filter-card { display: none !important; }
            
            .report-title {
                display: block;
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid #eee;
            }
            .panel-card, .stat-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                page-break-inside: avoid;
            }
            .container { max-width: 100% !important; width: 100% !important; }
            .table-custom th { background-color: #f1f2f6 !important; -webkit-print-color-adjust: exact; }
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
            <div class="nav-item"><a href="reports.php" class="nav-link active"><i class="fas fa-file-alt"></i> รายงานข้อมูล</a></div>
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
        
        <!-- --- เพิ่ม HTML สำหรับปุ่ม 3 ขีดและพื้นหลังสีดำตรงนี้ --- -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <div class="mobile-toggle-btn mb-4 no-print">
            <div class="d-flex align-items-center gap-3">
                <button id="openSidebar" class="btn btn-white bg-white shadow-sm border-0 px-3 py-2 rounded-3">
                    <i class="fas fa-bars text-success fs-4"></i>
                </button>
                <h5 class="fw-bold mb-0 text-success"><i class="fas fa-leaf me-2"></i>iWasteBank</h5>
            </div>
        </div>
        
        <!-- Header (Hide on Print) -->
        <div class="page-header d-flex flex-wrap justify-content-between align-items-end no-print gap-3">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-file-alt text-secondary fs-1"></i>
                <div>
                    <h3 class="fw-bold mb-0 text-dark">รายงานข้อมูลการทิ้งขยะ</h3>
                    <p class="text-muted mb-0">สรุปข้อมูลการทำรายการตามช่วงเวลา และออกรายงาน PDF</p>
                </div>
            </div>
            <button type="button" onclick="window.print()" class="btn btn-success rounded-pill px-4 py-2 shadow-sm fw-bold">
                <i class="fas fa-print me-2"></i> พิมพ์ / บันทึก PDF
            </button>
        </div>

        <!-- Report Title (Visible ONLY on Print) -->
        <div class="report-title">
            <h2 class="fw-bold">รายงานข้อมูลการทิ้งขยะ (iWasteBank)</h2>
            <div class="text-muted">
                ประจำช่วงวันที่ <?php echo htmlspecialchars(date('d/m/Y', strtotime($startDate))); ?> 
                ถึง <?php echo htmlspecialchars(date('d/m/Y', strtotime($endDate))); ?>
            </div>
            <div class="small mt-1 text-muted">พิมพ์เมื่อ: <?php echo date('d/m/Y H:i:s'); ?></div>
        </div>

        <!-- Filter Card -->
        <div class="panel-card mb-4 filter-card no-print border-top border-warning border-4">
            <div class="panel-title mb-3">
                <i class="fas fa-filter text-warning"></i> กรองข้อมูลตามช่วงเวลา
            </div>
            <form method="GET" action="reports.php">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">วันที่เริ่มต้น</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">วันที่สิ้นสุด</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>" required>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-warning fw-bold flex-fill shadow-sm">
                            <i class="fas fa-search me-1"></i> ดูรายงาน
                        </button>
                        <a href="reports.php" class="btn btn-light border shadow-sm" title="เริ่มใหม่ (เดือนปัจจุบัน)">
                            <i class="fas fa-sync-alt"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- สรุปตัวเลข (Summary) -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon icon-record me-3 shadow-sm"><i class="fas fa-recycle"></i></div>
                        <div>
                            <div class="text-muted small fw-bold mb-1">จำนวนรายการทิ้งขยะ</div>
                            <h3 class="fw-bolder mb-0 text-dark"><?php echo number_format($totalRecords); ?> <span class="fs-6 fw-normal text-muted">รายการ</span></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon icon-point me-3 shadow-sm"><i class="fas fa-star"></i></div>
                        <div>
                            <div class="text-muted small fw-bold mb-1">คะแนนรวมที่แจกไป</div>
                            <h3 class="fw-bolder mb-0 text-dark"><?php echo number_format($totalPoints, 0); ?> <span class="fs-6 fw-normal text-muted">คะแนน</span></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon icon-type me-3 shadow-sm"><i class="fas fa-layer-group"></i></div>
                        <div>
                            <div class="text-muted small fw-bold mb-1">ประเภทขยะที่พบ</div>
                            <h3 class="fw-bolder mb-0 text-dark"><?php echo number_format(count($typeRows)); ?> <span class="fs-6 fw-normal text-muted">ประเภท</span></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- สรุปตามประเภท -->
        <div class="panel-card mb-4 border-top border-success border-4">
            <div class="panel-title">
                <i class="fas fa-chart-pie text-success"></i> สรุปตามประเภทขยะ
            </div>
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ประเภทขยะ</th>
                            <th class="text-end">จำนวนรายการ</th>
                            <th class="text-end">คะแนนรวม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($typeRows) > 0): ?>
                            <?php foreach ($typeRows as $row): ?>
                                <tr>
                                    <td>
                                        <span class="type-badge shadow-sm">
                                            <i class="fas <?php echo wasteIcon($row['waste_type']); ?>"></i>
                                            <?php echo htmlspecialchars(wasteName($row['waste_type'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold text-dark">
                                        <?php echo number_format((int)$row['total']); ?> รายการ
                                    </td>
                                    <td class="text-end fw-bold text-success">
                                        +<?php echo number_format((float)$row['points'], 0); ?> คะแนน
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">
                                    <div class="empty-state">
                                        <i class="fas fa-box-open fa-2x mb-2"></i>
                                        <div>ไม่มีข้อมูลในช่วงวันที่เลือก</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- รายการทั้งหมด -->
        <div class="panel-card border-top border-primary border-4">
            <div class="panel-title d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list text-primary"></i> บันทึกรายการทิ้งขยะ (Logs)</span>
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 no-print"><i class="fas fa-database me-1"></i> waste_logs</span>
            </div>
            
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ประเภทขยะ</th>
                            <th class="text-center">คะแนนที่ได้รับ</th>
                            <th>วันที่ / เวลา</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logResult->num_rows > 0): ?>
                            <?php while ($row = $logResult->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-muted fw-bold">#<?php echo (int)$row['id']; ?></td>
                                    <td>
                                        <span class="type-badge shadow-sm">
                                            <i class="fas <?php echo wasteIcon($row['waste_type']); ?>"></i>
                                            <?php echo htmlspecialchars(wasteName($row['waste_type'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-center fw-bold text-success">
                                        +<?php echo number_format((float)$row['points'], 0); ?>
                                    </td>
                                    <td class="text-nowrap text-muted small">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo thaiDateTime($row['created_at']); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <div class="fw-bold mb-1 text-dark">ไม่พบข้อมูลการทิ้งขยะ</div>
                                        <div class="small">ลองเลือกช่วงวันที่อื่น</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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