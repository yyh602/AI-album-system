<?php
// 檢查連接類型並正確關閉
if ($link instanceof PgSQLWrapper) {
    $link->close(); // 使用 PgSQLWrapper 的 close 方法
} elseif ($link instanceof PDO) {
    $link = null; // PDO 連接會自動關閉
} elseif ($link instanceof mysqli) {
    mysqli_close($link);
} else {
    // 如果是其他類型，設為 null
    $link = null;
}
