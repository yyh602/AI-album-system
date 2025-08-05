<?php
// 檢查連接類型並正確關閉
if ($link instanceof PDO) {
    $link = null; // PDO 連接會自動關閉
} elseif ($link instanceof mysqli) {
    mysqli_close($link);
} else {
    // 如果是 PDOWrapper 或其他類型，設為 null
    $link = null;
}
?>
