<?php
// pages/company/raporlar.php
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

// Tarih aralığı filtresi
$baslangic_tarihi = $_GET['baslangic'] ?? date('Y-m-01');
$bitis_tarihi = $_GET['bitis'] ?? date('Y-m-t');

try {
    $db = getDB();
    
    // Genel istatistikler (Türkiye saatine göre)
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as toplam_sefer,
            SUM(CASE WHEN departure_time > ? THEN 1 ELSE 0 END) as aktif_sefer,
            (SELECT COUNT(*) FROM tickets WHERE trip_id IN (SELECT id FROM trips WHERE company_id = ?)) as toplam_bilet,
            (SELECT SUM(total_price) FROM tickets WHERE status = 'active' AND trip_id IN (SELECT id FROM trips WHERE company_id = ?)) as toplam_gelir
        FROM trips 
        WHERE company_id = ?
    ");
    $stmt->execute([$simdi, $company_id, $company_id, $company_id]);
    $genel_istatistikler = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Aylık satışlar
    $stmt = $db->prepare("
        SELECT 
            strftime('%Y-%m', tk.created_at) as ay,
            COUNT(*) as bilet_sayisi,
            SUM(tk.total_price) as aylik_gelir
        FROM tickets tk
        JOIN trips tr ON tk.trip_id = tr.id
        WHERE tr.company_id = ? AND tk.created_at BETWEEN ? AND ?
        GROUP BY strftime('%Y-%m', tk.created_at)
        ORDER BY ay DESC
    ");
    $stmt->execute([$company_id, $baslangic_tarihi, $bitis_tarihi]);
    $aylik_satislar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Popüler güzergahlar
    $stmt = $db->prepare("
        SELECT 
            departure_city,
            destination_city,
            COUNT(*) as bilet_sayisi,
            SUM(tk.total_price) as gelir
        FROM tickets tk
        JOIN trips tr ON tk.trip_id = tr.id
        WHERE tr.company_id = ? AND tk.created_at BETWEEN ? AND ?
        GROUP BY departure_city, destination_city
        ORDER BY bilet_sayisi DESC
        LIMIT 10
    ");
    $stmt->execute([$company_id, $baslangic_tarihi, $bitis_tarihi]);
    $populer_guzergahlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $hata = "Raporlar yüklenirken hata: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - Firma Panel - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #333; }
        .header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .filter-form { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .report-section { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .report-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .report-table th, .report-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .report-table th { background: #f2f2f2; }
    </style>
</head>
<body>
    <div class="nav">
        <a href="../../index.php">🏠 Ana Sayfa</a>
        <a href="panel.php">📊 Firma Panel</a>
        <a href="seferler.php">🚌 Seferlerim</a>
        <a href="biletler.php">🎫 Biletler</a>
        <a href="kuponlar.php">🎁 Kuponlar</a>
        <a href="../logout.php">🚪 Çıkış</a>
    </div>

    <div class="header">
        <h1>📊 Performans Raporları</h1>
        <p>Firmanızın performansını detaylı şekilde takip edin</p>
    </div>

    <?php if (isset($hata)): ?>
        <div style="color: red; background: #ffeaea; padding: 15px; border-radius: 5px;">
            <?php echo $hata; ?>
        </div>
    <?php endif; ?>

    <!-- Filtre Formu -->
    <div class="filter-form">
        <h3>📅 Tarih Aralığı Seçin</h3>
        <form method="GET">
            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end;">
                <div>
                    <label>Başlangıç Tarihi</label>
                    <input type="date" name="baslangic" value="<?php echo htmlspecialchars($baslangic_tarihi); ?>" style="width: 100%; padding: 8px;">
                </div>
                <div>
                    <label>Bitiş Tarihi</label>
                    <input type="date" name="bitis" value="<?php echo htmlspecialchars($bitis_tarihi); ?>" style="width: 100%; padding: 8px;">
                </div>
                <div>
                    <button type="submit" style="padding: 8px 20px; background: #3498db; color: white; border: none; border-radius: 5px;">🔍 Filtrele</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Genel İstatistikler -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $genel_istatistikler['toplam_sefer'] ?? 0; ?></div>
            <div>Toplam Sefer</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $genel_istatistikler['aktif_sefer'] ?? 0; ?></div>
            <div>Aktif Sefer</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $genel_istatistikler['toplam_bilet'] ?? 0; ?></div>
            <div>Toplam Bilet</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format(($genel_istatistikler['toplam_gelir'] ?? 0) / 100, 2); ?> TL</div>
            <div>Toplam Gelir</div>
        </div>
    </div>

    <!-- Aylık Satış Raporu -->
    <div class="report-section">
        <h3>📈 Aylık Satış Raporu</h3>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Ay</th>
                    <th>Bilet Sayısı</th>
                    <th>Toplam Gelir</th>
                    <th>Ortalama Bilet Fiyatı</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aylik_satislar as $satis): ?>
                    <tr>
                        <td><strong><?php echo date('F Y', strtotime($satis['ay'] . '-01')); ?></strong></td>
                        <td><?php echo $satis['bilet_sayisi']; ?> bilet</td>
                        <td><strong><?php echo number_format($satis['aylik_gelir'] / 100, 2); ?> TL</strong></td>
                        <td><?php echo number_format(($satis['aylik_gelir'] / $satis['bilet_sayisi']) / 100, 2); ?> TL</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Popüler Güzergahlar -->
    <div class="report-section">
        <h3>🏆 Popüler Güzergahlar</h3>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Güzergah</th>
                    <th>Bilet Sayısı</th>
                    <th>Toplam Gelir</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($populer_guzergahlar as $guzergah): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($guzergah['departure_city']); ?> → <?php echo htmlspecialchars($guzergah['destination_city']); ?></strong></td>
                        <td><?php echo $guzergah['bilet_sayisi']; ?> bilet</td>
                        <td><strong><?php echo number_format($guzergah['gelir'] / 100, 2); ?> TL</strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>