<?php session_start(); ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iWasteBank - ระบบจัดการขยะอัจฉริยะ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Sarabun', sans-serif; 
            background-color: #f4f9f6;
            overflow-x: hidden;
        }

        /* Navbar */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            padding: 15px 0;
        }
        .navbar-brand {
            font-weight: 800;
            color: #104A33 !important;
            font-size: 1.5rem;
        }

        /* Hero Section */
        .hero-section { 
            background: linear-gradient(135deg, #104A33 0%, #2ecc71 100%); 
            color: white;
            padding: 120px 0 100px;
            position: relative;
            overflow: hidden;
            border-bottom-left-radius: 50px;
            border-bottom-right-radius: 50px;
            box-shadow: 0 15px 30px rgba(46, 204, 113, 0.15);
        }
        
        /* ของตกแต่งพื้นหลัง */
        .hero-section::before {
            content: ''; position: absolute; top: -50px; left: -100px; width: 400px; height: 400px;
            background: rgba(255,255,255,0.05); border-radius: 50%;
        }
        .hero-section::after {
            content: ''; position: absolute; bottom: -100px; right: -50px; width: 300px; height: 300px;
            background: rgba(255,255,255,0.08); border-radius: 50%;
        }

        .brand-icon-large {
            font-size: 5rem;
            margin-bottom: 20px;
            text-shadow: 0 10px 20px rgba(0,0,0,0.15);
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }

        .hero-title { font-weight: 800; font-size: 4rem; margin-bottom: 15px; letter-spacing: -1px; }
        .hero-subtitle { font-size: 1.3rem; font-weight: 300; opacity: 0.9; margin-bottom: 45px; }

        /* Buttons */
        .btn-custom { 
            padding: 15px 40px; 
            font-size: 1.15rem; 
            border-radius: 50px; 
            transition: all 0.3s; 
            position: relative;
            z-index: 1;
        }
        .btn-login { 
            background-color: white; 
            color: #104A33; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .btn-login:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 15px 30px rgba(0,0,0,0.25); 
            background-color: #f8f9fa;
            color: #104A33;
        }
        .btn-register { 
            background-color: rgba(255,255,255,0.1); 
            color: white; 
            border: 2px solid rgba(255,255,255,0.6); 
            backdrop-filter: blur(5px);
        }
        .btn-register:hover { 
            background-color: white; 
            color: #104A33;
            transform: translateY(-5px); 
        }

        /* Features Section */
        .features-section { padding: 80px 0; }
        .feature-card {
            background: white;
            border-radius: 28px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            transition: all 0.4s;
            height: 100%;
            border: 1px solid rgba(0,0,0,0.02);
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(46, 204, 113, 0.15);
        }
        .feature-icon {
            width: 80px;
            height: 80px;
            background: #e8f5e9;
            color: #2ecc71;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 25px;
        }
        .feature-title { font-weight: 700; color: #104A33; margin-bottom: 15px; }

        @media (max-width: 768px) {
            .hero-title { font-size: 2.8rem; }
            .btn-group-custom { display: flex; flex-direction: column; gap: 15px; padding: 0 20px; }
            .btn-custom { width: 100%; margin: 0 !important; }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-leaf text-success me-2"></i>iWasteBank
            </a>
            <div class="d-none d-md-block">
                <a href="login.php" class="btn btn-outline-success rounded-pill px-4 fw-bold me-2">เข้าสู่ระบบ</a>
                <a href="register.php" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">สมัครสมาชิก</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section text-center">
        <div class="container position-relative" style="z-index: 2;">
            <div class="brand-icon-large">
                <i class="fas fa-recycle"></i>
            </div>
            <h1 class="hero-title">iWasteBank</h1>
            <p class="hero-subtitle">เปลี่ยนขยะให้เป็นแต้มสะสม เพื่อโลกที่สะอาดขึ้นด้วยเทคโนโลยี</p>
            
            <div class="btn-group-custom mt-2">
                <a href="login.php" class="btn btn-custom btn-login fw-bold me-md-3">
                    <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบ
                </a>
                <a href="register.php" class="btn btn-custom btn-register fw-bold">
                    <i class="fas fa-user-plus me-2"></i>สมัครสมาชิกใหม่
                </a>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="features-section container">
        <div class="text-center mb-5">
            <h2 class="fw-bold text-dark mb-2">ทำงานอย่างไร?</h2>
            <p class="text-muted">3 ขั้นตอนง่ายๆ ในการร่วมสร้างสิ่งแวดล้อมที่ดีและรับสิทธิประโยชน์</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-trash-alt"></i></div>
                    <h5 class="feature-title">1. แยกและทิ้งขยะ</h5>
                    <p class="text-muted small mb-0">คัดแยกขยะของคุณตามประเภท (พลาสติก, กระดาษ, โลหะ) และนำมาทิ้งที่ถังขยะอัจฉริยะของเรา</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-coins"></i></div>
                    <h5 class="feature-title">2. รับแต้มสะสม</h5>
                    <p class="text-muted small mb-0">ระบบจะคำนวณและประเมินมูลค่าขยะโดยอัตโนมัติ พร้อมโอนแต้มสะสมเข้าสู่บัญชีของคุณทันที</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-gift"></i></div>
                    <h5 class="feature-title">3. แลกรับรางวัล</h5>
                    <p class="text-muted small mb-0">นำแต้มสะสมที่คุณมี มาแลกเปลี่ยนเป็นเงินสด หรือของรางวัลสุดพิเศษผ่านระบบได้อย่างง่ายดาย</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>