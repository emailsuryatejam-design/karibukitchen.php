<?php
// Generate PWA icons - run once then delete
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$dir = __DIR__ . '/icons';
if (!is_dir($dir)) mkdir($dir, 0755, true);

foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);

    // Background: dark blue (#0f3460)
    $bg = imagecolorallocate($img, 15, 52, 96);
    imagefill($img, 0, 0, $bg);

    // Draw rounded-ish circle accent
    $accent = imagecolorallocate($img, 233, 69, 96); // #e94560
    $cx = $size / 2;
    $cy = $size / 2;
    $radius = $size * 0.35;
    imagefilledellipse($img, (int)$cx, (int)($cy - $size * 0.05), (int)($radius * 2), (int)($radius * 2), $accent);

    // Draw "K" letter
    $white = imagecolorallocate($img, 255, 255, 255);
    $fontSize = $size * 0.35;

    // Use built-in font for simplicity
    $fontNum = 5; // largest built-in font
    $text = "KK";
    $textWidth = imagefontwidth($fontNum) * strlen($text);
    $textHeight = imagefontheight($fontNum);

    // Scale text by drawing it large
    $scale = $fontSize / $textHeight;
    $textImg = imagecreatetruecolor((int)($textWidth), (int)($textHeight));
    $tbg = imagecolorallocate($textImg, 15, 52, 96);
    imagefill($textImg, 0, 0, $tbg);
    imagecolortransparent($textImg, $tbg);
    $tw = imagecolorallocate($textImg, 255, 255, 255);
    imagestring($textImg, $fontNum, 0, 0, $text, $tw);

    // Copy scaled text to main image
    $dw = (int)($textWidth * $scale);
    $dh = (int)($textHeight * $scale);
    $dx = (int)(($size - $dw) / 2);
    $dy = (int)(($size - $dh) / 2) - (int)($size * 0.02);
    imagecopyresized($img, $textImg, $dx, $dy, 0, 0, $dw, $dh, $textWidth, $textHeight);
    imagedestroy($textImg);

    // Add small subtitle bar at bottom
    $barHeight = (int)($size * 0.12);
    $barY = $size - $barHeight - (int)($size * 0.08);
    imagefilledrectangle($img, (int)($size * 0.2), $barY, (int)($size * 0.8), $barY + $barHeight, $accent);

    $file = $dir . "/icon-{$size}.png";
    imagepng($img, $file);
    imagedestroy($img);
    echo "Created: icon-{$size}.png<br>";
}
echo "<hr>All icons generated! You can delete this file now.";
