<?php
/**
 * Security event logging (NIST SP 800-53 AU-2, AU-3).
 *
 * Addresses the Repudiation finding: without a record of authentication
 * attempts and privileged actions, no administrative change is attributable to
 * anyone, and a successful compromise leaves no trace to investigate.
 *
 * The log is written outside the document root so it cannot be retrieved over
 * HTTP. Credentials are never recorded - only the identity claimed and the
 * outcome.
 */

require_once __DIR__ . '/env.php';

function logSecurityEvent(string $event, array $context = []): void
{
    $line = sprintf(
        "%s\t%s\t%s\t%s\t%s\n",
        gmdate('c'),
        $event,
        $_SERVER['REMOTE_ADDR'] ?? '-',
        $_SESSION['role'] ?? 'anonymous',
        json_encode($context, JSON_UNESCAPED_SLASHES)
    );

    @file_put_contents(logDir() . '/security.log', $line, FILE_APPEND | LOCK_EX);
}
