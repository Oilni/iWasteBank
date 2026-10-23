<?php
require_once 'config.php';

// 1. เช็คสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 2. รับค่า ID และดึงข้อมูล
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admin_waste_logs.php");
    exit();
}

$id = $conn->real_escape_string($_GET['id']);

$sql = "SELECT w.*, u.firstname, u.lastname 
        FROM waste_logs w 
        LEFT JOIN users u ON w.phone = u.phone 
        WHERE w.id = '$id'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo "<script>alert('ไม่พบข้อมูลรายการนี้'); window.location='admin_waste_logs.php';</script>";
    exit();
}

$data = $result->fetch_assoc();

// --- ส่วนจัดการเรื่อง Path รูปภาพ ---
// ปรับแก้ชื่อโฟลเดอร์ตามที่คุณเก็บจริง เช่น 'uploads/' หรือ 'img/waste/'
$image_folder = "uploads/"; 
$full_image_path = $image_folder . $data['image_path'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดรายการ #<?php echo $id; ?> - iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        body { background: #f4f7f6; font-family: 'Sarabun', sans-serif; }
        .card-detail { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; }
        .detail-header { background: #198754; color: white; padding: 20px; }
        .info-label { color: #6c757d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; }
        .info-value { font-weight: 700; color: #333; font-size: 1.1rem; margin-bottom: 20px; }
        .waste-img-container { background: #eee; border-radius: 15px; overflow: hidden; position: relative; min-height: 250px; display: flex; align-items: center; justify-content: center; }
        .waste-img { width: 100%; height: auto; object-fit: contain; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="mb-4">
                <a href="admin_waste_logs.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                    <i class="fas fa-arrow-left me-1"></i> ย้อนกลับ
                </a>
            </div>

            <div class="card card-detail bg-white">
                <div class="detail-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="mb-0 fw-bold">ID รายการ: #<?php echo $id; ?></h4>
                            <small class="opacity-75">บันทึกเมื่อ: <?php echo date('d/m/Y H:i:s', strtotime($data['created_at'])); ?></small>
                        </div>
                        <div class="col-auto">
                             <span class="badge bg-light text-success fs-6 px-3">
                                <i class="fas fa-coins me-1"></i> +<?php echo number_format($data['points']); ?> แต้ม
                             </span>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="info-label">ผู้ทำรายการ (เบอร์โทรศัพท์)</div>
                            <div class="info-value text-primary"><?php echo $data['phone']; ?></div>

                            <div class="info-label">ชื่อสมาชิก</div>
                            <div class="info-value"><?php echo htmlspecialchars($data['firstname'] ?? 'ไม่ระบุชื่อ'); ?> <?php echo htmlspecialchars($data['lastname'] ?? ''); ?></div>

                            <div class="info-label">ประเภทขยะที่ตรวจพบ</div>
                            <div class="info-value">
                                <span class="badge bg-success px-3 py-2 rounded-pill">
                                    <i class="fas fa-tag me-1"></i> <?php echo strtoupper($data['waste_type']); ?>
                                </span>
                            </div>

                            <div class="info-label">ชื่อไฟล์ภาพในระบบ</div>
                            <div class="info-value small text-muted"><?php echo $data['image_path']; ?></div>

                            <div class="mt-4">
                                <a href="admin_edit_waste.php?id=<?php echo $id; ?>" class="btn btn-warning w-100 fw-bold rounded-pill">
                                    <i class="fas fa-edit me-1"></i> แก้ไขรายการนี้
                                </a>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="info-label mb-2">ภาพหลักฐานการทิ้งขยะ</div>
                            <div class="waste-img-container shadow-sm">
                                <?php if(!empty($data['image_path']) && file_exists($full_image_path)): ?>
                                    <img src="<?php echo $full_image_path; ?>" class="waste-img" alt="Waste Image">
                                <?php else: ?>
                                    <div class="text-center text-muted p-5">
                                        <i class="fas fa-image fa-4x mb-3"></i>
                                        <p>ไม่พบไฟล์ภาพในโฟลเดอร์ <?php echo $image_folder; ?></p>
                                        <small>(ชื่อไฟล์: <?php echo $data['image_path']; ?>)</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>