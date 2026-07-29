<?php
/**
 * Boundary input validation (NIST SP 800-53 SI-10).
 *
 * Every request is checked for presence, type, format and length at the trust
 * boundary, before any handler runs. Prepared statements stop input altering
 * query structure; this stops malformed input entering the system at all.
 *
 * The rules mirror Admin/assets/js/include/validation.js. Client-side checks
 * are a usability feature and can be bypassed by posting directly to the API,
 * so the same constraints are enforced here where they cannot be skipped.
 *
 * Deny by default: a function code with no rule set is rejected.
 */

require_once __DIR__ . '/schema_allowlist.php';

/**
 * type, max length, required
 *
 * Types: str | int | decimal | email | phone | gender
 */
function inputRules(): array
{
    return [
        // --- public ---
        // 'email' is deliberately str, not email: the seeded administrator
        // account uses "admin" as its address. See report section 5 (residual).
        'login'                => ['email'    => ['str', 254, true],
                                   'password' => ['str', 255, true]],

        'addCustomer'          => ['name'     => ['str', 100, true],
                                   'email'    => ['email', 254, true],
                                   'phone'    => ['phone', 20, true],
                                   'nic'      => ['str', 20, true],
                                   'address'  => ['str', 255, true],
                                   'gender'   => ['gender', 1, true],
                                   'password' => ['str', 255, true]],

        'addcontact'           => ['name'     => ['str', 100, true],
                                   'email'    => ['email', 254, true],
                                   'subject'  => ['str', 150, true],
                                   'message'  => ['str', 2000, true]],

        'checkArea'            => ['send_location' => ['str', 100, true],
                                   'end_location'  => ['str', 100, true]],

        'addRequest'           => ['customer_id'   => ['int', 11, true],
                                   'sender_phone'  => ['phone', 20, true],
                                   'weight'        => ['decimal', 10, true],
                                   'send_location' => ['str', 100, true],
                                   'end_location'  => ['str', 100, true],
                                   'total_fee'     => ['decimal', 12, true],
                                   'res_phone'     => ['phone', 20, true],
                                   'red_address'   => ['str', 255, true],
                                   'res_name'      => ['str', 100, true]],

        // --- account self-service ---
        'checkEmail'           => ['customer_id' => ['int', 11, true],
                                   'email'       => ['email', 254, true]],

        'checkPassword'        => ['customer_id' => ['int', 11, true],
                                   'password'    => ['str', 255, true]],

        'checkPasswordByEmail' => ['email'    => ['str', 254, true],
                                   'password' => ['str', 255, true]],

        // --- administrative ---
        'addEmployee'          => ['name'      => ['str', 100, true],
                                   'email'     => ['email', 254, true],
                                   'phone'     => ['phone', 20, true],
                                   'nic'       => ['str', 20, true],
                                   'address'   => ['str', 255, true],
                                   'gender'    => ['gender', 1, true],
                                   'password'  => ['str', 255, true],
                                   'branch_id' => ['int', 11, true]],

        'addBranch'            => ['branch_name' => ['str', 100, true]],
        'addArea'              => ['area_name'   => ['str', 100, true]],

        'addPrice'             => ['start_area' => ['str', 100, true],
                                   'end_area'   => ['str', 100, true],
                                   'price'      => ['decimal', 12, true]],

        // Table / column names are validated separately by the identifier
        // allow-list in schema_allowlist.php; only the values are checked here.
        'updateData'           => ['id'    => ['str', 254, true],
                                   'value' => ['str', 1000, true]],
        'deleteData'           => ['id'    => ['str', 254, true]],
        'permanantDeleteData'  => ['id'    => ['str', 254, true]],
        'changesettings'       => ['value' => ['str', 1000, true]],

        // --- no request body ---
        'getCustomerTbleData'  => [],
        'insertImageUpload'    => [],
        'SettingImage'         => [],
    ];
}

function validField(string $type, string $value): bool
{
    switch ($type) {
        case 'int':
            return ctype_digit($value);

        case 'decimal':
            return (bool) preg_match('/^\d+(\.\d{1,2})?$/', $value);

        case 'email':
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

        // Mirrors phonenumber() in validation.js
        case 'phone':
            return (bool) preg_match('/^\(?\d{3}\)?[-. ]?\d{3}[-. ]?\d{4}$/', $value);

        case 'gender':
            return $value === '0' || $value === '1';

        case 'str':
        default:
            return true;
    }
}

/**
 * Validate the request body for a function code.
 * Halts execution with 400 on any failure; the caller never regains control.
 */
function validateInput(string $functionCode, array $data): void
{
    $rules = inputRules();

    if (!array_key_exists($functionCode, $rules)) {
        rejectRequest('Unknown request');
    }

    foreach ($rules[$functionCode] as $field => [$type, $max, $required]) {

        $present = isset($data[$field]) && $data[$field] !== '';

        if (!$present) {
            if ($required) {
                rejectRequest('Missing required field');
            }
            continue;
        }

        $value = (string) $data[$field];

        if (mb_strlen($value) > $max) {
            rejectRequest('Field exceeds maximum length');
        }

        if (!validField($type, $value)) {
            rejectRequest('Field failed format validation');
        }
    }
}
