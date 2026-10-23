<?php
include 'config.php';
//session_start(); // อย่าลืมใส่ session_start() ไว้บรรทัดแรกสุดนะครับ

if(isset($_POST['login'])){
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password']; 

    $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $result = $conn->query($sql);

    if($result->num_rows > 0){
        $row = $result->fetch_assoc();
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['fname'] = $row['firstname'];
        $_SESSION['phone'] = $row['phone'];

        if($row['role'] == 'admin'){
            header("Location: admin.php");
        } else {
            // ปรับจาก index.php เป็นหน้า dashboard ของ user ตามที่เราคุยกัน
            header("Location: user_dashboard.php"); 
        }
        exit();
    } else {
        $error = "Username หรือ Password ไม่ถูกต้อง";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - iWasteBank</title>
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
        }
        .login-wrapper {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            padding: 40px 30px;
            border: none;
            position: relative;
            overflow: hidden;
        }
        /* ของตกแต่งพื้นหลังการ์ด */
        .login-card::before {
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
            width: 75px;
            height: 75px;
            background: linear-gradient(135deg, #104A33 0%, #2ecc71 100%);
            color: white;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 35px;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
            position: relative;
            z-index: 1;
        }
        .login-header {
            text-align: center;
            margin-bottom: 35px;
            position: relative;
            z-index: 1;
        }
        .login-header h3 {
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
        }
        .form-control {
            border-radius: 15px;
            padding: 14px 15px 14px 50px;
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

        /* Button */
        .btn-login {
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
        }
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
            color: white;
        }

        /* Footer Links */
        .login-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px dashed #eee;
            position: relative;
            z-index: 1;
        }
        .link-back {
            color: #6c757d;
            transition: 0.3s;
        }
        .link-back:hover { color: #104A33; }
        .link-register {
            color: #2ecc71;
            transition: 0.3s;
        }
        .link-register:hover { color: #104A33; }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        
        <div class="login-header">
            <div class="brand-icon">
                <i class="fas fa-leaf"></i>
            </div>
            <h3>iWasteBank</h3>
            <p class="text-muted small mb-0">ยินดีต้อนรับเข้าสู่ระบบจัดการขยะ</p>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger border-0 rounded-4 shadow-sm text-center py-2 mb-4 d-flex align-items-center justify-content-center" style="font-size: 0.9rem;">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-icon-wrapper">
                <i class="fas fa-user input-icon"></i>
                <input type="text" name="username" class="form-control" placeholder="ชื่อผู้ใช้งาน (Username)" required>
            </div>
            
            <div class="input-icon-wrapper mb-4">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" name="password" class="form-control" placeholder="รหัสผ่าน (Password)" required>
            </div>
            
            <button type="submit" name="login" class="btn btn-login w-100 shadow-sm">
                เข้าสู่ระบบ <i class="fas fa-sign-in-alt ms-1"></i>
            </button>
        </form>

        <div class="d-flex justify-content-between align-items-center login-footer">
            <a href="index.php" class="text-decoration-none small link-back fw-bold">
                <i class="fas fa-arrow-left me-1"></i> กลับหน้าหลัก
            </a>
            <a href="register.php" class="text-decoration-none small fw-bold link-register">
                สมัครสมาชิกใหม่ <i class="fas fa-user-plus ms-1"></i>
            </a>
        </div>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>