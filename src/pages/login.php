<?php
// pages/login.php
require_once '../includes/auth.php';
require_once '../includes/config.php';
require_once '../includes/functions.php';

if(isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$errors = [];
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if(empty($email)) $errors[] = "Email gereklidir";
    if(empty($password)) $errors[] = "Şifre gereklidir";
    
    if(empty($errors)) {
        try{
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if($user && password_verify($password,$user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_balance'] = $user['balance'];

                if ($user['role'] === 'company' && isset($user['company_id']) && !empty($user['company_id'])) {
                    $_SESSION['user_company_id'] = $user['company_id'];
                }

                if ($user['role'] === 'admin') {
                    header('Location: ../pages/admin/index.php');
                    exit;
                } elseif ($user['role'] === 'company') {
                    header('Location: ../pages/company/panel.php');
                    exit;
                } else {
                    header('Location: ../index.php');
                    exit;
                }
                
            } else {
                $errors[] = "Email veya şifre hatalı";
            }
        } catch (Exception $e) {
            $errors[] = "Giriş sırasında hata: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - <?php echo SITE_NAME; ?></title>
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
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }
        .login-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .login-header h1 {
            margin: 0;
            font-size: 2.2em;
        }
        .login-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .login-content {
            padding: 40px 30px;
        }
        .form-group {
            margin-bottom: 25px;
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
        .register-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
        }
        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover {
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
    </style>
</head>
<body>
    <div class="nav">
        <a href="../index.php">🏠 Ana Sayfaya Dön</a>
    </div>

    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Giriş Yap</h1>
            <p><?php echo SITE_NAME; ?> sistemine hoş geldiniz</p>
        </div>
        
        <div class="login-content">
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <h4 style="margin: 0 0 10px 0;">❌ Hatalar:</h4>
                    <?php foreach ($errors as $error): ?>
                        <p style="margin: 5px 0;">• <?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">📧 Email Adresi</label>
                    <input type="email" name="email" id="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           placeholder="ornek@email.com" required>
                </div>

                <div class="form-group">
                    <label for="password">🔒 Şifre</label>
                    <input type="password" name="password" id="password" 
                           placeholder="Şifrenizi giriniz" required>
                </div>

                <button type="submit" class="btn">🚀 Giriş Yap</button>
            </form>

            <div class="register-link">
                Hesabınız yok mu? <a href="register.php">Hemen Kayıt Olun</a>
            </div>
        </div>
    </div>
</body>
</html>