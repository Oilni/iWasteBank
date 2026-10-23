<?php
//session_start();
require_once 'config.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_phone = $_SESSION['phone'];
$msg = "";

// --- 1. จัดการการบันทึกข้อมูลส่วนตัวและรูปภาพ ---
if (isset($_POST['update_profile'])) {
    $firstname = $conn->real_escape_string($_POST['firstname'] ?? '');
    $lastname  = $conn->real_escape_string($_POST['lastname'] ?? '');
    $email     = $conn->real_escape_string($_POST['email'] ?? '');
    $address   = $conn->real_escape_string($_POST['address'] ?? '');
    $province  = $conn->real_escape_string($_POST['province'] ?? '');
    $zipcode   = $conn->real_escape_string($_POST['zipcode'] ?? '');

    // จัดการรูปภาพ (ถ้ามีการเลือกรูปใหม่)
    if (!empty($_FILES['profile_img']['name'])) {
        $target_dir = "uploads/";
        $ext = strtolower(pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION));
        $new_name = "profile_" . $user_phone . "_" . time() . "." . $ext;
        
        if (move_uploaded_file($_FILES['profile_img']['tmp_name'], $target_dir . $new_name)) {
            $conn->query("UPDATE users SET profile_img = '$new_name' WHERE phone = '$user_phone'");
        }
    }

    $sql_upd = "UPDATE users SET 
                firstname='$firstname', lastname='$lastname', 
                email='$email', address='$address', 
                province='$province', zipcode='$zipcode' 
                WHERE phone='$user_phone'";
    
    if ($conn->query($sql_upd)) {
        $msg = "บันทึกข้อมูลเรียบร้อยแล้ว!";
    }
}

