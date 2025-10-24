<?php
// pages/biletlerim.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Sadece giriş yapan kullanıcılar erişebilir
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();

// Filtreleme
$filtre = $_GET['filtre'] ?? 'tum';

// Biletleri getir
try {
    $db = getDB();
    
    $where_conditions = ["t.user_id = ?"];
    $params = [$user['id']];
    
    switch ($filtre) {
        case 'aktif':
            $where_conditions[] = "t.status = 'active'";
            $where_conditions[] = "tr.departure_time > datetime('now')";
            break;
        case 'iptal':
            $where_conditions[] = "t.status = 'cancelled'";
            break;
        case 'gecmis':
            $where_conditions[] = "t.status = 'active'";
            $where_conditions[] = "tr.departure_time < datetime('now')";
            break;
        case 'tum':
        default:
            // Tüm biletler
            break;
    }
    
    $where_sql = implode(" AND ", $where_conditions);
    
    // GÜNCELLENMİŞ SQL SORGUSU: GROUP BY eklendi
    $stmt = $db->prepare("
        SELECT t.id as ticket_id, t.*, tr.*, bc.name as firma_adi, 
               GROUP_CONCAT(bs.seat_number) as koltuk_numaralari,
               COUNT(bs.id) as koltuk_sayisi
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        JOIN bus_company bc ON tr.company_id = bc.id
        LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
        WHERE $where_sql
        GROUP BY t.id
        ORDER BY tr.departure_time DESC
    ");
    $stmt->execute($params);
    $biletler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("❌ Hata: " . $e->getMessage());
}

// İstatistikler
try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as toplam_bilet,
            SUM(CASE WHEN t.status = 'active' AND tr.departure_time > datetime('now') THEN 1 ELSE 0 END) as aktif_bilet,
            SUM(CASE WHEN t.status = 'cancelled' THEN 1 ELSE 0 END) as iptal_bilet,
            SUM(CASE WHEN t.status = 'active' AND tr.departure_time < datetime('now') THEN 1 ELSE 0 END) as gecmis_bilet
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        WHERE t.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $istatistikler = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $istatistikler = ['toplam_bilet' => 0, 'aktif_bilet' => 0, 'iptal_bilet' => 0, 'gecmis_bilet' => 0];
}

// Başarı ve hata mesajlarını kontrol et
$basarili_mesaji = '';
$hata_mesaji = '';

if (isset($_SESSION['basarili'])) {
    $basarili_mesaji = $_SESSION['basarili'];
    unset($_SESSION['basarili']);
}

