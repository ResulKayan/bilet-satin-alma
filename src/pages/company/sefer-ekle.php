<?php
// pages/company/sefer-ekle.php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isCompanyAdmin()) {
    header('Location: ../login.php');
    exit;
}

$user = getCurrentUser();
$company_id = $user['company_id'];

// Türkiye şehirleri listesi
$sehirler = [
    'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Amasya', 'Ankara', 'Antalya', 'Artvin',
    'Aydın', 'Balıkesir', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa', 'Çanakkale',
    'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Edirne', 'Elazığ', 'Erzincan', 'Erzurum',
    'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Isparta', 'Mersin',
    'İstanbul', 'İzmir', 'Kars', 'Kastamonu', 'Kayseri', 'Kırklareli', 'Kırşehir', 'Kocaeli',
    'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Kahramanmaraş', 'Mardin', 'Muğla', 'Muş', 'Nevşehir',
    'Niğde', 'Ordu', 'Rize', 'Sakarya', 'Samsun', 'Siirt', 'Sinop', 'Sivas', 'Tekirdağ', 'Tokat',
    'Trabzon', 'Tunceli', 'Şanlıurfa', 'Uşak', 'Van', 'Yozgat', 'Zonguldak', 'Aksaray', 'Bayburt',
    'Karaman', 'Kırıkkale', 'Batman', 'Şırnak', 'Bartın', 'Ardahan', 'Iğdır', 'Yalova', 'Karabük',
    'Kilis', 'Osmaniye', 'Düzce'
];

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $departure_city = trim($_POST['departure_city'] ?? '');
    $destination_city = trim($_POST['destination_city'] ?? '');
    $departure_time = $_POST['departure_time'] ?? '';
    $arrival_time = $_POST['arrival_time'] ?? '';
    $price = floatval($_POST['price'] ?? 0) * 100; // TL'den kuruşa çevir
    $capacity = intval($_POST['capacity'] ?? 0);

    try {
        $db = getDB();
        
        // Validasyon
        $hata = '';
        if (empty($departure_city)) $hata = "Kalkış şehri gereklidir";
        elseif (empty($destination_city)) $hata = "Varış şehri gereklidir";
        elseif ($departure_city === $destination_city) $hata = "Kalkış ve varış şehirleri aynı olamaz";
        elseif (empty($departure_time)) $hata = "Kalkış zamanı gereklidir";
        elseif (empty($arrival_time)) $hata = "Varış zamanı gereklidir";
        elseif (strtotime($arrival_time) <= strtotime($departure_time)) $hata = "Varış zamanı kalkış zamanından sonra olmalıdır";
        elseif ($price <= 0) $hata = "Geçerli bir fiyat giriniz";
        elseif ($capacity <= 0 || $capacity > 100) $hata = "Geçerli bir kapasite giriniz (1-100 arası)";

        if (empty($hata)) {
            $sefer_id = generateUUID();
            
            $stmt = $db->prepare("INSERT INTO trips (id, company_id, departure_city, destination_city, departure_time, arrival_time, price, capacity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$sefer_id, $company_id, $departure_city, $destination_city, $departure_time, $arrival_time, $price, $capacity]);
            
            $basari = "✅ Sefer başarıyla eklendi!";
            
            // Formu temizle
            $departure_city = $destination_city = $departure_time = $arrival_time = '';
            $price = $capacity = 0;
        }
    } catch (Exception $e) {
        $hata = "Sefer eklenirken hata: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sefer Ekle - Firma Panel - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #333; }
        .form-container { background: white; padding: 30px; border-radius: 10px; max-width: 600px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { padding: 12px 30px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .success { color: green; background: #eaffea; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { color: red; background: #ffeaea; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    </style>
</head>
<body>
    <div class="nav">
        <a href="../../index.php">🏠 Ana Sayfa</a>
        <a href="panel.php">📊 Firma Panel</a>
        <a href="seferler.php">🚌 Seferlerim</a>
        <a href="../logout.php">🚪 Çıkış</a>
    </div>

    <div class="form-container">
        <h1>➕ Yeni Sefer Ekle</h1>

        <?php if (isset($basari)): ?>
            <div class="success"><?php echo $basari; ?></div>
        <?php endif; ?>

        <?php if (isset($hata)): ?>
            <div class="error"><?php echo $hata; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="departure_city">Kalkış Şehri *</label>
                    <select name="departure_city" id="departure_city" required>
                        <option value="">-- Şehir Seçin --</option>
                        <?php foreach ($sehirler as $sehir): ?>
                            <option value="<?php echo $sehir; ?>" <?php echo ($departure_city ?? '') === $sehir ? 'selected' : ''; ?>>
                                <?php echo $sehir; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="destination_city">Varış Şehri *</label>
                    <select name="destination_city" id="destination_city" required>
                        <option value="">-- Şehir Seçin --</option>
                        <?php foreach ($sehirler as $sehir): ?>
                            <option value="<?php echo $sehir; ?>" <?php echo ($destination_city ?? '') === $sehir ? 'selected' : ''; ?>>
                                <?php echo $sehir; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="departure_time">Kalkış Zamanı *</label>
                    <input type="datetime-local" name="departure_time" id="departure_time" 
                           value="<?php echo htmlspecialchars($departure_time ?? ''); ?>" 
                           min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="arrival_time">Varış Zamanı *</label>
                    <input type="datetime-local" name="arrival_time" id="arrival_time" 
                           value="<?php echo htmlspecialchars($arrival_time ?? ''); ?>" 
                           min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="price">Fiyat (TL) *</label>
                    <input type="number" name="price" id="price" min="1" step="0.01" 
                           value="<?php echo htmlspecialchars(($price ?? 0) / 100); ?>" 
                           placeholder="125.50" required>
                </div>

                <div class="form-group">
                    <label for="capacity">Kapasite (Koltuk Sayısı) *</label>
                    <input type="number" name="capacity" id="capacity" min="1" max="100" 
                           value="<?php echo htmlspecialchars($capacity ?? 0); ?>" 
                           placeholder="45" required>
                </div>
            </div>

            <button type="submit">✅ Sefer Ekle</button>
            <a href="seferler.php" style="margin-left: 10px; color: #666; text-decoration: none;">← Sefer Listesine Dön</a>
        </form>
    </div>

    <script>
        // Kalkış şehri değiştiğinde varış şehirlerini güncelle (aynı şehri engelle)
        document.getElementById('departure_city').addEventListener('change', function() {
            const destinationSelect = document.getElementById('destination_city');
            const selectedDeparture = this.value;
            
            // Tüm seçenekleri göster
            for (let option of destinationSelect.options) {
                option.style.display = 'block';
            }
            
            // Kalkış şehrini varış seçeneklerinden gizle
            if (selectedDeparture) {
                for (let option of destinationSelect.options) {
                    if (option.value === selectedDeparture) {
                        option.style.display = 'none';
                    }
                }
            }
        });

        // Sayfa yüklendiğinde de aynı işlemi yap
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('departure_city').dispatchEvent(new Event('change'));
        });

        // Varış zamanının kalkış zamanından sonra olmasını sağla
        document.getElementById('departure_time').addEventListener('change', function() {
            const arrivalInput = document.getElementById('arrival_time');
            arrivalInput.min = this.value;
            
            // Eğer mevcut varış zamanı kalkıştan önceyse, kalkış zamanına ayarla
            if (arrivalInput.value && arrivalInput.value < this.value) {
                arrivalInput.value = this.value;
            }
        });
    </script>
</body>
</html>