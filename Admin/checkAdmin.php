<?php
/**
 * Admin page guard.
 *
 * The previous version compared $_SESSION['admin'] - which holds an email
 * address - against the literal string 'admin', and its redirect was not
 * followed by exit(), so execution continued and the guarded page rendered
 * anyway. Both defects are corrected here: the check is against an explicit
 * role, and requireRole() terminates the request on refusal.
 */

require_once __DIR__ . '/../server/inc/authorize.php';

requireRole('admin');
