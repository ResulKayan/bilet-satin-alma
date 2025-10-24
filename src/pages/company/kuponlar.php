<?php
// pages/company/kuponlar.php
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

// Önce coupons tablosunda status sütunu var mı kontrol et, yoksa ekle
try {
    $db = getDB();
    
    // coupons tablosunda status sütunu var mı kontrol et
    $stmt = $db->query("PRAGMA table_info(coupons)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $status_column_exists = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'status') {
            $status_column_exists = true;
            break;
        }
    }
    
    // Eğer status sütunu yoksa, ekle
    if (!$status_column_exists) {
        $db->exec("ALTER TABLE coupons ADD COLUMN status TEXT DEFAULT 'active'");
    }
    
} catch (Exception $e) {
    // Hata olursa devam et, belki tablo zaten güncel
}

// Firma bilgilerini getir
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT name FROM bus_company WHERE id = ?");
    $stmt->execute([$company_id]);
    $firma = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$firma) {
        die("❌ Firma bilgileri bulunamadı.");
    }
    
    // Firma adından ön ek oluştur
    $firma_adi = $firma['name'];
    $on_ek = firmaAdindanOnEkOlustur($firma_adi);
    
} catch (Exception $e) {
    die("❌ Hata: " . $e->getMessage());
}

// KUPON EKLEME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kupon_ekle'])) {
    $kod = trim($_POST['kod'] ?? '');
    $indirim = floatval($_POST['indirim'] ?? 0);
    $kullanım_limiti = intval($_POST['kullanım_limiti'] ?? 0);
    $son_kullanma_tarihi = $_POST['son_kullanma_tarihi'] ?? '';

    try {
        $db = getDB();
        
        // Validasyon
        $hata = '';
        if (empty($kod)) $hata = "Kupon kodu gereklidir";
        elseif ($indirim <= 0 || $indirim > 100) $hata = "İndirim oranı 1-100 arasında olmalıdır";
        elseif ($kullanım_limiti <= 0) $hata = "Kullanım limiti 0'dan büyük olmalıdır";
        elseif (empty($son_kullanma_tarihi)) $hata = "Son kullanma tarihi gereklidir";

        if (empty($hata)) {
            $kupon_id = generateUUID();
            
            // OTOMATİK ÖN EK: Firma adını kupon koduna ekle
            $tam_kod = $on_ek . '_' . strtoupper($kod);
            
            // Kupon kodunun benzersiz olup olmadığını kontrol et
            $stmt = $db->prepare("SELECT id FROM coupons WHERE code = ?");
            $stmt->execute([$tam_kod]);
            
            if ($stmt->fetch()) {
                $hata = "Bu kupon kodu zaten kullanılıyor: " . $tam_kod;
            } else {
                $stmt = $db->prepare("INSERT INTO coupons (id, code, discount, usage_limit, expire_date, company_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$kupon_id, $tam_kod, $indirim / 100, $kullanım_limiti, $son_kullanma_tarihi, $company_id]);
                
                $basari = "✅ Kupon başarıyla eklendi!<br>Kupon Kodu: <strong>" . $tam_kod . "</strong>";
            }
        }
    } catch (Exception $e) {
        $hata = "Kupon eklenirken hata: " . $e->getMessage();
    }
}

// KUPON DÜZENLEME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kupon_duzenle'])) {
    $kupon_id = $_POST['kupon_id'] ?? '';
    $indirim = floatval($_POST['indirim'] ?? 0);
    $kullanım_limiti = intval($_POST['kullanım_limiti'] ?? 0);
    $son_kullanma_tarihi = $_POST['son_kullanma_tarihi'] ?? '';
    $durum = $_POST['durum'] ?? 'active';

    try {
        $db = getDB();
        
        // Kuponun bu firmaya ait olduğunu kontrol et
        $stmt = $db->prepare("SELECT id FROM coupons WHERE id = ? AND company_id = ?");
        $stmt->execute([$kupon_id, $company_id]);
        
        if (!$stmt->fetch()) {
            $hata = "❌ Bu kuponu düzenleme yetkiniz yok!";
        } else {
            $stmt = $db->prepare("UPDATE coupons SET discount = ?, usage_limit = ?, expire_date = ?, status = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$indirim / 100, $kullanım_limiti, $son_kullanma_tarihi, $durum, $kupon_id, $company_id]);
            
            $basari = "✅ Kupon başarıyla güncellendi!";
        }
    } catch (Exception $e) {
        $hata = "Kupon güncellenirken hata: " . $e->getMessage();
    }
}

