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
