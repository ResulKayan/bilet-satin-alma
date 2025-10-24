<?php
// pages/sefer-ara.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/cities.php'; // Şehir listesini içe aktar

$user = getCurrentUser();

// Arama parametrelerini al
$nereden = $_GET['nereden'] ?? '';
$nereye = $_GET['nereye'] ?? '';
$tarih = $_GET['tarih'] ?? '';

// Şu anki zamanı Türkiye saatine göre al
$simdi = date('Y-m-d H:i:s');

// Seferleri getir
$seferler = [];
if (!empty($nereden) && !empty($nereye)) {
    try {
        $db = getDB();
        
        $sql = "
            SELECT t.*, 
                   bc.name as firma_adi,
                   bc.logo_path,
                   (SELECT COUNT(*) FROM booked_seats WHERE ticket_id IN (SELECT id FROM tickets WHERE trip_id = t.id)) as dolu_koltuk_sayisi
            FROM trips t 
            JOIN bus_company bc ON t.company_id = bc.id
            WHERE t.departure_city = ? AND t.destination_city = ?
        ";
        
        $params = [$nereden, $nereye];
        
        // Tarih filtresi (tarih doluysa)
        if (!empty($tarih)) {
            $sql .= " AND DATE(t.departure_time) = ?";
            $params[] = $tarih;
        }
        
        // Sadece gelecek seferleri göster (Türkiye saatine göre)
        $sql .= " AND t.departure_time > ? ORDER BY t.departure_time ASC";
        $params[] = $simdi;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $seferler = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $hata = "Seferler yüklenirken hata: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sefer Ara - <?php echo SITE_NAME; ?></title>
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
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
        .search-form { 
            background: white; 
            padding: 30px; 
            border-radius: 15px; 
            margin: 30px 0; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group { margin-bottom: 20px; }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: bold; 
            color: #333; 
        }
        input, select { 
            width: 100%; 
            padding: 12px; 
            border: 2px solid #e9ecef; 
            border-radius: 8px; 
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input:focus, select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .btn { 
            padding: 12px 30px; 
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
        }
        .btn:hover { 
            background: var(--secondary-color); 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-success { background: var(--success-color); }
        .btn-warning { background: var(--warning-color); color: #000; }
        .btn-danger { background: var(--danger-color); }
        .trip-card { 
            background: white; 
            border: 1px solid #e9ecef; 
            padding: 25px; 
            margin: 20px 0; 
            border-radius: 12px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        .trip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .firma-logo { 
            width: 70px; 
            height: 70px; 
            object-fit: contain; 
            margin-right: 20px;
            border-radius: 8px;
            border: 2px solid #f8f9fa;
        }
        .trip-header { 
            display: flex; 
            align-items: center; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #f8f9fa;
            padding-bottom: 15px;
        }
        .trip-info { 
            display: grid; 
            grid-template-columns: 2fr 1fr 1fr 1fr; 
            gap: 20px; 
            margin-bottom: 20px;
            align-items: center;
        }
        .trip-price { 
            font-size: 1.8em; 
            font-weight: bold; 
            color: var(--danger-color); 
            text-align: center;
        }
        .route-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .route-arrow {
            font-size: 1.5em;
            color: var(--primary-color);
        }
        .city-name {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
        }
        .time-info {
            text-align: center;
        }
        .time {
            font-size: 1.4em;
            font-weight: bold;
            color: #333;
        }
        .date {
            color: #666;
            font-size: 0.9em;
        }
        .city-select-hint {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
        .bos-sonuc {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            background: white;
            border-radius: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .arama-sonuc {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid var(--danger-color);
        }
        @media (max-width: 768px) {
            .trip-info {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .nav {
                flex-direction: column;
                gap: 10px;
            }
            .nav-links {
                justify-content: center;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav">
            <div class="logo">
                <h2 style="margin: 0; color: var(--primary-color);">🔍 <?php echo SITE_NAME; ?></h2>
            </div>
            <div class="nav-links">
                <a href="../index.php">🏠 Ana Sayfa</a>
                <?php if($user): ?>
                    <a href="hesabim.php">👤 Hesabım</a>
                    <a href="biletlerim.php">🎫 Biletlerim</a>
                    <a href="logout.php">🚪 Çıkış</a>
                <?php else: ?>
                    <a href="login.php">🔐 Giriş Yap</a>
                    <a href="register.php">📝 Kayıt Ol</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="page-header">
            <h1>🔍 Sefer Ara</h1>
            <p style="color: #666; margin-top: 10px;">İstediğiniz rotada seferleri arayın ve biletinizi hemen alın</p>
        </div>

        <!-- Sefer Arama Formu -->
        <div class="search-form">
            <form method="GET" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nereden">Nereden:</label>
                        <select name="nereden" id="nereden" required>
                            <option value="">-- Şehir Seçin --</option>
                            <optgroup label="🔸 Popüler Şehirler">
                                <?php foreach($populer_sehirler as $sehir): ?>
                                    <option value="<?php echo $sehir; ?>" <?php echo $nereden === $sehir ? 'selected' : ''; ?>>
                                        <?php echo $sehir; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="🏙️ Tüm Şehirler">
                                <?php foreach($sehirler as $sehir): ?>
                                    <?php if(!in_array($sehir, $populer_sehirler)): ?>
                                        <option value="<?php echo $sehir; ?>" <?php echo $nereden === $sehir ? 'selected' : ''; ?>>
                                            <?php echo $sehir; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <div class="city-select-hint">Kalkış şehrini seçin</div>
                    </div>

                    <div class="form-group">
                        <label for="nereye">Nereye:</label>
                        <select name="nereye" id="nereye" required>
                            <option value="">-- Şehir Seçin --</option>
                            <optgroup label="🔸 Popüler Şehirler">
                                <?php foreach($populer_sehirler as $sehir): ?>
                                    <option value="<?php echo $sehir; ?>" <?php echo $nereye === $sehir ? 'selected' : ''; ?>>
                                        <?php echo $sehir; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="🏙️ Tüm Şehirler">
                                <?php foreach($sehirler as $sehir): ?>
                                    <?php if(!in_array($sehir, $populer_sehirler)): ?>
                                        <option value="<?php echo $sehir; ?>" <?php echo $nereye === $sehir ? 'selected' : ''; ?>>
                                            <?php echo $sehir; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <div class="city-select-hint">Varış şehrini seçin</div>
                    </div>

                    <div class="form-group">
                        <label for="tarih">Tarih (Opsiyonel):</label>
                        <input type="date" name="tarih" id="tarih" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               value="<?php echo htmlspecialchars($tarih); ?>">
                        <div class="city-select-hint">Belirli bir tarih için arama yapın</div>
                    </div>
                </div>

                <button type="submit" class="btn">🔍 Seferleri Ara</button>
                <button type="button" class="btn btn-warning" onclick="clearSearch()">🗑️ Temizle</button>
                <button type="button" class="btn btn-success" onclick="swapCities()">🔄 Yön Değiştir</button>
            </form>
        </div>

        <!-- Sefer Sonuçları -->
        <div class="results">
            <?php if (isset($hata)): ?>
                <div class="error">
                    <h4 style="margin: 0 0 10px 0;">❌ Hata!</h4>
                    <p style="margin: 0;"><?php echo $hata; ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($nereden) && !empty($nereye)): ?>
                <div class="arama-sonuc">
                    <h3 style="margin: 0 0 10px 0; color: #333;">
                        🔍 Arama Sonuçları
                    </h3>
                    <p style="margin: 0; color: #666;">
                        <strong><?php echo htmlspecialchars($nereden); ?></strong> - 
                        <strong><?php echo htmlspecialchars($nereye); ?></strong>
                        <?php if ($tarih): ?>
                            - <strong><?php echo htmlspecialchars($tarih); ?></strong>
                        <?php endif; ?>
                        (<?php echo count($seferler); ?> sefer bulundu)
                    </p>
                </div>
            <?php endif; ?>

            <?php if (empty($seferler) && !empty($nereden) && !empty($nereye)): ?>
                <div class="bos-sonuc">
                    <h3 style="color: #666; margin-bottom: 20px;">❌ Sefer Bulunamadı</h3>
                    <p style="color: #888; margin-bottom: 30px;">
                        Aradığınız kriterlere uygun sefer bulunamadı.<br>
                        Lütfen farklı tarih veya güzergah deneyin.
                    </p>
                    <div style="margin-top: 15px;">
                        <button class="btn btn-warning" onclick="clearSearch()">🔄 Yeni Arama Yap</button>
                        <button class="btn btn-success" onclick="swapCities()">🔄 Yön Değiştir</button>
                    </div>
                </div>
            <?php elseif (!empty($seferler)): ?>
                <?php foreach ($seferler as $sefer): ?>
                    <?php 
                    $bos_koltuk = $sefer['capacity'] - $sefer['dolu_koltuk_sayisi'];
                    $kalkis_tarih = date('d.m.Y', strtotime($sefer['departure_time']));
                    $kalkis_saat = date('H:i', strtotime($sefer['departure_time']));
                    $varis_saat = date('H:i', strtotime($sefer['arrival_time']));
                    $seyahat_suresi = strtotime($sefer['arrival_time']) - strtotime($sefer['departure_time']);
                    $saat = floor($seyahat_suresi / 3600);
                    $dakika = floor(($seyahat_suresi % 3600) / 60);
                    ?>
                    
                    <div class="trip-card">
                        <div class="trip-header">
                            <?php if (!empty($sefer['logo_path'])): ?>
                                <img src="<?php echo htmlspecialchars($sefer['logo_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($sefer['firma_adi']); ?>" 
                                     class="firma-logo">
                            <?php endif; ?>
                            <div style="flex: 1;">
                                <h3 style="margin: 0; color: #333;"><?php echo htmlspecialchars($sefer['firma_adi']); ?></h3>
                                <p style="margin: 5px 0 0 0; color: #666;">
                                    📅 <?php echo $kalkis_tarih; ?> • 
                                    🕒 <?php echo $saat; ?>s <?php echo $dakika; ?>dk • 
                                    💺 <?php echo $bos_koltuk ?> boş koltuk
                                </p>
                            </div>
                        </div>
                        
                        <div class="trip-info">
                            <div class="route-info">
                                <div class="time-info">
                                    <div class="time"><?php echo $kalkis_saat; ?></div>
                                    <div class="city-name"><?php echo htmlspecialchars($sefer['departure_city']); ?></div>
                                </div>
                                
                                <div class="route-arrow">➡️</div>
                                
                                <div class="time-info">
                                    <div class="time"><?php echo $varis_saat; ?></div>
                                    <div class="city-name"><?php echo htmlspecialchars($sefer['destination_city']); ?></div>
                                </div>
                            </div>
                            
                            <div class="time-info">
                                <div style="font-size: 1.1em; font-weight: bold; color: #333;">⏱️ Süre</div>
                                <div><?php echo $saat; ?>s <?php echo $dakika; ?>dk</div>
                            </div>
                            
                            <div class="time-info">
                                <div style="font-size: 1.1em; font-weight: bold; color: #333;">💺 Koltuk</div>
                                <div><?php echo $bos_koltuk ?> / <?php echo $sefer['capacity']; ?> boş</div>
                            </div>
                            
                            <div class="trip-price"><?php echo number_format($sefer['price'] / 100, 2); ?> TL</div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 20px;">
                            <?php if ($user): ?>
                                <?php if ($bos_koltuk > 0): ?>
                                    <a href="bilet-al.php?sefer_id=<?php echo $sefer['id']; ?>" class="btn btn-success">
                                        🎫 Bilet Al
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-danger" disabled>❌ Dolu</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-warning">
                                    🔐 Bilet Almak İçin Giriş Yap
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bos-sonuc">
                    <h3 style="color: #666; margin-bottom: 20px;">🔍 Sefer Arama</h3>
                    <p style="color: #888;">
                        Lütfen sefer aramak için yukarıdaki formu doldurun.<br>
                        İstediğiniz güzergahta tüm seferleri listeleyebilirsiniz.
                    </p>
                    <div style="margin-top: 20px;">
                        <button class="btn" onclick="document.getElementById('nereden').focus()">
                            🚀 Hemen Arama Yap
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Arama temizleme
        function clearSearch() {
            document.getElementById('nereden').value = '';
            document.getElementById('nereye').value = '';
            document.getElementById('tarih').value = '';
            window.location.href = 'sefer-ara.php';
        }

        // Şehirleri değiştir (yön değiştir)
        function swapCities() {
            const nereden = document.getElementById('nereden').value;
            const nereye = document.getElementById('nereye').value;
            
            if (nereden && nereye) {
                document.getElementById('nereden').value = nereye;
                document.getElementById('nereye').value = nereden;
                document.querySelector('form').submit();
            } else if (nereden && !nereye) {
                document.getElementById('nereye').value = nereden;
                document.getElementById('nereden').value = '';
            } else if (!nereden && nereye) {
                document.getElementById('nereden').value = nereye;
                document.getElementById('nereye').value = '';
            }
        }

        // Form validasyonu
        document.querySelector('form').addEventListener('submit', function(e) {
            const nereden = document.getElementById('nereden').value;
            const nereye = document.getElementById('nereye').value;
            
            if (!nereden || !nereye) {
                e.preventDefault();
                alert('Lütfen nereden ve nereye seçiniz.');
                return false;
            }
            
            if (nereden === nereye) {
                e.preventDefault();
                alert('Kalkış ve varış şehirleri aynı olamaz.');
                return false;
            }
        });

        // Sayfa yüklendiğinde tarih input'unu boş bırak
        document.addEventListener('DOMContentLoaded', function() {
            const tarihInput = document.getElementById('tarih');
            // Tarih input'unu boş bırak - opsiyonel olduğu için
            if (!tarihInput.value) {
                tarihInput.value = '';
            }
        });
    </script>
</body>
</html>