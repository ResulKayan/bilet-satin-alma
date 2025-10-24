<?php
// pages/bilet-al.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Sadece giriş yapan kullanıcılar erişebilir
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}


$user = getCurrentUser();

// Sefer ID kontrolü
$sefer_id = $_GET['sefer_id'] ?? '';
if (empty($sefer_id)) {
    die("❌ Sefer ID'si belirtilmedi.");
}

// Sefer bilgilerini getir
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT t.*, bc.name as firma_adi, bc.logo_path,
               (SELECT COUNT(*) FROM booked_seats WHERE ticket_id IN (SELECT id FROM tickets WHERE trip_id = t.id)) as dolu_koltuk_sayisi
        FROM trips t 
        JOIN bus_company bc ON t.company_id = bc.id
        WHERE t.id = ?
    ");
    $stmt->execute([$sefer_id]);
    $sefer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sefer) {
        die("❌ Sefer bulunamadı.");
    }
    
    // Dolu koltukları getir
    $stmt = $db->prepare("
        SELECT bs.seat_number 
        FROM booked_seats bs
        JOIN tickets tk ON bs.ticket_id = tk.id
        WHERE tk.trip_id = ? AND tk.status = 'active'
    ");
    $stmt->execute([$sefer_id]);
    $dolu_koltuklar = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    die("❌ Hata: " . $e->getMessage());
}

