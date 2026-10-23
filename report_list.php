<?php
session_start();
include 'config.php';

// 1. เช็คสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_phone = $_SESSION['phone'];

// 2. ดึงข้อมูลการแจ้งปัญหาจากตาราง reports
$sql = "SELECT * FROM reports WHERE phone = '$user_phone' ORDER BY created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการแจ้งปัญหา - iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #F4F9F6;
            font-family: 'Sarabun', sans-serif;
            color: #333;
        }

        /* Navbar Style */
        .navbar-brand {
            color: #104A33 !important;
            font-weight: 700;
            font-size: 1.4rem;
        }

        /* Layout & Cards */
        .container-main {
            max-width: 1100px;
            margin-top: 20px;
        }

        .card-report {
            border-radius: 20px;
            border: none;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.03);
            background: #fff;
        }

        /* Table Styles */
        .table thead th {
            border-bottom: 2px solid #f0f2f5;
            color: #6c757d;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-bottom: 12px;
        }

        .table tbody td {
            vertical-align: middle;
            color: #2C3E50;
            font-weight: 500;
            border-bottom: 1px solid #f8f9fa;
            padding: 15px 10px;
        }

        /* Badges (Pastel Tone) */
        .badge-process {
            background-color: #FFF3CD;
            color: #856404;
            font-weight: 600;
            padding: 8px 12px;
            font-size: 0.85rem;
        }

        .badge-success {
            background-color: #D1E7DD;
            color: #0F5132;
            font-weight: 600;
            padding: 8px 12px;
            font-size: 0.85rem;
        }

        /* Buttons */
        .btn-action {
            font-size: 0.85rem;
            font-weight: 600;
            padding: 8px 20px;
            transition: 0.3s;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-add {
            background: #104A33;
            color: white;
            border: none;
        }

        .btn-add:hover {
            background: #0c3625;
            color: white;
        }
    </style>
</head>

<body>

    <!-- Navbar (เหมือนหน้า Dashboard) -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm py-3 mb-4">
        <div class="container container-main mt-0">
            <a class="navbar-brand d-flex align-items-center gap-2" href="user_dashboard.php">
                <i class="fas fa-leaf text-success"></i>
                <span>iWasteBank</span>
            </a>
            <div class="d-flex align-items-center gap-2 bg-light rounded-pill px-3 py-2 border">
                <i class="fas fa-user-circle text-success fs-5"></i>
                <span class="fw-bold text-dark text-truncate" style="max-width: 120px;">ผู้ใช้งาน</span>
            </div>
        </div>
    </nav>

    <div class="container container-main pb-5">
        <!-- Header Section -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 55px; height: 55px; font-size: 24px;">
                    <i class="fas fa-tools"></i>
                </div>
                <div>
                    <h4 class="fw-bold text-dark mb-1">รายการแจ้งปัญหาของคุณ</h4>
                    <p class="text-muted small mb-0">ประวัติการแจ้งปัญหาและติดตามสถานะการดำเนินการ</p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="user_dashboard.php" class="btn btn-white bg-white text-dark fw-bold rounded-pill px-4 shadow-sm border btn-action">
                    <i class="fas fa-arrow-left me-2"></i>กลับแดชบอร์ด
                </a>
                <a href="report_form.php" class="btn btn-add fw-bold rounded-pill px-4 shadow-sm btn-action">
                    <i class="fas fa-plus me-2"></i> แจ้งปัญหาใหม่
                </a>
            </div>
        </div>

        <!-- Table Section -->
        <div class="card card-report p-4 p-md-5">
            <div class="table-responsive">
                <table id="reportTable" class="table table-hover w-100 align-middle">
                    <thead>
                        <tr>
                            <th width="15%">วันที่แจ้ง</th>
                            <th width="40%">หัวข้อปัญหา</th>
                            <th width="20%">สถานะ</th>
                            <th width="25%" class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="text-muted small"><i class="far fa-calendar-alt me-1"></i> <?php echo date('d/m/Y', strtotime($row['created_at'])); ?></div>
                                        <div class="fw-bold text-dark"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($row['subject']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill <?php echo ($row['status'] == 'แก้ไขเสร็จสิ้น') ? 'badge-success' : 'badge-process'; ?>">
                                            <?php echo ($row['status'] == 'แก้ไขเสร็จสิ้น') ? '<i class="fas fa-check-circle me-1"></i>' : '<i class="fas fa-hourglass-half me-1"></i>'; ?>
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-outline-success fw-bold rounded-pill px-4 btn-action bg-white shadow-sm"
                                            onclick='showReportDetail(<?php echo json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="fas fa-search me-1"></i> ดูรายละเอียด
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal รายละเอียด -->
    <div class="modal fade" id="reportDetailModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header border-0 bg-light p-4 pb-3">
                    <h5 class="modal-title fw-bold text-dark"><i class="fas fa-file-alt text-success me-2"></i>รายละเอียดเรื่องที่แจ้ง</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-2 letter-spacing-1">หัวข้อเรื่อง</h6>
                        <p id="detSubject" class="fw-bold text-dark fs-5 mb-0"></p>
                    </div>
                    <div class="mb-4">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-2">รายละเอียดเพิ่มเติม</h6>
                        <p id="detDesc" class="p-3 bg-light rounded-4 text-muted mb-0 border-start border-success border-4" style="white-space: pre-wrap; min-height: 80px;"></p>
                    </div>

                    <div id="detImgArea" class="mt-4" style="display:none;">
                        <h6 class="fw-bold text-secondary text-uppercase small mb-2">รูปภาพหลักฐาน</h6>
                        <div class="text-center p-2 border rounded-4 bg-light">
                            <img id="detImg" src="" class="img-fluid rounded-3 shadow-sm" style="max-height: 300px; width: 100%; object-fit: contain;">
                        </div>
                    </div>

                    <div id="noImgText" class="text-center text-muted mt-4 py-4 border border-dashed rounded-4 bg-light" style="display:none;">
                        <i class="fas fa-image-slash fa-2x mb-2 text-secondary opacity-50"></i>
                        <div class="small fw-bold">ไม่มีรูปภาพประกอบ</div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-secondary w-100 rounded-pill fw-bold" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#reportTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
                },
                order: [
                    [0, 'desc']
                ],
                dom: '<"row mb-3"<"col-md-6"l><"col-md-6"f>>rt<"row mt-3"<"col-md-6"i><"col-md-6"p>>', // ปรับ Layout ตารางให้สวยขึ้น
            });
        });

        // ฟังก์ชันแสดงข้อมูลใน Modal
        function showReportDetail(data) {
            $('#detSubject').text(data.subject);
            $('#detDesc').text(data.description);

            if (data.evidence_image && data.evidence_image.trim() !== "" && data.evidence_image !== "NULL") {
                let imgSource = 'uploads/' + data.evidence_image;
                $('#detImg').attr('src', imgSource);
                $('#detImgArea').show();
                $('#noImgText').hide();
            } else {
                $('#detImgArea').hide();
                $('#detImg').attr('src', '');
                $('#noImgText').show();
            }

            var myModal = new bootstrap.Modal(document.getElementById('reportDetailModal'));
            myModal.show();
        }
    </script>
</body>

</html>