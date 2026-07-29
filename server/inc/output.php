<?php
/**
 * Output encoding (OWASP A03:2025 Cross-Site Scripting).
 *
 * Values read back from the database are attacker-controlled: a customer
 * chooses their own name, address and NIC at registration. Writing them into a
 * page unencoded lets a stored payload execute in the browser of whoever views
 * it - typically an administrator listing customers, which is the worst
 * possible audience for it.
 *
 * Prepared statements stop input changing the meaning of a SQL statement.
 * They do nothing about input changing the meaning of an HTML document; that
 * requires encoding at the point of output, which is what this does.
 *
 * ENT_QUOTES converts both double and single quotes, so the helper is also safe
 * inside single-quoted attributes such as onchange='...'. Encoding at output
 * rather than on input keeps the stored value intact and correct for other
 * contexts (email, PDF, JSON), which input sanitisation would not.
 */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
