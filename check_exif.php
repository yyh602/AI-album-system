<?php
header('Content-Type: application/json');

$extensions = [
    'exif' => extension_loaded('exif'),
    'gd' => extension_loaded('gd'),
    'imagick' => extension_loaded('imagick'),
    'fileinfo' => extension_loaded('fileinfo')
];

$functions = [
    'exif_read_data' => function_exists('exif_read_data'),
    'getimagesize' => function_exists('getimagesize'),
    'imagecreatefromjpeg' => function_exists('imagecreatefromjpeg')
];

echo json_encode([
    'extensions' => $extensions,
    'functions' => $functions,
    'php_version' => PHP_VERSION
]);
?> 