<?php
// pages/admin/index.php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Sadece admin erişebilir
requireAdmin();

$user = getCurrentUser();

// İstatistikleri getir
try {
    $db = getDB();

    // Kullanıcı sayısı
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $user_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Firma sayısı
    $stmt = $db->query("SELECT COUNT(*) as count FROM bus_company");
    $company_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Sefer sayısı
    $stmt = $db->query("SELECT COUNT(*) as count FROM trips");
    $trip_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Bilet sayısı
    $stmt = $db->query("SELECT COUNT(*) as count FROM tickets");
    $ticket_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

} catch (Exception $e) {
    $error = "İstatistikler yüklenirken hata: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .admin-header { background: #2c3e50; color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .admin-nav { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .admin-nav a { display: block; padding: 10px; margin: 5px 0; background: #3498db; color: white; text-decoration: none; border-radius: 5px; }
        .admin-nav a:hover { background: #2980b9; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #333; }
    </style>
</head>
<body>
    <div class="nav">
        <a href="../../index.php">🏠 Ana Sayfa</a>
        <a href="../hesabim.php">👤 Hesabım</a>
        <a href="../logout.php">🚪 Çıkış Yap</a>
    </div>

    <div class="admin-header">
        <h1>⚙️ Admin Panel</h1>
        <p>Hoş geldiniz, <strong><?php echo htmlspecialchars($user['name']); ?></strong> (<?php echo htmlspecialchars($user['role']); ?>)</p>
    </div>

    <?php if (isset($error)): ?>
        <div style="color: red; background: #ffeaea; padding: 15px; border-radius: 5px;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $user_count; ?></div>
            <div>Toplam Kullanıcı</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $company_count; ?></div>
            <div>Otobüs Firması</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $ticket_count; ?></div>
            <div>Satılan Bilet</div>
        </div>
    </div>

    <div class="admin-nav">
        <h2>Yönetim İşlemleri</h2>
        <a href="firma-yonetimi.php">🚌 Firma Yönetimi</a>
        <a href="kullanici-yonetimi.php">👥 Kullanıcı Yönetimi</a>
        <a href="kupon-yonetimi.php">🎫 Kupon Yönetimi</a>
    </div>

    <div style="background: white; padding: 20px; border-radius: 10px;">
        <h2>Son İşlemler</h2>
        <p>Bu alanda son yapılan işlemler gösterilecek.</p>
        <p><em>Yakında eklenecek...</em></p>
    </div>
</body>
</html>