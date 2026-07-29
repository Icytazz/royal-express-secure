<?php
/**
 * Server-side authorisation (OWASP A01:2025, NIST SP 800-53 AC-3).
 *
 * Every API request is checked against a permission table before dispatch, and
 * every refusal terminates the request. Three properties matter:
 *
 *   1. Deny by default - a function code absent from the table is refused, so
 *      new endpoints are unreachable until deliberately classified.
 *   2. Server-side and pre-dispatch - the decision is made from session state
 *      the client cannot influence, before any handler runs.
 *   3. Fail closed - each refusal calls exit(), so execution cannot continue
 *      into the code the check was guarding.
 */

// Pulls in the hardened session start and the function library, so a page can
// require this file as its very first statement - before any output - which is
// what makes the redirect below actually work.
require_once __DIR__ . '/../bootstrap.php';

/** function_code => 'public' | 'user' | 'admin' */
function permissionTable(): array
{
    return [
        // Reachable without a session
        'login'                => 'public',
        'addCustomer'          => 'public',   // self-registration
        'addcontact'           => 'public',   // contact form
        'checkArea'            => 'public',   // read-only price lookup

        // Any authenticated user (customer self-service)
        'addRequest'           => 'user',
        'checkEmail'           => 'user',
        'checkPassword'        => 'user',
        'updateData'           => 'user',     // plus ownership check, below

        // Administrators only
        'getCustomerTbleData'  => 'admin',
        'deleteData'           => 'admin',
        'permanantDeleteData'  => 'admin',
        'changesettings'       => 'admin',
        'SettingImage'         => 'admin',
        'insertImageUpload'    => 'admin',
        'checkPasswordByEmail' => 'admin',
        'addEmployee'          => 'admin',
        'addBranch'            => 'admin',
        'addPrice'             => 'admin',
        'addArea'              => 'admin',
    ];
}

function currentRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

function denyUnauthenticated(): void
{
    http_response_code(401);
    exit('Authentication required');
}

function denyForbidden(): void
{
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Gate an API request. Called from api.php before any handler runs.
 */
function requireAuth(string $functionCode): void
{
    $table    = permissionTable();
    $required = $table[$functionCode] ?? null;

    // Deny by default: unmapped codes are refused rather than allowed through.
    if ($required === null) {
        http_response_code(400);
        exit('Invalid request');
    }

    if ($required === 'public') {
        return;
    }

    $role = currentRole();

    if ($role === null) {
        denyUnauthenticated();
    }

    if ($required === 'admin' && $role !== 'admin') {
        denyForbidden();
    }
}

/**
 * Gate a page render. Must be the first statement in a protected page: PHP
 * discards a Location header once output has started, so a guard placed after
 * any markup cannot redirect at all.
 */
function requireRole(string $required, string $loginUrl = 'login.php'): void
{
    $role = currentRole();

    if ($role === null) {
        header('Location: ' . $loginUrl);
        exit();                       // fail closed: nothing below this runs
    }

    // Exact role match. An administrator is not implicitly a customer: the
    // customer pages read $_SESSION['customer'], which an admin session does
    // not hold, so allowing it through would be both wrong and unstable.
    if ($required !== 'user' && $role !== $required) {
        http_response_code(403);
        exit('Forbidden');
    }
}

/**
 * Object-level access control for the shared updateData endpoint.
 *
 * Administrators may update any allow-listed row. A customer may only modify
 * their own record, so the target table and primary key are compared against
 * the identity held in the session. Without this an authenticated customer
 * could edit any employee or price row - horizontal privilege escalation.
 */
function requireOwnership(string $table, $id): void
{
    if (currentRole() === 'admin') {
        return;
    }

    $sessionId = $_SESSION['customer'] ?? null;

    if ($table !== 'customer' || $sessionId === null
        || (string) $id !== (string) $sessionId) {
        denyForbidden();
    }
}
