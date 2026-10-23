<?php
session_start();
include 'config.php';

// ดึง 10 อันดับแรกที่มีแต้มรวมสูงสุด
$top_users = [];
$sql = "SELECT u.firstname, u.lastname, u.profile_img, SUM(w.points) as total_points 
        FROM users u 
        JOIN waste_logs w ON u.phone = w.phone 
        GROUP BY u.phone 
        ORDER BY total_points DESC 
        LIMIT 10";

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $top_users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กระดานจัดอันดับ - iWasteBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f7f9fc; font-family: 'Sarabun', sans-serif; color: #333; }
        .leaderboard-header { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); padding: 50px 20px 80px 20px; border-radius: 0 0 40px 40px; color: white; text-align: center; box-shadow: 0 10px 30px rgba(230, 126, 34, 0.3); position: relative; }
        .back-btn { position: absolute; top: 25px; left: 20px; color: white; text-decoration: none; background: rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 50px; font-weight: 600; backdrop-filter: blur(5px); }
        .rank-card { background: white; border-radius: 20px; padding: 15px 25px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 5px 15px rgba(0,0,0,0.03); transition: 0.3s; }
        .rank-card:hover { transform: translateX(5px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
        
        .rank-number { font-size: 1.8rem; font-weight: 800; width: 40px; text-align: center; }
        .rank-1 .rank-number { color: #f1c40f; font-size: 2.2rem; }
        .rank-2 .rank-number { color: #bdc3c7; font-size: 2rem; }
        .rank-3 .rank-number { color: #cd7f32; font-size: 2rem; }
        .rank-normal .rank-number { color: #95a5a6; }
        
        .rank-1 { border-left: 6px solid #f1c40f; background: #fffcf0; }
        .rank-2 { border-left: 6px solid #bdc3c7; background: #fdfdfd; }
        .rank-3 { border-left: 6px solid #cd7f32; background: #fdf9f5; }
        
        .user-avatar { width: 55px; height: 55px; border-radius: 50%; object-fit: cover; border: 2px solid #eee; }
    </style>
</head>
<body>

<div class="leaderboard-header">
    <a href="user_dashboard.php" class="back-btn"><i class="fas fa-arrow-left me-2"></i>แดชบอร์ด</a>
    <i class="fas fa-trophy fa-3x mb-3 text-warning shadow-sm rounded-circle bg-white p-3"></i>
    <h2 class="fw-bold mb-1">Hall of Fame</h2>
    <p class="mb-0 opacity-75">10 อันดับฮีโร่รักษ์โลกที่มีแต้มสะสมสูงสุด</p>
</div>

<div class="container pb-5" style="max-width: 700px; margin-top: -40px; position: relative; z-index: 10;">
    <?php if (empty($top_users)): ?>
        <div class="text-center py-5 bg-white rounded-4 shadow-sm">
            <h5 class="text-muted">ยังไม่มีข้อมูลการสะสมแต้ม</h5>
        </div>
    <?php else: ?>
        <?php foreach ($top_users as $index => $user): 
            $rank = $index + 1;
            $class = ($rank == 1) ? 'rank-1' : (($rank == 2) ? 'rank-2' : (($rank == 3) ? 'rank-3' : 'rank-normal'));
            
            $img = (!empty($user['profile_img']) && file_exists("uploads/" . $user['profile_img'])) 
                   ? "uploads/" . $user['profile_img'] 
                   : "https://cdn-icons-png.flaticon.com/512/149/149071.png";
        ?>
            <div class="rank-card <?php echo $class; ?>">
                <div class="d-flex align-items-center gap-3">
                    <div class="rank-number">
                        <?php if($rank == 1): ?><i class="fas fa-crown"></i>
                        <?php else: echo $rank; endif; ?>
                    </div>
                    <img src="<?php echo $img; ?>" class="user-avatar" alt="Avatar">
                    <div>
                        <div class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></div>
                        <div class="small text-muted">นักรักษ์โลก อันดับที่ <?php echo $rank; ?></div>
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-bolder text-success fs-4"><?php echo number_format($user['total_points']); ?></div>
                    <div class="small text-muted text-uppercase fw-bold">แต้ม</div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>