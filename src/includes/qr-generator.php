<?php
// includes/qr-generator.php

/**
 * Google Charts API kullanarak QR kod oluşturur
 * @param string $data QR kodda kodlanacak veri
 * @param int $size QR kod boyutu (piksel)
 * @return string QR kod image URL
 */
function generateQRCodeWithGoogle($data, $size = 200) {
    $encodedData = urlencode($data);
    return "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl={$encodedData}&choe=UTF-8";
}

/**
 * GD kütüphanesi ile basit bir QR benzeri desen oluşturur
 * @param string $data QR kodda kodlanacak veri
 * @param int $size QR kod boyutu (piksel)
 * @return string Base64 kodlanmış image data
 */
function generateSimpleQRWithGD($data, $size = 200) {
    // Görseli oluştur
    $image = imagecreate($size, $size);
    
    // Renkler
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    
    // Beyaz arka plan
    imagefill($image, 0, 0, $white);
    
    // Verinin MD5 hash'ini kullanarak deterministik bir desen oluştur
    $hash = md5($data);
    $blockSize = $size / 10; // 10x10 grid
    
    for ($i = 0; $i < 10; $i++) {
        for ($j = 0; $j < 10; $j++) {
            $char = $hash[($i * 10 + $j) % 32];
            // Hex karakterini 0-15 arası sayıya çevir, 8'den büyükse kare çiz
            if (hexdec($char) > 8) {
                imagefilledrectangle(
                    $image, 
                    $i * $blockSize, 
                    $j * $blockSize, 
                    ($i + 1) * $blockSize - 1, 
                    ($j + 1) * $blockSize - 1, 
                    $black
                );
            }
        }
    }
    
    // Image'i base64 olarak döndür
    ob_start();
    imagepng($image);
    $imageData = ob_get_clean();
    imagedestroy($image);
    
    return 'data:image/png;base64,' . base64_encode($imageData);
}

/**
 * QR kod oluşturucu ana fonksiyon
 * @param string $data QR kodda kodlanacak veri
 * @param int $size QR kod boyutu (piksel)
 * @param bool $useGoogle true ise Google API, false ise GD kullanır
 * @return string QR kod image URL veya base64 data
 */
function generateQRCode($data, $size = 200, $useGoogle = true) {
    if ($useGoogle) {
        return generateQRCodeWithGoogle($data, $size);
    } else {
        return generateSimpleQRWithGD($data, $size);
    }
}
?>