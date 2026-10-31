<?php
// includes/captcha.php - Secure Alphanumeric CAPTCHA Image Generator
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Anti-caching HTTP headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Generate 5-character random alphanumeric string (avoiding ambiguous chars like 0, O, 1, I, L)
$allowed_chars = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
$captcha_length = 5;
$captcha_code = '';
$max_idx = strlen($allowed_chars) - 1;

for ($i = 0; $i < $captcha_length; $i++) {
    $captcha_code .= $allowed_chars[random_int(0, $max_idx)];
}

// Store in session for server-side verification (both registration and forgot-password)
$_SESSION['student_reg_captcha'] = $captcha_code;
$_SESSION['captcha_code'] = $captcha_code;

// Image dimensions
$width = 160;
$height = 46;

if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
    header('Content-Type: image/png');
    $image = imagecreatetruecolor($width, $height);

    // Color definitions matching system theme
    $bg_color = imagecolorallocate($image, 251, 243, 222); // Cream #fbf3de
    $border_color = imagecolorallocate($image, 217, 119, 6); // Amber border
    $text_colors = [
        imagecolorallocate($image, 100, 25, 20),   // Primary Maroon #641914
        imagecolorallocate($image, 30, 41, 59),    // Slate Dark #1e293b
        imagecolorallocate($image, 180, 83, 9),    // Warm Amber #b45309
        imagecolorallocate($image, 69, 15, 12)     // Dark Burgundy
    ];
    $noise_colors = [
        imagecolorallocate($image, 230, 200, 160),
        imagecolorallocate($image, 220, 210, 190),
        imagecolorallocate($image, 200, 160, 140)
    ];

    // Fill background
    imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);

    // Draw background noise grid/lines
    for ($i = 0; $i < 6; $i++) {
        $nc = $noise_colors[array_rand($noise_colors)];
        imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $nc);
    }

    // Draw noise dots
    for ($i = 0; $i < 120; $i++) {
        $nc = $noise_colors[array_rand($noise_colors)];
        imagesetpixel($image, random_int(0, $width), random_int(0, $height), $nc);
    }

    // Draw border
    imagerectangle($image, 0, 0, $width - 1, $height - 1, $border_color);

    // Draw characters with spacing and slight vertical offset
    $char_spacing = intval($width / ($captcha_length + 1));
    for ($i = 0; $i < $captcha_length; $i++) {
        $char = $captcha_code[$i];
        $color = $text_colors[$i % count($text_colors)];
        $x = 18 + ($i * 26);
        $y = random_int(12, 16);

        // GD built-in font size 5 (largest built-in bitmap font)
        // Double-draw with 1px shadow for bold effect and readability
        $shadow_color = imagecolorallocate($image, 230, 210, 180);
        imagestring($image, 5, $x + 1, $y + 1, $char, $shadow_color);
        imagestring($image, 5, $x, $y, $char, $color);
    }

    // A subtle wavy foreground line through characters
    $line_color = imagecolorallocate($image, 140, 60, 40);
    $prev_x = 5;
    $prev_y = random_int(15, 30);
    for ($x = 15; $x < $width - 5; $x += 15) {
        $next_y = random_int(15, 30);
        imageline($image, $prev_x, $prev_y, $x, $next_y, $line_color);
        $prev_x = $x;
        $prev_y = $next_y;
    }

    imagepng($image);
    imagedestroy($image);
    exit;
} else {
    // Pure SVG Fallback if GD is ever disabled
    header('Content-Type: image/svg+xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    ?>
    <svg xmlns="http://www.w3.org/2000/svg" width="<?= $width ?>" height="<?= $height ?>" viewBox="0 0 <?= $width ?> <?= $height ?>">
      <rect width="100%" height="100%" fill="#fbf3de" stroke="#d97706" stroke-width="1.5" rx="6"/>
      <path d="M 10 25 Q 50 10 90 28 T 150 20" fill="none" stroke="#e0c29f" stroke-width="2"/>
      <text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle" font-family="monospace, sans-serif" font-size="22" font-weight="900" letter-spacing="8" fill="#641914">
        <?= htmlspecialchars($captcha_code) ?>
      </text>
    </svg>
    <?php
    exit;
}
