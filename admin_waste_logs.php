<?php
require_once 'config.php';

// 1. เช็คสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 2. ดึงข้อมูลประวัติการทิ้งขยะทั้งหมด พร้อมชื่อผู้ใช้งาน
$sql = "SELECT w.*, u.firstname 
        FROM waste_logs w 
        LEFT JOIN users u ON w.phone = u.phone 
        ORDER BY w.created_at DESC";
$result = $conn->query($sql);

// 3. สรุปยอดรวม (สำหรับโชว์หัวตาราง)
$summary = $conn->query("SELECT COUNT(*) as total_rows, SUM(points) as total_points FROM waste_logs")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติทั้งหมด - Admin iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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

        /* Action Buttons */
        .btn-action { padding: 5px 12px; font-size: 0.85rem; border-radius: 8px; transition: 0.2s; font-weight: 600; }
        .btn-view { background-color: #e8f5e9; color: #2e7d32; border: none; }
        .btn-view:hover { background-color: #2e7d32; color: #fff; transform: translateY(-2px); }
        .btn-edit { background-color: #fff3e0; color: #ef6c00; border: none; }
        .btn-edit:hover { background-color: #ef6c00; color: #fff; transform: translateY(-2px); }

        /* DataTables Customization */
        div.dataTables_wrapper div.dataTables_filter input {
            border-radius: 50px;
            padding: 5px 15px;
            border: 1px solid #ced4da;
        }
        .pagination .page-item.active .page-link {
            background-color: #198754;
            border-color: #198754;
        }
        .pagination .page-link { color: #198754; }

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
            <div class="nav-item"><a href="admin_waste_logs.php" class="nav-link active"><i class="fas fa-list-ul"></i> ประวัติทั้งหมด</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-file-alt"></i> รายงานข้อมูล</a></div>
            <div class="nav-item"><a href="trends.php" class="nav-link"><i class="fas fa-chart-line"></i> แนวโน้มข้อมูล</a></div>
            <div class="nav-item"><a href="telegram_logs.php" class="nav-link"><i class="fas fa-bell"></i> ประวัติ Telegram</a></div>

            <div class="px-4 text-uppercase small fw-bold mb-2 opacity-50 mt-4">ตั้งค่าระบบ</div>
            <div class="nav-item"><a href="admin_manage_waste_types.php" class="nav-link"><i class="fas fa-coins"></i> ตั้งค่าคะแนน</a></div>
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

        <div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-list-ul text-secondary fs-1"></i>
                <div>
                    <h3 class="fw-bold mb-0 text-dark">ประวัติการทำรายการขยะทั้งหมด</h3>
                    <p class="text-muted mb-0">ตรวจสอบข้อมูลการทิ้งขยะและการแจกแต้มย้อนหลัง</p>
                </div>
            </div>
            <a href="export_csv.php" class="btn btn-success rounded-pill px-4 shadow-sm fw-bold">
                <i class="fas fa-file-excel me-2"></i>ดาวน์โหลด Excel (CSV)
            </a>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="panel-card border-top border-success border-4 h-100">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex justify-content-center align-items-center me-4" style="width: 70px; height: 70px;">
                            <i class="fas fa-list-ol fa-2x"></i>
                        </div>
                        <div>
                            <small class="text-muted text-uppercase fw-bold mb-1 d-block">จำนวนรายการทั้งหมด</small>
                            <h2 class="fw-bolder text-dark mb-0"><?php echo number_format($summary['total_rows']); ?> <span class="fs-6 text-muted fw-normal">รายการ</span></h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel-card border-top border-primary border-4 h-100">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center me-4" style="width: 70px; height: 70px;">
                            <i class="fas fa-star fa-2x"></i>
                        </div>
                        <div>
                            <small class="text-muted text-uppercase fw-bold mb-1 d-block">คะแนนที่แจกไปรวม</small>
                            <h2 class="fw-bolder text-dark mb-0"><?php echo number_format($summary['total_points']); ?> <span class="fs-6 text-muted fw-normal">แต้ม</span></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Panel -->
        <div class="panel-card">
            <div class="panel-title mb-4">
                <i class="fas fa-database text-secondary"></i> ตารางบันทึกการทำรายการ (Waste Logs)
            </div>
            
            <div class="table-responsive">
                <table id="logsTable" class="table table-custom align-middle w-100">
                    <thead>
                        <tr>
                            <th width="5%">ID</th>
                            <th width="15%">วันที่-เวลา</th>
                            <th width="20%">ชื่อสมาชิก</th>
                            <th width="15%">เบอร์โทรศัพท์</th>
                            <th width="15%">ประเภทขยะ</th>
                            <th width="10%" class="text-center">แต้มที่ได้</th>
                            <th width="20%" class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="text-muted fw-bold">#<?php echo $row['id']; ?></td>
                                <td class="small text-muted"><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-light text-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <strong class="text-dark"><?php echo htmlspecialchars($row['firstname'] ?? 'ไม่ระบุชื่อ'); ?></strong>
                                    </div>
                                </td>
                                <td><span class="text-muted"><i class="fas fa-phone-alt me-1"></i><?php echo $row['phone']; ?></span></td>
                                <td>
                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"><?php echo $row['waste_type']; ?></span>
                                </td>
                                <td class="text-center fw-bold text-primary">+<?php echo number_format($row['points']); ?></td>
                                <td class="text-center">
                                    <div class="btn-group shadow-sm" role="group">
                                        <a href="admin_view_waste.php?id=<?php echo $row['id']; ?>" class="btn btn-action btn-view border-end">
                                            <i class="fas fa-eye me-1"></i> ดู
                                        </a>
                                        <a href="admin_edit_waste.php?id=<?php echo $row['id']; ?>" class="btn btn-action btn-edit">
                                            <i class="fas fa-edit me-1"></i> แก้ไข
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#logsTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/th.json"
        },
        "order": [[0, "desc"]], // เรียงตาม ID ล่าสุด
        "pageLength": 25,
        "responsive": true,
        "columnDefs": [
            { "orderable": false, "targets": 6 } // ปิดการกดเรียงลำดับที่คอลัมน์ "จัดการ"
        ]
    });
});
</script>

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