<?php
require_once 'config.php';

// 1. เช็คสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 2. จัดการลบสมาชิก
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    header("Location: admin_manage_users.php?msg=deleted");
}

// 3. ดึงข้อมูลสมาชิกพร้อมคำนวณคะแนน 3 ส่วน
$sql = "SELECT 
            u.id, 
            u.firstname, 
            u.phone, 
            u.created_at,
            COALESCE(up.total_points, 0) as total_earned,
            COALESCE((SELECT SUM(points_used) FROM redemptions WHERE phone = u.phone AND status != 'ยกเลิก'), 0) as total_redeemed
        FROM users u 
        LEFT JOIN user_points up ON u.phone = up.phone 
        WHERE u.role != 'admin'
        ORDER BY u.id DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสมาชิก - Admin iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
            margin-bottom: 2rem;
        }

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

        div.dataTables_wrapper div.dataTables_filter input {
            border-radius: 50px;
            padding: 5px 15px;
            border: 1px solid #ced4da;
        }

        .pagination .page-item.active .page-link {
            background-color: #198754;
            border-color: #198754;
        }

        .pagination .page-link {
            color: #198754;
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
                <div class="nav-item"><a href="admin_manage_redeems.php" class="nav-link"><i class="fas fa-gift"></i> รายการแลกของรางวัล</a></div>
                <div class="nav-item"><a href="admin_manage_users.php" class="nav-link active"><i class="fas fa-users"></i> จัดการสมาชิก</a></div>
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
                    <i class="fas fa-users text-info fs-1"></i>
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">จัดการข้อมูลสมาชิก</h3>
                        <p class="text-muted mb-0">ตรวจสอบรายชื่อสมาชิกและประวัติคะแนนสะสมทั้งหมด</p>
                    </div>
                </div>

                <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                    <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4">
                        <i class="fas fa-check-circle me-2"></i> ลบข้อมูลสมาชิกออกจากระบบเรียบร้อยแล้ว
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Table Panel -->
                <div class="panel-card border-top border-info border-4">
                    <div class="panel-title mb-4">
                        <i class="fas fa-list-ul text-info"></i> รายชื่อสมาชิกและสรุปคะแนน
                    </div>

                    <div class="table-responsive">
                        <table id="userTable" class="table table-custom align-middle w-100">
                            <thead>
                                <tr>
                                    <th>วันที่สมัคร</th>
                                    <th>ชื่อสมาชิก</th>
                                    <th>เบอร์โทรศัพท์</th>
                                    <th class="text-center">สะสมรวม</th>
                                    <th class="text-center">แลกแล้ว</th>
                                    <th class="text-center">คงเหลือ</th>
                                    <th class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()):
                                    $earned = (int)$row['total_earned'];
                                    $redeemed = (int)$row['total_redeemed'];
                                    $remaining = $earned - $redeemed;
                                ?>
                                    <tr>
                                        <td class="small text-muted"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <strong class="text-dark"><?php echo htmlspecialchars($row['firstname']); ?></strong>
                                            </div>
                                        </td>
                                        <td><span class="text-muted"><i class="fas fa-mobile-alt me-2"></i><?php echo $row['phone']; ?></span></td>
                                        <td class="text-center fw-bold text-primary"><?php echo number_format($earned); ?></td>
                                        <td class="text-center fw-bold text-danger"><?php echo number_format($redeemed); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold" style="font-size: 0.9rem;">
                                                <?php echo number_format($remaining); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="?delete_id=<?php echo $row['id']; ?>"
                                                class="btn btn-sm btn-outline-danger rounded-pill px-3 shadow-sm transition-hover"
                                                onclick="return confirm('ยืนยันการลบสมาชิกรายนี้?')">
                                                <i class="fas fa-trash-alt me-1"></i> ลบ
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
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
            $('#userTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/th.json"
                },
                "order": [
                    [0, "desc"]
                ],
                "responsive": true
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