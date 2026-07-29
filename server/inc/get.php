<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema_allowlist.php';

function getAllBranch()
{
    include 'connection.php';

    $viewcat = "SELECT * FROM branch WHERE is_deleted = 0";
    return mysqli_query($con, $viewcat);
}

function getAllArea()
{
    include 'connection.php';

    $viewcat = "SELECT * FROM area WHERE is_deleted = 0";
    return mysqli_query($con, $viewcat);
}

function getAllAreabyID($area_id)
{
    include 'connection.php';

    return dbQuery($con,
        "SELECT * FROM area WHERE is_deleted = 0 AND area_id = ?",
        "s", [$area_id]);
}

function getAllPrice()
{
    include 'connection.php';

    $viewcat = "SELECT * FROM price_table WHERE is_deleted = 0";
    return mysqli_query($con, $viewcat);
}

function checkPrice($start_area, $end_area)
{
    include 'connection.php';

    return dbCount($con,
        "SELECT * FROM price_table
          WHERE is_deleted = 0 AND start_area = ? AND end_area = ?",
        "ss", [$start_area, $end_area]);
}

function getBille($customer_id)
{
    include 'connection.php';

    return dbQuery($con,
        "SELECT * FROM request
           JOIN customer ON customer.customer_id = request.customer_id
          WHERE request.customer_id = ?",
        "s", [$customer_id]);
}

//employee

function getAllemployee()
{
    include 'connection.php';

    $q1 = "SELECT * FROM employee WHERE is_deleted = 0 AND email != 'admin'";
    return mysqli_query($con, $q1);
}

function getemployeeByID($emp_id)
{
    include 'connection.php';

    return dbQuery($con,
        "SELECT * FROM employee WHERE is_deleted = 0 AND emp_id = ?",
        "s", [$emp_id]);
}

function getemployeeByEmail($email)
{
    include 'connection.php';

    return dbQuery($con,
        "SELECT * FROM employee WHERE is_deleted = 0 AND email = ?",
        "s", [$email]);
}

function getBranchByID($branch_id)
{
    include 'connection.php';

    return dbQuery($con,
        "SELECT * FROM branch WHERE is_deleted = 0 AND branch_id = ?",
        "s", [$branch_id]);
}

function getAllTrackingByCUS($customer_id)
{
    include 'connection.php';

    return dbQuery($con,
        "SELECT * FROM request
          WHERE is_deleted = 0 AND customer_id = ?
          ORDER BY date_updated DESC",
        "s", [$customer_id]);
}

function getAllTracking()
{
    include 'connection.php';

    $viewcat = "SELECT * FROM request
                  JOIN customer ON customer.customer_id = request.customer_id
                 WHERE request.is_deleted = 0
                 ORDER BY date_updated DESC";
    return mysqli_query($con, $viewcat);
}

function checkemployeetByEmail($email)
{
    include 'connection.php';

    $employees = dbCount($con,
        "SELECT * FROM employee WHERE email = ? AND is_deleted = 0",
        "s", [$email]);

    if ($employees > 0) {
        return $employees;
    }

    $customers = dbCount($con,
        "SELECT * FROM customer WHERE email = ? AND is_deleted = 0",
        "s", [$email]);

    return $customers > 0 ? $customers : 0;
}

function getAllgalleryImages()
{
    include 'connection.php';

    $q1 = "SELECT * FROM gallery";
    return mysqli_query($con, $q1);
}

//customer

function checkuserPassword($data)
{
    include 'connection.php';

    $customer_id = $data['customer_id'] ?? '';
    $password    = $data['password']    ?? '';

    // The password cannot be part of the WHERE clause once it is hashed: the
    // row is fetched by identity, then the submitted value is verified.
    $res = dbQuery($con,
        "SELECT password FROM customer WHERE is_deleted = 0 AND customer_id = ?",
        "s", [$customer_id]);

    $row = $res ? mysqli_fetch_assoc($res) : null;

    // Callers treat a positive number as success, so the 1/0 contract is kept.
    echo ($row !== null && password_verify($password, $row['password'])) ? 1 : 0;
}

function checkArea($data)
{
    include 'connection.php';

    $start_area = $data['send_location'] ?? '';
    $end_area   = $data['end_location']  ?? '';

    $res = dbQuery($con,
        "SELECT * FROM price_table
          WHERE is_deleted = 0 AND start_area = ? AND end_area = ?",
        "ss", [$start_area, $end_area]);

    $row = $res ? mysqli_fetch_assoc($res) : null;
    echo $row['price'] ?? '';
}

