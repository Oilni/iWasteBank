<?php
require_once 'config.php';

// 1. เช็คสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 2. จัดการลบรายงาน (ถ้าจำเป็นยังคงไว้)
if (isset($_GET['delete'])) {
    $report_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM reports WHERE id = $report_id");
    header("Location: admin_manage_reports.php?msg=deleted");
}

// 3. ดึงข้อมูลรายงาน
$result = $conn->query("SELECT * FROM reports ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการปัญหา - iWasteBank</title>
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
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            background: #f8f9fa;
        }

        .table-custom td {
            vertical-align: middle;
            border-bottom: 1px solid #f1f2f6;
        }

        .badge-status {
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 50px;
            font-weight: 600;
        }

        .btn-detail {
            border-radius: 50px;
            font-weight: 600;
            transition: 0.3s;
            padding: 5px 15px;
            font-size: 0.85rem;
        }

        .btn-detail:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(220, 53, 69, 0.2);
        }

        /* --- โค้ด CSS สำหรับมือถือ --- */
        .mobile-toggle-btn { display: none; }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed !important;
                left: -300px; /* ซ่อนเมนูไว้ทางซ้าย */
                top: 0;
                height: 100vh;
                z-index: 1050;
                box-shadow: 5px 0 15px rgba(0,0,0,0.2);
                transition: left 0.3s ease-in-out !important; /* เพิ่มความสมูทตอนสไลด์ */
            }
            .sidebar.show { 
                left: 0 !important; /* บังคับเลื่อนเมนูออกมา */
            }
            
            .main-content { padding: 1rem; width: 100%; }
            .mobile-toggle-btn { display: block; } 
            
            /* แบ็คกราวด์สีดำโปร่งแสงเวลาเปิดเมนู */
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
            
            <!-- --- เพิ่ม HTML สำหรับปุ่ม 3 ขีดและพื้นหลังสีดำตรงนี้ (บรรทัดแรกในแท็ก main) --- -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>
            <div class="mobile-toggle-btn mb-4">
                <div class="d-flex align-items-center gap-3">
                    <button id="openSidebar" class="btn btn-white bg-white shadow-sm border-0 px-3 py-2 rounded-3">
                        <i class="fas fa-bars text-success fs-4"></i>
                    </button>
                    <h5 class="fw-bold mb-0 text-success"><i class="fas fa-leaf me-2"></i>iWasteBank</h5>
                </div>
            </div>
            <!-- ------------------------------------------------------------- -->

            <!-- Header -->
            <div class="page-header">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <i class="fas fa-tools text-danger fs-1"></i>
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">ระบบจัดการปัญหา</h3>
                        <p class="text-muted mb-0">รายการแจ้งปัญหาและข้อร้องเรียนจากผู้ใช้งาน</p>
                    </div>
                </div>
                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4">
                        <i class="fas fa-check-circle me-2"></i> ดำเนินการเรียบร้อยแล้ว!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Table Panel -->
                <div class="panel-card border-top border-danger border-4">
                    <div class="panel-title">
                        <i class="fas fa-clipboard-list text-danger"></i> รายการแจ้งปัญหาจากผู้ใช้
                    </div>

                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>วันที่แจ้ง</th>
                                    <th>ผู้แจ้ง (เบอร์โทร)</th>
                                    <th>หัวข้อเรื่อง</th>
                                    <th class="text-center">สถานะ</th>
                                    <th class="text-center">การดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()):
                                        $u_phone = $row['phone'];
                                        $u_res = $conn->query("SELECT firstname FROM users WHERE phone = '$u_phone' LIMIT 1");
                                        $u_name = ($u_res && $u_res->num_rows > 0) ? $u_res->fetch_assoc()['firstname'] : "ไม่พบชื่อ";

                                        // สีสถานะ
                                        $status_class = 'bg-secondary';
                                        if ($row['status'] == 'กำลังดำเนินการ') $status_class = 'bg-warning text-dark';
                                        if ($row['status'] == 'แก้ไขเสร็จสิ้น') $status_class = 'bg-success';
                                    ?>
                                        <tr>
                                            <td class="small text-muted"><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="bg-light text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                    <div>
                                                        <strong class="text-dark"><?php echo $u_name; ?></strong><br>
                                                        <small class="text-muted"><i class="fas fa-phone-alt me-1"></i><?php echo $u_phone; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-truncate fw-bold text-dark" style="max-width: 250px;">
                                                    <?php echo htmlspecialchars($row['subject']); ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-status <?php echo $status_class; ?>">
                                                    <?php echo $row['status']; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="admin_report_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-danger btn-detail">
                                                    <i class="fas fa-search me-1"></i> ดูรายละเอียด
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3 text-light"></i><br>
                                            ไม่พบข้อมูลการแจ้งปัญหา
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
    
    <!-- --- เพิ่ม JavaScript สำหรับปุ่ม 3 ขีด ตรงนี้ (ก่อนปิด body) --- -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ดึงองค์ประกอบของปุ่ม เมนู และพื้นหลังสีดำมาใช้งาน
            const openBtn = document.getElementById('openSidebar');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            // เช็คว่ามีปุ่มและเมนูอยู่จริง ค่อยสั่งทำงาน
            if (openBtn && sidebar && overlay) {
                // เมื่อกดปุ่ม 3 ขีด ให้เปิดเมนู
                openBtn.addEventListener('click', function() {
                    sidebar.classList.add('show');
                    overlay.classList.add('show');
                });

                // เมื่อกดที่พื้นหลังสีดำ ให้ปิดเมนู
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                });
            }
        });
    </script>
</body>

</html>