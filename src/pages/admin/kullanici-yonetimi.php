<?php
// pages/admin/kullanici-yonetimi.php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Sadece admin erişebilir
requireAdmin();

// Firmaları getir (select box için)
try {
    $db = getDB();
    $stmt = $db->query("SELECT id, name FROM bus_company ORDER BY name");
    $firmalar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $firmalar = [];
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = $_POST['islem'] ?? '';
    
    try {
        $db = getDB();
        
        if ($islem === 'kullanici_ekle') {
            // Yeni kullanıcı ekle
            $user_id = generateUUID();
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $balance = floatval($_POST['balance'] ?? 0);
            $company_id = $_POST['company_id'] ?? null;
            
            // Validasyon
            $errors = [];
            if (empty($full_name)) $errors[] = "Ad soyad gereklidir";
            if (empty($email)) $errors[] = "Email gereklidir";
            if (empty($password)) $errors[] = "Şifre gereklidir";
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Geçerli bir email adresi girin";
            if (strlen($password) < 6) $errors[] = "Şifre en az 6 karakter olmalıdır";
            
            // Firma admini için firma seçimi kontrolü
            if ($role === 'company' && empty($company_id)) {
                $errors[] = "Firma admini için firma seçimi gereklidir";
            }
            
            // Email kontrolü
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Bu email adresi zaten kayıtlı";
            }
            
            if (empty($errors)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Firma admini ise company_id'yi de ekle
                if ($role === 'company') {
                    $stmt = $db->prepare("INSERT INTO users (id, full_name, email, password, role, balance, company_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $full_name, $email, $hashed_password, $role, $balance, $company_id]);
                } else {
                    $stmt = $db->prepare("INSERT INTO users (id, full_name, email, password, role, balance) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $full_name, $email, $hashed_password, $role, $balance]);
                }
                
                $basari = "✅ Kullanıcı başarıyla oluşturuldu: " . htmlspecialchars($full_name);
            } else {
                $hata = "• " . implode("<br>• ", $errors);
            }
            
        } elseif ($islem === 'rol_degistir' && isset($_POST['user_id']) && isset($_POST['yeni_rol'])) {
            // Kullanıcı rolünü değiştir
            $user_id = $_POST['user_id'];
            $yeni_rol = $_POST['yeni_rol'];
            
            $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$yeni_rol, $user_id]);
            $basari = "✅ Kullanıcı rolü başarıyla güncellendi";
            
        } elseif ($islem === 'bakiye_ayarla' && isset($_POST['user_id']) && isset($_POST['yeni_bakiye'])) {
            // Kullanıcı bakiyesini ayarla (ekleme veya direkt değiştirme)
            $user_id = $_POST['user_id'];
            $yeni_bakiye = floatval($_POST['yeni_bakiye']);
            $islem_tipi = $_POST['islem_tipi']; // 'ekle' veya 'ayarla'
            
            if ($yeni_bakiye < 0) {
                $hata = "❌ Geçerli bir bakiye giriniz";
            } else {
                if ($islem_tipi === 'ekle') {
                    // Mevcut bakiyeye ekle
                    $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $stmt->execute([$yeni_bakiye, $user_id]);
                    $basari = "✅ Kullanıcı bakiyesine $yeni_bakiye TL eklendi";
                } else {
                    // Bakiyeyi direkt değiştir
                    $stmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
                    $stmt->execute([$yeni_bakiye, $user_id]);
                    $basari = "✅ Kullanıcı bakiyesi $yeni_bakiye TL olarak ayarlandı";
                }
            }
            
        } elseif ($islem === 'kullanici_sil' && isset($_POST['user_id'])) {
            // Kullanıcı sil
            $user_id = $_POST['user_id'];
            
            // Önce bu kullanıcıya ait bilet var mı kontrol et
            $stmt = $db->prepare("SELECT COUNT(*) as bilet_sayisi FROM tickets WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $bilet_sayisi = $stmt->fetch(PDO::FETCH_ASSOC)['bilet_sayisi'];
            
            if ($bilet_sayisi > 0) {
                $hata = "❌ Bu kullanıcıya ait $bilet_sayisi bilet bulunuyor. Önce biletleri silmelisiniz.";
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $basari = "✅ Kullanıcı başarıyla silindi";
            }
        }
        
    } catch (Exception $e) {
        $hata = "İşlem sırasında hata: " . $e->getMessage();
    }
}

