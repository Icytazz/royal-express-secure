#!/usr/bin/env python3
"""
Quality gate: fail the build on ERROR-severity Semgrep findings.

Also writes a summary to the GitHub Actions run page so findings are visible
without downloading the artifact.
"""

import json
import os
import sys
from collections import Counter

RESULTS = "semgrep-results.json"


def main() -> int:
    try:
        with open(RESULTS) as f:
            data = json.load(f)
    except (OSError, json.JSONDecodeError) as exc:
        print(f"::error::Could not read {RESULTS}: {exc}")
        return 1

    results = data.get("results", [])
    errors = [r for r in results if r["extra"]["severity"] == "ERROR"]
    warnings = [r for r in results if r["extra"]["severity"] == "WARNING"]

    # A rule that fails to parse is disabled silently, and the scan still exits
    # zero. Without this check a broken ruleset looks identical to clean code.
    parse_errors = data.get("errors", [])

    by_rule = Counter(r["check_id"].rsplit(".", 1)[-1] for r in errors)

    lines = [
        "## Semgrep SAST",
        "",
        f"- Branch: `{os.getenv('GITHUB_REF_NAME', 'unknown')}`",
        f"- Total findings: **{len(results)}**",
        f"- ERROR: **{len(errors)}** · WARNING: **{len(warnings)}**",
        f"- Rule/parse errors: **{len(parse_errors)}**",
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
        lines += ["### Rule errors", ""]
        for e in parse_errors[:10]:
            lines.append(f"- {str(e.get('message', e))[:200]}")
        lines.append("")

    summary = "\n".join(lines)
    print(summary)

    if path := os.getenv("GITHUB_STEP_SUMMARY"):
        with open(path, "a") as f:
            f.write(summary + "\n")

    if parse_errors:
        print("::error::Ruleset failed to parse - findings cannot be trusted.")
        return 1

    if errors:
        print(f"::error::Quality gate failed: {len(errors)} ERROR-severity findings.")
        return 1

    print("Quality gate passed: no ERROR-severity findings.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
