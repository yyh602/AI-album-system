<?php
// 檢查連接類型並正確關閉
if ($link instanceof PDO) {
    $link = null; // PDO 連接會自動關閉
} else {
    mysqli_close($link);
}
?>