// KUPON SİLME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kupon_sil'])) {
    $kupon_id = $_POST['kupon_id'] ?? '';
    
    try {
        $db = getDB();
        
        // Kuponun bu firmaya ait olduğunu ve kullanılmadığını kontrol et
        $stmt = $db->prepare("
            SELECT c.id, 
                   (SELECT COUNT(*) FROM user_coupons WHERE coupon_id = c.id) as kullanım_sayisi
            FROM coupons c 
            WHERE c.id = ? AND c.company_id = ?
        ");
        $stmt->execute([$kupon_id, $company_id]);
        $kupon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$kupon) {
            $hata = "❌ Bu kuponu silme yetkiniz yok!";
        } elseif ($kupon['kullanım_sayisi'] > 0) {
            $hata = "❌ Bu kupon kullanılmış olduğu için silinemez!";
        } else {
            $stmt = $db->prepare("DELETE FROM coupons WHERE id = ? AND company_id = ?");
            $stmt->execute([$kupon_id, $company_id]);
            $basari = "✅ Kupon başarıyla silindi!";
        }
    } catch (Exception $e) {
        $hata = "Kupon silinirken hata: " . $e->getMessage();
    }
}

// TOPLU KUPON OLUŞTURMA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toplu_kupon_olustur'])) {
    $adet = intval($_POST['adet'] ?? 0);
    $indirim = floatval($_POST['toplu_indirim'] ?? 0);
    $kullanım_limiti = intval($_POST['toplu_kullanım_limiti'] ?? 1);
    $son_kullanma_tarihi = $_POST['toplu_son_kullanma_tarihi'] ?? '';
    $kod_uzunlugu = intval($_POST['kod_uzunlugu'] ?? 8);

    try {
        $db = getDB();
        $olusturulan_kuponlar = [];
        $basarisiz_kuponlar = [];
        
        for ($i = 0; $i < $adet; $i++) {
            // Rastgele kod oluştur
            $rastgele_kod = substr(strtoupper(bin2hex(random_bytes($kod_uzunlugu))), 0, $kod_uzunlugu);
            $tam_kod = $on_ek . '_' . $rastgele_kod;
            $kupon_id = generateUUID();
            
            // Kupon kodunun benzersiz olup olmadığını kontrol et
            $stmt = $db->prepare("SELECT id FROM coupons WHERE code = ?");
            $stmt->execute([$tam_kod]);
            
            if ($stmt->fetch()) {
                $basarisiz_kuponlar[] = $tam_kod;
                continue; // Benzersiz değilse atla
            }
            
            $stmt = $db->prepare("INSERT INTO coupons (id, code, discount, usage_limit, expire_date, company_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$kupon_id, $tam_kod, $indirim / 100, $kullanım_limiti, $son_kullanma_tarihi, $company_id]);
            
            $olusturulan_kuponlar[] = $tam_kod;
        }
        
        $basari = "✅ <strong>$adet</strong> adet kupon başarıyla oluşturuldu!";
        if (!empty($olusturulan_kuponlar)) {
            $basari .= "<br>Oluşturulan kuponlar: <strong>" . implode(', ', $olusturulan_kuponlar) . "</strong>";
        }
        if (!empty($basarisiz_kuponlar)) {
            $hata = "❌ Bazı kuponlar oluşturulamadı (benzersiz değil): " . implode(', ', $basarisiz_kuponlar);
        }
        
    } catch (Exception $e) {
        $hata = "Toplu kupon oluşturulurken hata: " . $e->getMessage();
    }
}

// Düzenlenecek kupon bilgilerini getir
$duzenlenecek_kupon = null;
if (isset($_GET['duzenle'])) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM coupons WHERE id = ? AND company_id = ?");
        $stmt->execute([$_GET['duzenle'], $company_id]);
        $duzenlenecek_kupon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$duzenlenecek_kupon) {
            $hata = "❌ Kupon bulunamadı veya düzenleme yetkiniz yok!";
        }
    } catch (Exception $e) {
        $hata = "Kupon bilgileri yüklenirken hata: " . $e->getMessage();
    }
}

