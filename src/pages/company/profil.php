<?php
// pages/company/profil.php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isCompanyAdmin()) {
    header('Location: ../login.php');
    exit;
}

$user = getCurrentUser();
$company_id = $user['company_id'];

// Firma bilgilerini getir
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM bus_company WHERE id = ?");
    $stmt->execute([$company_id]);
    $firma = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$firma) {
        die("❌ Firma bilgileri bulunamadı.");
    }
} catch (Exception $e) {
    die("❌ Hata: " . $e->getMessage());
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firma_adi = trim($_POST['firma_adi'] ?? '');
    $logo_path = trim($_POST['logo_path'] ?? '');

    try {
        if (empty($firma_adi)) {
            $hata = "Firma adı gereklidir";
        } else {
            $stmt = $db->prepare("UPDATE bus_company SET name = ?, logo_path = ? WHERE id = ?");
            $stmt->execute([$firma_adi, $logo_path, $company_id]);
            
            $basari = "✅ Firma bilgileri başarıyla güncellendi!";
            
            // Firma bilgilerini yeniden getir
            $stmt = $db->prepare("SELECT * FROM bus_company WHERE id = ?");
            $stmt->execute([$company_id]);
            $firma = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $hata = "Güncelleme sırasında hata: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Firma Panel - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #333; }
        .profile-container { background: white; padding: 30px; border-radius: 10px; max-width: 600px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { padding: 12px 30px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .success { color: green; background: #eaffea; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { color: red; background: #ffeaea; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .logo-preview { max-width: 150px; max-height: 150px; margin: 10px 0; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="nav">
        <a href="../../index.php">🏠 Ana Sayfa</a>
        <a href="panel.php">📊 Firma Panel</a>
        <a href="seferler.php">🚌 Seferlerim</a>
        <a href="../logout.php">🚪 Çıkış</a>
    </div>

    <div class="profile-container">
        <h1>🏢 Firma Profili</h1>

        <?php if (isset($basari)): ?>
            <div class="success"><?php echo $basari; ?></div>
        <?php endif; ?>

        <?php if (isset($hata)): ?>
            <div class="error"><?php echo $hata; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="firma_adi">Firma Adı *</label>
                <input type="text" name="firma_adi" id="firma_adi" value="<?php echo htmlspecialchars($firma['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="logo_path">Logo Yolu (URL)</label>
                <input type="text" name="logo_path" id="logo_path" value="<?php echo htmlspecialchars($firma['logo_path'] ?? ''); ?>" placeholder="/assets/images/logo.png">
                
                <?php if (!empty($firma['logo_path'])): ?>
                    <div>
                        <strong>Mevcut Logo:</strong><br>
                        <img src="<?php echo htmlspecialchars($firma['logo_path']); ?>" alt="Mevcut Logo" class="logo-preview" onerror="this.style.display='none'">
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Firma ID</label>
                <input type="text" value="<?php echo $company_id; ?>" disabled style="background: #f5f5f5;">
                <small>Bu ID değiştirilemez</small>
            </div>

            <div class="form-group">
                <label>Kayıt Tarihi</label>
                <input type="text" value="<?php echo date('d.m.Y H:i', strtotime($firma['created_at'])); ?>" disabled style="background: #f5f5f5;">
            </div>

            <button type="submit">✅ Profili Güncelle</button>
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
    </script>
</body>
</html>