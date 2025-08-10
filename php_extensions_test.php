<?php
echo "<h1>PHP 擴展檢查</h1>";

echo "<h2>PHP 版本：</h2>";
echo "PHP 版本：" . phpversion() . "<br>";

echo "<h2>PDO 相關擴展：</h2>";
echo "pdo: " . (extension_loaded('pdo') ? '✅ 已載入' : '❌ 未載入') . "<br>";
echo "pdo_pgsql: " . (extension_loaded('pdo_pgsql') ? '✅ 已載入' : '❌ 未載入') . "<br>";
echo "pdo_mysql: " . (extension_loaded('pdo_mysql') ? '✅ 已載入' : '❌ 未載入') . "<br>";

echo "<h2>PostgreSQL 相關擴展：</h2>";
echo "pgsql: " . (extension_loaded('pgsql') ? '✅ 已載入' : '❌ 未載入') . "<br>";

echo "<h2>所有已載入的擴展：</h2>";
$extensions = get_loaded_extensions();
sort($extensions);
echo "<ul>";
foreach ($extensions as $ext) {
    echo "<li>$ext</li>";
}
echo "</ul>";

echo "<h2>PHP 配置資訊：</h2>";
echo "extension_dir: " . ini_get('extension_dir') . "<br>";
echo "loaded_extensions: " . ini_get('loaded_extensions') . "<br>";

echo "<hr>";
echo "<p><strong>檢查完成時間：</strong>" . date('Y-m-d H:i:s') . "</p>";
?> 