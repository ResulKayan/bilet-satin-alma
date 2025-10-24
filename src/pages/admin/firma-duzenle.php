<?php
// pages/admin/firma-duzenle.php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Sadece admin erişebilir
requireAdmin();

// Firma ID kontrolü
$firma_id = $_GET['id'] ?? '';
if (empty($firma_id)) {
    header('Location: firma-yonetimi.php');
    exit;
}

// Firma bilgilerini getir
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT bc.*, 
               (SELECT COUNT(*) FROM trips WHERE company_id = bc.id) as sefer_sayisi,
               (SELECT COUNT(*) FROM users WHERE company_id = bc.id AND role = 'company') as admin_sayisi,
               (SELECT u.full_name FROM users u WHERE u.company_id = bc.id AND u.role = 'company' LIMIT 1) as admin_adi,
               (SELECT u.email FROM users u WHERE u.company_id = bc.id AND u.role = 'company' LIMIT 1) as admin_email,
               (SELECT u.id FROM users u WHERE u.company_id = bc.id AND u.role = 'company' LIMIT 1) as admin_id
        FROM bus_company bc 
        WHERE bc.id = ?
    ");
    $stmt->execute([$firma_id]);
    $firma = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$firma) {
        $_SESSION['hata'] = "Firma bulunamadı!";
        header('Location: firma-yonetimi.php');
        exit;
    }
    
} catch (Exception $e) {
    $_SESSION['hata'] = "Firma bilgileri yüklenirken hata: " . $e->getMessage();
    header('Location: firma-yonetimi.php');
    exit;
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = $_POST['islem'] ?? '';
    
    try {
        $db = getDB();
        
        if ($islem === 'firma_duzenle') {
            // Firma bilgilerini güncelle
            $firma_adi = trim($_POST['firma_adi'] ?? '');
            $logo_path = trim($_POST['logo_path'] ?? '');
            
            if (empty($firma_adi)) {
                $hata = "Firma adı gereklidir";
            } else {
                $stmt = $db->prepare("UPDATE bus_company SET name = ?, logo_path = ? WHERE id = ?");
                $stmt->execute([$firma_adi, $logo_path, $firma_id]);
                $basari = "✅ Firma bilgileri başarıyla güncellendi";
                
                // Firma bilgilerini yeniden getir (TÜM ALANLARLA)
                $stmt = $db->prepare("
                    SELECT bc.*, 
                           (SELECT COUNT(*) FROM trips WHERE company_id = bc.id) as sefer_sayisi,
                           (SELECT COUNT(*) FROM users WHERE company_id = bc.id AND role = 'company') as admin_sayisi,
                           (SELECT u.full_name FROM users u WHERE u.company_id = bc.id AND u.role = 'company' LIMIT 1) as admin_adi,
                           (SELECT u.email FROM users u WHERE u.company_id = bc.id AND u.role = 'company' LIMIT 1) as admin_email,
                           (SELECT u.id FROM users u WHERE u.company_id = bc.id AND u.role = 'company' LIMIT 1) as admin_id
                    FROM bus_company bc 
                    WHERE bc.id = ?
                ");
                $stmt->execute([$firma_id]);
                $firma = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
        } elseif ($islem === 'admin_ata' && isset($_POST['user_id'])) {
            // Firmaya admin ata
            $user_id = $_POST['user_id'];
            
            // Önce mevcut admini bul ve user rolüne döndür
            $stmt = $db->prepare("SELECT id FROM users WHERE company_id = ? AND role = 'company'");
            $stmt->execute([$firma_id]);
            $mevcut_admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($mevcut_admin) {
                $stmt = $db->prepare("UPDATE users SET role = 'user', company_id = NULL WHERE id = ?");
                $stmt->execute([$mevcut_admin['id']]);
            }
            
            // Yeni kullanıcıyı firma admini yap
            $stmt = $db->prepare("UPDATE users SET role = 'company', company_id = ? WHERE id = ?");
            $stmt->execute([$firma_id, $user_id]);
            
            // Bilgileri al
            $stmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $kullanici = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $basari = "✅ " . htmlspecialchars($kullanici['full_name'] ?: $kullanici['email']) . " kullanıcısı " . htmlspecialchars($firma['name']) . " firmasına admin olarak atandı.";
            
            // Firma bilgilerini yeniden getir (TÜM ALANLARLA)
            $stmt = $db->prepare("
                SELECT bc.*, 
                       (SELECT COUNT(*) FROM trips WHERE company_id = bc.id) as sefer_sayisi,
                       (SELECT COUNT(*) FROM users WHERE company_id = bc.id AND role = 'company') as admin_sayisi,
                       (SELECT u.full_name FROM users u WHERE u.company_id = bc.id AND u.role = 'company' LIMIT 1) as admin_adi,
                       (SELECT u.email FROM users u WHERE u.company_id = bc.id AND u.role = 'company' LIMIT 1) as admin_email,
                       (SELECT u.id FROM users u WHERE u.company_id = bc.id AND u.role = 'company' LIMIT 1) as admin_id
                FROM bus_company bc 
                WHERE bc.id = ?
            ");
            $stmt->execute([$firma_id]);
            $firma = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } elseif ($islem === 'admin_kaldir' && isset($_POST['admin_id'])) {
            // Firmadan admin kaldır
            $admin_id = $_POST['admin_id'];
            
            $stmt = $db->prepare("UPDATE users SET role = 'user', company_id = NULL WHERE id = ?");
            $stmt->execute([$admin_id]);
            
            $basari = "✅ Firma admini başarıyla kaldırıldı.";
            
            // Firma bilgilerini yeniden getir (TÜM ALANLARLA)
            $stmt = $db->prepare("
                SELECT bc.*, 
                       (SELECT COUNT(*) FROM trips WHERE company_id = bc.id) as sefer_sayisi,
                       (SELECT COUNT(*) FROM users WHERE company_id = bc.id AND role = 'company') as admin_sayisi,
                       (SELECT u.full_name FROM users u WHERE u.company_id = bc.id AND u.role = 'company' LIMIT 1) as admin_adi,
                       (SELECT u.email FROM users u WHERE u.company_id = bc.id AND u.role = 'company' LIMIT 1) as admin_email,
                       (SELECT u.id FROM users u WHERE u.company_id = bc.id AND u.role = 'company' LIMIT 1) as admin_id
                FROM bus_company bc 
                WHERE bc.id = ?
            ");
            $stmt->execute([$firma_id]);
            $firma = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {
        $hata = "İşlem sırasında hata: " . $e->getMessage();
    }
}

// Firma admini atama için kullanıcıları getir
try {
    $stmt = $db->query("
        SELECT id, full_name, email, role, company_id 
        FROM users 
        WHERE role = 'user' OR role = 'company'
        ORDER BY full_name, email
    ");
    $kullanicilar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $kullanicilar = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Düzenle - Admin Panel - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .admin-header { background: #2c3e50; color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #333; }
        .form-container, .info-container { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea, select { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 5px; }
        button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #219a52; }
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }
        .success { color: green; background: #eaffea; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffeaea; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
        .info-card { background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #3498db; }
        .admin-bilgi { background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .admin-yok { background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0; color: #856404; }
        .logo-preview { max-width: 150px; max-height: 150px; margin: 10px 0; border-radius: 8px; border: 1px solid #ddd; }
        .action-buttons { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .admin-actions { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #e67e22; }
    </style>
</head>
<body>
    <div class="nav">
        <a href="../../index.php">🏠 Ana Sayfa</a>
        <a href="../hesabim.php">👤 Hesabım</a>
        <a href="index.php">⚙️ Admin Panel</a>
        <a href="firma-yonetimi.php">🚌 Firma Yönetimi</a>
        <a href="../logout.php">🚪 Çıkış Yap</a>
    </div>

    <div class="admin-header">
        <h1>✏️ Firma Düzenle</h1>
        <p><strong>Firma:</strong> <?php echo htmlspecialchars($firma['name']); ?></p>
        <p><strong>Firma ID:</strong> <?php echo $firma_id; ?></p>
    </div>

    <?php if (isset($basari)): ?>
        <div class="success"><?php echo $basari; ?></div>
    <?php endif; ?>

    <?php if (isset($hata)): ?>
        <div class="error"><?php echo $hata; ?></div>
    <?php endif; ?>

    <!-- Firma Bilgileri -->
    <div class="info-container">
        <h2>📊 Firma İstatistikleri</h2>
        <div class="info-grid">
            <div class="info-card">
                <h3>🚌 Sefer Sayısı</h3>
                <p style="font-size: 2em; font-weight: bold; color: #2c3e50;">
                    <?php echo isset($firma['sefer_sayisi']) ? $firma['sefer_sayisi'] : 0; ?>
                </p>
            </div>
            <div class="info-card">
                <h3>👤 Admin Durumu</h3>
                <p style="font-size: 1.5em; font-weight: bold; color: <?php echo (isset($firma['admin_sayisi']) && $firma['admin_sayisi'] > 0) ? '#27ae60' : '#e74c3c'; ?>;">
                    <?php echo (isset($firma['admin_sayisi']) && $firma['admin_sayisi'] > 0) ? '✅ Atanmış' : '❌ Atanmamış'; ?>
                </p>
            </div>
            <div class="info-card">
                <h3>📅 Kayıt Tarihi</h3>
                <p style="font-size: 1.2em; color: #2c3e50;">
                    <?php echo date('d.m.Y H:i', strtotime($firma['created_at'])); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Admin Yönetimi -->
    <div class="form-container">
        <h2>👤 Firma Admin Yönetimi</h2>
        
        <?php if (isset($firma['admin_adi']) && !empty($firma['admin_adi'])): ?>
            <div class="admin-bilgi">
                <h3>✅ Mevcut Firma Admini</h3>
                <p><strong>Ad Soyad:</strong> <?php echo htmlspecialchars($firma['admin_adi']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($firma['admin_email']); ?></p>
                
                <form method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="islem" value="admin_kaldir">
                    <input type="hidden" name="admin_id" value="<?php echo $firma['admin_id']; ?>">
                    <button type="submit" class="btn-danger" onclick="return confirm('Bu admini firmadan kaldırmak istediğinizden emin misiniz?')">
                        🗑️ Admini Kaldır
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="admin-yok">
                <h3>❌ Admin Atanmamış</h3>
                <p>Bu firmanın henüz bir admini bulunmuyor. Aşağıdan bir kullanıcıyı admin olarak atayabilirsiniz.</p>
            </div>
        <?php endif; ?>

        <!-- Yeni Admin Ata Formu -->
        <div class="admin-actions">
            <h3><?php echo (isset($firma['admin_adi']) && !empty($firma['admin_adi'])) ? '🔄 Admin Değiştir' : '➕ Yeni Admin Ata'; ?></h3>
            <form method="POST">
                <input type="hidden" name="islem" value="admin_ata">
                
                <div class="form-group">
                    <label for="user_id">Kullanıcı Seçin:</label>
                    <select name="user_id" id="user_id" required>
                        <option value="">Kullanıcı Seçin</option>
                        <?php foreach ($kullanicilar as $kullanici): ?>
                            <option value="<?php echo $kullanici['id']; ?>">
                                <?php echo htmlspecialchars($kullanici['full_name'] ?: $kullanici['email']); ?> 
                                (<?php echo htmlspecialchars($kullanici['email']); ?>)
                                - <?php echo $kullanici['role'] === 'company' ? '🚌 Mevcut Firma Admin' : '👤 Kullanıcı'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="mevcutAdminUyari" style="display: none; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;">
                    ⚠️ Seçilen kullanıcı zaten bir firma admini. Bu işlem mevcut firmasından alınacak.
                </div>
                
                <button type="submit" class="btn btn-success">
                    <?php echo (isset($firma['admin_adi']) && !empty($firma['admin_adi'])) ? '🔄 Admini Değiştir' : '✅ Admin Ata'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Firma Düzenleme Formu -->
    <div class="form-container">
        <h2>✏️ Firma Bilgilerini Düzenle</h2>
        <form method="POST">
            <input type="hidden" name="islem" value="firma_duzenle">
            
            <div class="form-group">
                <label for="firma_adi">Firma Adı *</label>
                <input type="text" name="firma_adi" id="firma_adi" 
                       value="<?php echo htmlspecialchars($firma['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="logo_path">Logo Yolu (URL)</label>
                <input type="text" name="logo_path" id="logo_path" 
                       value="<?php echo htmlspecialchars($firma['logo_path'] ?? ''); ?>" 
                       placeholder="/assets/images/logo.png">
                <?php if (!empty($firma['logo_path'])): ?>
                    <div>
                        <strong>Mevcut Logo:</strong>
                        <img src="<?php echo htmlspecialchars($firma['logo_path']); ?>" 
                             alt="Mevcut Logo" class="logo-preview"
                             onerror="this.style.display='none'">
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn btn-success">✅ Bilgileri Güncelle</button>
                <a href="firma-yonetimi.php" class="btn">← Firma Yönetimine Dön</a>
                <a href="index.php" class="btn">⚙️ Admin Paneline Dön</a>
            </div>
        </form>
    </div>

    <script>
        // Logo önizleme
        document.getElementById('logo_path').addEventListener('input', function() {
            const preview = document.querySelector('.logo-preview');
            if (preview) {
                preview.src = this.value;
            }
        });

        // Kullanıcı seçimi değiştiğinde uyarı göster
        document.getElementById('user_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const isCompanyAdmin = selectedOption.text.includes('🚌 Mevcut Firma Admin');
            document.getElementById('mevcutAdminUyari').style.display = isCompanyAdmin ? 'block' : 'none';
        });
    </script>
</body>
</html>