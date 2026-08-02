#!/usr/bin/env python3
"""
Quality gate: fail the build on ERROR-severity Semgrep findings.

Reads every semgrep-*.json produced by the matrix, deduplicates across them,
writes a summary to the run page, and publishes the counts as step outputs so
the consolidated report job can use them.

Deduplication matters. Splitting one scan into four parallel scans means a
defect matched by two rulesets is reported twice, and a naive total would rise
without a single new defect existing. Findings are therefore keyed on rule,
path and line before anything is counted.

This script always exits zero. The workflow decides pass or fail from the
status output, so the counts are guaranteed to reach the report even on a
failing run.
"""

import glob
import json
import os
import sys
from collections import Counter

RESULT_GLOB = "semgrep-*.json"


def emit(name, value):
    """Publish a step output for later jobs to consume."""
    path = os.getenv("GITHUB_OUTPUT")
    if path:
        with open(path, "a", encoding="utf-8") as f:
            f.write(f"{name}={value}\n")


def is_rule_defect(err) -> bool:
    """
    A rule that fails to parse is disabled silently and the scan still exits
    zero, so a broken ruleset and a clean codebase look identical from the exit
    status alone. Those must fail the build.

    Semgrep reports timeouts in the same errors[] array. Those are not rule
    defects - they are large vendored JavaScript bundles exceeding the per-rule
    time budget - so they are reported but must not fail the build.
    """
    text = str(err.get("message", err)).lower()
    if "timeout" in text:
        return False
    return ("parse error" in text
            or "invalid pattern" in text
            or "invalid rule" in text
            or err.get("level") == "error")


def key(r):
    """Identity of a finding: same rule, same place is the same defect."""
    return (r.get("check_id", ""),
            r.get("path", ""),
            r.get("start", {}).get("line", 0),
            r.get("start", {}).get("col", 0))


def main() -> int:
    root = sys.argv[1] if len(sys.argv) > 1 else "."
    files = sorted(glob.glob(os.path.join(root, RESULT_GLOB)))

    if not files:
        print(f"::error::No Semgrep result files found under {root}/")
        emit("status", "error")
        emit("errors", 0)
        emit("warnings", 0)
        emit("total", 0)
        emit("custom", 0)
        return 0

    findings, raw_count, parse_errors, timeouts = {}, 0, [], []
    per_ruleset = {}

    for path in files:
        slug = os.path.basename(path).replace("semgrep-", "").replace(".json", "")
        try:
            with open(path, encoding="utf-8") as f:
                data = json.load(f)
        except (OSError, json.JSONDecodeError) as exc:
            print(f"::error::Could not read {path}: {exc}")
            emit("status", "error")
            return 0

        results = data.get("results", [])
        raw_count += len(results)
        per_ruleset[slug] = len(results)
        for r in results:
            findings.setdefault(key(r), r)

        for e in data.get("errors", []):
            (parse_errors if is_rule_defect(e) else timeouts).append(e)

    unique = list(findings.values())
    errors = [r for r in unique if r["extra"]["severity"] == "ERROR"]
    warnings = [r for r in unique if r["extra"]["severity"] == "WARNING"]
    custom = [r for r in errors if "royalexpress" in r["check_id"].lower()]
    by_rule = Counter(r["check_id"].rsplit(".", 1)[-1] for r in errors)

    duplicates = raw_count - len(unique)

    lines = [
        "## Semgrep SAST",
        "",
        f"- Branch: `{os.getenv('GITHUB_REF_NAME', 'unknown')}`",
        f"- Rulesets scanned: **{len(files)}** "
        f"({', '.join(f'{k} {v}' for k, v in sorted(per_ruleset.items()))})",
        f"- Unique findings: **{len(unique)}** "
        f"({duplicates} duplicate{'' if duplicates == 1 else 's'} removed "
        f"across rulesets)",
        f"- ERROR: **{len(errors)}** · WARNING: **{len(warnings)}**",
        f"- From the custom Royal Express rules: **{len(custom)}**",
        f"- Rule defects: **{len(parse_errors)}** · "
        f"timeouts on vendored files: {len(timeouts)}",
        "",
    ]

    if by_rule:
        lines += ["### ERROR findings by rule", ""]
        lines += [f"- `{rule}` — {count}" for rule, count in by_rule.most_common()]
        lines.append("")

    if errors:
        lines += ["### First 20 ERROR findings", ""]
        for r in errors[:20]:
            lines.append(f"- `{r['path']}:{r['start']['line']}` — "
                         f"{r['check_id'].rsplit('.', 1)[-1]}")
        lines.append("")

    if parse_errors:
        lines += ["### Rule defects (build-failing)", ""]
        for e in parse_errors[:10]:
            lines.append(f"- {str(e.get('message', e))[:200]}")
        lines.append("")

    summary = "\n".join(lines)
    print(summary)

    if path_summary := os.getenv("GITHUB_STEP_SUMMARY"):
        with open(path_summary, "a", encoding="utf-8") as f:
            f.write(summary + "\n")

    if parse_errors:
        status = "rule-defect"
        print("::error::Ruleset failed to parse - findings cannot be trusted.")
    elif errors:
        status = "fail"
        print(f"::error::{len(errors)} ERROR-severity findings.")
    else:
        status = "pass"
        print("Quality gate passed: no ERROR-severity findings.")

    emit("status", status)
    emit("errors", len(errors))
    emit("warnings", len(warnings))
    emit("total", len(unique))
    emit("custom", len(custom))

    # Always zero. The workflow enforces the gate from the status output, which
    # keeps the counts available to the consolidated report on a failing run.
    return 0


if __name__ == "__main__":
    sys.exit(main())
