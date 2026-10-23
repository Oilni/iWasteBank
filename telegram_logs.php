<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$binFilter = trim($_GET['bin'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');

$binTypes = [];
$typeResult = $conn->query("SELECT DISTINCT bin_name FROM telegram_logs WHERE bin_name <> '' ORDER BY bin_name ASC");
if ($typeResult) {
    while ($row = $typeResult->fetch_assoc()) {
        $binTypes[] = $row['bin_name'];
    }
}

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(bin_name LIKE ? OR message LIKE ? OR status LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}
if ($binFilter !== '') {
    $where[] = "bin_name = ?";
    $params[] = $binFilter;
    $types .= 's';
}
if ($dateFilter !== '') {
    $where[] = "DATE(created_at) = ?";
    $params[] = $dateFilter;
    $types .= 's';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM telegram_logs{$whereSql}");
if ($types !== '') $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$stmt = $conn->prepare(
    "SELECT id, bin_name, message, status, created_at
     FROM telegram_logs{$whereSql}
     ORDER BY created_at DESC, id DESC
     LIMIT ? OFFSET ?"
);
$bindParams = $params;
$bindParams[] = $perPage;
$bindParams[] = $offset;
$bindTypes = $types . 'ii';
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$result = $stmt->get_result();

$allTotal = (int)($conn->query("SELECT COUNT(*) AS total FROM telegram_logs")->fetch_assoc()['total'] ?? 0);
$successTotal = (int)($conn->query("SELECT COUNT(*) AS total FROM telegram_logs WHERE status = 'ส่งสำเร็จ'")->fetch_assoc()['total'] ?? 0);
$todayTotal = (int)($conn->query("SELECT COUNT(*) AS total FROM telegram_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'] ?? 0);

function thaiDateTime($value)
{
    $ts = strtotime($value);
    if (!$ts) return htmlspecialchars($value);
    return date('d/m/', $ts) . ((int)date('Y', $ts) + 543) . ' ' . date('H:i:s', $ts);
}
function binIcon($name)
{
    if (mb_strpos($name, 'พลาสติก') !== false) return 'fa-bottle-water';
    if (mb_strpos($name, 'กระป๋อง') !== false) return 'fa-can-food';
    if (mb_strpos($name, 'กระดาษ') !== false) return 'fa-file-lines';
    if (mb_strpos($name, 'ทั่วไป') !== false) return 'fa-trash';
    return 'fa-trash-can';
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการแจ้งเตือน Telegram - Admin iWasteBank</title>
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

        .panel-card,
        .stat-card {
            border: none;
            border-radius: var(--card-radius);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            background: #fff;
        }

        .stat-card {
            height: 100%;
            padding: 1.5rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .telegram-icon {
            background: #e7f7ef;
            color: #198754;
        }

        .success-icon {
            background: #e8f7ee;
            color: #198754;
        }

        .today-icon {
            background: #fff3cd;
            color: #997404;
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

        .message-cell {
            min-width: 250px;
            max-width: 400px;
            word-break: break-word;
        }

        .bin-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #eaf7f0;
            color: #198754;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 999px;
            background: #d1e7dd;
            color: #0f5132;
            font-size: .85rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .empty-state {
            padding: 55px 20px;
            text-align: center;
            color: #6c757d;
        }

        .pagination .page-link {
            color: #198754;
        }

        .pagination .active .page-link {
            background: #198754;
            border-color: #198754;
            color: #fff;
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
                <div class="nav-item"><a href="telegram_logs.php" class="nav-link active"><i class="fas fa-bell"></i> ประวัติ Telegram</a></div>

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

            <div class="d-flex align-items-center gap-3 mb-4">
                <i class="fas fa-bell text-primary fs-1"></i>
                <div>
                    <h3 class="fw-bold mb-0 text-dark">ประวัติการแจ้งเตือน Telegram</h3>
                    <p class="text-muted mb-0">ตรวจสอบประวัติการส่งข้อความแจ้งเตือนไปยังกลุ่ม Telegram ของแอดมิน</p>
                </div>
            </div>

            <!-- STAT CARDS -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card p-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon telegram-icon me-3"><i class="fas fa-bell"></i></div>
                            <div>
                                <div class="text-muted small">การแจ้งเตือนทั้งหมด</div>
                                <div class="fs-4 fw-bold"><?php echo number_format($allTotal); ?> <span class="fs-6 fw-normal text-muted">ครั้ง</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card p-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon success-icon me-3"><i class="fas fa-circle-check"></i></div>
                            <div>
                                <div class="text-muted small">ส่งสำเร็จ</div>
                                <div class="fs-4 fw-bold"><?php echo number_format($successTotal); ?> <span class="fs-6 fw-normal text-muted">ครั้ง</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card p-3">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon today-icon me-3"><i class="fas fa-calendar-day"></i></div>
                            <div>
                                <div class="text-muted small">แจ้งเตือนวันนี้</div>
                                <div class="fs-4 fw-bold"><?php echo number_format($todayTotal); ?> <span class="fs-6 fw-normal text-muted">ครั้ง</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILTER CARD -->
            <div class="panel-card p-4 mb-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-filter me-2 text-success"></i>ค้นหาและกรองข้อมูล</h6>
                <form method="GET" action="telegram_logs.php">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">ค้นหา</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="ค้นหาประเภทถังหรือข้อความ..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">ประเภทถัง</label>
                            <select name="bin" class="form-select">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($binTypes as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $binFilter === $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">วันที่</label>
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($dateFilter); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-success w-100 fw-bold"><i class="fas fa-search me-1"></i>ค้นหา</button>
                            <a href="telegram_logs.php" class="btn btn-outline-secondary" title="ล้างตัวกรอง"><i class="fas fa-rotate-left"></i></a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TABLE PANEL -->
            <div class="panel-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">รายการแจ้งเตือน</h6>
                        <div class="text-muted small">พบ <?php echo number_format($totalRows); ?> รายการ</div>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2"><i class="fas fa-database me-1"></i>telegram_logs</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ประเภทถัง</th>
                                <th>ข้อความแจ้งเตือน</th>
                                <th>สถานะ</th>
                                <th>วันที่ / เวลา</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-muted fw-bold">#<?php echo (int)$row['id']; ?></td>
                                        <td>
                                            <span class="bin-badge">
                                                <i class="fas <?php echo binIcon($row['bin_name']); ?>"></i>
                                                <?php echo htmlspecialchars($row['bin_name']); ?>
                                            </span>
                                        </td>
                                        <td class="message-cell"><?php echo nl2br(htmlspecialchars($row['message'])); ?></td>
                                        <td>
                                            <span class="status-badge">
                                                <i class="fas fa-check-circle me-1"></i>
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap text-muted small">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo thaiDateTime($row['created_at']); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="fas fa-bell-slash fa-3x mb-3 text-light"></i>
                                            <div class="fw-bold mb-1 text-dark">ไม่พบประวัติการแจ้งเตือน</div>
                                            <div class="small">เมื่อ ESP32 ส่งแจ้งเตือน Telegram รายการจะแสดงที่หน้านี้</div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center mb-0">
                            <?php $qp = ['search' => $search, 'bin' => $binFilter, 'date' => $dateFilter]; ?>
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($qp, ['page' => $page - 1])); ?>"><i class="fas fa-chevron-left"></i></a>
                            </li>
                            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($qp, ['page' => $p])); ?>"><?php echo $p; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($qp, ['page' => $page + 1])); ?>"><i class="fas fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
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