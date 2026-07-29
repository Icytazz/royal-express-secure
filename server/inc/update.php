<?php
function updateDataTable($data)
{
    include 'connection.php';
    require_once __DIR__ . '/schema_allowlist.php';

    $table  = $data['table']   ?? '';
    $keyCol = $data['id_fild'] ?? '';
    $field  = $data['field']   ?? '';
    $id     = $data['id']      ?? '';
    $value  = $data['value']   ?? '';

    assertIdentifiers('update', $table, $keyCol, $field);

    // Single chokepoint for credential writes. Both change-password flows post
    // here with field=password, and so would any future caller, so hashing is
    // applied at the write itself rather than at each call site.
    if ($field === 'password') {
        $value = password_hash($value, PASSWORD_DEFAULT);
    }

    $sql  = "UPDATE `$table` SET `$field` = ? WHERE `$keyCol` = ?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $value, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

function changePageSettings($data)
{
    include 'connection.php';
    require_once __DIR__ . '/schema_allowlist.php';

    $field = $data['field'] ?? '';
    $value = $data['value'] ?? '';

    if (!in_array($field, allowedSettingsFields(), true)) rejectRequest();

    $sql  = "UPDATE `settings` SET `$field` = ?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "s", $value);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

function editSettingImage($data, $img)
{
    include 'connection.php';
    require_once __DIR__ . '/schema_allowlist.php';

    $field = $data['field'] ?? '';

    if (!in_array($field, allowedSettingsFields(), true)) rejectRequest();

    $sql  = "UPDATE `settings` SET `$field` = ?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "s", $img);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

?>