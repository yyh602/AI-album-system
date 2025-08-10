<?php
// MySQL 資料庫操作輔助函數 (簡化版)

function db_prepare($link, $sql) {
    if ($link instanceof mysqli) {
        return mysqli_prepare($link, $sql);
    }
    return false;
}

function db_bind_param($stmt, $types, ...$params) {
    if ($stmt) {
        return mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    return false;
}

function db_execute($stmt) {
    if ($stmt) {
        return mysqli_stmt_execute($stmt);
    }
    return false;
}

function db_get_result($stmt) {
    if ($stmt) {
        return mysqli_stmt_get_result($stmt);
    }
    return false;
}

function db_fetch_assoc($result) {
    if ($result) {
        return mysqli_fetch_assoc($result);
    }
    return false;
}

function db_fetch_all($result, $mode = MYSQLI_ASSOC) {
    if ($result) {
        return mysqli_fetch_all($result, $mode);
    }
    return [];
}

function db_num_rows($result) {
    if ($result) {
        return mysqli_num_rows($result);
    }
    return 0;
}

function db_stmt_error($stmt) {
    if ($stmt) {
        return mysqli_stmt_error($stmt);
    }
    return 'Unknown error';
}

function db_stmt_affected_rows($stmt) {
    if ($stmt) {
        return mysqli_stmt_affected_rows($stmt);
    }
    return 0;
}

function db_stmt_close($stmt) {
    if ($stmt) {
        mysqli_stmt_close($stmt);
    }
}

function db_begin_transaction($link) {
    if ($link instanceof mysqli) {
        return mysqli_begin_transaction($link);
    }
    return false;
}

function db_commit($link) {
    if ($link instanceof mysqli) {
        return mysqli_commit($link);
    }
    return false;
}

function db_rollback($link) {
    if ($link instanceof mysqli) {
        return mysqli_rollback($link);
    }
    return false;
}