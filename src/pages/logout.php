<?php
// pages/logout.php
require_once '../includes/config.php';

// Tüm session verilerini temizle
session_destroy();

// Ana sayfaya yönlendir
header('Location: ../index.php');
exit;
?>