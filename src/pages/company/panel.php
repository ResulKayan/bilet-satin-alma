<?php
// pages/company/panel.php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isCompanyAdmin()) {
    header('Location: ../login.php');
    exit;
}

$user = getCurrentUser();
$company_id = $user['company_id'];

// Türkiye saatine göre şu anki zaman
$simdi = date('Y-m-d H:i:s');
$bugun = date('Y-m-d');

// Firma bilgilerini ve istatistikleri getir
try {
    $db = getDB();
    
    // 1. Firma bilgilerini getir
    $stmt = $db->prepare("SELECT * FROM bus_company WHERE id = ?");
    $stmt->execute([$company_id]);
    $firma = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. İstatistikleri getir (Türkiye saatine göre)
    // Toplam sefer
    $stmt = $db->prepare("SELECT COUNT(*) as toplam_sefer FROM trips WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $toplam_sefer = $stmt->fetch(PDO::FETCH_ASSOC)['toplam_sefer'];
    
    // Aktif sefer (gelecek seferler - Türkiye saatine göre)
    $stmt = $db->prepare("SELECT COUNT(*) as aktif_sefer FROM trips WHERE company_id = ? AND departure_time > ?");
    $stmt->execute([$company_id, $simdi]);
    $aktif_sefer = $stmt->fetch(PDO::FETCH_ASSOC)['aktif_sefer'];
    
    // Toplam bilet
    $stmt = $db->prepare("SELECT COUNT(*) as toplam_bilet FROM tickets WHERE trip_id IN (SELECT id FROM trips WHERE company_id = ?)");
    $stmt->execute([$company_id]);
    $toplam_bilet = $stmt->fetch(PDO::FETCH_ASSOC)['toplam_bilet'];
    
    // Toplam gelir
    $stmt = $db->prepare("SELECT SUM(total_price) as toplam_gelir FROM tickets WHERE trip_id IN (SELECT id FROM trips WHERE company_id = ?) AND status = 'active'");
    $stmt->execute([$company_id]);
    $toplam_gelir_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $toplam_gelir = $toplam_gelir_result['toplam_gelir'] ? $toplam_gelir_result['toplam_gelir'] / 100 : 0;
    
} catch (Exception $e) {
    $firma = ['name' => 'Firma Bilgisi Yok'];
    $toplam_sefer = 0;
    $aktif_sefer = 0;
    $toplam_bilet = 0;
    $toplam_gelir = 0;
    $hata = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Paneli - <?php echo SITE_NAME; ?></title>
    <style>
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .nav { 
            background: white; 
            padding: 15px 20px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .nav a { 
            margin-right: 20px; 
            text-decoration: none; 
            color: #333; 
            font-weight: 500;
        }
        .nav a:hover { color: #667eea; }
        .header { 
            background: white; 
            padding: 25px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .dashboard { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 20px; 
            margin-bottom: 30px;
        }
        .stat-card { 
            background: white; 
            padding: 25px; 
            border-radius: 10px; 
            text-align: center; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number { 
            font-size: 2.5em; 
            font-weight: bold; 
            margin: 10px 0;
        }
        .menu-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 20px; 
        }
        .menu-card { 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            text-align: center; 
            text-decoration: none; 
            color: #333;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .menu-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            background: #667eea;
            color: white;
        }
        .menu-icon { 
            font-size: 3em; 
            margin-bottom: 15px; 
        }
        .company-badge {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            margin-left: 10px;
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 15px; 
            border-radius: 8px; 
            margin: 15px 0;
            border-left: 4px solid #dc3545;
        }
        .firma-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 10px;
            margin-right: 20px;
        }
        .firma-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .dashboard, .menu-grid {
                grid-template-columns: 1fr;
            }
            .firma-header {
                flex-direction: column;
                text-align: center;
            }
            .firma-logo {
                margin-right: 0;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="nav">
        <a href="../../index.php">🏠 Ana Sayfa</a>
        <a href="panel.php">📊 Firma Panel</a>
        <a href="seferler.php">🚌 Seferlerim</a>
        <a href="biletler.php">🎫 Biletler</a>
        <a href="kuponlar.php">🎁 Kuponlar</a>
        <a href="raporlar.php">📊 Raporlar</a>
        <a href="profil.php">🏢 Profil</a>
        <a href="../logout.php">🚪 Çıkış</a>
    </div>

    <?php if(isset($hata)): ?>
        <div class="error">
            <h3>❌ Veritabanı Hatası</h3>
            <p><?php echo $hata; ?></p>
        </div>
    <?php endif; ?>

    <div class="header">
        <div class="firma-header">
            <?php if(!empty($firma['logo_path'])): ?>
                <img src="<?php echo htmlspecialchars($firma['logo_path']); ?>" 
                     alt="<?php echo htmlspecialchars($firma['name']); ?>" 
                     class="firma-logo">
            <?php endif; ?>
            <div>
                <h1>🏢 <?php echo htmlspecialchars($firma['name']); ?></h1>
                <p>Hoş geldiniz, <strong><?php echo htmlspecialchars($user['name']); ?></strong>! 
                   <span class="company-badge">FİRMA ADMIN</span>
                </p>
                <p><strong>Firma ID:</strong> <?php echo $company_id; ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            </div>
        </div>
    </div>

    <!-- İstatistik Kartları -->
    <div class="dashboard">
        <div class="stat-card">
            <div class="menu-icon">🚌</div>
            <div class="stat-number"><?php echo $toplam_sefer; ?></div>
            <p>Toplam Sefer</p>
        </div>
        <div class="stat-card">
            <div class="menu-icon">✅</div>
            <div class="stat-number"><?php echo $aktif_sefer; ?></div>
            <p>Aktif Sefer</p>
        </div>
        <div class="stat-card">
            <div class="menu-icon">🎫</div>
            <div class="stat-number"><?php echo $toplam_bilet; ?></div>
            <p>Satılan Bilet</p>
        </div>
        <div class="stat-card">
            <div class="menu-icon">💰</div>
            <div class="stat-number"><?php echo number_format($toplam_gelir, 2); ?> TL</div>
            <p>Toplam Gelir</p>
        </div>
    </div>

    <!-- Hızlı Menü -->
    <div class="menu-grid">
        <a href="sefer-ekle.php" class="menu-card">
            <div class="menu-icon">➕</div>
            <h3>Sefer Ekle</h3>
            <p>Yeni sefer oluştur</p>
        </a>
        
        <a href="seferler.php" class="menu-card">
            <div class="menu-icon">📋</div>
            <h3>Seferlerim</h3>
            <p>Seferleri yönet</p>
        </a>
        
        <a href="biletler.php" class="menu-card">
            <div class="menu-icon">🎫</div>
            <h3>Biletler</h3>
            <p>Bilet satışlarını gör</p>
        </a>
        
        <a href="kuponlar.php" class="menu-card">
            <div class="menu-icon">🎁</div>
            <h3>Kuponlar</h3>
            <p>İndirim kuponları</p>
        </a>
        
        <a href="raporlar.php" class="menu-card">
            <div class="menu-icon">📊</div>
            <h3>Raporlar</h3>
            <p>Detaylı raporlar</p>
        </a>
        
        <a href="profil.php" class="menu-card">
            <div class="menu-icon">🏢</div>
            <h3>Profil</h3>
            <p>Firma profili</p>
        </a>
    </div>

    <script>
        console.log('Firma Paneli Yüklendi - Firma: <?php echo $firma['name']; ?>');
        console.log('Kullanıcı: <?php echo $user['email']; ?>');
    </script>
</body>
</html>