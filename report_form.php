<?php
session_start();
include 'config.php';

// 1. เช็คสิทธิ์การเข้าใช้งาน
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

$waste_id = $_GET['id'] ?? null; // รับ ID จากหน้า Dashboard
$subject_default = $waste_id ? "แจ้งปัญหาเกี่ยวกับรายการขยะ ID: #$waste_id" : "";

// 2. จัดการเมื่อมีการกดปุ่มส่งฟอร์ม
if (isset($_POST['submit_report'])) {
    $phone = $_SESSION['phone'];
    $subject = $conn->real_escape_string($_POST['subject']);
    $description = $conn->real_escape_string($_POST['description']);
    $waste_log_id = !empty($_POST['waste_id']) ? $_POST['waste_id'] : "NULL";
    
    $image_name = null;
    if (!empty($_FILES['image']['name'])) {
        $image_name = time() . "_" . $_FILES['image']['name'];
        move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $image_name);
    }

    $sql = "INSERT INTO reports (phone, waste_log_id, subject, description, evidence_image) 
            VALUES ('$phone', $waste_log_id, '$subject', '$description', " . ($image_name ? "'$image_name'" : "NULL") . ")";
    
    if ($conn->query($sql)) {
        header("Location: report_list.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ส่งเรื่องแจ้งปัญหา - iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #F4F9F6; font-family: 'Sarabun', sans-serif; color: #333; }
        
        /* Navbar Style */
        .navbar-brand { color: #104A33 !important; font-weight: 700; font-size: 1.4rem; }
        
        /* Card & Layout */
        .container-main { max-width: 1100px; margin-top: 20px; }
        .form-container { max-width: 700px; margin: 0 auto; }
        .card-form { border-radius: 20px; border: none; box-shadow: 0 8px 25px rgba(0,0,0,0.03); background: #fff; }
        
        /* Form Inputs */
        .form-label { font-weight: 600; color: #2C3E50; margin-bottom: 8px; }
        .form-control { border-radius: 12px; padding: 12px 15px; border: 1px solid #dee2e6; background-color: #fcfcfc; transition: all 0.3s; }
        .form-control:focus { border-color: #198754; box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.15); background-color: #fff; }
        
        /* Buttons */
        .btn-main { background: #104A33; color: white; border: none; font-weight: 600; padding: 12px; transition: 0.3s; }
        .btn-main:hover { background: #0c3625; color: white; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(16, 74, 51, 0.3); }
        .btn-cancel { font-weight: 600; padding: 12px; transition: 0.3s; }
        .btn-cancel:hover { background-color: #e2e6ea; }
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
    <div class="form-container">
        
        <!-- Header Section -->
        <div class="d-flex align-items-center gap-3 mb-4">
            <a href="report_list.php" class="btn btn-white bg-white text-dark rounded-circle shadow-sm border" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; transition: 0.3s;">
                <i class="fas fa-arrow-left fs-5"></i>
            </a>
            <div>
                <h4 class="fw-bold text-dark mb-1">ส่งเรื่องแจ้งปัญหา</h4>
                <p class="text-muted small mb-0">พบปัญหาการใช้งานหรือมีข้อเสนอแนะ แจ้งแอดมินได้เลย</p>
            </div>
        </div>

        <!-- Form Card -->
        <div class="card card-form p-4 p-md-5">
            <div class="mb-4 text-center">
                <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px; font-size: 30px;">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <h5 class="fw-bold text-dark">แบบฟอร์มข้อมูล</h5>
                <p class="text-muted small">กรุณากรอกข้อมูลให้ครบถ้วนเพื่อให้แอดมินตรวจสอบได้รวดเร็วขึ้น</p>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="waste_id" value="<?php echo htmlspecialchars($waste_id); ?>">
                
                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-heading text-secondary me-2"></i>หัวข้อเรื่อง <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($subject_default); ?>" placeholder="เช่น แลกแต้มไม่ได้, ตู้ไม่สแกนขยะ" required>
                </div>
                
                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-align-left text-secondary me-2"></i>รายละเอียดเพิ่มเติม <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control" rows="5" placeholder="อธิบายรายละเอียดของปัญหาที่พบ วันเวลา หรือสถานที่..." required></textarea>
                </div>
                
                <div class="mb-5">
                    <label class="form-label"><i class="fas fa-camera text-secondary me-2"></i>แนบรูปภาพหลักฐาน <span class="text-muted fw-normal small">(ถ้ามี)</span></label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <div class="form-text mt-2 small"><i class="fas fa-info-circle me-1"></i> รองรับไฟล์รูปภาพ (.jpg, .png) เพื่อช่วยให้แอดมินเห็นภาพปัญหาชัดเจนขึ้น</div>
                </div>
                
                <hr class="mb-4 opacity-25">
                
                <div class="d-grid gap-3">
                    <button type="submit" name="submit_report" class="btn btn-main btn-lg rounded-pill shadow-sm">
                        <i class="fas fa-paper-plane me-2"></i> ยืนยันการส่งเรื่อง
                    </button>
                    <a href="report_list.php" class="btn btn-light btn-cancel rounded-pill text-muted">
                        ยกเลิก
                    </a>
                </div>
            </form>
        </div>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>