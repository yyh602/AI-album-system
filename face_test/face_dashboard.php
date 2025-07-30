<?php
$faces = glob("faces/*.jpg");
$groups = glob("group/people_*");

// 若按下「偵測人臉」按鈕，執行 test_vision.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_time_limit(120); // 延長執行時間
    exec("C:\\Python313\\python.exe test_vision.php", $output);
    header("Location: " . $_SERVER['PHP_SELF']); // 重新導向避免重新送出表單
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>人臉辨識</title>
</head>
<body>
    <h1>人臉辨識</h1>

    <form method="POST">
        <button type="submit">Vision api偵測人臉</button>
    </form>

    <h2>大頭照</h2>
    <div style="display: flex; flex-wrap: wrap;">
        <?php foreach ($faces as $face): ?>
            <img src="<?= $face ?>" width="100" style="margin:5px;">
        <?php endforeach; ?>
    </div>

    <h2>比對結果</h2>
    <?php if (empty($groups)): ?>
        <p>尚未產生任何分群</p>
    <?php else: ?>
        <?php foreach ($groups as $group): ?>
            <h3><?= basename($group) ?></h3>
            <div style="display: flex; flex-wrap: wrap;">
                <?php foreach (glob("$group/*.jpg") as $img): ?>
                    <img src="<?= $img ?>" width="100" style="margin:5px;">
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