// Kuponları getir (sadece bu firma için)
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM user_coupons WHERE coupon_id = c.id) as kullanım_sayisi
        FROM coupons c 
        WHERE c.company_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$company_id]);
    $kuponlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $kuponlar = [];
    $hata_liste = "Kuponlar yüklenirken hata: " . $e->getMessage();
}

// Kupon istatistiklerini getir (Türkiye saatine göre)
try {
    $db = getDB();
    
    // Genel istatistikler
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as toplam_kupon,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as aktif_kupon,
            SUM(CASE WHEN expire_date < ? THEN 1 ELSE 0 END) as suresi_dolmus,
            (SELECT COUNT(*) FROM user_coupons WHERE coupon_id IN (SELECT id FROM coupons WHERE company_id = ?)) as toplam_kullanım
        FROM coupons 
        WHERE company_id = ?
    ");
    $stmt->execute([$simdi, $company_id, $company_id]);
    $istatistikler = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $istatistikler = ['toplam_kupon' => 0, 'aktif_kupon' => 0, 'suresi_dolmus' => 0, 'toplam_kullanım' => 0];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuponlar - Firma Panel - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #333; }
        .header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .form-container { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .kupon-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 10px; overflow: hidden; }
        .kupon-table th, .kupon-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .kupon-table th { background: #f2f2f2; }
        .btn { padding: 8px 15px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-success { background: #27ae60; }
        .btn-warning { background: #f39c12; }
        .btn-danger { background: #e74c3c; }
        .btn-info { background: #17a2b8; }
        .success { color: green; background: #eaffea; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffeaea; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .expired { color: #999; text-decoration: line-through; }
        .on-ek-bilgi { background: #e8f4fd; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #3498db; }
        .kupon-onizleme { background: #f0f8f0; padding: 10px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #27ae60; }
        .kupon-kod { font-family: 'Courier New', monospace; font-weight: bold; background: #f8f9fa; padding: 5px 10px; border-radius: 3px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
        .stat-card { background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stat-number { font-size: 1.8em; font-weight: bold; margin: 5px 0; }
        .tab-container { margin: 20px 0; }
        .tab-buttons { display: flex; border-bottom: 1px solid #ddd; }
        .tab-btn { padding: 10px 20px; background: #f5f5f5; border: none; cursor: pointer; border-radius: 5px 5px 0 0; margin-right: 5px; }
        .tab-btn.active { background: #3498db; color: white; }
        .tab-content { display: none; padding: 20px; background: white; border-radius: 0 0 5px 5px; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="nav">
        <a href="../../index.php">🏠 Ana Sayfa</a>
        <a href="panel.php">📊 Firma Panel</a>
        <a href="seferler.php">🚌 Seferlerim</a>
        <a href="biletler.php">🎫 Biletler</a>
        <a href="../logout.php">🚪 Çıkış</a>
    </div>

    <div class="header">
        <h1>🎁 Kupon Yönetimi</h1>
        <p>Firmanıza özel indirim kuponları oluşturun ve yönetin</p>
        
        <div class="on-ek-bilgi">
            <strong>ℹ️ Otomatik Kupon Kodu Sistemi</strong><br>
            Kupon kodlarınızın başına otomatik olarak firma ön eki eklenir:<br>
            <strong>Ön Ek:</strong> <span class="kupon-kod"><?php echo $on_ek; ?>_</span><br>
            <strong>Örnek:</strong> "<span class="kupon-kod"><?php echo $on_ek; ?>_YAZ2024</span>"
        </div>
    </div>

    <?php if (isset($basari)): ?>
        <div class="success"><?php echo $basari; ?></div>
    <?php endif; ?>

    <?php if (isset($hata)): ?>
        <div class="error"><?php echo $hata; ?></div>
    <?php endif; ?>

    <!-- İstatistik Kartları -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>📊 Toplam Kupon</div>
            <div class="stat-number"><?php echo $istatistikler['toplam_kupon']; ?></div>
        </div>
        <div class="stat-card">
            <div>✅ Aktif Kupon</div>
            <div class="stat-number"><?php echo $istatistikler['aktif_kupon']; ?></div>
        </div>
        <div class="stat-card">
            <div>⏰ Süresi Dolan</div>
            <div class="stat-number"><?php echo $istatistikler['suresi_dolmus']; ?></div>
        </div>
        <div class="stat-card">
            <div>🎫 Toplam Kullanım</div>
            <div class="stat-number"><?php echo $istatistikler['toplam_kullanım']; ?></div>
        </div>
    </div>

    <!-- Tab Container -->
    <div class="tab-container">
        <div class="tab-buttons">
            <button class="tab-btn active" onclick="openTab('tekli-tab')">➕ Tekli Kupon Ekle</button>
            <button class="tab-btn" onclick="openTab('toplu-tab')">📦 Toplu Kupon Oluştur</button>
            <button class="tab-btn" onclick="openTab('liste-tab')">📋 Kupon Listesi</button>
        </div>

        <!-- TEKLİ KUPON EKLEME TAB -->
        <div id="tekli-tab" class="tab-content active">
            <h2><?php echo $duzenlenecek_kupon ? '✏️ Kupon Düzenle' : '➕ Yeni Kupon Ekle'; ?></h2>
            
            <div class="kupon-onizleme" id="kuponOnizleme" style="display: <?php echo $duzenlenecek_kupon ? 'none' : 'block'; ?>;">
                <strong>Kupon Kodu Önizleme:</strong> 
                <span class="kupon-kod" id="onizlemeKodu"><?php echo $duzenlenecek_kupon ? htmlspecialchars($duzenlenecek_kupon['code']) : ''; ?></span>
            </div>
            
            <form method="POST">
                <?php if ($duzenlenecek_kupon): ?>
                    <input type="hidden" name="kupon_id" value="<?php echo $duzenlenecek_kupon['id']; ?>">
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
                    <div>
                        <label>Kupon Kodu *</label>
                        <?php if ($duzenlenecek_kupon): ?>
                            <input type="text" value="<?php echo htmlspecialchars($duzenlenecek_kupon['code']); ?>" disabled style="background: #f5f5f5;">
                            <small>Kupon kodu değiştirilemez</small>
                        <?php else: ?>
                            <input type="text" name="kod" id="kodInput" required 
                                   placeholder="YAZ2024" oninput="gosterKuponOnizleme()"
                                   pattern="[A-Za-z0-9]+" title="Sadece harf ve rakam kullanın">
                            <small>Sadece harf ve rakam (boşluk yok)</small>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label>İndirim Oranı (%) *</label>
                        <input type="number" name="indirim" min="1" max="100" required 
                               value="<?php echo $duzenlenecek_kupon ? ($duzenlenecek_kupon['discount'] * 100) : ''; ?>" 
                               placeholder="25">
                    </div>
                    <div>
                        <label>Kullanım Limiti *</label>
                        <input type="number" name="kullanım_limiti" min="1" required 
                               value="<?php echo $duzenlenecek_kupon ? $duzenlenecek_kupon['usage_limit'] : ''; ?>" 
                               placeholder="100">
                    </div>
                    <div>
                        <label>Son Kullanma *</label>
                        <input type="date" name="son_kullanma_tarihi" required 
                               value="<?php echo $duzenlenecek_kupon ? $duzenlenecek_kupon['expire_date'] : ''; ?>"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <?php if ($duzenlenecek_kupon): ?>
                    <div style="margin-top: 15px;">
                        <label>Durum</label>
                        <select name="durum" style="padding: 8px; border-radius: 5px;">
                            <option value="active" <?php echo ($duzenlenecek_kupon['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>✅ Aktif</option>
                            <option value="inactive" <?php echo ($duzenlenecek_kupon['status'] ?? 'active') === 'inactive' ? 'selected' : ''; ?>>❌ Pasif</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 20px;">
                    <?php if ($duzenlenecek_kupon): ?>
                        <button type="submit" name="kupon_duzenle" class="btn btn-warning">✅ Kuponu Güncelle</button>
                        <a href="kuponlar.php" class="btn">❌ İptal</a>
                    <?php else: ?>
                        <button type="submit" name="kupon_ekle" class="btn btn-success">✅ Kupon Ekle</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- TOPLU KUPON OLUŞTURMA TAB -->
        <div id="toplu-tab" class="tab-content">
            <h2>📦 Toplu Kupon Oluştur</h2>
            <p>Birden fazla rastgele kodlu kupon oluşturun</p>
            
            <form method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
                    <div>
                        <label>Kupon Adeti *</label>
                        <input type="number" name="adet" min="1" max="100" required placeholder="10">
                    </div>
                    <div>
                        <label>İndirim Oranı (%) *</label>
                        <input type="number" name="toplu_indirim" min="1" max="100" required placeholder="15">
                    </div>
                    <div>
                        <label>Kullanım Limiti *</label>
                        <input type="number" name="toplu_kullanım_limiti" min="1" required placeholder="1">
                    </div>
                    <div>
                        <label>Son Kullanma *</label>
                        <input type="date" name="toplu_son_kullanma_tarihi" required 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <label>Kod Uzunluğu</label>
                    <input type="number" name="kod_uzunlugu" min="4" max="12" value="8">
                    <small>Rastgele kodun karakter uzunluğu (4-12)</small>
                </div>
                <button type="submit" name="toplu_kupon_olustur" class="btn btn-info" style="margin-top: 15px;">
                    🚀 Toplu Kupon Oluştur
                </button>
            </form>
        </div>

        <!-- KUPON LİSTESİ TAB -->
        <div id="liste-tab" class="tab-content">
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
                            $aktif = !$suresi_dolmus && !$kullanım_dolmus && ($kupon['status'] === 'active');
                            ?>
                            <tr class="<?php echo $aktif ? '' : 'expired'; ?>">
                                <td>
                                    <span class="kupon-kod"><?php echo htmlspecialchars($kupon['code']); ?></span>
                                </td>
                                <td><strong><?php echo ($kupon['discount'] * 100); ?>%</strong></td>
                                <td><?php echo $kupon['kullanım_sayisi']; ?> / <?php echo $kupon['usage_limit']; ?></td>
                                <td><?php echo date('d.m.Y', strtotime($kupon['expire_date'])); ?></td>
                                <td>
                                    <?php if ($aktif): ?>
                                        <span style="color: green;">✅ Aktif</span>
                                    <?php elseif ($suresi_dolmus): ?>
                                        <span style="color: red;">⏰ Süresi Dolmuş</span>
                                    <?php elseif ($kullanım_dolmus): ?>
                                        <span style="color: red;">🎫 Limit Dolmuş</span>
                                    <?php elseif (($kupon['status'] ?? 'active') === 'inactive'): ?>
                                        <span style="color: orange;">❌ Pasif</span>
                                    <?php else: ?>
                                        <span style="color: gray;">❓ Bilinmeyen</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($kupon['created_at'])); ?></td>
                                <td>
                                    <a href="?duzenle=<?php echo $kupon['id']; ?>" class="btn btn-warning">✏️</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Bu kuponu silmek istediğinizden emin misiniz?')">
                                        <input type="hidden" name="kupon_id" value="<?php echo $kupon['id']; ?>">
                                        <button type="submit" name="kupon_sil" class="btn btn-danger" 
                                                <?php echo $kupon['kullanım_sayisi'] > 0 ? 'disabled title="Kullanılmış kupon silinemez"' : ''; ?>>🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function gosterKuponOnizleme() {
            const kodInput = document.getElementById('kodInput');
            const onizlemeDiv = document.getElementById('kuponOnizleme');
            const onizlemeKodu = document.getElementById('onizlemeKodu');
            
            if (kodInput.value.trim() !== '') {
                // Sadece harf ve rakam kalacak şekilde temizle
                const temizKod = kodInput.value.replace(/[^A-Za-z0-9]/g, '');
                kodInput.value = temizKod;
                
                const tamKod = '<?php echo $on_ek; ?>_' + temizKod.toUpperCase();
                onizlemeKodu.textContent = tamKod;
                onizlemeDiv.style.display = 'block';
            } else {
                onizlemeDiv.style.display = 'none';
            }
        }
        
        function openTab(tabName) {
            // Tüm tab içeriklerini gizle
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Tüm tab butonlarını pasif yap
            const tabButtons = document.getElementsByClassName('tab-btn');
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            
            // Seçilen tab'ı aktif yap
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        // Sayfa yüklendiğinde input'u dinle
        const kodInput = document.getElementById('kodInput');
        if (kodInput) {
            kodInput.addEventListener('input', gosterKuponOnizleme);
        }
        
        // URL'de tab parametresi varsa o tab'ı aç
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam) {
            openTab(tabParam + '-tab');
        }
    </script>
</body>
</html>