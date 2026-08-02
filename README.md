[![Secure Pipeline](https://github.com/Icytazz/royal-express-secure/actions/workflows/secure-pipeline.yml/badge.svg?branch=secure-fixes)](https://github.com/Icytazz/royal-express-secure/actions/workflows/secure-pipeline.yml)

# Royal Express Courier Management System — secured

A security remediation of the Royal Express Courier Management System, carried
out for CT123-3-3-ASC Advanced Software Security. The application itself is by
Pathum Wijesekara; the security work, the custom scanning rules and the
pipeline are mine.

## Branches

| Branch | Contents |
|---|---|
| `baseline` | The original application, frozen. Kept deliberately so the before-and-after comparison is between two runs of the same pipeline rather than between a measurement and a recollection. |
| `secure-fixes` | The remediated application. |
| `main` | Untouched upstream. |

The pipeline runs on all three. It passes on `secure-fixes` and fails on
`baseline`, which is the point.

## What was fixed

**SQL injection permitting authentication bypass.** The login query
concatenated both submitted values into its `WHERE` clause, so `' OR 1=1-- -`
returned the administrator row without a password. The query is now prepared
with a bound parameter, and the password has left the query entirely —
verification is `password_verify()` in PHP rather than a side effect of query
text.

**Broken access control.** The dispatcher invoked handlers straight from
`$_POST['function_code']` with no session, role or ownership check, so an
anonymous request could create an employee. `authorize.php` now holds a
19-endpoint permission table consulted before dispatch, denies by default, and
adds object-level ownership checks. Page guards were added to all 14 protected
pages, and the redirects that previously failed open now halt execution.

**Plaintext password storage.** Passwords were written verbatim. They are now
bcrypt digests with a per-user salt, hashed at a chokepoint inside the update
function so that no write path — including the change-password route — can
store cleartext.

Supporting work: a least-privilege database account, credentials moved to the
environment, a central exception handler, security event logging outside the
web root, and response headers.

## The pipeline

```
                    ┌─ Semgrep: p/php ──────────┐
  concurrency ──────┼─ Semgrep: security-audit ─┼─→ quality ─→ ZAP ─┐
  control           ├─ Semgrep: owasp-top-ten ──┤     gate          │
                    └─ Semgrep: custom rules ───┘                   ├─→ consolidated
                                                                    │   report
              ┌─ Dependency-Check (SCA) ─────────────────────────────┤
              └─ Gitleaks (secrets) ─────────────────────────────────┘
```

| Stage | Tool | Detects |
|---|---|---|
| Commit | Semgrep | Concatenated SQL, unencoded output, plaintext credential writes, guards that fail open |
| Commit | OWASP Dependency-Check | Known vulnerabilities in bundled JavaScript libraries |
| Commit | Gitleaks | Credentials anywhere in the commit history |
| Post-gate | OWASP ZAP | Header, cookie and disclosure issues against a running instance |

**Custom rules.** `.semgrep/royal-express.yml` holds three rules written for
the specific defects found in this application, each carrying metadata linking
it to the OWASP category, NIST control and MITRE ATT&CK technique the analysis
identified. They fire 56 times on `baseline` and zero times on `secure-fixes`.

**Quality gate.** `.github/scripts/gate.py` fails the build on error-severity
findings, and also on a rule that fails to parse — a malformed rule is disabled
silently and the scan still exits zero, so a broken ruleset and clean code are
otherwise indistinguishable.

**Consolidated report.** `.github/scripts/consolidate.py` merges all four
tools' output into one document, published to the run summary, uploaded as an
artefact, and posted as a comment on pull requests.

**Supply chain.** Every action is pinned to a commit SHA rather than a tag,
because a tag is mutable and a mutable reference is an unverified dependency.
The pipeline's own static analysis flagged this in an earlier version of the
workflow. Dependabot proposes updates so that pinned does not become stale.

## Running the pipeline locally

```bash
pip install semgrep
semgrep scan --config ./.semgrep/ --json-output=semgrep-custom.json .
python .github/scripts/gate.py .
```

## Repository layout

```
.github/workflows/secure-pipeline.yml   the pipeline
.github/scripts/gate.py                 quality gate
.github/scripts/consolidate.py          consolidated report
.github/dependabot.yml                  action update policy
.semgrep/royal-express.yml              custom rules
server/api.php                          request dispatcher
server/inc/authorize.php                authorisation gate
server/inc/validate.php                 boundary validation
server/inc/schema_allowlist.php         identifier allow-list
server/inc/output.php                   contextual output encoding
```

## Known limitations

Disclosed rather than omitted. The application runs over plain HTTP, so the
`Secure` cookie attribute cannot be set. Cross-site request forgery protection
rests on `SameSite=Strict` rather than synchroniser tokens. The content
security policy is deployed report-only. There is no rate limiting on
authentication and no password complexity policy.
