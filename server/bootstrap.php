<?php
/**
 * Shared bootstrap: session plus the data-access function library.
 *
 * Pages include this to obtain the query functions. They must NOT include
 * api.php: that file is a request controller, and its validation and
 * authorisation guards assume an inbound API call. Including a controller as a
 * library runs those guards in a context they were never written for, which
 * denies ordinary page renders.
 */

require_once __DIR__ . '/inc/env.php';
require_once __DIR__ . '/inc/logger.php';
require_once __DIR__ . '/inc/output.php';

loadEnv(dirname(__DIR__) . '/.env');

/* ---------------------------------------------------------------------------
 * Error handling
 *
 * Stack traces, file paths and SQL fragments are diagnostic information for
 * the developer and reconnaissance material for an attacker. Detail is written
 * to a log outside the document root; the client sees a generic message.
 * ------------------------------------------------------------------------ */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', logDir() . '/php-error.log');
error_reporting(E_ALL);

set_exception_handler(function ($e) {
    error_log('Uncaught exception: ' . $e->getMessage()
              . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(500);
    }
    exit('An unexpected error occurred. Please try again later.');
});

/* ---------------------------------------------------------------------------
 * Response headers
 *
 * headers_sent() is checked because a few legacy pages emit markup before
 * reaching this file; on those the headers are skipped rather than raising a
 * warning.
 * ------------------------------------------------------------------------ */

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');           // clickjacking
    header('X-Content-Type-Options: nosniff');       // MIME sniffing
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header_remove('X-Powered-By');                   // version disclosure

    // Report-only first: the application loads jQuery, Bootstrap and fonts
    // from CDNs, so an enforcing policy is tuned against real violations
    // before being switched on.
    header("Content-Security-Policy-Report-Only: "
         . "default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://code.jquery.com; "
         . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com data:; "
         . "img-src 'self' data:; "
         . "frame-ancestors 'self'");
}

if (session_id() == '') {
    // HttpOnly keeps the cookie out of reach of JavaScript, limiting session
    // theft via XSS. SameSite=Strict stops the browser attaching it to
    // cross-site requests, which blocks CSRF against these endpoints.
    // 'secure' must become true once the application is served over HTTPS.
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => false,
    ]);
    session_start();
}

require_once __DIR__ . '/inc/get.php';
require_once __DIR__ . '/inc/update.php';
require_once __DIR__ . '/inc/delete.php';
require_once __DIR__ . '/inc/add.php';
