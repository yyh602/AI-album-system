<?php
ini_set('memory_limit', '512M');

require 'vendor/autoload.php';
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/shining-glyph-465006-i1-8f6de1bb78de.json');

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;

// ===== 資料夾設定 =====
$uploadDir = 'uploads';
$faceDir = 'faces';
$margin = 20;
$iouThreshold = 0.65;

if (!is_dir($faceDir)) mkdir($faceDir);

// ===== IOU 判斷重複人臉用 =====
function iou($a, $b) {
    $x1 = max($a['x'], $b['x']);
    $y1 = max($a['y'], $b['y']);
    $x2 = min($a['x'] + $a['width'], $b['x'] + $b['width']);
    $y2 = min($a['y'] + $a['height'], $b['y'] + $b['height']);
    $inter = max(0, $x2 - $x1) * max(0, $y2 - $y1);
    $areaA = $a['width'] * $a['height'];
    $areaB = $b['width'] * $b['height'];
    return $inter / ($areaA + $areaB - $inter);
}

// ===== Vision API 初始化 =====
$client = new ImageAnnotatorClient();
$faceIndex = 0;
$mapFile = fopen("faces/source_map.txt", "w");

foreach (glob("$uploadDir/*.{jpg,jpeg,png}", GLOB_BRACE) as $imgPath) {
    $imgName = basename($imgPath);
    $imageData = file_get_contents($imgPath);
    $imgRes = imagecreatefromstring($imageData);
    $imgW = imagesx($imgRes);
    $imgH = imagesy($imgRes);

    // 呼叫 Vision API
    $image = (new Image())->setContent($imageData);
    $feature = (new Feature())->setType(Feature\Type::FACE_DETECTION);
    $request = (new AnnotateImageRequest())->setImage($image)->setFeatures([$feature]);

    $batchReq = new BatchAnnotateImagesRequest();
    $batchReq->setRequests([$request]);

    $response = $client->batchAnnotateImages($batchReq)->getResponses()[0];
    if ($response->hasError()) continue;

    $allBoxes = [];
    foreach ($response->getFaceAnnotations() as $face) {
        $vertices = $face->getBoundingPoly()->getVertices();
        if (count($vertices) < 2) continue;

        $x1 = $vertices[0]->getX() ?? 0;
        $y1 = $vertices[0]->getY() ?? 0;
        $x2 = $vertices[2]->getX() ?? ($x1 + 1);
        $y2 = $vertices[2]->getY() ?? ($y1 + 1);

        $x = max($x1 - $margin, 0);
        $y = max($y1 - $margin, 0);
        $w = min($x2 - $x1 + 2 * $margin, $imgW - $x);
        $h = min($y2 - $y1 + 2 * $margin, $imgH - $y);

        $allBoxes[] = ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h];
    }

    // 去除重複
    $finalBoxes = [];
    foreach ($allBoxes as $box) {
        $isDup = false;
        foreach ($finalBoxes as $exist) {
            if (iou($box, $exist) > $iouThreshold) {
                $isDup = true;
                break;
            }
        }
        if (!$isDup) $finalBoxes[] = $box;
    }

    // 裁切人臉
    foreach ($finalBoxes as $box) {
        $crop = imagecrop($imgRes, $box);
        if ($crop) {
            $facePath = "$faceDir/face_{$faceIndex}.jpg";
            imagejpeg($crop, $facePath);
            fwrite($mapFile, "face_{$faceIndex}.jpg => $imgName\n");
            imagedestroy($crop);
            $faceIndex++;
        }
    }

    imagedestroy($imgRes);
}

fclose($mapFile);
$client->close();

echo "✅ 共擷取 $faceIndex 張人臉到 faces/\n";
