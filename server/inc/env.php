<?php
/**
 * Minimal environment loader.
 *
 * Real environment variables take precedence, so CI can inject secrets without
 * a file present. The .env file is a development convenience only: in
 * production the values should come from the process environment and no file
 * should exist inside the document root at all.
 */

function loadEnv(string $path): void
{
    static $seen = [];

    if (isset($seen[$path]) || !is_readable($path)) {
        return;
    }
    $seen[$path] = true;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {

        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key   = trim($key);
        $value = trim($value, " \t\"'");

        // Never override a value already supplied by the environment.
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

function envOr(string $key, string $default = ''): string
{
    $value = getenv($key);

    return ($value === false || $value === '') ? $default : $value;
}

/** Writable log directory, kept outside the document root. */
function logDir(): string
{
    $dir = envOr('LOG_DIR', dirname(__DIR__, 3) . '/cms-logs');

    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    return $dir;
}
