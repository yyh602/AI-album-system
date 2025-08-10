<?php
// MySQL 資料庫關閉
if ($link instanceof mysqli) {
    mysqli_close($link);
} else {
    // 設為 null
    $link = null;
}