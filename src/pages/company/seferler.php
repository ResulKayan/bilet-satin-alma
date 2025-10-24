<?php
// pages/company/seferler.php
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

// Sefer silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sefer_sil'])) {
    try {
        $db = getDB();
        $sefer_id = $_POST['sefer_id'];
        
        // Seferin bu firmaya ait olduğunu ve bilet satılmadığını kontrol et
        $stmt = $db->prepare("
            SELECT t.id, 
                   (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id) as bilet_sayisi
            FROM trips t 
            WHERE t.id = ? AND t.company_id = ?
        ");
        $stmt->execute([$sefer_id, $company_id]);
        $sefer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sefer) {
            $hata = "❌ Bu seferi silme yetkiniz yok";
        } elseif ($sefer['bilet_sayisi'] > 0) {
            $hata = "❌ Bu sefer için bilet satıldığından silinemez";
        } else {
            // Seferi sil
            $stmt = $db->prepare("DELETE FROM trips WHERE id = ?");
            $stmt->execute([$sefer_id]);
            $basari = "✅ Sefer başarıyla silindi";
        }
    } catch (Exception $e) {
        $hata = "Silme işlemi sırasında hata: " . $e->getMessage();
    }
}

// Seferleri getir (Türkiye saatine göre)
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT t.*, 
               (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id) as bilet_sayisi,
               (SELECT COUNT(*) FROM booked_seats WHERE ticket_id IN (SELECT id FROM tickets WHERE trip_id = t.id)) as dolu_koltuk_sayisi,
               CASE 
                   WHEN t.departure_time < ? THEN 'completed'
                   WHEN t.departure_time > ? THEN 'upcoming'
                   ELSE 'active'
               END as durum
        FROM trips t 
        WHERE t.company_id = ?
        ORDER BY t.departure_time DESC
    ");
    $stmt->execute([$simdi, $simdi, $company_id]);
    $seferler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $seferler = [];
    $hata = "Seferler yüklenirken hata: " . $e->getMessage();
}

