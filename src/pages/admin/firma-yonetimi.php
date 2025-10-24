<?php
// pages/admin/firma-yonetimi.php
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
        
        if ($islem === 'firma_ekle') {
            // Yeni firma ekle
            $firma_id = generateUUID();
            $firma_adi = trim($_POST['firma_adi'] ?? '');
            $logo_path = trim($_POST['logo_path'] ?? '');
            
            if (empty($firma_adi)) {
                $hata = "Firma adı gereklidir";
            } else {
                $stmt = $db->prepare("INSERT INTO bus_company (id, name, logo_path) VALUES (?, ?, ?)");
                $stmt->execute([$firma_id, $firma_adi, $logo_path]);
                $basari = "✅ Firma başarıyla eklendi: " . htmlspecialchars($firma_adi);
            }
            
        } elseif ($islem === 'firma_sil' && isset($_POST['firma_id'])) {
            // Firma sil
            $firma_id = $_POST['firma_id'];
            
            // Önce bu firmaya ait sefer var mı kontrol et
            $stmt = $db->prepare("SELECT COUNT(*) as sefer_sayisi FROM trips WHERE company_id = ?");
            $stmt->execute([$firma_id]);
            $sefer_sayisi = $stmt->fetch(PDO::FETCH_ASSOC)['sefer_sayisi'];
            
            if ($sefer_sayisi > 0) {
                $hata = "❌ Bu firmaya ait $sefer_sayisi sefer bulunuyor. Önce seferleri silmelisiniz.";
            } else {
                $stmt = $db->prepare("DELETE FROM bus_company WHERE id = ?");
                $stmt->execute([$firma_id]);
                $basari = "✅ Firma başarıyla silindi";
            }
        }
        
    } catch (Exception $e) {
        $hata = "İşlem sırasında hata: " . $e->getMessage();
    }
}

// Firmaları getir
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT bc.*, 
               (SELECT COUNT(*) FROM trips WHERE company_id = bc.id) as sefer_sayisi,
               (SELECT COUNT(*) FROM users WHERE company_id = bc.id) as admin_sayisi
        FROM bus_company bc 
        ORDER BY bc.name
    ");
    $firmalar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $firmalar = [];
    $hata_liste = "Firmalar yüklenirken hata: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Yönetimi - Admin Panel - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .admin-header { background: #2c3e50; color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #333; }
        .form-container, .list-container { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 5px; }
        button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .success { color: green; background: #eaffea; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffeaea; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .firma-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .firma-table th, .firma-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .firma-table th { background: #f2f2f2; }
        .firma-table tr:hover { background: #f9f9f9; }
        .action-buttons { display: flex; gap: 5px; }
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
        <h1>🚌 Firma Yönetimi</h1>
        <p>Otobüs firmalarını yönetin</p>
    </div>

    <?php if (isset($basari)): ?>
        <div class="success"><?php echo $basari; ?></div>
    <?php endif; ?>

    <?php if (isset($hata)): ?>
        <div class="error"><?php echo $hata; ?></div>
    <?php endif; ?>

    <!-- Yeni Firma Ekleme Formu -->
    <div class="form-container">
        <h2>➕ Yeni Firma Ekle</h2>
        <form method="POST">
            <input type="hidden" name="islem" value="firma_ekle">
            
            <div class="form-group">
                <label for="firma_adi">Firma Adı *</label>
                <input type="text" name="firma_adi" id="firma_adi" required>
            </div>
            
            <div class="form-group">
                <label for="logo_path">Logo Yolu (URL)</label>
                <input type="text" name="logo_path" id="logo_path" placeholder="/assets/images/logo.png">
            </div>
            
            <button type="submit">✅ Firma Ekle</button>
        </form>
    </div>

    <!-- Firma Listesi -->
    <div class="list-container">
        <h2>📋 Firma Listesi</h2>
        
        <?php if (isset($hata_liste)): ?>
            <div class="error"><?php echo $hata_liste; ?></div>
        <?php endif; ?>

        <?php if (empty($firmalar)): ?>
            <p>Henüz firma bulunmamaktadır.</p>
        <?php else: ?>
            <table class="firma-table">
                <thead>
                    <tr>
                        <th>Firma Adı</th>
                        <th>Sefer Sayısı</th>
                        <th>Admin Sayısı</th>
                        <th>Oluşturulma Tarihi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($firmalar as $firma): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($firma['name']); ?></strong></td>
                            <td><?php echo $firma['sefer_sayisi']; ?> sefer</td>
                            <td><?php echo $firma['admin_sayisi']; ?> admin</td>
                            <td><?php echo date('d.m.Y H:i', strtotime($firma['created_at'])); ?></td>
                            <td class="action-buttons">
                                <a href="firma-duzenle.php?id=<?php echo $firma['id']; ?>" class="btn" style="text-decoration: none;">✏️ Düzenle</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Bu firmayı silmek istediğinizden emin misiniz?')">
                                    <input type="hidden" name="islem" value="firma_sil">
                                    <input type="hidden" name="firma_id" value="<?php echo $firma['id']; ?>">
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