<?php
/**
 * Database connection.
 *
 * Credentials come from the environment rather than being embedded in source,
 * and the account used holds only the DML privileges the application needs -
 * no DROP, GRANT or FILE. A successful injection or code-execution flaw is
 * therefore bounded by what that account can do.
 */

require_once __DIR__ . '/env.php';

loadEnv(dirname(__DIR__, 2) . '/.env');

$con = @mysqli_connect(
    envOr('DB_HOST', 'localhost'),
    envOr('DB_USER', 'cms_app'),
    envOr('DB_PASS', ''),
    envOr('DB_NAME', 'royal_express_db')
);

if (!$con) {
    // The reason goes to the log; the client is told nothing about the
    // database, its host or the account in use.
    error_log('Database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    exit('Service temporarily unavailable.');
}
