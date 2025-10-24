<?php
// pages/register.php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($name)) $errors[] = "İsim gereklidir";
    if (empty($surname)) $errors[] = "Soyisim gereklidir";
    if (empty($email)) $errors[] = "Email gereklidir";
    if (empty($password)) $errors[] = "Şifre gereklidir";
    if (empty($password_confirm)) $errors[] = "Şifre tekrarı gereklidir";
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Geçerli bir email adresi girin";
    }
    
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = "Şifre en az 6 karakter olmalıdır";
    }
    
    if ($password !== $password_confirm) {
        $errors[] = "Şifreler eşleşmiyor";
    }
    
    if (empty($errors)) {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $errors[] = "Bu email adresi zaten kayıtlı";
            } else {
                $user_id = generateUUID();
                $full_name = $name . " " . $surname;
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO users (id, full_name, email, role, password, balance) VALUES (?, ?, ?, 'user', ?, 800.00)");
                $stmt->execute([$user_id, $full_name, $email, $hashed_password]);
                
                $success = true;
            }
            
        } catch (Exception $e) {
            $errors[] = "Kayıt sırasında hata: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - <?php echo SITE_NAME; ?></title>
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
            padding: 0; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
        }
        .register-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .register-header h1 {
            margin: 0;
            font-size: 2.2em;
        }
        .register-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .register-content {
            padding: 40px 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            box-sizing: border-box;
        }
        input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--danger-color);
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--success-color);
            text-align: center;
        }
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
        }
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .nav {
            position: absolute;
            top: 20px;
            left: 20px;
        }
        .nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 5px;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s;
        }
        .nav a:hover {
            background: rgba(255,255,255,0.3);
        }
        .name-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 480px) {
            .name-fields {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="nav">
        <a href="../index.php">🏠 Ana Sayfaya Dön</a>
    </div>

    <div class="register-container">
        <div class="register-header">
            <h1>📝 Kayıt Ol</h1>
            <p>Yeni hesap oluşturun ve hemen bilet almaya başlayın</p>
        </div>
        
        <div class="register-content">
            <?php if ($success): ?>
                <div class="success">
                    <h3 style="margin: 0 0 15px 0;">✅ Kayıt Başarılı!</h3>
                    <p style="margin: 0 0 15px 0;">Hesabınız başarıyla oluşturuldu. Giriş yapabilirsiniz.</p>
                    <a href="login.php" class="btn" style="text-decoration: none; display: inline-block; width: auto; padding: 10px 20px;">
                        🔐 Giriş Yap
                    </a>
                </div>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="error">
                        <h4 style="margin: 0 0 10px 0;">❌ Hatalar:</h4>
                        <?php foreach ($errors as $error): ?>
                            <p style="margin: 5px 0;">• <?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="name-fields">
                        <div class="form-group">
                            <label for="name">👤 İsim</label>
                            <input type="text" name="name" id="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                   placeholder="Adınız" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="surname">👥 Soyisim</label>
                            <input type="text" name="surname" id="surname" 
                                   value="<?php echo htmlspecialchars($_POST['surname'] ?? ''); ?>" 
                                   placeholder="Soyadınız" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">📧 Email Adresi</label>
                        <input type="email" name="email" id="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               placeholder="ornek@email.com" required>
                    </div>

                    <div class="form-group">
                        <label for="password">🔒 Şifre</label>
                        <input type="password" name="password" id="password" 
                               placeholder="En az 6 karakter" required>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">🔁 Şifre Tekrarı</label>
                        <input type="password" name="password_confirm" id="password_confirm" 
                               placeholder="Şifrenizi tekrar girin" required>
                    </div>

                    <button type="submit" class="btn">🚀 Kayıt Ol</button>
                </form>

                <div class="login-link">
                    Zaten hesabınız var mı? <a href="login.php">Giriş Yapın</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>