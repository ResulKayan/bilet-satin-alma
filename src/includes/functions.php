<?php
require_once 'config.php';

//UUID oluşturma fonksiyonu
function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

//database bağlantı fonksiyonu
function getDB() {
    try {
        //database varmı kontrol et
        if(!file_exists(DB_PATH)) {
            die("❌ Database dosyası bulunamadı: " . DB_PATH);  
        }
        //sqlite bağlan
        $db = new PDO('sqlite:' . DB_PATH);

        //hataları göster
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $db;
    } catch(PDOException $e) {
        die ("❌ Database bağlantı hatası: " . $e->getMessage());
    }
}

/**
 * Firma adından kupon ön eki oluşturur
 */
function firmaAdindanOnEkOlustur($firma_adi) {
    // Türkçe karakterleri İngilizce karşılıklarına çevir
    $firma_adi = tr_to_en($firma_adi);
    
    // Sadece harf ve rakamları al, boşlukları kaldır
    $temiz_adi = preg_replace('/[^A-Za-z0-9]/', '', $firma_adi);
    
    // Maksimum 5 karakter al ve büyük harfe çevir
    $on_ek = substr($temiz_adi, 0, 5);
    
    return strtoupper($on_ek);
}

/**
 * Türkçe karakterleri İngilizce karşılıklarına çevirir
 */
function tr_to_en($text) {
    $turkce = array('ç', 'Ç', 'ğ', 'Ğ', 'ı', 'İ', 'ö', 'Ö', 'ş', 'Ş', 'ü', 'Ü');
    $ingilizce = array('c', 'C', 'g', 'G', 'i', 'I', 'o', 'O', 's', 'S', 'u', 'U');
    
    return str_replace($turkce, $ingilizce, $text);
}

?>