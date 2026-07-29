<?php
/**
 * Parameterised query helpers.
 *
 * Values are bound rather than interpolated, so user input is always treated as
 * data and can never alter the structure of the statement.
 */

/** Run a parameterised query and return the result set. */
function dbQuery($con, string $sql, string $types = '', array $params = [])
{
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return false;
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);

    return $res;
}

/** Row count for a parameterised SELECT. */
function dbCount($con, string $sql, string $types = '', array $params = []): int
{
    $res = dbQuery($con, $sql, $types, $params);

    return $res ? mysqli_num_rows($res) : 0;
}
