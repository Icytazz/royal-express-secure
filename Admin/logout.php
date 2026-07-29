<?php
require_once __DIR__ . '/../server/bootstrap.php';
require_once __DIR__ . '/../server/inc/logger.php';

logSecurityEvent('logout', [
    'identity' => $_SESSION['admin'] ?? $_SESSION['customer'] ?? '-',
]);

// Clear the session data, then the cookie, then the server-side record.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

// Server-side redirect rather than a client-side script: it cannot be skipped
// by a client that ignores JavaScript.
header('Location: login.php');
exit();