if (isset($_SESSION['hata'])) {
    $hata_mesaji = $_SESSION['hata'];
    unset($_SESSION['hata']);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biletlerim - <?php echo SITE_NAME; ?></title>
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
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
        .filtreler { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .filtreler a { 
            margin: 0 8px; 
            padding: 10px 20px; 
            border-radius: 25px; 
            text-decoration: none; 
            background: #f8f9fa;
            color: #333;
            transition: all 0.3s;
            display: inline-block;
            font-weight: 500;
        }
        .filtreler a.aktif { 
            background: var(--primary-color); 
            color: white; 
        }
        .filtreler a:hover { 
            background: #e9ecef; 
            transform: translateY(-2px);
        }
        .filtreler a.aktif:hover {
            background: var(--secondary-color);
        }
        .istatistikler { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 15px; 
            margin-bottom: 30px; 
        }
        .istatistik-kutu { 
            background: white; 
            padding: 25px; 
            border-radius: 10px; 
            text-align: center; 
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .istatistik-kutu:hover {
            transform: translateY(-5px);
        }
        .istatistik-kutu h3 {
            margin: 0 0 15px 0;
            color: #666;
            font-size: 1em;
        }
        .bilet-listesi { 
            display: grid; 
            gap: 20px; 
        }
        .bilet-karti { 
            background: white; 
            padding: 25px; 
            border-radius: 15px; 
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            display: grid; 
            grid-template-columns: 1fr auto; 
            gap: 25px;
            transition: all 0.3s;
            border-left: 5px solid var(--primary-color);
        }
        .bilet-karti:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .bilet-bilgileri h3 { 
            margin: 0 0 15px 0; 
            color: #333;
            font-size: 1.4em;
        }
        .bilet-durumu { 
            text-align: center; 
            padding: 12px 20px; 
            border-radius: 25px; 
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 0.9em;
        }
        .durum-aktif { 
            background: #d4edda; 
            color: #155724; 
        }
        .durum-iptal { 
            background: #f8d7da; 
            color: #721c24; 
        }
        .durum-gecmis { 
            background: #e2e3e5; 
            color: #383d41; 
        }
        .btn { 
            padding: 12px 20px; 
            background: var(--primary-color); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block;
            font-weight: 500;
            transition: all 0.3s;
            text-align: center;
            font-size: 0.9em;
        }
        .btn:hover { 
            background: var(--secondary-color); 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
        .btn-secondary { 
            background: #6c757d; 
        }
        .btn-secondary:hover { 
            background: #545b62; 
        }
        .bos-liste { 
            text-align: center; 
            padding: 60px 20px; 
            color: #666; 
            background: white;
            border-radius: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            padding: 15px 20px; 
            border-radius: 10px; 
            margin: 20px 0;
            border-left: 5px solid var(--success-color);
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 15px 20px; 
            border-radius: 10px; 
            margin: 20px 0;
            border-left: 5px solid var(--danger-color);
        }
        .bilet-detay {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        .bilet-detay-item {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f0f0;
        }
        .bilet-detay-label {
            font-weight: 600;
            color: #666;
        }
        .bilet-detay-value {
            color: #333;
            font-weight: 500;
        }
        .koltuk-bilgisi {
            background: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
            display: inline-block;
        }
        .koltuk-yok {
            color: #dc3545;
            font-style: italic;
        }
        .fiyat {
            color: var(--danger-color);
            font-weight: bold;
            font-size: 1.1em;
        }
        .kalan-sure {
            color: var(--success-color);
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .bilet-karti {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .istatistikler {
                grid-template-columns: repeat(2, 1fr);
            }
            .bilet-detay {
                grid-template-columns: 1fr;
            }
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
        @media (max-width: 480px) {
            .istatistikler {
                grid-template-columns: 1fr;
            }
            .filtreler a {
                display: block;
                margin: 5px 0;
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
                <a href="sefer-ara.php">🔍 Sefer Ara</a>
                <a href="logout.php">🚪 Çıkış Yap</a>
            </div>
        </div>

        <div class="page-header">
            <h1>🎫 Biletlerim</h1>
            <p style="color: #666; margin-top: 10px;">Tüm biletlerinizi buradan yönetebilirsiniz</p>
        </div>

        <?php if ($basarili_mesaji): ?>
            <div class="success">
                <h3 style="margin: 0 0 10px 0;">✅ Başarılı!</h3>
                <p style="margin: 0;"><?php echo $basarili_mesaji; ?></p>
            </div>
        <?php endif; ?>

        <?php if ($hata_mesaji): ?>
            <div class="error">
                <h3 style="margin: 0 0 10px 0;">❌ Hata!</h3>
                <p style="margin: 0;"><?php echo $hata_mesaji; ?></p>
            </div>
        <?php endif; ?>

        <!-- İstatistikler -->
        <div class="istatistikler">
            <div class="istatistik-kutu">
                <h3>Toplam Bilet</h3>
                <p style="font-size: 2.5em; font-weight: bold; color: var(--primary-color); margin: 0;"><?php echo $istatistikler['toplam_bilet']; ?></p>
            </div>
            <div class="istatistik-kutu">
                <h3>Aktif Biletler</h3>
                <p style="font-size: 2.5em; font-weight: bold; color: var(--success-color); margin: 0;"><?php echo $istatistikler['aktif_bilet']; ?></p>
            </div>
            <div class="istatistik-kutu">
                <h3>İptal Edilenler</h3>
                <p style="font-size: 2.5em; font-weight: bold; color: var(--danger-color); margin: 0;"><?php echo $istatistikler['iptal_bilet']; ?></p>
            </div>
            <div class="istatistik-kutu">
                <h3>Geçmiş Biletler</h3>
                <p style="font-size: 2.5em; font-weight: bold; color: #6c757d; margin: 0;"><?php echo $istatistikler['gecmis_bilet']; ?></p>
            </div>
        </div>

        <!-- Filtreler -->
        <div class="filtreler">
            <strong style="display: block; margin-bottom: 15px; color: #333;">Filtrele:</strong>
            <a href="?filtre=tum" class="<?php echo $filtre === 'tum' ? 'aktif' : ''; ?>">📋 Tüm Biletler</a>
            <a href="?filtre=aktif" class="<?php echo $filtre === 'aktif' ? 'aktif' : ''; ?>">✅ Aktif Biletler</a>
            <a href="?filtre=iptal" class="<?php echo $filtre === 'iptal' ? 'aktif' : ''; ?>">❌ İptal Edilenler</a>
            <a href="?filtre=gecmis" class="<?php echo $filtre === 'gecmis' ? 'aktif' : ''; ?>">📅 Geçmiş Biletler</a>
        </div>

        <!-- Bilet Listesi -->
        <div class="bilet-listesi">
            <?php if (empty($biletler)): ?>
                <div class="bos-liste">
                    <h2 style="color: #666; margin-bottom: 20px;">🎫 Henüz biletiniz bulunmuyor</h2>
                    <p style="color: #888; margin-bottom: 30px;">İlk biletinizi almak için hemen sefer arayın!</p>
                    <a href="sefer-ara.php" class="btn" style="padding: 15px 30px; font-size: 1.1em;">🔍 Sefer Ara ve Bilet Al</a>
                </div>
            <?php else: ?>
                <?php foreach ($biletler as $bilet): ?>
                    <?php
                    // Bilet durumunu belirle
                    $simdi = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
                    $kalkis = new DateTime($bilet['departure_time'], new DateTimeZone('Europe/Istanbul'));

                    if ($bilet['status'] === 'cancelled') {
                        $durum = 'iptal';
                        $durum_metin = 'İptal Edildi';
                        $durum_class = 'durum-iptal';
                    } elseif ($kalkis < $simdi) {
                        $durum = 'gecmis';
                        $durum_metin = 'Yolculuk Tamamlandı';
                        $durum_class = 'durum-gecmis';
                    } else {
                        $durum = 'aktif';
                        $durum_metin = 'Aktif';
                        $durum_class = 'durum-aktif';
                    }
                    
                    // Kalan süreyi hesapla
                    $kalan_metin = '';
                    if ($durum === 'aktif') {
                        $kalan = $simdi->diff($kalkis);
                        if ($kalan->days > 0) {
                            $kalan_metin = $kalan->days . ' gün ' . $kalan->h . ' saat';
                        } else {
                            $kalan_metin = $kalan->h . ' saat ' . $kalan->i . ' dakika';
                        }
                    }
                    
                    // Koltuk bilgilerini işle
                    $koltuk_numaralari = '';
                    if (!empty($bilet['koltuk_numaralari'])) {
                        $koltuk_numaralari = $bilet['koltuk_numaralari'];
                    }
                    ?>
                    
                    <div class="bilet-karti">
                        <div class="bilet-bilgileri">
                            <h3>
                                <?php echo htmlspecialchars($bilet['departure_city']); ?> 
                                <span style="color: var(--primary-color);">→</span> 
                                <?php echo htmlspecialchars($bilet['destination_city']); ?>
                            </h3>
                            
                            <div class="bilet-detay">
                                <div>
                                    <div class="bilet-detay-item">
                                        <span class="bilet-detay-label">Firma:</span>
                                        <span class="bilet-detay-value"><?php echo htmlspecialchars($bilet['firma_adi']); ?></span>
                                    </div>
                                    <div class="bilet-detay-item">
                                        <span class="bilet-detay-label">Kalkış:</span>
                                        <span class="bilet-detay-value"><?php echo date('d.m.Y H:i', strtotime($bilet['departure_time'])); ?></span>
                                    </div>
                                    <div class="bilet-detay-item">
                                        <span class="bilet-detay-label">Varış:</span>
                                        <span class="bilet-detay-value"><?php echo date('d.m.Y H:i', strtotime($bilet['arrival_time'])); ?></span>
                                    </div>
                                </div>
                                <div>
                                    <div class="bilet-detay-item">
                                        <span class="bilet-detay-label">Koltuk:</span>
                                        <span class="bilet-detay-value">
                                            <?php if (!empty($koltuk_numaralari)): ?>
                                                <span class="koltuk-bilgisi"><?php echo $koltuk_numaralari; ?></span>
                                            <?php else: ?>
                                                <span class="koltuk-yok">Koltuk bilgisi yok</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="bilet-detay-item">
                                        <span class="bilet-detay-label">Ücret:</span>
                                        <span class="bilet-detay-value fiyat">
                                            <?php echo number_format($bilet['total_price'] / 100, 2); ?> TL
                                        </span>
                                    </div>
                                    <?php if ($durum === 'aktif'): ?>
                                        <div class="bilet-detay-item">
                                            <span class="bilet-detay-label">Kalkışa Kalan:</span>
                                            <span class="bilet-detay-value kalan-sure"><?php echo $kalan_metin; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bilet-aksiyonlar">
                            <div class="bilet-durumu <?php echo $durum_class; ?>">
                                <strong><?php echo $durum_metin; ?></strong>
                            </div>
                            
                            <div style="display: grid; gap: 10px; min-width: 150px;">
                                <a href="bilet-detay.php?bilet_id=<?php echo $bilet['ticket_id']; ?>" class="btn">
                                    👁️ Detayları Gör
                                
                                    <a href="bilet-detay.php?bilet_id=<?php echo $bilet['ticket_id']; ?>" class="btn btn-success">
                                    🖨️ İndir
                                </a>

                                <?php if ($durum === 'aktif'): ?>
                                    <a href="bilet-iptal.php?bilet_id=<?php echo $bilet['ticket_id']; ?>" class="btn btn-danger" 
                                       onclick="return confirm('Bu bilet iptal edilecek. Emin misiniz?')">
                                        ❌ İptal Et
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>
                                        <?php echo $durum === 'iptal' ? '❌ İptal Edildi' : '📅 Geçmiş'; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Yazdırma butonu için
        function printBilet() {
            window.print();
        }

        // Sayfa yüklendiğinde animasyon ekle
        document.addEventListener('DOMContentLoaded', function() {
            // Bilet kartlarına sıralı animasyon ekle
            const biletKartlari = document.querySelectorAll('.bilet-karti');
            biletKartlari.forEach((kart, index) => {
                setTimeout(() => {
                    kart.style.opacity = '0';
                    kart.style.transform = 'translateY(20px)';
                    kart.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        kart.style.opacity = '1';
                        kart.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
        });
    </script>
</body>
</html>