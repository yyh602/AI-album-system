<?php
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300); // 允許最多執行 300 秒

require 'vendor/autoload.php';

putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/shining-glyph-465006-i1-8f6de1bb78de.json');

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;

$root = __DIR__;
$uploadDir = "$root/uploads";
$faceDir = "$root/faces";
$groupDir = "$root/group";
$scale = 0.9;

// 清空資料夾
function cleanDirectory($dir) {
    if (!is_dir($dir)) return;
    foreach (glob("$dir/*") as $f) {
        if (is_file($f)) unlink($f);
        if (is_dir($f)) array_map('unlink', glob("$f/*"));
    }
}
if (!is_dir($faceDir)) mkdir($faceDir, 0777, true);
if (!is_dir($groupDir)) mkdir($groupDir, 0777, true);
cleanDirectory($faceDir);
cleanDirectory($groupDir);

// 掃描圖片
$images = array_diff(scandir($uploadDir), ['.', '..']);
$requests = [];
$faceMap = [];
$imgResources = [];

foreach ($images as $imgName) {
    $imgPath = "$uploadDir/$imgName";
    $imgData = file_get_contents($imgPath);
    $image = (new Image())->setContent($imgData);
    $feature = (new Feature())->setType(Feature\Type::FACE_DETECTION);
    $req = (new AnnotateImageRequest())->setImage($image)->setFeatures([$feature]);
    $requests[] = $req;
    $imgResources[] = ['path' => $imgPath, 'name' => $imgName];
}

// 呼叫 Vision API
$client = new ImageAnnotatorClient();
$batchReq = new BatchAnnotateImagesRequest();
$batchReq->setRequests($requests);
$responses = $client->batchAnnotateImages($batchReq)->getResponses();
$client->close();

$faceIndex = 0;

foreach ($responses as $i => $response) {
    if ($response->hasError()) continue;
    $faces = $response->getFaceAnnotations();
    $imgPath = $imgResources[$i]['path'];
    $imgName = $imgResources[$i]['name'];
    $src = imagecreatefromjpeg($imgPath);
    $imgW = imagesx($src);
    $imgH = imagesy($src);

    foreach ($faces as $face) {
        $vertices = $face->getBoundingPoly()->getVertices();
        if (count($vertices) < 2) continue;
        $x1 = $vertices[0]->getX() ?? 0;
        $y1 = $vertices[0]->getY() ?? 0;
        $x2 = $vertices[2]->getX() ?? ($x1 + 1);
        $y2 = $vertices[2]->getY() ?? ($y1 + 1);

        $boxW = $x2 - $x1;
        $boxH = $y2 - $y1;
        $centerX = $x1 + ($boxW / 2);
        $centerY = $y1 + ($boxH / 2);
        $newW = intval($boxW * $scale);
        $newH = intval($boxH * $scale);
        $x = max(intval($centerX - $newW / 2), 0);
        $y = max(intval($centerY - $newH / 2), 0);
        $w = min($newW, $imgW - $x);
        $h = min($newH, $imgH - $y);

        $crop = imagecrop($src, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
        if ($crop) {
            $fname = "face_$faceIndex.jpg";
            $fpath = "$faceDir/$fname";
            imagejpeg($crop, $fpath);
            $faceMap[$fname] = $imgName;
            imagedestroy($crop);
            $faceIndex++;
        }
    }
    imagedestroy($src);
}

file_put_contents("$root/face_map.json", json_encode($faceMap, JSON_PRETTY_PRINT));

// 延遲確保圖片寫入
usleep(500000); // 0.5秒

// 🧠 指定 Python 路徑與環境變數
putenv("PYTHONHOME=C:\\Python313");
putenv("PYTHONPATH=C:\\Users\\1311134007\\AppData\\Roaming\\Python\\Python313\\site-packages");

$python = "C:\\Python313\\python.exe";
$script = "$root/group_faces.py";

// 執行 Python 並擷取輸出
exec("\"$python\" \"$script\" 2>&1", $output);

// 🔍 顯示 Python 執行輸出
echo "<h2>🔍 Python 分群輸出：</h2><pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";

// 📂 顯示分群結果
echo "<h2>人臉分群結果：</h2>";
$groups = glob("$groupDir/person_*");

if (empty($groups)) {
    echo "<p>沒有找到群組。</p>";
} else {
    foreach ($groups as $folder) {
        $folderName = basename($folder);
        echo "<h3>$folderName</h3>";
        foreach (glob("$folder/*") as $img) {
            $relPath = str_replace($root . "/", "", $img);
            echo "<img src='$relPath' width='100' style='margin:5px'>";
        }
    }
}
?>
