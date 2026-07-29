<?php
/**
 * API request controller. Entry point for all AJAX calls.
 *
 * Not to be included by pages - see bootstrap.php.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/inc/authorize.php';
require_once __DIR__ . '/inc/validate.php';

$functionCode = $_GET['function_code'] ?? '';

// Authorisation first: an unauthorised caller is refused before the request
// body is examined at all (NIST SP 800-53 AC-3).
requireAuth($functionCode);

// Then boundary validation, so no handler is reached with input that failed
// type, format or length checks (NIST SP 800-53 SI-10).
validateInput($functionCode, $_POST);

if (isset($_GET['function_code']) && $_GET['function_code'] == 'getCustomerTbleData') {
    echo json_encode(getAllCustomer());
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'updateData') {
    updateDataTable($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'insertImageUpload') {

    $img = $_FILES['file']['name'];
    $target_dir = "uploads/gallery/";
    $target_file = $target_dir . basename($img);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $extensions_arr = array("jpg", "jpeg", "png", "gif", "jfif", "svg", "webp");

    if (in_array($imageFileType, $extensions_arr)) {
        move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . $img);
        insertImagetoGallery($img);
    }
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'deleteData') {
    deleteDataTables($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'permanantDeleteData') {
    permanantDeleteDataTable($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'changesettings') {
    changePageSettings($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'SettingImage') {

    $img = $_FILES['file']['name'];
    $target_dir = "uploads/settings/";
    $target_file = $target_dir . basename($img);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $extensions_arr = array("jpg", "jpeg", "png", "gif", "jfif", "svg", "webp");

    if (in_array($imageFileType, $extensions_arr)) {
        move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . $img);
        editSettingImage($_POST, $img);
    }
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'login') {
    echo getLoginAdmin($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'checkPasswordByEmail') {
    checkPasswordByName($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'addcontact') {
    addMessage($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'addCustomer') {
    createCustomer($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'checkEmail') {
    checkUserEmail($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'checkPassword') {
    checkuserPassword($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'addEmployee') {
    addEmployee($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'addBranch') {
    addBranch($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'addPrice') {
    addPrice($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'checkArea') {
    checkArea($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'addArea') {
    addArea($_POST);
} else if (isset($_GET['function_code']) && $_GET['function_code'] == 'addRequest') {
    addRequest($_POST);
}
