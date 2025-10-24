<?php
// pages/admin/kupon-yonetimi.php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Sadece admin erişebilir
requireAdmin();

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = $_POST['islem'] ?? '';
    
    try {
        $db = getDB();
        
        if ($islem === 'kupon_ekle') {
            // Yeni kupon ekle
            $kupon_id = generateUUID();
            $kod = trim($_POST['kod'] ?? '');
            $indirim = floatval($_POST['indirim'] ?? 0);
            $kullanım_limiti = intval($_POST['kullanım_limiti'] ?? 0);
            $son_kullanma_tarihi = $_POST['son_kullanma_tarihi'] ?? '';
            
            // Validasyon
            $hata = '';
            if (empty($kod)) $hata = "Kupon kodu gereklidir";
            elseif ($indirim <= 0 || $indirim > 1) $hata = "İndirim oranı 0-1 arasında olmalıdır (0.25 = %25)";
            elseif ($kullanım_limiti <= 0) $hata = "Kullanım limiti 0'dan büyük olmalıdır";
            elseif (empty($son_kullanma_tarihi)) $hata = "Son kullanma tarihi gereklidir";
            elseif (strtotime($son_kullanma_tarihi) < time()) $hata = "Son kullanma tarihi geçmiş olamaz";
            
            if (empty($hata)) {
                // Kupon kodunun benzersiz olup olmadığını kontrol et
                $stmt = $db->prepare("SELECT id FROM coupons WHERE code = ?");
                $stmt->execute([$kod]);
                
                if ($stmt->fetch()) {
                    $hata = "Bu kupon kodu zaten kullanılıyor";
                } else {
                    $stmt = $db->prepare("INSERT INTO coupons (id, code, discount, usage_limit, expire_date) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$kupon_id, $kod, $indirim, $kullanım_limiti, $son_kullanma_tarihi]);
                    $basari = "✅ Kupon başarıyla eklendi: " . htmlspecialchars($kod);
                }
            }
            
        } elseif ($islem === 'kupon_sil' && isset($_POST['kupon_id'])) {
            // Kupon sil
            $kupon_id = $_POST['kupon_id'];
            
            // Önce bu kupon kullanılmış mı kontrol et
            $stmt = $db->prepare("SELECT COUNT(*) as kullanım_sayisi FROM user_coupons WHERE coupon_id = ?");
            $stmt->execute([$kupon_id]);
            $kullanım_sayisi = $stmt->fetch(PDO::FETCH_ASSOC)['kullanım_sayisi'];
            
            if ($kullanım_sayisi > 0) {
                $hata = "❌ Bu kupon $kullanım_sayisi kez kullanılmış. Önce kullanım kayıtlarını silmelisiniz.";
            } else {
                $stmt = $db->prepare("DELETE FROM coupons WHERE id = ?");
                $stmt->execute([$kupon_id]);
                $basari = "✅ Kupon başarıyla silindi";
            }
        }
        
    } catch (Exception $e) {
        $hata = "İşlem sırasında hata: " . $e->getMessage();
    }
}

// Kuponları getir
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM user_coupons WHERE coupon_id = c.id) as kullanım_sayisi
        FROM coupons c 
        ORDER BY c.created_at DESC
    ");
    $kuponlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $kuponlar = [];
    $hata_liste = "Kuponlar yüklenirken hata: " . $e->getMessage();
}

