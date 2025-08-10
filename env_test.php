<?php
echo "<h1>Environment Test</h1>";
echo "<p>PHP is working!</p>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>Environment Variables:</h2>";
echo "<ul>";
echo "<li>DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "</li>";
echo "<li>DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NOT SET') . "</li>";
echo "<li>DB_USER: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "</li>";
echo "<li>DB_PASS: " . (strlen($_ENV['DB_PASS'] ?? '') > 0 ? 'SET' : 'NOT SET') . "</li>";
echo "<li>DB_PORT: " . ($_ENV['DB_PORT'] ?? 'NOT SET') . "</li>";
echo "</ul>";

echo "<p>✅ If you can see this, PHP is working correctly!</p>";
?> 