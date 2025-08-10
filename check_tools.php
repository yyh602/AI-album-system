<?php
header('Content-Type: application/json');

$tools = [];

// 檢查 exiftool
$exiftoolPath = "exiftool";
if (!file_exists($exiftoolPath)) {
    $exiftoolPath = "/usr/bin/exiftool";
}
$tools['exiftool'] = [
    'path' => $exiftoolPath,
    'exists' => file_exists($exiftoolPath),
    'version' => null
];

if ($tools['exiftool']['exists']) {
    $output = [];
    exec("$exiftoolPath -ver", $output, $returnCode);
    if ($returnCode === 0 && !empty($output)) {
        $tools['exiftool']['version'] = trim($output[0]);
    }
}

// 檢查 ImageMagick
$magickPath = "magick";
if (!file_exists($magickPath)) {
    $magickPath = "/usr/bin/magick";
}
$tools['imagemagick'] = [
    'path' => $magickPath,
    'exists' => file_exists($magickPath),
    'version' => null
];

if ($tools['imagemagick']['exists']) {
    $output = [];
    exec("$magickPath -version", $output, $returnCode);
    if ($returnCode === 0 && !empty($output)) {
        $tools['imagemagick']['version'] = trim($output[0]);
    }
}

// 檢查 convert 命令
$convertPath = "convert";
if (!file_exists($convertPath)) {
    $convertPath = "/usr/bin/convert";
}
$tools['convert'] = [
    'path' => $convertPath,
    'exists' => file_exists($convertPath),
    'version' => null
];

if ($tools['convert']['exists']) {
    $output = [];
    exec("$convertPath -version", $output, $returnCode);
    if ($returnCode === 0 && !empty($output)) {
        $tools['convert']['version'] = trim($output[0]);
    }
}

echo json_encode($tools, JSON_PRETTY_PRINT);
?> 