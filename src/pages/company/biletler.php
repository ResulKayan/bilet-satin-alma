<?php
// pages/company/biletler.php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isCompanyAdmin()) {
    header('Location: ../login.php');
    exit;
}

$user = getCurrentUser();
$company_id = $user['company_id'];

// Biletleri getir
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT 
            tk.id as bilet_id,
            tk.status as bilet_durumu,
            tk.total_price,
            tk.created_at as bilet_tarihi,
            tr.departure_city,
            tr.destination_city,
            tr.departure_time,
            tr.arrival_time,
            bs.seat_number,
            u.full_name as yolcu_adi,
            u.email as yolcu_email
        FROM tickets tk
        JOIN trips tr ON tk.trip_id = tr.id
        JOIN booked_seats bs ON tk.id = bs.ticket_id
        JOIN users u ON tk.user_id = u.id
        WHERE tr.company_id = ?
        ORDER BY tk.created_at DESC
    ");
    $stmt->execute([$company_id]);
    $biletler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $biletler = [];
    $hata = "Biletler yüklenirken hata: " . $e->getMessage();
}

// İstatistikler
try {
    $db = getDB();
    
    // Toplam bilet sayısı
    $stmt = $db->prepare("SELECT COUNT(*) as toplam FROM tickets WHERE trip_id IN (SELECT id FROM trips WHERE company_id = ?)");
    $stmt->execute([$company_id]);
    $toplam_bilet = $stmt->fetch(PDO::FETCH_ASSOC)['toplam'];
    
    // Aktif bilet sayısı
    $stmt = $db->prepare("SELECT COUNT(*) as aktif FROM tickets WHERE status = 'active' AND trip_id IN (SELECT id FROM trips WHERE company_id = ?)");
    $stmt->execute([$company_id]);
    $aktif_bilet = $stmt->fetch(PDO::FETCH_ASSOC)['aktif'];
    
    // Toplam gelir
    $stmt = $db->prepare("SELECT SUM(total_price) as gelir FROM tickets WHERE status = 'active' AND trip_id IN (SELECT id FROM trips WHERE company_id = ?)");
    $stmt->execute([$company_id]);
    $toplam_gelir = $stmt->fetch(PDO::FETCH_ASSOC)['gelir'] / 100;
    
} catch (Exception $e) {
    $istatistik_hata = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biletler - Firma Panel - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #333; }
        .header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .bilet-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; border-radius: 10px; overflow: hidden; }
        .bilet-table th, .bilet-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .bilet-table th { background: #f2f2f2; }
        .status-active { color: green; font-weight: bold; }
        .status-cancelled { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <div class="nav">
        <a href="../../index.php">🏠 Ana Sayfa</a>
        <a href="panel.php">📊 Firma Panel</a>
        <a href="seferler.php">🚌 Seferlerim</a>
        <a href="../logout.php">🚪 Çıkış</a>
    </div>

    <div class="header">
        <h1>🎫 Bilet Satışları</h1>
        <p>Firmanıza ait bilet satışlarını görüntüleyin</p>
    </div>

    <!-- İstatistikler -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $toplam_bilet ?? 0; ?></div>
            <div>Toplam Bilet</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $aktif_bilet ?? 0; ?></div>
            <div>Aktif Bilet</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($toplam_gelir ?? 0, 2); ?> TL</div>
            <div>Toplam Gelir</div>
        </div>
    </div>

    <?php if (isset($hata)): ?>
        <div style="color: red; background: #ffeaea; padding: 15px; border-radius: 5px;">
            <?php echo $hata; ?>
        </div>
    <?php endif; ?>

    <table class="bilet-table">
        <thead>
            <tr>
                <th>Yolcu</th>
                <th>Güzergah</th>
                <th>Kalkış</th>
                <th>Koltuk</th>
                <th>Fiyat</th>
                <th>Durum</th>
                <th>Satış Tarihi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($biletler as $bilet): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($bilet['yolcu_adi']); ?></strong><br>
                        <small><?php echo htmlspecialchars($bilet['yolcu_email']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($bilet['departure_city']); ?> → <?php echo htmlspecialchars($bilet['destination_city']); ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($bilet['departure_time'])); ?></td>
                    <td><strong><?php echo $bilet['seat_number']; ?></strong></td>
                    <td><strong><?php echo number_format($bilet['total_price'] / 100, 2); ?> TL</strong></td>
                    <td class="status-<?php echo $bilet['bilet_durumu']; ?>">
                        <?php echo $bilet['bilet_durumu'] === 'active' ? '✅ Aktif' : '❌ İptal'; ?>
                    </td>
                    <td><?php echo date('d.m.Y H:i', strtotime($bilet['bilet_tarihi'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>