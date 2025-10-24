<?php
// pages/bilet-iptal.php - İYİLEŞTİRİLMİŞ
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
$bilet_id = $_GET['bilet_id'] ?? '';

if (empty($bilet_id)) {
    $_SESSION['hata'] = "Bilet ID'si belirtilmedi.";
    header('Location: biletlerim.php');
    exit;
}

try {
    $db = getDB();
    
    // İYİLEŞTİRİLMİŞ SQL SORGUSU - GROUP_CONCAT ile tüm koltukları getir
    $stmt = $db->prepare("
        SELECT t.*, tr.*, bc.name as firma_adi, 
               GROUP_CONCAT(bs.seat_number) as koltuk_numaralari,
               COUNT(bs.id) as koltuk_sayisi
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        JOIN bus_company bc ON tr.company_id = bc.id
        LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
        WHERE t.id = ? AND t.user_id = ?
        GROUP BY t.id
    ");
    $stmt->execute([$bilet_id, $user['id']]);
    $bilet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bilet) {
        $_SESSION['hata'] = "Bilet bulunamadı veya bu bilete erişim yetkiniz yok.";
        header('Location: biletlerim.php');
        exit;
    }
    
    // Bilet zaten iptal edilmiş mi kontrol et
    if ($bilet['status'] === 'cancelled') {
        $_SESSION['hata'] = "Bu bilet zaten iptal edilmiş.";
        header('Location: bilet-detay.php?bilet_id=' . $bilet_id);
        exit;
    }
    
} catch (Exception $e) {
    $_SESSION['hata'] = "Sistem hatası: " . $e->getMessage();
    header('Location: biletlerim.php');
    exit;
}

// İptal edilebilirlik kontrolü
$simdi = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
$kalkis_zamani = new DateTime($bilet['departure_time'], new DateTimeZone('Europe/Istanbul'));

// Sefer saati geçmiş mi kontrol et
if ($kalkis_zamani <= $simdi) {
    $_SESSION['hata'] = "Bu biletin sefer saati geçmiş, iptal edilemez.";
    header('Location: bilet-detay.php?bilet_id=' . $bilet_id);
    exit;
}

$kalan_sure = $simdi->diff($kalkis_zamani);
$toplam_dakika = ($kalan_sure->days * 24 * 60) + ($kalan_sure->h * 60) + $kalan_sure->i;

// İade hesaplama - Geliştirilmiş kurallar
$iade_yuzdesi = 0;
$iade_tutari = 0;
$iptal_edilebilir = true;
$iptal_nedeni = '';

if ($toplam_dakika > 1440) { // 24 saatten fazla
    $iade_yuzdesi = 100;
    $iptal_nedeni = '24 saatten fazla kala';
} elseif ($toplam_dakika > 180) { // 3-24 saat arası
    $iade_yuzdesi = 75;
    $iptal_nedeni = '3-24 saat arası';
} elseif ($toplam_dakika > 60) { // 1-3 saat arası
    $iade_yuzdesi = 50;
    $iptal_nedeni = '1-3 saat arası';
} else { // 1 saatten az
    $iptal_edilebilir = false;
    $iptal_nedeni = '1 saatten az kala iptal edilemez';
}

if ($iptal_edilebilir) {
    $iade_tutari = ($bilet['total_price'] / 100) * ($iade_yuzdesi / 100);
}

