<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = "";
$error = "";

// สร้างโฟลเดอร์เก็บรูปถ้ายังไม่มี
$upload_dir = 'uploads/rewards/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 1. จัดการเพิ่มของรางวัล
if (isset($_POST['add_reward'])) {
    $name = $conn->real_escape_string($_POST['reward_name']);
    $points = (int)$_POST['points_required'];
    $stock = (int)$_POST['stock'];
    $status = 'พร้อมแลก'; // ค่าเริ่มต้นตอนเพิ่มใหม่
    
    // จัดการอัปโหลดรูปภาพ
    $image_path = NULL;
    if (isset($_FILES['reward_image']) && $_FILES['reward_image']['error'] == 0) {
        $ext = pathinfo($_FILES['reward_image']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid('reward_') . '.' . $ext;
        $target_file = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['reward_image']['tmp_name'], $target_file)) {
            $image_path = $new_filename;
        }
    }

    $stmt = $conn->prepare("INSERT INTO rewards (reward_name, points_required, stock, image_path, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("siiss", $name, $points, $stock, $image_path, $status);
    
    if ($stmt->execute()) {
        $msg = "เพิ่มของรางวัลเข้าคลังสำเร็จ!";
    } else {
        $error = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}

// 2. จัดการอัปเดตข้อมูลของรางวัล (สต๊อก, แต้ม, สถานะ)
if (isset($_POST['update_reward'])) {
    $id = (int)$_POST['reward_id'];
    $stock = (int)$_POST['new_stock'];
    $points = (int)$_POST['new_points'];
    $status = $conn->real_escape_string($_POST['new_status']);
    
    $stmt = $conn->prepare("UPDATE rewards SET stock = ?, points_required = ?, status = ? WHERE id = ?");
    $stmt->bind_param("iisi", $stock, $points, $status, $id);
    
    if($stmt->execute()){
        $msg = "อัปเดตข้อมูลของรางวัลสำเร็จแล้ว!";
    } else {
        $error = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}

// 3. ดึงข้อมูลของรางวัลทั้งหมด
$rewards = [];
$res = $conn->query("SELECT * FROM rewards ORDER BY created_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rewards[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการคลังของรางวัล - Admin iWasteBank</title>
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

        /* ปรับความกว้าง Sidebar ให้เท่ากับหน้าอื่น ๆ ที่ 260px */
        .sidebar {
            width: 260px;
            min-width: 260px;
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
            height: 100%;
        }

        .reward-img-preview {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px dashed #ddd;
        }

        /* สำหรับตาราง Input */
        .table-custom th {
            color: #95a5a6;
            font-weight: 600;
            font-size: 0.9rem;
            background: #f8f9fa;
        }
        .table-custom td {
            vertical-align: middle;
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
            <div class="nav-item"><a href="admin_manage_users.php" class="nav-link"><i class="fas fa-users"></i> จัดการสมาชิก</a></div>
            <div class="nav-item"><a href="admin_waste_logs.php" class="nav-link"><i class="fas fa-list-ul"></i> ประวัติทั้งหมด</a></div>
            <div class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-file-alt"></i> รายงานข้อมูล</a></div>
            <div class="nav-item"><a href="trends.php" class="nav-link"><i class="fas fa-chart-line"></i> แนวโน้มข้อมูล</a></div>
            <div class="nav-item"><a href="telegram_logs.php" class="nav-link"><i class="fas fa-bell"></i> ประวัติ Telegram</a></div>

            <div class="px-4 text-uppercase small fw-bold mb-2 opacity-50 mt-4">ตั้งค่าระบบ</div>
            <div class="nav-item"><a href="admin_manage_waste_types.php" class="nav-link"><i class="fas fa-coins"></i> ตั้งค่าคะแนน</a></div>
            <div class="nav-item"><a href="admin_manage_prices.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> ตั้งราคาขยะ</a></div>
            <div class="nav-item"><a href="admin_manage_rewards.php" class="nav-link active"><i class="fas fa-box-open"></i> จัดการคลังของรางวัล</a></div>
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

        <h3 class="fw-bold mb-1 text-dark"><i class="fas fa-box-open text-primary me-2"></i>จัดการคลังของรางวัล (Reward Catalog)</h3>
        <p class="text-muted mb-4">เพิ่ม ลบ และอัปเดตข้อมูลของรางวัลสำหรับผู้ใช้งาน (ปรับแต้ม สต๊อก และสถานะได้อิสระ)</p>

        <?php if($msg): ?>
            <div class="alert alert-success rounded-4 shadow-sm border-0"><i class="fas fa-check-circle me-2"></i><?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger rounded-4 shadow-sm border-0"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- 1. ฟอร์มเพิ่มของรางวัล (ปรับเป็น 4 ส่วน) -->
            <div class="col-xl-4 col-lg-5">
                <div class="panel-card border-top border-primary border-4">
                    <h6 class="fw-bold mb-4"><i class="fas fa-plus-circle text-primary me-2"></i>เพิ่มของรางวัลใหม่</h6>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">ชื่อของรางวัล</label>
                            <input type="text" name="reward_name" class="form-control" placeholder="เช่น แก้วน้ำเยติ, ร่มพับ" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">แต้มที่ต้องใช้แลก</label>
                            <div class="input-group">
                                <input type="number" name="points_required" class="form-control text-warning fw-bold" required min="1">
                                <span class="input-group-text bg-light">แต้ม</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">จำนวนสต๊อก</label>
                            <input type="number" name="stock" class="form-control" value="10" required min="0">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">รูปภาพของรางวัล</label>
                            <input type="file" name="reward_image" class="form-control" accept="image/jpeg, image/png, image/webp" required>
                        </div>
                        <button type="submit" name="add_reward" class="btn btn-primary w-100 rounded-pill fw-bold py-2 shadow-sm">
                            <i class="fas fa-save me-1"></i> เพิ่มลงแคตตาล็อก
                        </button>
                    </form>
                </div>
            </div>

            <!-- 2. ตารางแสดงรายการของรางวัล (ปรับเป็น 8 ส่วน) -->
            <div class="col-xl-8 col-lg-7">
                <div class="panel-card border-top border-success border-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-list text-success me-2"></i>คลังของรางวัลปัจจุบัน</h5>
                    <div class="table-responsive">
                        <table class="table table-custom table-hover align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center">รูปภาพ</th>
                                    <th>ชื่อของรางวัล</th>
                                    <th class="text-center">แต้มที่ใช้</th>
                                    <th class="text-center">สต๊อก</th>
                                    <th class="text-center">สถานะ</th>
                                    <th class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($rewards)): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">ยังไม่มีของรางวัลในระบบ</td></tr>
                                <?php else: foreach($rewards as $item): ?>
                                <tr>
                                    <!-- ใช้ HTML5 attribute "form" เชื่อม Input เข้ากับฟอร์มเดียวกัน -->
                                    <form method="POST" id="editForm_<?php echo $item['id']; ?>"></form>
                                    <input type="hidden" name="reward_id" value="<?php echo $item['id']; ?>" form="editForm_<?php echo $item['id']; ?>">
                                    
                                    <td class="text-center">
                                        <img src="uploads/rewards/<?php echo htmlspecialchars($item['image_path']); ?>" 
                                             class="reward-img-preview shadow-sm" alt="reward">
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($item['reward_name']); ?></div>
                                        <div class="small text-muted">ID: #<?php echo $item['id']; ?></div>
                                    </td>
                                    <td class="text-center">
                                        <!-- แก้ไขแต้มได้อิสระ -->
                                        <input type="number" name="new_points" class="form-control text-center fw-bold text-warning" 
                                               value="<?php echo $item['points_required']; ?>" min="1" required
                                               form="editForm_<?php echo $item['id']; ?>" title="ปรับแต้ม" style="min-width: 80px;">
                                    </td>
                                    <td class="text-center">
                                        <!-- แก้ไขสต๊อก -->
                                        <input type="number" name="new_stock" class="form-control text-center" 
                                               value="<?php echo $item['stock']; ?>" min="0" required
                                               form="editForm_<?php echo $item['id']; ?>" title="ปรับสต๊อก" style="min-width: 70px;">
                                    </td>
                                    <td class="text-center">
                                        <!-- ปรับสถานะได้อิสระ -->
                                        <?php 
                                            // ปรับสีขอบให้ตรงกับสถานะ
                                            $border_color = '';
                                            if($item['status'] == 'พร้อมแลก') $border_color = 'border-success text-success bg-success bg-opacity-10 fw-bold';
                                            if($item['status'] == 'ของหมด') $border_color = 'border-warning text-warning bg-warning bg-opacity-10 fw-bold';
                                            if($item['status'] == 'ยกเลิก') $border_color = 'border-danger text-danger bg-danger bg-opacity-10 fw-bold';
                                        ?>
                                        <select name="new_status" class="form-select <?php echo $border_color; ?>" form="editForm_<?php echo $item['id']; ?>" style="min-width: 110px;">
                                            <option value="พร้อมแลก" <?php if($item['status'] == 'พร้อมแลก') echo 'selected'; ?>>พร้อมแลก</option>
                                            <option value="ของหมด" <?php if($item['status'] == 'ของหมด') echo 'selected'; ?>>ของหมด</option>
                                            <option value="ยกเลิก" <?php if($item['status'] == 'ยกเลิก') echo 'selected'; ?>>ยกเลิก</option>
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <!-- ปุ่มกดเซฟทีเดียว -->
                                        <button type="submit" name="update_reward" form="editForm_<?php echo $item['id']; ?>" class="btn btn-sm btn-success w-100 rounded-pill shadow-sm py-2">
                                            <i class="fas fa-save me-1"></i>บันทึก
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
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