// İstatistikleri getir (Türkiye saatine göre)
try {
    $db = getDB();
    
    // Toplam sefer
    $stmt = $db->prepare("SELECT COUNT(*) as toplam FROM trips WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $toplam_sefer = $stmt->fetch(PDO::FETCH_ASSOC)['toplam'];
    
    // Aktif sefer (gelecek - Türkiye saatine göre)
    $stmt = $db->prepare("SELECT COUNT(*) as aktif FROM trips WHERE company_id = ? AND departure_time > ?");
    $stmt->execute([$company_id, $simdi]);
    $aktif_sefer = $stmt->fetch(PDO::FETCH_ASSOC)['aktif'];
    
    // Tamamlanan sefer (Türkiye saatine göre)
    $stmt = $db->prepare("SELECT COUNT(*) as tamamlanan FROM trips WHERE company_id = ? AND departure_time < ?");
    $stmt->execute([$company_id, $simdi]);
    $tamamlanan_sefer = $stmt->fetch(PDO::FETCH_ASSOC)['tamamlanan'];
    
    // Toplam bilet
    $stmt = $db->prepare("SELECT COUNT(*) as bilet FROM tickets WHERE trip_id IN (SELECT id FROM trips WHERE company_id = ?)");
    $stmt->execute([$company_id]);
    $toplam_bilet = $stmt->fetch(PDO::FETCH_ASSOC)['bilet'];
    
} catch (Exception $e) {
    $istatistik_hata = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seferlerim - Firma Panel - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #333; }
        .header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .sefer-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 10px; overflow: hidden; }
        .sefer-table th, .sefer-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .sefer-table th { background: #f2f2f2; }
        .btn { padding: 8px 15px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger { background: #e74c3c; }
        .btn-success { background: #27ae60; }
        .btn-warning { background: #f39c12; }
        .success { color: green; background: #eaffea; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffeaea; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .status-upcoming { color: #27ae60; font-weight: bold; }
        .status-active { color: #f39c12; font-weight: bold; }
        .status-completed { color: #7f8c8d; font-weight: bold; }
        .actions { display: flex; gap: 5px; }
    </style>
</head>
<body>
    <div class="nav">
        <a href="../../index.php">🏠 Ana Sayfa</a>
        <a href="panel.php">📊 Firma Panel</a>
        <a href="sefer-ekle.php">➕ Sefer Ekle</a>
        <a href="../logout.php">🚪 Çıkış</a>
    </div>

    <div class="header">
        <h1>🚌 Seferlerim</h1>
        <p>Firmanıza ait seferleri yönetin</p>
    </div>

    <?php if (isset($basari)): ?>
        <div class="success"><?php echo $basari; ?></div>
    <?php endif; ?>

    <?php if (isset($hata)): ?>
        <div class="error"><?php echo $hata; ?></div>
    <?php endif; ?>

    <!-- İstatistikler -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $toplam_sefer ?? 0; ?></div>
            <div>Toplam Sefer</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $aktif_sefer ?? 0; ?></div>
            <div>Aktif Sefer</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $tamamlanan_sefer ?? 0; ?></div>
            <div>Tamamlanan</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $toplam_bilet ?? 0; ?></div>
            <div>Toplam Bilet</div>
        </div>
    </div>

    <a href="sefer-ekle.php" class="btn btn-success">➕ Yeni Sefer Ekle</a>

    <table class="sefer-table">
        <thead>
            <tr>
                <th>Güzergah</th>
                <th>Kalkış</th>
                <th>Varış</th>
                <th>Fiyat</th>
                <th>Kapasite</th>
                <th>Dolu Koltuk</th>
                <th>Bilet Sayısı</th>
                <th>Durum</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($seferler as $sefer): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($sefer['departure_city']); ?> → <?php echo htmlspecialchars($sefer['destination_city']); ?></strong>
                    </td>
                    <td><?php echo date('d.m.Y H:i', strtotime($sefer['departure_time'])); ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($sefer['arrival_time'])); ?></td>
                    <td><strong><?php echo number_format($sefer['price'] / 100, 2); ?> TL</strong></td>
                    <td><?php echo $sefer['capacity']; ?> koltuk</td>
                    <td><?php echo $sefer['dolu_koltuk_sayisi']; ?> koltuk</td>
                    <td><?php echo $sefer['bilet_sayisi']; ?> bilet</td>
                    <td class="status-<?php echo $sefer['durum']; ?>">
                        <?php 
                        switch($sefer['durum']) {
                            case 'upcoming': echo '🟢 Yaklaşan'; break;
                            case 'active': echo '🟡 Devam Eden'; break;
                            case 'completed': echo '🔴 Tamamlandı'; break;
                            default: echo $sefer['durum'];
                        }
                        ?>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="sefer-duzenle.php?id=<?php echo $sefer['id']; ?>" class="btn" title="Düzenle">✏️</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Bu seferi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')">
                                <input type="hidden" name="sefer_id" value="<?php echo $sefer['id']; ?>">
                                <button type="submit" name="sefer_sil" class="btn btn-danger" 
                                        <?php echo $sefer['bilet_sayisi'] > 0 ? 'disabled title="Bilet satılan sefer silinemez"' : 'title="Sil"'; ?>>🗑️</button>
                            </form>
                            <a href="biletler.php?sefer_id=<?php echo $sefer['id']; ?>" class="btn btn-warning" title="Biletleri Gör">🎫</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (empty($seferler)): ?>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 10px; margin-top: 20px;">
            <h3>📭 Henüz sefer bulunmuyor</h3>
            <p>İlk seferinizi oluşturmak için aşağıdaki butonu kullanın.</p>
            <a href="sefer-ekle.php" class="btn btn-success">➕ İlk Seferi Ekle</a>
        </div>
    <?php endif; ?>
</body>
</html>