<?php
// pages/hesabim.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if(!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT t.*, tr.departure_city, tr.destination_city, tr.departure_time, tr.arrival_time, tr.price,
               bc.name as company_name
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        JOIN bus_company bc ON tr.company_id = bc.id
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $biletler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $biletler = [];
    $error = "Biletler yüklenirken hata: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesabım - <?php echo SITE_NAME; ?></title>
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }
        
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .nav { 
            background: white; 
            padding: 15px 20px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .nav-links {
            display: flex;
            gap: 15px;
        }
        .nav a { 
            text-decoration: none; 
            color: #333; 
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .nav a:hover { 
            color: var(--primary-color); 
            background: #f8f9fa;
        }
        .page-header {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .page-header h1 {
            margin: 0;
            font-size: 2.5em;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .user-info { 
            background: white; 
            padding: 30px; 
            border-radius: 15px; 
            margin-bottom: 30px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-left: 5px solid var(--primary-color);
        }
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .user-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid var(--primary-color);
        }
        .user-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9em;
        }
        .user-value {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
            margin-top: 5px;
        }
        .bakiye {
            color: var(--success-color);
            font-size: 1.4em !important;
        }
        .bilet-list { 
            background: white; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .bilet-card { 
            border: 1px solid #e9ecef; 
            padding: 25px; 
            margin: 20px 0; 
            border-radius: 12px; 
            transition: all 0.3s;
            border-left: 5px solid var(--primary-color);
        }
        .bilet-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .status-active { 
            background: #d4edda; 
            color: #155724; 
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .status-cancelled { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .status-expired { 
            background: #e2e3e5; 
            color: #383d41; 
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .btn { 
            padding: 10px 20px; 
            background: var(--primary-color); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block;
            font-weight: 500;
            transition: all 0.3s;
            margin: 5px;
        }
        .btn:hover { 
            background: var(--secondary-color); 
            transform: translateY(-2px);
        }
        .btn-danger { 
            background: var(--danger-color); 
        }
        .btn-danger:hover { 
            background: #c82333; 
        }
        .btn-success { 
            background: var(--success-color); 
        }
        .btn-success:hover { 
            background: #218838; 
        }
        .bos-liste {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .bilet-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }
        .bilet-rotasi {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
        }
        .bilet-detay {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 15px 0;
        }
        .bilet-bilgi {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f0f0;
        }
        .bilgi-label {
            font-weight: 600;
            color: #666;
        }
        .bilgi-deger {
            color: #333;
        }
        .fiyat {
            color: var(--danger-color);
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .bilet-detay {
                grid-template-columns: 1fr;
            }
            .nav-links {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <div class="logo">
                <h2 style="margin: 0; color: var(--primary-color);">👤 <?php echo SITE_NAME; ?></h2>
            </div>
            <div class="nav-links">
                <a href="../index.php">🏠 Ana Sayfa</a>
                <a href="sefer-ara.php">🔍 Sefer Ara</a>
                <a href="biletlerim.php">🎫 Biletlerim</a>
                <a href="logout.php">🚪 Çıkış Yap</a>
            </div>
        </div>

        <div class="page-header">
            <h1>👤 Hesabım</h1>
            <p style="color: #666; margin-top: 10px;">Kişisel bilgileriniz ve bilet geçmişiniz</p>
        </div>

        <div class="user-info">
            <h2 style="margin-top: 0; color: var(--primary-color);">📊 Kullanıcı Bilgileri</h2>
            <div class="user-grid">
                <div class="user-item">
                    <div class="user-label">Ad Soyad</div>
                    <div class="user-value"><?php echo htmlspecialchars($user['name']); ?></div>
                </div>
                <div class="user-item">
                    <div class="user-label">Email</div>
                    <div class="user-value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                <div class="user-item">
                    <div class="user-label">Bakiye</div>
                    <div class="user-value bakiye"><?php echo number_format($user['balance'], 2); ?> TL</div>
                </div>
            </div>
        </div>

        <div class="bilet-list">
            <h2 style="margin-top: 0; color: var(--primary-color);">🎟️ Biletlerim</h2>
            
            <?php if (isset($error)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 15px 0;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($biletler)): ?>
                <div class="bos-liste">
                    <h3 style="color: #666;">🎫 Henüz biletiniz bulunmamaktadır</h3>
                    <p style="color: #888; margin-bottom: 30px;">İlk biletinizi almak için hemen sefer arayın!</p>
                    <a href="sefer-ara.php" class="btn btn-success">🚌 Bilet Al</a>
                </div>
            <?php else: ?>
                <?php foreach ($biletler as $bilet): ?>
                    <div class="bilet-card">
                        <div class="bilet-header">
                            <div class="bilet-rotasi">
                                <?php echo htmlspecialchars($bilet['departure_city']); ?> 
                                <span style="color: var(--primary-color);">→</span> 
                                <?php echo htmlspecialchars($bilet['destination_city']); ?>
                            </div>
                            <div>
                                <span class="status-<?php echo $bilet['status']; ?>">
                                    <?php 
                                    $durumlar = [
                                        'active' => 'Aktif',
                                        'cancelled' => 'İptal Edildi',
                                        'expired' => 'Süresi Dolmuş'
                                    ];
                                    echo $durumlar[$bilet['status']] ?? $bilet['status'];
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="bilet-detay">
                            <div>
                                <div class="bilet-bilgi">
                                    <span class="bilgi-label">Firma:</span>
                                    <span class="bilgi-deger"><?php echo htmlspecialchars($bilet['company_name']); ?></span>
                                </div>
                                <div class="bilet-bilgi">
                                    <span class="bilgi-label">Kalkış:</span>
                                    <span class="bilgi-deger"><?php echo date('d.m.Y H:i', strtotime($bilet['departure_time'])); ?></span>
                                </div>
                                <div class="bilet-bilgi">
                                    <span class="bilgi-label">Varış:</span>
                                    <span class="bilgi-deger"><?php echo date('d.m.Y H:i', strtotime($bilet['arrival_time'])); ?></span>
                                </div>
                            </div>
                            <div>
                                <div class="bilet-bilgi">
                                    <span class="bilgi-label">Fiyat:</span>
                                    <span class="bilgi-deger fiyat"><?php echo number_format($bilet['total_price'] / 100, 2); ?> TL</span>
                                </div>
                                <div class="bilet-bilgi">
                                    <span class="bilgi-label">Bilet Tarihi:</span>
                                    <span class="bilgi-deger"><?php echo date('d.m.Y H:i', strtotime($bilet['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($bilet['status'] === 'active'): ?>
                            <div style="margin-top: 15px; display: flex; gap: 10px;">
                                <a href="bilet-iptal.php?bilet_id=<?php echo $bilet['id']; ?>" class="btn btn-danger">
                                    ❌ İptal Et
                                </a>
                                <a href="bilet-detay.php?bilet_id=<?php echo $bilet['id']; ?>" class="btn">
                                    📄 Detayları Gör
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>