// Kupon istatistikleri
try {
    $db = getDB();
    
    // Toplam kupon sayısı
    $stmt = $db->query("SELECT COUNT(*) as toplam FROM coupons");
    $toplam_kupon = $stmt->fetch(PDO::FETCH_ASSOC)['toplam'];
    
    // Aktif kupon sayısı (son kullanma tarihi geçmemiş)
    $stmt = $db->query("SELECT COUNT(*) as aktif FROM coupons WHERE expire_date > datetime('now')");
    $aktif_kupon = $stmt->fetch(PDO::FETCH_ASSOC)['aktif'];
    
    // Toplam kullanım sayısı
    $stmt = $db->query("SELECT COUNT(*) as toplam_kullanım FROM user_coupons");
    $toplam_kullanım = $stmt->fetch(PDO::FETCH_ASSOC)['toplam_kullanım'];
    
} catch (Exception $e) {
    $istatistik_hata = "İstatistikler yüklenirken hata: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kupon Yönetimi - Admin Panel - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .admin-header { background: #2c3e50; color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #333; }
        .form-container, .list-container { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 5px; }
        button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .success { color: green; background: #eaffea; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffeaea; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .kupon-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .kupon-table th, .kupon-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .kupon-table th { background: #f2f2f2; }
        .kupon-table tr:hover { background: #f9f9f9; }
        .action-buttons { display: flex; gap: 5px; }
        .kupon-kod { font-family: monospace; background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
        .expired { color: #999; text-decoration: line-through; }
        .active { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <div class="nav">
        <a href="../../index.php">🏠 Ana Sayfa</a>
        <a href="../hesabim.php">👤 Hesabım</a>
        <a href="index.php">⚙️ Admin Panel</a>
        <a href="../logout.php">🚪 Çıkış Yap</a>
    </div>

    <div class="admin-header">
        <h1>🎫 Kupon Yönetimi</h1>
        <p>İndirim kuponlarını yönetin</p>
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
            <div class="stat-number"><?php echo $toplam_kupon ?? 0; ?></div>
            <div>Toplam Kupon</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $aktif_kupon ?? 0; ?></div>
            <div>Aktif Kupon</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $toplam_kullanım ?? 0; ?></div>
            <div>Toplam Kullanım</div>
        </div>
    </div>

    <?php if (isset($istatistik_hata)): ?>
        <div class="error"><?php echo $istatistik_hata; ?></div>
    <?php endif; ?>

    <!-- Yeni Kupon Ekleme Formu -->
    <div class="form-container">
        <h2>➕ Yeni Kupon Ekle</h2>
        <form method="POST">
            <input type="hidden" name="islem" value="kupon_ekle">
            
            <div class="form-group">
                <label for="kod">Kupon Kodu *</label>
                <input type="text" name="kod" id="kod" required placeholder="YAZ2024">
            </div>
            
            <div class="form-group">
                <label for="indirim">İndirim Oranı *</label>
                <input type="number" name="indirim" id="indirim" min="0.01" max="1" step="0.01" required placeholder="0.25 = %25 indirim">
                <small>0-1 arası değer girin (0.25 = %25 indirim)</small>
            </div>
            
            <div class="form-group">
                <label for="kullanım_limiti">Kullanım Limiti *</label>
                <input type="number" name="kullanım_limiti" id="kullanım_limiti" min="1" required placeholder="100">
            </div>
            
            <div class="form-group">
                <label for="son_kullanma_tarihi">Son Kullanma Tarihi *</label>
                <input type="date" name="son_kullanma_tarihi" id="son_kullanma_tarihi" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <button type="submit">✅ Kupon Ekle</button>
        </form>
    </div>

    <!-- Kupon Listesi -->
    <div class="list-container">
        <h2>📋 Kupon Listesi</h2>
        
        <?php if (isset($hata_liste)): ?>
            <div class="error"><?php echo $hata_liste; ?></div>
        <?php endif; ?>

        <?php if (empty($kuponlar)): ?>
            <p>Henüz kupon bulunmamaktadır.</p>
        <?php else: ?>
            <table class="kupon-table">
                <thead>
                    <tr>
                        <th>Kupon Kodu</th>
                        <th>İndirim</th>
                        <th>Kullanım</th>
                        <th>Kullanım Limiti</th>
                        <th>Son Kullanma</th>
                        <th>Durum</th>
                        <th>Oluşturulma</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kuponlar as $kupon): ?>
                        <?php 
                        $simdi = time();
                        $son_kullanma = strtotime($kupon['expire_date']);
                        $suresi_dolmus = $son_kullanma < $simdi;
                        $kullanım_dolmus = $kupon['kullanım_sayisi'] >= $kupon['usage_limit'];
                        $aktif = !$suresi_dolmus && !$kullanım_dolmus;
                        ?>
                        <tr class="<?php echo $aktif ? '' : 'expired'; ?>">
                            <td>
                                <span class="kupon-kod"><?php echo htmlspecialchars($kupon['code']); ?></span>
                            </td>
                            <td><strong><?php echo ($kupon['discount'] * 100); ?>%</strong></td>
                            <td><?php echo $kupon['kullanım_sayisi']; ?> / <?php echo $kupon['usage_limit']; ?></td>
                            <td><?php echo $kupon['usage_limit']; ?> kullanım</td>
                            <td><?php echo date('d.m.Y', strtotime($kupon['expire_date'])); ?></td>
                            <td>
                                <?php if ($aktif): ?>
                                    <span class="active">✅ Aktif</span>
                                <?php elseif ($suresi_dolmus): ?>
                                    <span class="expired">❌ Süresi Dolmuş</span>
                                <?php elseif ($kullanım_dolmus): ?>
                                    <span class="expired">❌ Limit Dolmuş</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($kupon['created_at'])); ?></td>
                            <td class="action-buttons">
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Bu kuponu silmek istediğinizden emin misiniz?')">
                                    <input type="hidden" name="islem" value="kupon_sil">
                                    <input type="hidden" name="kupon_id" value="<?php echo $kupon['id']; ?>">
                                    <button type="submit" class="btn-danger">🗑️ Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div style="margin-top: 30px;">
        <a href="index.php" class="btn">← Admin Paneline Dön</a>
    </div>
</body>
</html>