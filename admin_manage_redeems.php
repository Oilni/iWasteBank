<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = "";
$error = "";

// 1. จัดการการกดปุ่ม อนุมัติ (รับของแล้ว) หรือ ยกเลิก
if (isset($_POST['action']) && isset($_POST['redeem_id'])) {
    $redeem_id = (int)$_POST['redeem_id'];
    $action = $_POST['action'];

    // เช็คข้อมูลก่อนว่ารายการนี้ยัง "รอรับของ" อยู่หรือไม่
    $res_check = $conn->query("SELECT reward_id, points_used, status FROM redemptions WHERE id = $redeem_id");
    if ($res_check && $res_check->num_rows > 0) {
        $row = $res_check->fetch_assoc();
        
        if ($row['status'] === 'รอรับของ') {
            if ($action === 'approve') {
                // เปลี่ยนสถานะเป็น รับของแล้ว
                $sql = "UPDATE redemptions SET status = 'รับของแล้ว', completed_at = CURRENT_TIMESTAMP WHERE id = $redeem_id";
                if ($conn->query($sql)) {
                    $msg = "ยืนยันการส่งมอบของรางวัลเรียบร้อยแล้ว";
                } else {
                    $error = "เกิดข้อผิดพลาด: " . $conn->error;
                }
            } elseif ($action === 'cancel') {
                $reward_id = $row['reward_id'];
                
                $conn->begin_transaction();
                try {
                    // 1. คืนสถานะเป็น ยกเลิก (ระบบผู้ใช้จะคำนวณแต้มคืนให้อัตโนมัติ เพราะเช็ค status != 'ยกเลิก')
                    $conn->query("UPDATE redemptions SET status = 'ยกเลิก' WHERE id = $redeem_id");
                    
                    // 2. คืนสต๊อกสินค้ากลับเข้าคลัง +1 และปรับสถานะของรางวัลถ้าของเคยหมด
                    $conn->query("UPDATE rewards SET stock = stock + 1, status = 'พร้อมแลก' WHERE id = $reward_id");
                    
                    $conn->commit();
                    $msg = "ยกเลิกรายการ คืนแต้มให้ผู้ใช้ และคืนสต๊อกสำเร็จ";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "เกิดข้อผิดพลาดในการยกเลิก: " . $e->getMessage();
                }
            }
        }
    }
}