// Değişkenleri başlat
$sefer_fiyat_tl = $sefer['price'] / 100;
$user_bakiye_tl = $user['balance'];
$toplam_ucret_tl = $sefer_fiyat_tl; // Varsayılan değer
$kupon = null;
$indirim_miktari = 0;
$errors = [];
$secilen_koltuk = $_POST['koltuk'] ?? '';
$kupon_kodu = trim($_POST['kupon_kodu'] ?? '');

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validasyon
    if (empty($secilen_koltuk)) {
        $errors[] = "Lütfen bir koltuk seçin";
    }
    
    if (in_array($secilen_koltuk, $dolu_koltuklar)) {
        $errors[] = "Bu koltuk zaten dolu";
    }
    
    if ($secilen_koltuk < 1 || $secilen_koltuk > $sefer['capacity']) {
        $errors[] = "Geçersiz koltuk numarası";
    }
    
    // Kupon kontrolü
    if (!empty($kupon_kodu)) {
        try {
            $stmt = $db->prepare("
                SELECT * FROM coupons 
                WHERE code = ? AND expire_date > datetime('now') 
                AND usage_limit > (SELECT COUNT(*) FROM user_coupons WHERE coupon_id = coupons.id)
            ");
            $stmt->execute([$kupon_kodu]);
            $kupon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$kupon) {
                $errors[] = "Geçersiz veya süresi dolmuş kupon kodu";
            } else {
                // İndirim miktarını hesapla
                $indirim_miktari = $sefer_fiyat_tl * ($kupon['discount'] / 100);
            }
            
        } catch (Exception $e) {
            $errors[] = "Kupon kontrolü sırasında hata: " . $e->getMessage();
        }
    }
    
    // Toplam ücreti hesapla (TL cinsinden)
    $toplam_ucret_tl = $sefer_fiyat_tl - $indirim_miktari;
    
    // Bakiye kontrolü
    if ($user_bakiye_tl < $toplam_ucret_tl) {
        $errors[] = "Yetersiz bakiye. Gerekli: " . number_format($toplam_ucret_tl, 2) . " TL, Mevcut: " . number_format($user_bakiye_tl, 2) . " TL";
    }
    
    // Eğer hata yoksa bilet oluştur
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Bilet oluştur (toplam ücreti kuruş cinsinden kaydet)
            $bilet_id = generateUUID();
            $toplam_ucret_kurus = $toplam_ucret_tl * 100;
            
            $stmt = $db->prepare("
                INSERT INTO tickets (id, trip_id, user_id, total_price, status) 
                VALUES (?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$bilet_id, $sefer_id, $user['id'], $toplam_ucret_kurus]);
            
            // Koltuk rezervasyonu
            $koltuk_id = generateUUID();
            $stmt = $db->prepare("
                INSERT INTO booked_seats (id, ticket_id, seat_number) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$koltuk_id, $bilet_id, $secilen_koltuk]);
            
            // Kupon kullanımı kaydet
            if ($kupon) {
                $user_kupon_id = generateUUID();
                $stmt = $db->prepare("
                    INSERT INTO user_coupons (id, coupon_id, user_id) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$user_kupon_id, $kupon['id'], $user['id']]);
            }
            
            // Bakiyeden düş (TL cinsinden)
            $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$toplam_ucret_tl, $user['id']]);
            
            // Session'daki kullanıcı bakiyesini güncelle
            $_SESSION['user']['balance'] = $user_bakiye_tl - $toplam_ucret_tl;

            $db->commit();
            
            // Başarılı - bilet detay sayfasına yönlendir
            $_SESSION['bilet_basari'] = "Bilet başarıyla alındı!";
            header('Location: bilet-detay.php?bilet_id=' . $bilet_id);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Bilet oluşturma sırasında hata: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet Al - <?php echo SITE_NAME; ?></title>
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
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .nav { 
            background: white; 
            padding: 15px 20px; 
            border-bottom: 1px solid #e9ecef;
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
        
        .content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 0;
        }
        
        .main-content {
            padding: 30px;
            border-right: 1px solid #e9ecef;
        }
        
        .sidebar {
            padding: 30px;
            background: #f8f9fa;
        }
        
        .sefer-bilgileri {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .sefer-bilgileri::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: rgba(255,255,255,0.3);
        }
        
        .sefer-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .firma-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
            margin-right: 15px;
            background: white;
            padding: 5px;
            border-radius: 8px;
        }
        
        .sefer-details {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 20px;
            align-items: center;
            text-align: center;
        }
        
        .city {
            font-size: 1.4em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .time {
            font-size: 2em;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .date {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .arrow {
            font-size: 2em;
            color: rgba(255,255,255,0.7);
        }
        
        .koltuk-secimi {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .koltuk-info {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .koltuk-legend {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            justify-content: center;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
        
        .bus-layout {
            background: #e9ecef;
            padding: 30px;
            border-radius: 12px;
            margin: 20px 0;
            position: relative;
        }
        
        .driver {
            position: absolute;
            top: 20px;
            left: 20px;
            background: #6c757d;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: bold;
            transform: rotate(-90deg);
            transform-origin: left top;
        }
        
        .koltuklar-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .koltuk {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            position: relative;
        }
        
        .koltuk:hover {
            transform: scale(1.05);
        }
        
        .koltuk.bos {
            background: var(--success-color);
            color: white;
        }
        
        .koltuk.dolu {
            background: var(--danger-color);
            color: white;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .koltuk.secili {
            background: var(--primary-color);
            color: white;
            border-color: var(--secondary-color);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .koltuk-number {
            font-size: 0.9em;
        }
        
        .odeme-bilgileri {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 15px 30px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-success {
            background: var(--success-color);
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: #000;
        }
        
        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .fiyat-detay {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
        }
        
        .fiyat-satir {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .fiyat-satir:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2em;
            color: var(--primary-color);
        }
        
        .indirim {
            color: var(--success-color);
        }
        
        .bakiye-info {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid var(--warning-color);
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid var(--danger-color);
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid var(--success-color);
        }
        
        .selected-seat-info {
            background: var(--primary-color);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 15px 0;
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .kupon-section {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .kupon-section input {
            flex: 1;
        }
        
        .kupon-section .btn {
            width: auto;
            padding: 12px 20px;
        }
        
        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                border-right: none;
                border-bottom: 1px solid #e9ecef;
            }
            
            .sefer-details {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .arrow {
                transform: rotate(90deg);
            }
            
            .koltuklar-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .kupon-section {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Navigasyon -->
        <div class="nav">
            <div class="logo">
                <h2 style="margin: 0; color: var(--primary-color);">🎫 Bilet Al</h2>
            </div>
            <div class="nav-links">
                <a href="../index.php">🏠 Ana Sayfa</a>
                <a href="hesabim.php">👤 Hesabım</a>
                <a href="biletlerim.php">📋 Biletlerim</a>
            </div>
        </div>

        <div class="content">
            <!-- Ana İçerik -->
            <div class="main-content">
                <?php if (!empty($errors)): ?>
                    <div class="error">
                        <h4>❌ İşlem Başarısız</h4>
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Sefer Bilgileri -->
                <div class="sefer-bilgileri">
                    <div class="sefer-header">
                        <?php if (!empty($sefer['logo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($sefer['logo_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($sefer['firma_adi']); ?>" 
                                 class="firma-logo">
                        <?php endif; ?>
                        <div>
                            <h2 style="margin: 0;"><?php echo htmlspecialchars($sefer['firma_adi']); ?></h2>
                            <p style="margin: 5px 0 0 0; opacity: 0.9;">Konforlu ve güvenli yolculuk</p>
                        </div>
                    </div>
                    
                    <div class="sefer-details">
                        <div>
                            <div class="city"><?php echo htmlspecialchars($sefer['departure_city']); ?></div>
                            <div class="time"><?php echo date('H:i', strtotime($sefer['departure_time'])); ?></div>
                            <div class="date"><?php echo date('d.m.Y', strtotime($sefer['departure_time'])); ?></div>
                        </div>
                        
                        <div class="arrow">➝</div>
                        
                        <div>
                            <div class="city"><?php echo htmlspecialchars($sefer['destination_city']); ?></div>
                            <div class="time"><?php echo date('H:i', strtotime($sefer['arrival_time'])); ?></div>
                            <div class="date"><?php echo date('d.m.Y', strtotime($sefer['arrival_time'])); ?></div>
                        </div>
                    </div>
                </div>

                <form method="POST" id="biletForm">
                    <!-- Koltuk Seçimi -->
                    <div class="koltuk-secimi">
                        <h2>💺 Koltuk Seçimi</h2>
                        
                        <div class="koltuk-info">
                            <div class="info-item">
                                <strong>Toplam:</strong> <?php echo $sefer['capacity']; ?> koltuk
                            </div>
                            <div class="info-item">
                                <strong>Dolu:</strong> <?php echo count($dolu_koltuklar); ?> koltuk
                            </div>
                            <div class="info-item">
                                <strong>Boş:</strong> <?php echo $sefer['capacity'] - count($dolu_koltuklar); ?> koltuk
                            </div>
                        </div>
                        
                        <div class="koltuk-legend">
                            <div class="legend-item">
                                <div class="legend-color" style="background: var(--success-color);"></div>
                                <span>Boş Koltuk</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: var(--danger-color);"></div>
                                <span>Dolu Koltuk</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: var(--primary-color);"></div>
                                <span>Seçili Koltuk</span>
                            </div>
                        </div>

                        <div class="bus-layout">
                            <div class="driver">SÜRÜCÜ</div>
                            <div class="koltuklar-grid">
                                <?php for ($i = 1; $i <= $sefer['capacity']; $i++): ?>
                                    <?php 
                                    $dolu = in_array($i, $dolu_koltuklar);
                                    $secili = ($secilen_koltuk == $i);
                                    ?>
                                    <div class="koltuk <?php echo $dolu ? 'dolu' : 'bos'; ?> <?php echo $secili ? 'secili' : ''; ?>"
                                         data-koltuk="<?php echo $i; ?>"
                                         onclick="<?php echo !$dolu ? "selectSeat($i)" : ""; ?>">
                                        <span class="koltuk-number"><?php echo $i; ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <input type="hidden" name="koltuk" id="selectedSeat" value="<?php echo htmlspecialchars($secilen_koltuk); ?>">
                        
                        <?php if (!empty($secilen_koltuk)): ?>
                            <div class="selected-seat-info">
                                ✅ Seçili Koltuk: <strong>#<?php echo $secilen_koltuk; ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Sağ Sidebar - Ödeme Bilgileri -->
            <div class="sidebar">
                <h2>💰 Ödeme Bilgileri</h2>
                
                <div class="form-group">
                    <label for="kupon_kodu">🎁 Kupon Kodu</label>
                    <div class="kupon-section">
                        <input type="text" name="kupon_kodu" id="kupon_kodu" 
                               value="<?php echo htmlspecialchars($kupon_kodu); ?>" 
                               placeholder="Kupon kodunuzu giriniz">
                        <button type="button" class="btn btn-warning" onclick="checkKupon()">Kuponu Uygula</button>
                    </div>
                    <small style="color: #666; display: block; margin-top: 5px;">Kuponunuz varsa girin ve uygulayın</small>
                </div>

                <div class="fiyat-detay">
                    <h3>📋 Fiyat Detayı</h3>
                    <div class="fiyat-satir">
                        <span>Bilet Fiyatı:</span>
                        <span><?php echo number_format($sefer_fiyat_tl, 2); ?> TL</span>
                    </div>
                    
                    <?php if ($kupon && $indirim_miktari > 0): ?>
                        <div class="fiyat-satir indirim">
                            <span>Kupon İndirimi (<?php echo ($kupon['discount'] * 100); ?>%):</span>
                            <span>-<?php echo number_format($indirim_miktari, 2); ?> TL</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="fiyat-satir">
                        <span><strong>Toplam Tutar:</strong></span>
                        <span><strong id="toplamTutar"><?php echo number_format($toplam_ucret_tl, 2); ?></strong> TL</span>
                    </div>
                </div>

                <div class="bakiye-info">
                    <h4>💰 Bakiyeniz</h4>
                    <p style="font-size: 1.3em; font-weight: bold; color: var(--success-color);">
                        <?php echo number_format($user_bakiye_tl, 2); ?> TL
                    </p>
                    <div id="bakiyeDurum" style="margin-top: 10px;">
                        <?php if ($user_bakiye_tl >= $toplam_ucret_tl && !empty($secilen_koltuk)): ?>
                            <span style="color: var(--success-color);">✅ Bakiye yeterli</span>
                        <?php elseif (empty($secilen_koltuk)): ?>
                            <span style="color: var(--warning-color);">⚠️ Lütfen koltuk seçin</span>
                        <?php else: ?>
                            <span style="color: var(--danger-color);">❌ Bakiye yetersiz</span>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" form="biletForm" class="btn btn-success" 
                        id="odemeButonu" style="width: 100%;"
                        <?php echo ($user_bakiye_tl < $toplam_ucret_tl || empty($secilen_koltuk)) ? 'disabled' : ''; ?>>
                    ✅ Bilet Al ve Ödeme Yap
                </button>
                
                <div style="text-align: center; margin-top: 15px;">
                    <a href="../index.php" style="color: #666; text-decoration: none;">← Sefer aramaya dön</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selectSeat(koltukNo) {
            // Sadece koltuk seçimini güncelle, formu submit etme
            document.getElementById('selectedSeat').value = koltukNo;
            
            // Tüm koltuklardan seçili class'ını kaldır
            document.querySelectorAll('.koltuk').forEach(koltuk => {
                koltuk.classList.remove('secili');
            });
            
            // Seçilen koltuğa seçili class'ını ekle
            event.target.classList.add('secili');
            
            // Seçili koltuk bilgisini göster
            showSelectedSeatInfo(koltukNo);
            
            // Ödeme butonunu güncelle
            updateOdemeButonu();
        }
        
        function showSelectedSeatInfo(koltukNo) {
            // Mevcut seçili koltuk bilgisini kaldır
            const existingInfo = document.querySelector('.selected-seat-info');
            if (existingInfo) {
                existingInfo.remove();
            }
            
            // Yeni seçili koltuk bilgisini ekle
            const koltukSecimiDiv = document.querySelector('.koltuk-secimi');
            const infoDiv = document.createElement('div');
            infoDiv.className = 'selected-seat-info';
            infoDiv.innerHTML = `✅ Seçili Koltuk: <strong>#${koltukNo}</strong>`;
            koltukSecimiDiv.appendChild(infoDiv);
        }
        
        function checkKupon() {
            // Kupon kontrolü için formu submit et
            document.getElementById('biletForm').submit();
        }
        
        function updateOdemeButonu() {
            const seciliKoltuk = document.getElementById('selectedSeat').value;
            const odemeButonu = document.getElementById('odemeButonu');
            const bakiyeDurum = document.getElementById('bakiyeDurum');
            
            if (seciliKoltuk) {
                odemeButonu.disabled = false;
                bakiyeDurum.innerHTML = '<span style="color: var(--success-color);">✅ Koltuk seçildi, ödeme yapabilirsiniz</span>';
            } else {
                odemeButonu.disabled = true;
                bakiyeDurum.innerHTML = '<span style="color: var(--warning-color);">⚠️ Lütfen koltuk seçin</span>';
            }
        }
        
        // Sayfa yüklendiğinde önceden seçili koltuk varsa göster
        document.addEventListener('DOMContentLoaded', function() {
            const selectedSeat = document.getElementById('selectedSeat').value;
            if (selectedSeat) {
                document.querySelectorAll('.koltuk').forEach(koltuk => {
                    if (koltuk.getAttribute('data-koltuk') === selectedSeat && !koltuk.classList.contains('dolu')) {
                        koltuk.classList.add('secili');
                    }
                });
                showSelectedSeatInfo(selectedSeat);
            }
            
            updateOdemeButonu();
        });
    </script>
</body>
</html>