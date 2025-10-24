<?php
// pages/bilet-detay.php
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
    die("Bilet ID'si belirtilmedi.");
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT t.*, tr.*, bc.name as firma_adi,
               GROUP_CONCAT(bs.seat_number) as koltuk_numaralari,
               u.full_name, u.email
        FROM tickets t
        JOIN trips tr ON t.trip_id = tr.id
        JOIN bus_company bc ON tr.company_id = bc.id
        JOIN users u ON t.user_id = u.id
        LEFT JOIN booked_seats bs ON t.id = bs.ticket_id
        WHERE t.id = ? AND t.user_id = ?
        GROUP BY t.id
    ");
    $stmt->execute([$bilet_id, $user['id']]);
    $bilet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$bilet) {
        die("Bilet bulunamadı veya erişim yetkiniz yok.");
    }
    
} catch (Exception $e) {
    die("Hata: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet Detay - <?php echo SITE_NAME; ?></title>
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
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); 
            color: white; 
            padding: 40px 30px; 
            text-align: center; 
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
        .btn { 
            padding: 12px 25px; 
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
        .btn-success { 
            background: var(--success-color); 
        }
        .btn-success:hover { 
            background: #218838; 
            transform: translateY(-2px);
        }
        .btn-secondary { 
            background: #6c757d; 
        }
        .btn-secondary:hover { 
            background: #545b62; 
        }
        .btn-danger { 
            background: var(--danger-color); 
        }
        .btn-danger:hover { 
            background: #c0392b; 
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

        /* Yazdırma için özel stiller */
        @media print {
            .nav, .no-print {
                display: none !important;
            }
            body {
                background: white;
                padding: 0;
            }
            .main-container {
                box-shadow: none;
                border: 1px solid #000;
                margin: 0;
            }
            .header {
                background: #333 !important;
                -webkit-print-color-adjust: exact;
            }
            .bilet-info {
                border-left: 5px solid #000;
            }
        }

        /* Bilet yazdırma görünümü */
        .bilet-yazdir {
            max-width: 100%;
            border: 2px solid #333;
            padding: 20px;
            background: white;
            font-family: Arial, sans-serif;
        }
        .bilet-baslik {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .bilet-barkod {
            text-align: center;
            font-family: monospace;
            font-size: 14px;
            margin: 20px 0;
            padding: 10px;
            background: #f5f5f5;
            border: 1px dashed #ccc;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav no-print">
            <div class="logo">
                <h2 style="margin: 0; color: var(--primary-color);">🎫 <?php echo SITE_NAME; ?></h2>
            </div>
            <div class="nav-links">
                <a href="../index.php">🏠 Ana Sayfa</a>
                <a href="hesabim.php">👤 Hesabım</a>
                <a href="biletlerim.php">🎫 Biletlerim</a>
            </div>
        </div>

        <div class="main-container">
            <div class="header">
                <h1>🎫 Bilet Detayı</h1>
                <p>Aşağıda bilet detaylarını görebilir ve indirebilirsiniz</p>
            </div>

            <div class="content">
                <!-- Bilet Bilgileri -->
                <div class="bilet-info">
                    <h3 style="margin-top: 0; color: var(--primary-color);">Bilet Bilgileri</h3>
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
                            <div class="info-item">
                                <span class="info-label">Varış:</span>
                                <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($bilet['arrival_time'])); ?></span>
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
                                <span class="info-value" style="color: <?php echo $bilet['status'] == 'active' ? 'var(--success-color)' : 'var(--danger-color)'; ?>; font-weight: bold;">
                                    <?php echo $bilet['status'] == 'active' ? 'Aktif' : 'İptal Edildi'; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Yolcu Adı:</span>
                                <span class="info-value"><?php echo htmlspecialchars($bilet['full_name']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Barkod Alanı -->
                    <div class="bilet-barkod">
                        <strong>BARKOD: </strong>BLT-<?php echo strtoupper(substr(md5($bilet['id']), 0, 12)); ?>
                    </div>

                    <!-- Yazdırma Uyarıları -->
                    <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 15px; border-left: 4px solid #ffc107;">
                        <h4 style="margin: 0 0 10px 0; color: #856404;">⚠️ İndirme Öncesi Notlar:</h4>
                        <ul style="margin: 0; color: #856404;">
                            <li>Biletinizi yolculuk sırasında hazır bulundurunuz</li>
                            <li>En az 30 dakika önce terminalde olunuz</li>
                            <li>Resmi kimlik belgenizi yanınızda bulundurunuz</li>
                        </ul>
                    </div>
                </div>

                <!-- Butonlar -->
                <div style="text-align: center; display: grid; grid-template-columns: 1fr 1fr; gap: 15px;" class="no-print">
                    <a href="biletlerim.php" class="btn btn-secondary">← Biletlerime Dön</a>
                    <button onclick="window.print()" class="btn btn-success">🖨️ Biletimi İndir</button>
                </div>

                <?php if ($bilet['status'] === 'active'): ?>
                    <div style="text-align: center; margin-top: 15px;" class="no-print">
                        <a href="bilet-iptal.php?bilet_id=<?php echo $bilet_id; ?>" class="btn btn-danger">
                            ❌ Biletimi İptal Et
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Yazdırma butonu için gelişmiş fonksiyon
        function yazdirBilet() {
            // Yazdırma öncesi uyarı
            if (confirm('Biletinizi indirmek üzeresiniz. Yazıcınızın hazır olduğundan emin olun.')) {
                window.print();
            }
        }

        // Sayfa yüklendiğinde yazdırma butonuna event listener ekle
        document.addEventListener('DOMContentLoaded', function() {
            const yazdirButonu = document.querySelector('button[onclick="window.print()"]');
            if (yazdirButonu) {
                yazdirButonu.setAttribute('onclick', 'yazdirBilet()');
            }
        });

        // Yazdırma sonrası callback (isteğe bağlı)
        window.onafterprint = function() {
            console.log('Bilet indirildi');
        };
    </script>
</body>
</html>