// 2. ดึงประวัติการแลกของรางวัลทั้งหมด
$redeems = [];
$sql = "SELECT r.id, r.points_used, r.status, r.redeemed_at, r.completed_at, 
               u.firstname, u.lastname, u.phone, 
               rw.reward_name, rw.image_path 
        FROM redemptions r
        JOIN users u ON r.phone = u.phone
        JOIN rewards rw ON r.reward_id = rw.id
        ORDER BY 
            CASE WHEN r.status = 'รอรับของ' THEN 1 ELSE 2 END, 
            r.redeemed_at DESC";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $redeems[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการแลกของรางวัล - Admin iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- เพิ่ม CSS ของ DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
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
        .sidebar-footer { padding: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.1); }
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
        }

        .reward-img-sm {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #ddd;
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
            <div class="nav-item"><a href="admin_manage_redeems.php" class="nav-link active"><i class="fas fa-gift"></i> รายการแลกของรางวัล</a></div>
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

        <h3 class="fw-bold mb-1 text-dark"><i class="fas fa-gift text-danger me-2"></i>รายการแลกของรางวัล</h3>
        <p class="text-muted mb-4">ตรวจสอบและยืนยันการส่งมอบของรางวัลให้ผู้ใช้งาน</p>

        <?php if($msg): ?>
            <div class="alert alert-success rounded-4 shadow-sm border-0"><i class="fas fa-check-circle me-2"></i><?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger rounded-4 shadow-sm border-0"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="panel-card border-top border-danger border-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-list-ul text-danger me-2"></i>คำขอแลกของรางวัลทั้งหมด</h5>
            </div>

            <div class="table-responsive">
                <!-- เพิ่ม ID redeemTable ตรงนี้ครับ -->
                <table id="redeemTable" class="table table-custom align-middle table-hover w-100">
                    <thead>
                        <tr>
                            <th>รหัส</th>
                            <th>ข้อมูลสมาชิก</th>
                            <th>ของรางวัลที่แลก</th>
                            <th class="text-center">แต้มที่ใช้</th>
                            <th>สถานะ</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($redeems)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">ยังไม่มีรายการแลกของรางวัล</td></tr>
                        <?php else: foreach($redeems as $r): ?>
                        <tr>
                            <td class="text-muted fw-bold">#<?php echo $r['id']; ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($r['firstname'] . ' ' . $r['lastname']); ?></div>
                                <div class="small text-muted"><i class="fas fa-phone-alt me-1"></i><?php echo htmlspecialchars($r['phone']); ?></div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <?php if(!empty($r['image_path'])): ?>
                                        <img src="uploads/rewards/<?php echo htmlspecialchars($r['image_path']); ?>" class="reward-img-sm" alt="reward">
                                    <?php else: ?>
                                        <div class="reward-img-sm d-flex align-items-center justify-content-center bg-light text-muted">
                                            <i class="fas fa-gift"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($r['reward_name']); ?></div>
                                        <div class="small text-muted">วันที่ขอ: <?php echo date('d/m/Y H:i', strtotime($r['redeemed_at'])); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center text-warning fw-bold fs-5">-<?php echo number_format($r['points_used']); ?></td>
                            <td>
                                <?php if($r['status'] == 'รอรับของ'): ?>
                                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm"><i class="fas fa-clock me-1"></i> รอรับของ</span>
                                <?php elseif($r['status'] == 'รับของแล้ว'): ?>
                                    <span class="badge bg-success px-3 py-2 rounded-pill shadow-sm"><i class="fas fa-check me-1"></i> รับของแล้ว</span>
                                    <div class="small text-muted mt-1"><?php echo date('d/m H:i', strtotime($r['completed_at'])); ?></div>
                                <?php else: ?>
                                    <span class="badge bg-danger px-3 py-2 rounded-pill shadow-sm"><i class="fas fa-times me-1"></i> ยกเลิก</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($r['status'] == 'รอรับของ'): ?>
                                    <form method="POST" class="d-inline-block" onsubmit="return confirm('ยืนยันว่าสมาชิกได้รับของรางวัลแล้วใช่หรือไม่?');">
                                        <input type="hidden" name="redeem_id" value="<?php echo $r['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm mb-1 w-100">ส่งมอบแล้ว</button>
                                    </form>
                                    <form method="POST" class="d-inline-block" onsubmit="return confirm('ต้องการยกเลิกรายการ คืนแต้ม และคืนสต๊อกใช่หรือไม่?');">
                                        <input type="hidden" name="redeem_id" value="<?php echo $r['id']; ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 w-100">ยกเลิก/คืนแต้ม</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-light rounded-pill px-3 disabled text-muted">จัดการแล้ว</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<!-- เพิ่ม JavaScript สำหรับ DataTables -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        var table = $('#redeemTable').DataTable({
            language: { 
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
            },
            order: [[0, 'desc']], // เรียงลำดับจากรหัสล่าสุดให้อยู่บนสุดเสมอ
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
        });
        
        // ตกแต่งช่อง Search ให้โค้งมนสวยงาม
        $('.dataTables_filter input').addClass('form-control rounded-pill px-3').attr('placeholder', 'ค้นหาชื่อ, เบอร์โทร...');
        $('.dataTables_length select').addClass('form-select rounded-pill px-3');

        // --- เพิ่มระบบรับค่าจากการสแกน QR Code ---
        const urlParams = new URLSearchParams(window.location.search);
        const searchQuery = urlParams.get('search');
        if (searchQuery) {
            // ถ้ามีการสแกนมา ให้พิมพ์เบอร์โทรลงช่องค้นหาและกรองข้อมูลทันที
            table.search(searchQuery).draw();
        }
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