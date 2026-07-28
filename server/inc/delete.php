<?php

function deleteDataTables($data)
{
    include 'connection.php';
    require_once __DIR__ . '/schema_allowlist.php';

    $table  = $data['table']   ?? '';
    $keyCol = $data['id_fild'] ?? '';
    $id     = $data['id']      ?? '';

    assertIdentifiers('soft_delete', $table, $keyCol);

    // Identifiers validated above; the value is bound.
    $sql  = "UPDATE `$table` SET is_deleted = 1 WHERE `$keyCol` = ?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "s", $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

function permanantDeleteDataTable($data)
{
    include 'connection.php';
    require_once __DIR__ . '/schema_allowlist.php';

    $table  = $data['table']   ?? '';
    $keyCol = $data['id_fild'] ?? '';
    $id     = $data['id']      ?? '';

    assertIdentifiers('hard_delete', $table, $keyCol);

    $sql  = "DELETE FROM `$table` WHERE `$keyCol` = ?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "s", $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}