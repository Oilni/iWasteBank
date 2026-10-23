<?php
include 'config.php';

$message = "";

if (isset($_POST['register'])) {
    // รับค่าจากฟอร์ม
    $fname = $conn->real_escape_string($_POST['firstname']);
    $lname = $conn->real_escape_string($_POST['lastname']);
    $uname = $conn->real_escape_string($_POST['username']);
    $pass  = $_POST['password']; // เก็บแบบตัวอักษรปกติ (Plain Text) ตามที่คุณต้องการ
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $bday  = $_POST['birthday'];

    // 1. ตรวจสอบว่ามี Username หรือ เบอร์โทร นี้ในระบบหรือยัง
    $check = $conn->query("SELECT id FROM users WHERE username = '$uname' OR phone = '$phone'");

    if ($check->num_rows > 0) {
        $message = "<div class='alert alert-danger border-0 rounded-4 shadow-sm text-center py-2 mb-4'><i class='fas fa-exclamation-circle me-2'></i>ขออภัย! Username หรือ เบอร์โทรศัพท์นี้ถูกใช้งานแล้ว</div>";
    } else {
        // 2. บันทึกข้อมูลลงตาราง users
        $sql = "INSERT INTO users (firstname, lastname, username, password, phone, email, birthday, role) 
                VALUES ('$fname', '$lname', '$uname', '$pass', '$phone', '$email', '$bday', 'user')";
        
        if ($conn->query($sql)) {
            // 3. สร้างแถวสำหรับเก็บแต้มเริ่มต้นที่ 0 ในตาราง user_points
            $conn->query("INSERT INTO user_points (phone, total_points) VALUES ('$phone', 0)");
            
            $message = "<div class='alert alert-success border-0 rounded-4 shadow-sm text-center py-2 mb-4'><i class='fas fa-check-circle me-2'></i>สมัครสมาชิกสำเร็จ! <a href='login.php' class='alert-link'>คลิกเพื่อเข้าสู่ระบบ</a></div>";
        } else {
            $message = "<div class='alert alert-danger border-0 rounded-4 shadow-sm text-center py-2 mb-4'><i class='fas fa-times-circle me-2'></i>เกิดข้อผิดพลาด: " . $conn->error . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #e8f5e9 0%, #a5d6a7 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 30px 0;
        }
        .register-wrapper {
            width: 100%;
            max-width: 550px;
            padding: 20px;
        }
        .register-card {
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            padding: 40px 35px;
            border: none;
            position: relative;
            overflow: hidden;
        }
        /* ของตกแต่งพื้นหลังการ์ด */
        .register-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: rgba(46, 204, 113, 0.05);
            border-radius: 50%;
            z-index: 0;
        }
        .brand-icon {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, #104A33 0%, #2ecc71 100%);
            color: white;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin: 0 auto 15px;
            box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
            position: relative;
            z-index: 1;
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }
        .register-header h3 {
            color: #104A33;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        /* Input Fields */
        .input-icon-wrapper {
            position: relative;
            margin-bottom: 1.25rem;
            z-index: 1;
        }
        .input-icon {
            position: absolute;
            top: 50%;
            left: 18px;
            transform: translateY(-50%);
            color: #2ecc71;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        .form-control {
            border-radius: 15px;
            padding: 12px 15px 12px 48px;
            background-color: #f8f9fa;
            border: 1px solid #edf2f6;
            font-size: 1rem;
            color: #333;
            transition: all 0.3s;
        }
        .form-control:focus {
            background-color: #fff;
            border-color: #2ecc71;
            box-shadow: 0 0 0 4px rgba(46, 204, 113, 0.15);
        }
        .form-control::placeholder {
            color: #adb5bd;
            font-size: 0.95rem;
        }
        label.form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
            margin-left: 5px;
            position: relative;
            z-index: 1;
        }

        /* Button */
        .btn-register {
            background: linear-gradient(135deg, #104A33 0%, #2ecc71 100%);
            border: none;
            border-radius: 15px;
            padding: 14px;
            font-weight: 700;
            font-size: 1.05rem;
            color: white;
            transition: all 0.3s;
            position: relative;
            z-index: 1;
            margin-top: 10px;
        }
        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
            color: white;
        }

        /* Footer Links */
        .register-footer {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px dashed #eee;
            position: relative;
            z-index: 1;
            text-align: center;
        }
        .link-login {
            color: #104A33;
            transition: 0.3s;
        }
        .link-login:hover { color: #2ecc71; }
    </style>
</head>
<body>

<div class="register-wrapper">
    <div class="register-card">
        
        <div class="register-header">
            <div class="brand-icon">
                <i class="fas fa-leaf"></i>
            </div>
            <h3>สมัครสมาชิก</h3>
            <p class="text-muted small mb-0">ร่วมเป็นส่วนหนึ่งของสังคมไร้ขยะไปกับ iWasteBank</p>
        </div>

        <?php echo $message; ?>

        <form method="POST">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label small">ชื่อ</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-id-card input-icon"></i>
                        <input type="text" name="firstname" class="form-control" placeholder="ชื่อจริง" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">นามสกุล</label>
                    <div class="input-icon-wrapper">
                        <i class="far fa-id-card input-icon"></i>
                        <input type="text" name="lastname" class="form-control" placeholder="นามสกุล" required>
                    </div>
                </div>
            </div>

            <label class="form-label small">Username (สำหรับเข้าสู่ระบบ)</label>
            <div class="input-icon-wrapper">
                <i class="fas fa-user input-icon"></i>
                <input type="text" name="username" class="form-control" placeholder="ตั้งชื่อผู้ใช้งาน" required>
            </div>

            <label class="form-label small">Password (สำหรับเข้าสู่ระบบ)</label>
            <div class="input-icon-wrapper">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" name="password" class="form-control" placeholder="ตั้งรหัสผ่าน" required>
            </div>

            <label class="form-label small">เบอร์โทรศัพท์ <span class="text-danger">*ใช้สำหรับสะสมแต้ม</span></label>
            <div class="input-icon-wrapper">
                <i class="fas fa-phone-alt input-icon"></i>
                <input type="text" name="phone" class="form-control" placeholder="เช่น 0812345678" maxlength="10" required>
            </div>

            <label class="form-label small">อีเมล (ถ้ามี)</label>
            <div class="input-icon-wrapper">
                <i class="fas fa-envelope input-icon"></i>
                <input type="email" name="email" class="form-control" placeholder="example@mail.com">
            </div>

            <label class="form-label small">วันเดือนปีเกิด</label>
            <div class="input-icon-wrapper mb-4">
                <i class="fas fa-calendar-alt input-icon"></i>
                <input type="date" name="birthday" class="form-control" required>
            </div>

            <button type="submit" name="register" class="btn btn-register w-100 shadow-sm">
                ยืนยันการสมัคร <i class="fas fa-check-circle ms-1"></i>
            </button>
        </form>

        <div class="register-footer">
            <span class="small text-muted">มีบัญชีอยู่แล้วใช่ไหม?</span> 
            <a href="login.php" class="text-decoration-none small fw-bold link-login ms-1">
                เข้าสู่ระบบเลย
            </a>
        </div>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>