// İptal işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancel'])) {
    try {
        $db->beginTransaction();
        
        // 1. Bilet durumunu 'cancelled' yap
        $stmt = $db->prepare("UPDATE tickets SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$bilet_id]);
        
        // 2. Para iadesi (sadece iade yüzdesi > 0 ise)
        if ($iade_yuzdesi > 0) {
            $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$iade_tutari, $user['id']]);
            
            // Session'daki kullanıcı bakiyesini güncelle
            $_SESSION['user']['balance'] += $iade_tutari;
        }
        
        // 3. Koltuk rezervasyonunu sil
        $stmt = $db->prepare("DELETE FROM booked_seats WHERE ticket_id = ?");
        $stmt->execute([$bilet_id]);
        
        $db->commit();
        
        $_SESSION['basarili'] = "Bilet başarıyla iptal edildi." . 
                                ($iade_yuzdesi > 0 ? " " . number_format($iade_tutari, 2) . " TL bakiyenize eklendi." : "");
        header('Location: biletlerim.php');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $hata = "İptal işlemi sırasında hata: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet İptal - <?php echo SITE_NAME; ?></title>
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }
        
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
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
        .main-container { 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .header { 
            background: linear-gradient(135deg, var(--danger-color) 0%, #c0392b 100%); 
            color: white; 
            padding: 40px 30px; 
            text-align: center; 
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        .header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1em;
        }
        .content { 
            padding: 30px; 
        }
        .bilet-info { 
            background: #f8f9fa; 
            padding: 25px; 
            border-radius: 10px; 
            margin-bottom: 25px;
            border-left: 5px solid var(--primary-color);
        }
        .iade-info { 
            background: #e8f5e8; 
            padding: 25px; 
            border-radius: 10px; 
            margin-bottom: 25px;
            border-left: 5px solid var(--success-color);
        }
        .warning { 
            background: #fff3cd; 
            padding: 25px; 
            border-radius: 10px; 
            margin-bottom: 25px;
            border-left: 5px solid var(--warning-color);
        }
        .no-refund { 
            background: #f8d7da; 
            padding: 25px; 
            border-radius: 10px; 
            margin-bottom: 25px;
            border-left: 5px solid var(--danger-color);
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 20px; 
            border-radius: 10px; 
            margin: 20px 0;
            border-left: 5px solid var(--danger-color);
        }
        .btn { 
            padding: 15px 30px; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block;
            margin: 5px;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 16px;
            text-align: center;
        }
        .btn-danger { 
            background: var(--danger-color); 
        }
        .btn-danger:hover { 
            background: #c0392b; 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-secondary { 
            background: #6c757d; 
        }
        .btn-secondary:hover { 
            background: #545b62; 
            transform: translateY(-2px);
        }
        .btn-success { 
            background: var(--success-color); 
        }
        .btn-success:hover { 
            background: #218838; 
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9ecef;
        }
        .info-label {
            font-weight: 600;
            color: #555;
        }
        .info-value {
            color: #333;
            font-weight: 500;
        }
        .iade-tutar {
            font-size: 2em;
            font-weight: bold;
            color: var(--success-color);
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: white;
            border-radius: 10px;
            border: 2px dashed var(--success-color);
        }
        .no-refund-text {
            font-size: 1.8em;
            font-weight: bold;
            color: var(--danger-color);
            text-align: center;
            margin: 20px 0;
            padding: 15px;
        }
        .koltuk-bilgisi {
            background: var(--primary-color);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            display: inline-block;
            margin: 2px;
        }
        .time-indicator {
            display: flex;
            justify-content: space-between;
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            border: 1px solid #e9ecef;
        }
        .time-item {
            text-align: center;
            flex: 1;
        }
        .time-value {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--primary-color);
        }
        .time-label {
            font-size: 0.9em;
            color: #666;
        }
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            .time-indicator {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <div class="logo">
                <h2 style="margin: 0; color: var(--primary-color);">🎫 <?php echo SITE_NAME; ?></h2>
            </div>
            <div class="nav-links">
                <a href="../index.php">🏠 Ana Sayfa</a>
                <a href="hesabim.php">👤 Hesabım</a>
                <a href="biletlerim.php">🎫 Biletlerim</a>
                <a href="bilet-detay.php?bilet_id=<?php echo $bilet_id; ?>">📄 Bilet Detay</a>
            </div>
        </div>

        <div class="main-container">
            <div class="header">
                <h1>❌ Bilet İptal</h1>
                <p>İptal işlemini onaylamadan önce lütfen bilgileri dikkatlice okuyun</p>
            </div>

            <div class="content">
                <?php if (isset($hata)): ?>
                    <div class="error">
                        <h3 style="margin: 0 0 10px 0;">❌ İşlem Hatası</h3>
                        <p style="margin: 0;"><?php echo $hata; ?></p>
                    </div>
                <?php endif; ?>

                <!-- Bilet Bilgileri -->
                <div class="bilet-info">
                    <h3 style="margin-top: 0; color: var(--primary-color);">🎫 İptal Edilecek Bilet</h3>
                    <div class="info-grid">
                        <div>
                            <div class="info-item">
                                <span class="info-label">Firma:</span>
                                <span class="info-value"><?php echo htmlspecialchars($bilet['firma_adi']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Güzergah:</span>
                                <span class="info-value">
                                    <?php echo htmlspecialchars($bilet['departure_city']); ?> 
                                    <span style="color: var(--primary-color);">→</span> 
                                    <?php echo htmlspecialchars($bilet['destination_city']); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Kalkış:</span>
                                <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($bilet['departure_time'])); ?></span>
                            </div>
                        </div>
                        <div>
                            <div class="info-item">
                                <span class="info-label">Koltuk No:</span>
                                <span class="info-value">
                                    <?php if (!empty($bilet['koltuk_numaralari'])): ?>
                                        <?php 
                                        $koltuklar = explode(',', $bilet['koltuk_numaralari']);
                                        foreach ($koltuklar as $koltuk): ?>
                                            <span class="koltuk-bilgisi"><?php echo trim($koltuk); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color: var(--danger-color);">Koltuk bilgisi bulunamadı</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Bilet Ücreti:</span>
                                <span class="info-value" style="color: var(--danger-color); font-weight: bold;">
                                    <?php echo number_format($bilet['total_price'] / 100, 2); ?> TL
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Bilet Durumu:</span>
                                <span class="info-value" style="color: var(--success-color); font-weight: bold;">Aktif</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Zaman Bilgisi -->
                <div class="time-indicator">
                    <div class="time-item">
                        <div class="time-value"><?php echo $kalan_sure->days > 0 ? $kalan_sure->days . 'g' : ''; ?> <?php echo $kalan_sure->h; ?>s <?php echo $kalan_sure->i; ?>d</div>
                        <div class="time-label">Kalkışa Kalan Süre</div>
                    </div>
                    <div class="time-item">
                        <div class="time-value"><?php echo date('H:i', strtotime($bilet['departure_time'])); ?></div>
                        <div class="time-label">Kalkış Saati</div>
                    </div>
                    <div class="time-item">
                        <div class="time-value"><?php echo date('H:i'); ?></div>
                        <div class="time-label">Şu anki Saat</div>
                    </div>
                </div>

                <!-- İade Bilgileri -->
                <?php if ($iptal_edilebilir): ?>
                    <div class="iade-info">
                        <h3 style="margin-top: 0; color: var(--success-color);">💰 İade Bilgileri</h3>
                        <div class="info-grid">
                            <div>
                                <div class="info-item">
                                    <span class="info-label">İptal Zamanı:</span>
                                    <span class="info-value"><?php echo $iptal_nedeni; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">İade Oranı:</span>
                                    <span class="info-value" style="color: var(--success-color); font-weight: bold;">
                                        %<?php echo $iade_yuzdesi; ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <div class="info-item">
                                    <span class="info-label">Ödenen Tutar:</span>
                                    <span class="info-value"><?php echo number_format($bilet['total_price'] / 100, 2); ?> TL</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">İade Kesintisi:</span>
                                    <span class="info-value" style="color: var(--danger-color);">
                                        -<?php echo number_format(($bilet['total_price'] / 100) - $iade_tutari, 2); ?> TL
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="iade-tutar">
                            🎉 İade Tutarı: <?php echo number_format($iade_tutari, 2); ?> TL
                        </div>
                        <p style="text-align: center; color: #666; font-size: 0.9em; margin: 0;">
                            Bu tutar bakiyenize anında eklenecek ve yeni bilet alımlarında kullanabileceksiniz.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="no-refund">
                        <h3 style="margin-top: 0; color: var(--danger-color);">⏰ İptal Kuralı</h3>
                        <div class="no-refund-text">
                            ❌ İade Yok
                        </div>
                        <p style="text-align: center; color: #666; font-size: 1em;">
                            Sefer kalkışına <strong>1 saatten az</strong> kaldığı için iptal edilemez ve iade uygulanmaz.
                        </p>
                        <div style="text-align: center; margin-top: 15px;">
                            <small style="color: #888;">
                                Kalkış saati: <?php echo date('H:i', strtotime($bilet['departure_time'])); ?> | 
                                Şu anki saat: <?php echo date('H:i'); ?>
                            </small>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Uyarılar -->
                <div class="warning">
                    <h3 style="margin-top: 0; color: var(--warning-color);">⚠️ Önemli Bilgiler</h3>
                    <div style="display: grid; gap: 10px;">
                        <p style="margin: 5px 0;">• <strong>İptal işlemi geri alınamaz</strong></p>
                        <?php if ($iptal_edilebilir && $iade_yuzdesi > 0): ?>
                            <p style="margin: 5px 0;">• <strong>İade tutarı bakiyenize eklenecek</strong></p>
                            <p style="margin: 5px 0;">• Yeni bilet alımlarında bakiyenizi kullanabilirsiniz</p>
                        <?php elseif (!$iptal_edilebilir): ?>
                            <p style="margin: 5px 0;">• <strong>Son 1 saat kuralı gereği iade uygulanmaz</strong></p>
                        <?php endif; ?>
                        <p style="margin: 5px 0;">• Koltuk rezervasyonunuz iptal edilecek</p>
                        <p style="margin: 5px 0;">• İptal işlemi tamamlandıktan sonra bilet durumu "İptal Edildi" olarak güncellenecek</p>
                    </div>
                </div>

                <!-- İptal Formu -->
                <?php if ($iptal_edilebilir): ?>
                    <form method="POST" onsubmit="return confirmIptal()">
                        <div style="text-align: center; margin-top: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <a href="bilet-detay.php?bilet_id=<?php echo $bilet_id; ?>" class="btn btn-secondary">
                                ← Vazgeç ve Geri Dön
                            </a>
                            <button type="submit" name="confirm_cancel" class="btn btn-danger">
                                ✅ Biletimi İptal Et
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="text-align: center; margin-top: 30px;">
                        <a href="bilet-detay.php?bilet_id=<?php echo $bilet_id; ?>" class="btn btn-secondary" style="padding: 15px 40px;">
                            ← Bilet Detayına Dön
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function confirmIptal() {
            const iadeTutari = <?php echo $iade_tutari; ?>;
            const iadeYuzdesi = <?php echo $iade_yuzdesi; ?>;
            
            let message;
            if (iadeYuzdesi === 100) {
                message = `Bilet iptal edilecek ve ${iadeTutari.toFixed(2)} TL (%100) bakiyenize eklenecek. Emin misiniz?`;
            } else if (iadeYuzdesi > 0) {
                message = `Bilet iptal edilecek ve ${iadeTutari.toFixed(2)} TL (%${iadeYuzdesi}) bakiyenize eklenecek. Emin misiniz?`;
            } else {
                message = 'Bilet iptal edilecek. İade uygulanmayacak. Emin misiniz?';
            }
            
            return confirm(message);
        }

        // Sayfa yüklendiğinde uyarı
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Bilet iptal sayfası yüklendi - Bilet ID: <?php echo $bilet_id; ?>');
        });
    </script>
</body>
</html>