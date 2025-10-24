<?php
// includes/config.php

// 1. Session başlat
session_start();

// 2. Database dosya yolu - DÜZELTTİM
define('DB_PATH', __DIR__ . '/../database/bilet.db');

// 3. Site bilgileri
define('SITE_NAME', 'Bilet Sistemi');

// 4. Hata gösterimi
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 5. Türkiye saat dilimini ayarla
date_default_timezone_set('Europe/Istanbul');
?>