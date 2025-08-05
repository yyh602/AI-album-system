<?php
session_start();

$captcha_code = '';
$captcha_length = 5;
$possible_chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

for ($i = 0; $i < $captcha_length; $i++) {
    $captcha_code .= $possible_chars[rand(0, strlen($possible_chars) - 1)];
}
$_SESSION["captcha"] = $captcha_code;

$image = imagecreatetruecolor(120, 40);
$bg_color = imagecolorallocate($image, 255, 255, 255);
$text_color = imagecolorallocate($image, 0, 0, 0);
$noise_color = imagecolorallocate($image, 100, 120, 180);

imagefilledrectangle($image, 0, 0, 120, 40, $bg_color);

for ($i = 0; $i < 80; $i++) {
    imageellipse($image, rand(0, 120), rand(0, 40), 1, 1, $noise_color);
}

$font_size = 5;
imagestring($image, $font_size, 25, 12, $captcha_code, $text_color);

header("Content-type: image/png");
imagepng($image);
imagedestroy($image);