// --- 2. ดึงข้อมูลสดจาก DB ---
$user = $conn->query("SELECT * FROM users WHERE phone = '$user_phone'")->fetch_assoc();
$display_img = (!empty($user['profile_img']) && file_exists("uploads/" . $user['profile_img'])) 
               ? "uploads/" . $user['profile_img'] . "?v=" . time() 
               : "https://cdn-icons-png.flaticon.com/512/149/149071.png";
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ของฉัน - iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary: #198754; 
            --primary-dark: #115c38;
            --bg: #F4F9F6; 
            --card-radius: 24px;
        }
        body { 
            background: var(--bg); 
            font-family: 'Sarabun', sans-serif; 
            color: #2C3E50; 
            padding-bottom: 50px;
        }
        
        /* Header Section */
        .profile-header { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); 
            height: 240px; 
            border-radius: 0 0 40px 40px; 
            position: relative; 
            box-shadow: 0 10px 30px rgba(25, 135, 84, 0.2);
            overflow: hidden;
        }
        .profile-header::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            top: -50px;
            right: -100px;
        }
        
        .back-btn { 
            position: absolute; 
            top: 25px; 
            left: 20px; 
            color: white; 
            text-decoration: none; 
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            backdrop-filter: blur(5px);
            transition: 0.3s;
            z-index: 10;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            transform: translateX(-3px);
        }
        
        /* Avatar */
        .avatar-container { 
            margin-top: -110px; 
            position: relative; 
            display: inline-block; 
            z-index: 5; 
        }
        .avatar-img { 
            width: 170px; 
            height: 170px; 
            border: 6px solid #fff; 
            border-radius: 50%; 
            object-fit: cover; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
            background: #fff; 
        }
        .cam-overlay { 
            position: absolute; 
            bottom: 5px; 
            right: 15px; 
            background: #FFC107; 
            color: #000; 
            width: 48px; 
            height: 48px; 
            border-radius: 50%; 
            border: 4px solid #fff; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            cursor: pointer; 
            transition: 0.3s; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .cam-overlay:hover { transform: scale(1.1) rotate(5deg); background: #FFD54F;}

        /* Card Style */
        .info-card { 
            background: #fff; 
            border: none; 
            border-radius: var(--card-radius); 
            box-shadow: 0 8px 25px rgba(0,0,0,0.03); 
            margin-bottom: 25px; 
            overflow: hidden; 
            transition: 0.3s;
        }
        .info-card:hover { box-shadow: 0 12px 30px rgba(0,0,0,0.06); }
        .card-title-box { 
            padding: 22px 25px; 
            border-bottom: 1px dashed #eee; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .card-title-box h6 { margin: 0; font-weight: 700; color: var(--primary); font-size: 1.1rem;}

        /* Form Styling */
        .form-control { 
            border-radius: 15px; 
            padding: 12px 18px; 
            border: 2px solid #eef2f5; 
            background: #fff; 
            font-size: 1rem; 
            font-weight: 500;
            color: #2C3E50;
            transition: all 0.3s ease;
        }
        
        /* สไตล์เมื่อช่องเป็น Readonly (โหมดดูข้อมูล) */
        .form-control[readonly] {
            background-color: transparent !important;
            border-color: transparent !important;
            padding-left: 0;
            padding-right: 0;
            font-weight: 600;
            color: #34495E;
            box-shadow: none !important;
        }

        .form-control:not([readonly]):focus { 
            box-shadow: 0 0 0 4px rgba(25, 135, 84, 0.15); 
            border-color: var(--primary); 
            background: #fdfdfd;
        }
        
        .label-custom { 
            font-size: 0.8rem; 
            font-weight: 700; 
            color: #95a5a6; 
            text-transform: uppercase; 
            margin-bottom: 5px; 
            letter-spacing: 0.5px;
        }
        
        /* Button Style */
        .btn-edit-toggle {
            background: #f1f8f5;
            color: var(--primary);
            border: none;
            padding: 6px 15px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: 0.3s;
        }
        .btn-edit-toggle:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-save { 
            background: linear-gradient(135deg, #198754 0%, #115c38 100%); 
            border: none; 
            border-radius: 50px; 
            padding: 16px; 
            font-weight: 700; 
            box-shadow: 0 10px 20px rgba(25, 135, 84, 0.2); 
            transition: 0.3s;
        }
        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px rgba(25, 135, 84, 0.3); 
        }

        /* Alert Animation */
        .alert-custom {
            animation: slideDown 0.5s ease forwards;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="profile-header">
    <a href="user_dashboard.php" class="back-btn"><i class="fas fa-arrow-left me-2"></i> กลับหน้าหลัก</a>
</div>

<div class="container text-center">
    <!-- รูปโปรไฟล์ -->
    <div class="avatar-container">
        <img src="<?php echo $display_img; ?>" id="previewImg" class="avatar-img shadow">
        <label for="imgInput" class="cam-overlay"><i class="fas fa-camera"></i></label>
    </div>
    
    <div class="mt-3 mb-4">
        <h3 class="fw-bolder mb-1 text-dark"><?php echo htmlspecialchars($user['firstname']." ".$user['lastname']); ?></h3>
        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-bold">
            <i class="fas fa-mobile-alt me-1"></i> <?php echo htmlspecialchars($user['phone']); ?>
        </span>
    </div>

    <?php if($msg): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 alert-custom" id="alert-box">
            <i class="fas fa-check-circle me-2 fs-5 align-middle"></i> <span class="fw-bold"><?php echo $msg; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="profileForm" class="text-start max-w-md mx-auto" style="max-width: 800px;">
        <input type="file" name="profile_img" id="imgInput" hidden accept="image/*" onchange="previewFile(this)">
        
        <!-- ข้อมูลผู้ใช้งาน -->
        <div class="info-card">
            <div class="card-title-box">
                <h6><i class="fas fa-id-card me-2"></i>ข้อมูลส่วนตัว</h6>
                <button type="button" class="btn-edit-toggle" onclick="toggleEdit('personal')">
                    <i class="fas fa-pen fa-sm me-1"></i> แก้ไข
                </button>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6 text-start">
                        <label class="label-custom">ชื่อจริง</label>
                        <input type="text" name="firstname" class="form-control" value="<?php echo htmlspecialchars($user['firstname']); ?>" readonly>
                    </div>
                    <div class="col-md-6 text-start">
                        <label class="label-custom">นามสกุล</label>
                        <input type="text" name="lastname" class="form-control" value="<?php echo htmlspecialchars($user['lastname']); ?>" readonly>
                    </div>
                    <div class="col-12 text-start">
                        <label class="label-custom">อีเมลติดต่อ</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly placeholder="ยังไม่ได้ระบุอีเมล">
                    </div>
                </div>
            </div>
        </div>

        <!-- ที่อยู่ปัจจุบัน -->
        <div class="info-card">
            <div class="card-title-box">
                <h6><i class="fas fa-map-marker-alt me-2"></i>ที่อยู่สำหรับจัดส่งของรางวัล</h6>
                <button type="button" class="btn-edit-toggle" onclick="toggleEdit('address')">
                    <i class="fas fa-pen fa-sm me-1"></i> แก้ไข
                </button>
            </div>
            <div class="card-body p-4">
                <div class="mb-4">
                    <label class="label-custom">ที่อยู่ (บ้านเลขที่ / ถนน / ตำบล / อำเภอ)</label>
                    <textarea name="address" class="form-control" rows="2" readonly placeholder="ยังไม่ได้ระบุที่อยู่"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>
                <div class="row g-4">
                    <div class="col-6">
                        <label class="label-custom">จังหวัด</label>
                        <input type="text" name="province" class="form-control" value="<?php echo htmlspecialchars($user['province'] ?? ''); ?>" readonly placeholder="-">
                    </div>
                    <div class="col-6">
                        <label class="label-custom">รหัสไปรษณีย์</label>
                        <input type="text" name="zipcode" class="form-control" value="<?php echo htmlspecialchars($user['zipcode'] ?? ''); ?>" readonly placeholder="-">
                    </div>
                </div>
            </div>
        </div>

        <!-- ปุ่มบันทึกการแก้ไขโปรไฟล์ (ซ่อนไว้ก่อน) -->
        <div id="saveBtnContainer" style="display: none;" class="text-center mt-2 mb-5">
            <button type="submit" name="update_profile" class="btn btn-save btn-success w-100 text-white fs-5">
                <i class="fas fa-save me-2"></i> บันทึกการเปลี่ยนแปลง
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // สลับโหมดแก้ไขข้อมูลโปรไฟล์
    function toggleEdit(section) {
        let selector = '';

        if (section === 'personal') {
            selector = 'input[name="firstname"], input[name="lastname"], input[name="email"]';
        } else if (section === 'address') {
            selector = 'textarea[name="address"], input[name="province"], input[name="zipcode"]';
        }

        const inputs = document.querySelectorAll(selector);
        const saveBtn = document.getElementById('saveBtnContainer');

        let isEditing = false;

        inputs.forEach(input => {
            // สลับสถานะ Readonly
            input.readOnly = !input.readOnly;
            
            if (!input.readOnly) {
                isEditing = true;
            }
        });

        // ตรวจสอบว่ามีช่องไหนเปิด Edit อยู่ไหม เพื่อโชว์ปุ่มบันทึก
        const anyEditable = document.querySelectorAll(
            '#profileForm input:not([name="profile_img"]):not([readonly]), #profileForm textarea:not([readonly])'
        );
        
        if (anyEditable.length > 0) {
            saveBtn.style.display = 'block';
        } else {
            saveBtn.style.display = 'none';
        }

        // ให้เคอร์เซอร์ไปที่ช่องแรกทันที
        if (isEditing && inputs.length > 0) {
            inputs[0].focus();
        }
    }

    // Preview รูปภาพโปรไฟล์ และเปิดปุ่ม Save
    function previewFile(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewImg').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
            
            // ถ้าเปลี่ยนรูป ให้โชว์ปุ่ม Save ทันที
            document.getElementById('saveBtnContainer').style.display = 'block';
        }
    }

    // ซ่อน Alert อัตโนมัติใน 3 วินาที
    setTimeout(() => {
        const alert = document.getElementById('alert-box');
        if (alert) {
            alert.style.transition = "opacity 0.5s ease";
            alert.style.opacity = "0";
            setTimeout(() => alert.style.display = 'none', 500);
        }
    }, 3000);
</script>
</body>
</html>