<?php
// 資料庫操作輔助函數

function db_prepare($link, $sql) {
    if ($link instanceof PDO) {
        return $link->prepare($sql);
    } else {
        return mysqli_prepare($link, $sql);
    }
}

function db_bind_param($stmt, $types, ...$params) {
    if ($stmt instanceof PDOStatement) {
        // PDO 不需要 bind_param，直接執行
        return true;
    } else {
        return mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
}

function db_execute($stmt) {
    if ($stmt instanceof PDOStatement) {
        return $stmt->execute();
    } else {
        return mysqli_stmt_execute($stmt);
    }
}

function db_get_result($stmt) {
    if ($stmt instanceof PDOStatement) {
        return $stmt;
    } else {
        return mysqli_stmt_get_result($stmt);
    }
}

function db_fetch_assoc($result) {
    if ($result instanceof PDOStatement) {
        return $result->fetch(PDO::FETCH_ASSOC);
    } else {
        return mysqli_fetch_assoc($result);
    }
}

function db_fetch_all($result, $mode = null) {
    if ($result instanceof PDOStatement) {
        return $result->fetchAll(PDO::FETCH_ASSOC);
    } else {
        return mysqli_fetch_all($result, $mode ?? MYSQLI_ASSOC);
    }
}

function db_num_rows($result) {
    if ($result instanceof PDOStatement) {
        return $result->rowCount();
    } else {
        return mysqli_num_rows($result);
    }
}

function db_stmt_error($stmt) {
    if ($stmt instanceof PDOStatement) {
        $error = $stmt->errorInfo();
        return $error[2] ?? 'Unknown error';
    } else {
        return mysqli_stmt_error($stmt);
    }
}

function db_stmt_affected_rows($stmt) {
    if ($stmt instanceof PDOStatement) {
        return $stmt->rowCount();
    } else {
        return mysqli_stmt_affected_rows($stmt);
    }
}

function db_stmt_close($stmt) {
    if ($stmt instanceof PDOStatement) {
        $stmt->closeCursor();
    } else {
        mysqli_stmt_close($stmt);
    }
}

function db_begin_transaction($link) {
    if ($link instanceof PDO) {
        return $link->beginTransaction();
    } else {
        return mysqli_begin_transaction($link);
    }
}

function db_commit($link) {
    if ($link instanceof PDO) {
        return $link->commit();
    } else {
        return mysqli_commit($link);
    }
}

function db_rollback($link) {
    if ($link instanceof PDO) {
        return $link->rollback();
    } else {
        return mysqli_rollback($link);
    }
}
?> 