// Kullanıcıları getir
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT u.*, 
               bc.name as firma_adi,
               (SELECT COUNT(*) FROM tickets WHERE user_id = u.id) as bilet_sayisi,
               (SELECT COUNT(*) FROM user_coupons WHERE user_id = u.id) as kupon_sayisi
        FROM users u 
        LEFT JOIN bus_company bc ON u.company_id = bc.id
        ORDER BY u.created_at DESC
    ");
    $kullanicilar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $kullanicilar = [];
    $hata_liste = "Kullanıcılar yüklenirken hata: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Admin Panel - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .admin-header { background: #2c3e50; color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #333; }
        .form-container, .list-container { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .success { color: green; background: #eaffea; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffeaea; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .kullanici-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .kullanici-table th, .kullanici-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .kullanici-table th { background: #f2f2f2; }
        .kullanici-table tr:hover { background: #f9f9f9; }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        .btn { padding: 8px 15px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-size: 12px; }
        .btn:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #219a52; }
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }
        .role-user { color: #3498db; font-weight: bold; }
        .role-company { color: #e67e22; font-weight: bold; }
        .role-admin { color: #e74c3c; font-weight: bold; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 10% auto; padding: 20px; border-radius: 10px; width: 450px; max-width: 90%; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; }
        .quick-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
        .stat-card { background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stat-number { font-size: 1.8em; font-weight: bold; color: #2c3e50; }
        .bakiye-info { background: #e8f4f8; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .bakiye-tahmin { background: #f0f8f0; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .firma-secim { transition: all 0.3s ease; }
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
        <h1>👥 Kullanıcı Yönetimi</h1>
        <p>Sistemdeki tüm kullanıcıları yönetin ve yeni kullanıcılar oluşturun</p>
    </div>

    <?php if (isset($basari)): ?>
        <div class="success"><?php echo $basari; ?></div>
    <?php endif; ?>

    <?php if (isset($hata)): ?>
        <div class="error"><?php echo $hata; ?></div>
    <?php endif; ?>

    <!-- Hızlı İstatistikler -->
    <div class="quick-stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($kullanicilar); ?></div>
            <div>Toplam Kullanıcı</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count(array_filter($kullanicilar, fn($k) => $k['role'] === 'user')); ?></div>
            <div>Normal Kullanıcı</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count(array_filter($kullanicilar, fn($k) => $k['role'] === 'company')); ?></div>
            <div>Firma Admin</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count(array_filter($kullanicilar, fn($k) => $k['role'] === 'admin')); ?></div>
            <div>Sistem Admin</div>
        </div>
    </div>

    <!-- Yeni Kullanıcı Ekleme Formu -->
    <div class="form-container">
        <h2>➕ Yeni Kullanıcı Oluştur</h2>
        <form method="POST">
            <input type="hidden" name="islem" value="kullanici_ekle">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="full_name">Ad Soyad *</label>
                    <input type="text" name="full_name" id="full_name" required 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" name="email" id="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="password">Şifre *</label>
                    <input type="password" name="password" id="password" required 
                           minlength="6" placeholder="En az 6 karakter">
                </div>
                
                <div class="form-group">
                    <label for="balance">Başlangıç Bakiyesi (TL)</label>
                    <input type="number" name="balance" id="balance" 
                           value="<?php echo htmlspecialchars($_POST['balance'] ?? '800'); ?>" 
                           min="0" step="0.01">
                </div>
            </div>

            <div class="form-group">
                <label for="role">Kullanıcı Rolü *</label>
                <select name="role" id="role" required onchange="toggleFirmaSelection()">
                    <option value="user" <?php echo ($_POST['role'] ?? '') === 'user' ? 'selected' : ''; ?>>👤 Normal Kullanıcı</option>
                    <option value="company" <?php echo ($_POST['role'] ?? '') === 'company' ? 'selected' : ''; ?>>🚌 Firma Admin</option>
                    <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>⚙️ Sistem Admin</option>
                </select>
            </div>

            <!-- YENİ: Firma Seçimi (Sadece Firma Admini seçilince görünecek) -->
            <div class="form-group firma-secim" id="firmaSecimDiv" style="display: none;">
                <label for="company_id">Firma *</label>
                <select name="company_id" id="company_id">
                    <option value="">Firma Seçin</option>
                    <?php foreach ($firmalar as $firma): ?>
                        <option value="<?php echo $firma['id']; ?>" 
                            <?php echo ($_POST['company_id'] ?? '') === $firma['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($firma['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-success">✅ Kullanıcı Oluştur</button>
        </form>
    </div>

    <!-- Kullanıcı Listesi -->
    <div class="list-container">
        <h2>📋 Kullanıcı Listesi (<?php echo count($kullanicilar); ?> kullanıcı)</h2>
        
        <?php if (isset($hata_liste)): ?>
            <div class="error"><?php echo $hata_liste; ?></div>
        <?php endif; ?>

        <?php if (empty($kullanicilar)): ?>
            <p>Henüz kullanıcı bulunmamaktadır.</p>
        <?php else: ?>
            <table class="kullanici-table">
                <thead>
                    <tr>
                        <th>Ad Soyad</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Bakiye</th>
                        <th>Firma</th>
                        <th>Bilet Sayısı</th>
                        <th>Kayıt Tarihi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kullanicilar as $kullanici): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($kullanici['full_name'] ?: '-'); ?></strong></td>
                            <td><?php echo htmlspecialchars($kullanici['email']); ?></td>
                            <td>
                                <span class="role-<?php echo $kullanici['role']; ?>">
                                    <?php 
                                    $roller = [
                                        'user' => '👤 Kullanıcı',
                                        'company' => '🚌 Firma Admin',
                                        'admin' => '⚙️ Sistem Admin'
                                    ];
                                    echo $roller[$kullanici['role']] ?? $kullanici['role'];
                                    ?>
                                </span>
                            </td>
                            <td><strong><?php echo number_format($kullanici['balance'], 2); ?> TL</strong></td>
                            <td><?php echo htmlspecialchars($kullanici['firma_adi'] ?: '-'); ?></td>
                            <td><?php echo $kullanici['bilet_sayisi']; ?> bilet</td>
                            <td><?php echo date('d.m.Y H:i', strtotime($kullanici['created_at'])); ?></td>
                            <td class="action-buttons">
                                <!-- Rol Değiştir Butonu -->
                                <button class="btn" onclick="openRolModal('<?php echo $kullanici['id']; ?>', '<?php echo $kullanici['role']; ?>', '<?php echo htmlspecialchars($kullanici['full_name'] ?: $kullanici['email']); ?>')">
                                    🔄 Rol
                                </button>
                                
                                <!-- Bakiye Yönetimi Butonu -->
                                <button class="btn btn-success" onclick="openBakiyeModal(
                                    '<?php echo $kullanici['id']; ?>', 
                                    '<?php echo htmlspecialchars($kullanici['full_name'] ?: $kullanici['email']); ?>',
                                    <?php echo $kullanici['balance']; ?>
                                )">
                                    💰 Bakiye
                                </button>
                                
                                <!-- Sil Butonu -->
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?')">
                                    <input type="hidden" name="islem" value="kullanici_sil">
                                    <input type="hidden" name="user_id" value="<?php echo $kullanici['id']; ?>">
                                    <button type="submit" class="btn-danger">🗑️ Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Rol Değiştir Modal -->
    <div id="rolModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeRolModal()">&times;</span>
            <h3>🔄 Kullanıcı Rolünü Değiştir</h3>
            <form method="POST" id="rolForm">
                <input type="hidden" name="islem" value="rol_degistir">
                <input type="hidden" name="user_id" id="rol_user_id">
                
                <div class="form-group">
                    <label for="yeni_rol">Yeni Rol:</label>
                    <select name="yeni_rol" id="yeni_rol" required>
                        <option value="user">👤 Kullanıcı</option>
                        <option value="company">🚌 Firma Admin</option>
                        <option value="admin">⚙️ Sistem Admin</option>
                    </select>
                </div>
                
                <p id="rolBilgi"></p>
                
                <button type="submit" class="btn">✅ Rolü Değiştir</button>
                <button type="button" class="btn btn-danger" onclick="closeRolModal()">❌ İptal</button>
            </form>
        </div>
    </div>

    <!-- Bakiye Yönetimi Modal -->
    <div id="bakiyeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeBakiyeModal()">&times;</span>
            <h3>💰 Kullanıcı Bakiyesi Yönetimi</h3>
            <form method="POST" id="bakiyeForm">
                <input type="hidden" name="islem" value="bakiye_ayarla">
                <input type="hidden" name="user_id" id="bakiye_user_id">
                
                <div class="form-group">
                    <label for="islem_tipi">İşlem Tipi:</label>
                    <select name="islem_tipi" id="islem_tipi" required>
                        <option value="ekle">➕ Mevcut Bakiyeye Ekle</option>
                        <option value="ayarla">✏️ Bakiyeyi Direkt Ayarla</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="yeni_bakiye" id="bakiye_label">Eklenecek Miktar (TL):</label>
                    <input type="number" name="yeni_bakiye" id="yeni_bakiye" min="0" max="100000" step="0.01" required>
                </div>
                
                <div class="bakiye-info">
                    <strong>Mevcut Bakiye:</strong> <span id="mevcutBakiyeDeger">0.00 TL</span>
                </div>
                
                <div class="bakiye-tahmin" id="yeniBakiyeTahmini" style="display: none;">
                    <strong>Yeni Bakiye:</strong> <span id="yeniBakiyeDeger">0.00 TL</span>
                </div>
                
                <p id="bakiyeBilgi"></p>
                
                <button type="submit" class="btn btn-success">✅ Bakiye İşlemini Uygula</button>
                <button type="button" class="btn btn-danger" onclick="closeBakiyeModal()">❌ İptal</button>
            </form>
        </div>
    </div>

    <div style="margin-top: 30px;">
        <a href="index.php" class="btn">← Admin Paneline Dön</a>
    </div>

    <script>
        // Firma seçimini göster/gizle
        function toggleFirmaSelection() {
            const roleSelect = document.getElementById('role');
            const firmaSecimDiv = document.getElementById('firmaSecimDiv');
            const companySelect = document.getElementById('company_id');
            
            if (roleSelect.value === 'company') {
                firmaSecimDiv.style.display = 'block';
                companySelect.setAttribute('required', 'required');
            } else {
                firmaSecimDiv.style.display = 'none';
                companySelect.removeAttribute('required');
            }
        }

        // Rol Modal İşlemleri
        function openRolModal(userId, currentRole, userName) {
            document.getElementById('rol_user_id').value = userId;
            document.getElementById('yeni_rol').value = currentRole;
            document.getElementById('rolBilgi').innerHTML = '<strong>Kullanıcı:</strong> ' + userName + '<br><strong>Mevcut Rol:</strong> ' + getRoleName(currentRole);
            document.getElementById('rolModal').style.display = 'block';
        }

        function closeRolModal() {
            document.getElementById('rolModal').style.display = 'none';
        }

        // Bakiye Modal İşlemleri
        function openBakiyeModal(userId, userName, mevcutBakiye) {
            document.getElementById('bakiye_user_id').value = userId;
            document.getElementById('mevcutBakiyeDeger').textContent = mevcutBakiye.toFixed(2) + ' TL';
            document.getElementById('bakiyeBilgi').innerHTML = '<strong>Kullanıcı:</strong> ' + userName;
            document.getElementById('bakiyeModal').style.display = 'block';
            
            // Input'u sıfırla
            document.getElementById('yeni_bakiye').value = '';
            document.getElementById('yeniBakiyeTahmini').style.display = 'none';
        }

        function closeBakiyeModal() {
            document.getElementById('bakiyeModal').style.display = 'none';
        }

        // İşlem tipi değiştiğinde label'ı güncelle
        document.getElementById('islem_tipi').addEventListener('change', function() {
            const label = document.getElementById('bakiye_label');
            if (this.value === 'ekle') {
                label.textContent = 'Eklenecek Miktar (TL):';
            } else {
                label.textContent = 'Yeni Bakiye (TL):';
            }
            updateBakiyeTahmini();
        });

        // Bakiye input'u değiştiğinde tahmini güncelle
        document.getElementById('yeni_bakiye').addEventListener('input', updateBakiyeTahmini);

        function updateBakiyeTahmini() {
            const islemTipi = document.getElementById('islem_tipi').value;
            const mevcutBakiyeText = document.getElementById('mevcutBakiyeDeger').textContent;
            const mevcutBakiye = parseFloat(mevcutBakiyeText) || 0;
            const yeniDeger = parseFloat(document.getElementById('yeni_bakiye').value) || 0;
            const tahminDiv = document.getElementById('yeniBakiyeTahmini');
            const tahminDeger = document.getElementById('yeniBakiyeDeger');
            
            if (yeniDeger > 0) {
                let yeniBakiye;
                if (islemTipi === 'ekle') {
                    yeniBakiye = mevcutBakiye + yeniDeger;
                    tahminDeger.textContent = mevcutBakiye.toFixed(2) + ' TL + ' + yeniDeger.toFixed(2) + ' TL = ' + yeniBakiye.toFixed(2) + ' TL';
                } else {
                    yeniBakiye = yeniDeger;
                    tahminDeger.textContent = yeniBakiye.toFixed(2) + ' TL (Mevcut: ' + mevcutBakiye.toFixed(2) + ' TL)';
                }
                tahminDiv.style.display = 'block';
            } else {
                tahminDiv.style.display = 'none';
            }
        }

        // Rol isimlerini getir
        function getRoleName(role) {
            const roles = {
                'user': '👤 Kullanıcı',
                'company': '🚌 Firma Admin', 
                'admin': '⚙️ Sistem Admin'
            };
            return roles[role] || role;
        }

        // Modal dışına tıklayınca kapat
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeRolModal();
                closeBakiyeModal();
            }
        }

        // Şifre güçlendirme uyarısı
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strength = document.getElementById('password-strength');
            
            if (!strength) {
                const strengthDiv = document.createElement('div');
                strengthDiv.id = 'password-strength';
                strengthDiv.style.marginTop = '5px';
                strengthDiv.style.fontSize = '0.8em';
                this.parentNode.appendChild(strengthDiv);
            }
            
            const strengthDiv = document.getElementById('password-strength');
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
            } else if (password.length < 6) {
                strengthDiv.innerHTML = '❌ Şifre çok kısa (en az 6 karakter)';
                strengthDiv.style.color = '#e74c3c';
            } else if (password.length < 8) {
                strengthDiv.innerHTML = '⚠️ Şifre orta seviye';
                strengthDiv.style.color = '#f39c12';
            } else {
                strengthDiv.innerHTML = '✅ Şifre güçlü';
                strengthDiv.style.color = '#27ae60';
            }
        });

        // Sayfa yüklendiğinde event listener'ları ekle
        document.addEventListener('DOMContentLoaded', function() {
            // Firma seçimini başlangıçta ayarla
            toggleFirmaSelection();
            
            // Rol değiştiğinde firma seçimini güncelle
            document.getElementById('role').addEventListener('change', toggleFirmaSelection);
            
            // Bakiye modalı için event listener'lar
            const islemTipiSelect = document.getElementById('islem_tipi');
            const bakiyeInput = document.getElementById('yeni_bakiye');
            
            if (islemTipiSelect) {
                islemTipiSelect.addEventListener('change', updateBakiyeTahmini);
            }
            if (bakiyeInput) {
                bakiyeInput.addEventListener('input', updateBakiyeTahmini);
            }
        });
    </script>
</body>
</html>