function checkAreaByName($area_name)
{
    include 'connection.php';

    return dbCount($con,
        "SELECT * FROM area WHERE area_name = ? AND is_deleted = 0",
        "s", [$area_name]);
}

function checkUserEmail($data)
{
    include 'connection.php';

    $customer_id = $data['customer_id'] ?? '';
    $email       = $data['email']       ?? '';

    echo dbCount($con,
        "SELECT * FROM customer
          WHERE is_deleted = 0 AND email = ? AND customer_id = ?",
        "ss", [$email, $customer_id]);
}

function getAllcustomerById($customer_id)
{
    include 'connection.php';

    return dbQuery($con,
        "SELECT * FROM customer WHERE is_deleted = 0 AND customer_id = ?",
        "s", [$customer_id]);
}

function getAllcustomers()
{
    include 'connection.php';

    $q1 = "SELECT * FROM customer WHERE is_deleted = 0 AND email != 'admin'";
    return mysqli_query($con, $q1);
}

function getLoginAdmin($data)
{
    include 'connection.php';

    $email    = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    $value = "";

    // employee / admin lookup
    $sql  = "SELECT * FROM employee WHERE email = ? AND is_deleted = 0";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $emp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // Constant-time comparison against the stored bcrypt hash.
    if ($emp !== null && password_verify($password, $emp['password'])) {

        $value = 'admin';
        $_SESSION['admin'] = $emp['email'];
        // TODO Phase 3: $_SESSION['role'] = 'admin';

    } else {

        // --- Customer lookup ---
        $sql  = "SELECT * FROM customer WHERE email = ? AND is_deleted = 0";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $cus = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($cus !== null && password_verify($password, $cus['password'])) {
            $value = 'customer';
            $_SESSION['customer'] = $cus['customer_id'];
            // TODO Phase 3: $_SESSION['role'] = 'customer';
        }
    }

    echo $value;
}

function checkemployee($email)
{
    include 'connection.php';

    return dbQuery($con,
        "SELECT * FROM employee WHERE email = ? AND is_deleted = 0",
        "s", [$email]);
}

function checkCustomerByEmail($email)
{
    include 'connection.php';

    return dbQuery($con,
        "SELECT * FROM customer WHERE email = ? AND is_deleted = 0",
        "s", [$email]);
}

function checkCustomerByID($customer_id)
{
    include 'connection.php';

    return dbQuery($con,
        "SELECT * FROM customer WHERE customer_id = ? AND is_deleted = 0",
        "s", [$customer_id]);
}

function getAllCustomer()
{
    include 'connection.php';

    $q1 = "SELECT * FROM customer WHERE is_deleted = 0 AND email != 'admin'";
    $table = mysqli_query($con, $q1);

    return mysqli_fetch_all($table, MYSQLI_ASSOC);
}

//contact

function getAllMessages()
{
    include 'connection.php';

    $messages = "SELECT * FROM contact";
    return mysqli_query($con, $messages);
}

//count

// $table is an identifier and cannot be bound, so it is validated against the
// allow-list before interpolation.
function dataCount($table)
{
    include 'connection.php';

    if (!array_key_exists($table, schemaAllowList())) {
        rejectRequest();
    }

    $res = mysqli_query($con, "SELECT COUNT(*) AS c FROM `$table` WHERE is_deleted = 0");
    $row = mysqli_fetch_assoc($res);
    echo $row['c'];
}

// Was dataCountWhere($table, $where) taking a raw SQL fragment.  Split into a
// validated column and a bound value so no caller can supply SQL text.
function dataCountWhere($table, $column, $value)
{
    include 'connection.php';

    $schema = schemaAllowList();

    if (!array_key_exists($table, $schema))                  rejectRequest();
    if (!in_array($column, $schema[$table]['fields'], true)) rejectRequest();

    $res = dbQuery($con,
        "SELECT COUNT(*) AS c FROM `$table` WHERE `$column` = ? AND is_deleted = 0",
        "s", [$value]);

    $row = mysqli_fetch_assoc($res);
    echo $row['c'];
}

//settings

function getAllSettings()
{
    include 'connection.php';

    $settings = "SELECT * FROM settings";
    return mysqli_query($con, $settings);
}

function checkPasswordByName($data)
{
    include 'connection.php';

    $email    = $data['email']    ?? '';
    $password = $data['password'] ?? '';

    $res = dbQuery($con,
        "SELECT password FROM employee WHERE email = ? AND is_deleted = 0",
        "s", [$email]);

    $row = $res ? mysqli_fetch_assoc($res) : null;

    echo ($row !== null && password_verify($password, $row['password'])) ? 1 : 0;
}
