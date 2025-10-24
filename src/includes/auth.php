<?php
// includes/auth.php
require_once 'config.php';

//kullanıcı giriş yapmışmı?
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

//Giriş yapmış kullanıcı bilgilerini getir
function getCurrentUser() {
    if(isLoggedIn()) {
        $userData = [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role'],
            'balance' => $_SESSION['user_balance'] ?? 0
        ];

        // SADECE firma admini için company_id'yi ekle
        if ($_SESSION['user_role'] === 'company' && isset($_SESSION['user_company_id'])) {
            $userData['company_id'] = $_SESSION['user_company_id'];
        }

        return $userData;
    }
    return null;
}

// Sadece giriş yapan kullanıcılar erişebilir
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../pages/login.php');
        exit;
    }
}

// Sadece admin erişebilir
function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        die("❌ Bu sayfaya erişim yetkiniz yok! Sadece adminler erişebilir.");
    }
}

// Sadece firma admini erişebilir
function requireCompany() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'company') {
        die("❌ Bu sayfaya erişim yetkiniz yok! Sadece firmalar erişebilir.");
    }
    // Ek güvenlik: company_id kontrolü
    if (!isset($_SESSION['user_company_id']) || empty($_SESSION['user_company_id'])) {
        die("❌ Firma bilgileriniz eksik! Lütfen yöneticiyle iletişime geçin.");
    }
}

// Kullanıcı user mi?
function isUser() {
    return isLoggedIn() && $_SESSION['user']['role'] === 'user';
}

// Kullanıcı admin mi?
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

// Kullanıcı firma admini mi?
function isCompanyAdmin() {
    return isLoggedIn() && 
           $_SESSION['user_role'] === 'company' && 
           isset($_SESSION['user_company_id']) && 
           !empty($_SESSION['user_company_id']);
}

function canPurchaseTickets() {
    return isUser(); // Sadece user rolündekiler bilet alabilir
}
?>