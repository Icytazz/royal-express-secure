#!/usr/bin/env python3
"""
Consolidated security report.

Part 1 section 7 proposed GitHub Actions with the expected output
"consolidated automated report". Four tools each writing their own artefact is
four reports. This walks the downloaded artefacts, merges what each tool found
into one document, and writes it to the run summary and to a file.

Deliberately tolerant of missing input. On a failing run some stages never
execute, and a report that crashes when a stage was skipped would be useless
exactly when it is most needed. A stage that did not run is reported as not
having run, which is different from a stage that ran and found nothing.
"""

import glob
import json
import os
import sys
from collections import Counter

# The gate and this report must agree on what counts as one finding. Importing
# the key rather than reimplementing it means they cannot drift apart: an
# earlier version of this script omitted the column and reported 86 unique
# findings where the gate reported 95, from identical input.
from gate import key, severity, FAILING, WARNING_LEVEL

BRANCH = os.getenv("GITHUB_REF_NAME", "unknown")
RUN = os.getenv("GITHUB_RUN_NUMBER", "?")
SHA = (os.getenv("GITHUB_SHA") or "")[:7]

ICON = {"success": "pass", "failure": "FAIL", "skipped": "not run",
        "cancelled": "cancelled", "": "unknown", None: "unknown"}


def read_json(path):
    try:
        with open(path, encoding="utf-8") as f:
            return json.load(f)
    except (OSError, json.JSONDecodeError):
        return None


# ---------------------------------------------------------------- sections

def semgrep_section(root):
    files = sorted(glob.glob(os.path.join(root, "**", "semgrep-*.json"),
                             recursive=True))
    if not files:
        return ["_No Semgrep results were produced._", ""], None

    findings, per_ruleset = {}, {}
    for path in files:
        slug = os.path.basename(path).replace("semgrep-", "").replace(".json", "")
        data = read_json(path)
        if not data:
            continue
        results = data.get("results", [])
        per_ruleset[slug] = len(results)
        for r in results:
            findings.setdefault(key(r), r)

    unique = list(findings.values())
    errors = [r for r in unique if severity(r) in FAILING]
    warns = [r for r in unique if severity(r) in WARNING_LEVEL]
    custom = [r for r in errors if "royalexpress" in r["check_id"].lower()]
    by_rule = Counter(r["check_id"].rsplit(".", 1)[-1] for r in errors)

    out = [
        f"Four rulesets ran in parallel; findings are deduplicated across them, "
        f"so a defect matched by two rulesets counts once.",
        "",
        "| Ruleset | Raw findings |",
        "|---|---|",
    ]
    out += [f"| {k} | {v} |" for k, v in sorted(per_ruleset.items())]
    out += [
        "",
        f"**{len(unique)} unique findings** — "
        f"{len(errors)} error severity, {len(warns)} warning severity. "
        f"{len(custom)} of the error findings come from the three custom rules "
        f"written for this application.",
        "",
    ]
    if by_rule:
        out += ["| Rule | Error findings |", "|---|---|"]
        out += [f"| `{r}` | {n} |" for r, n in by_rule.most_common()]
        out.append("")
    if errors:
        out += ["<details><summary>First 15 error findings</summary>", ""]
        out += [f"- `{r['path']}:{r['start']['line']}` — "
                f"{r['check_id'].rsplit('.', 1)[-1]}" for r in errors[:15]]
        out += ["", "</details>", ""]
    return out, len(errors)


def dependency_check_section(root):
    status = read_json(os.path.join(root, "sca-dependency-check-report",
                                    "sca-status.json")) or {}
    outcome = status.get("outcome", "unknown")

    hits = glob.glob(os.path.join(root, "**", "dependency-check-report.json"),
                     recursive=True)
    if not hits:
        note = ("The National Vulnerability Database feed was unavailable or "
                "rate-limited, so this stage did not complete. It is reported "
                "rather than omitted: a supply-chain check that quietly did "
                "not run is worse than one that visibly did not."
                if outcome != "success" else
                "No machine-readable report was produced.")
        return [f"_{note}_", ""], None

    data = read_json(hits[0]) or {}
    rows, total = [], 0
    for dep in data.get("dependencies", []):
        vulns = dep.get("vulnerabilities") or []
        if not vulns:
            continue
        total += len(vulns)
        worst = sorted(vulns, key=lambda v: v.get("cvssv3", {})
                       .get("baseScore", 0), reverse=True)[0]
        rows.append((os.path.basename(dep.get("fileName", "?")),
                     len(vulns), worst.get("name", "?"),
                     worst.get("severity", "?")))

    if not rows:
        return ["No known vulnerabilities matched in the bundled libraries.",
                ""], 0

    out = [f"**{total} known vulnerabilities** across "
           f"{len(rows)} bundled components.", "",
           "| Component | CVEs | Highest | Severity |", "|---|---|---|---|"]
    out += [f"| `{f}` | {n} | {c} | {s} |"
            for f, n, c, s in sorted(rows, key=lambda r: -r[1])[:12]]
    out.append("")
    return out, total


def gitleaks_section(root):
    hits = glob.glob(os.path.join(root, "**", "*.sarif"), recursive=True)
    hits = [h for h in hits if "gitleaks" in h.lower() or
            os.path.basename(h) == "results.sarif"]
    if not hits:
        return ["_No secret-scanning report was produced._", ""], None

    total = 0
    for h in hits:
        data = read_json(h) or {}
        for run in data.get("runs", []):
            total += len(run.get("results", []))

    if total == 0:
        return ["No secrets detected across the full commit history. The scan "
                "covers every commit, not just the working tree, because a "
                "credential removed in a later commit is still recoverable "
                "from history.", ""], 0
    return [f"**{total} potential secrets** detected — see the artefact.",
            ""], total


def zap_section(root):
    hits = glob.glob(os.path.join(root, "**", "report_json.json"),
                     recursive=True)
    if not hits:
        return ["_The dynamic scan did not run. It depends on the quality "
                "gate passing, so a build that fails static analysis never "
                "reaches the stage that deploys and scans a running "
                "instance._", ""], None

    data = read_json(hits[0]) or {}
    alerts = []
    for site in data.get("site", []):
        alerts += site.get("alerts", [])
    if not alerts:
        return ["The baseline scan reported no alerts against the running "
                "instance.", ""], 0

    by_risk = Counter(a.get("riskdesc", "?").split(" ")[0] for a in alerts)
    out = [f"**{len(alerts)} alerts** against the running instance.", "",
           "| Risk | Count |", "|---|---|"]
    out += [f"| {r} | {n} |" for r, n in by_risk.most_common()]
    out.append("")
    return out, len(alerts)


# ------------------------------------------------------------------- main

def main() -> int:
    root = sys.argv[1] if len(sys.argv) > 1 else "artifacts"
    dest = sys.argv[2] if len(sys.argv) > 2 else "security-report.md"

    gate_status = os.getenv("GATE_STATUS", "unknown")
    results = {
        "SAST (Semgrep)": os.getenv("RESULT_SAST", ""),
        "Quality gate": os.getenv("RESULT_GATE", ""),
        "SCA (Dependency-Check)": os.getenv("RESULT_SCA", ""),
        "Secret scanning (Gitleaks)": os.getenv("RESULT_SECRETS", ""),
        "DAST (OWASP ZAP)": os.getenv("RESULT_DAST", ""),
    }

    doc = [
        f"# Consolidated security report",
        "",
        f"Branch `{BRANCH}` · run #{RUN} · commit `{SHA}`",
        "",
        "One report from four tools. Each stage also publishes its own "
        "artefact; this merges them so the security position can be read in "
        "one place rather than reconstructed from four downloads.",
        "",
        "## Stage results",
        "",
        "| Stage | Result |",
        "|---|---|",
    ]
    doc += [f"| {k} | {ICON.get(v, v or 'unknown')} |" for k, v in results.items()]
    doc += ["", f"**Quality gate: {gate_status.upper()}** — "
                f"{os.getenv('GATE_ERRORS', '?')} error-severity findings, "
                f"{os.getenv('GATE_WARNINGS', '?')} warnings, "
                f"{os.getenv('GATE_CUSTOM', '?')} from the custom rules.", ""]

    for title, fn in (("## Static analysis", semgrep_section),
                      ("## Software composition analysis", dependency_check_section),
                      ("## Secret scanning", gitleaks_section),
                      ("## Dynamic analysis", zap_section)):
        doc.append(title)
        doc.append("")
        body, _ = fn(root)
        doc += body

    doc += [
        "---",
        "",
        "Generated by `.github/scripts/consolidate.py`. Counts are "
        "deduplicated across rulesets, so a defect reported by more than one "
        "ruleset is counted once.",
        "",
    ]

    text = "\n".join(doc)
    with open(dest, "w", encoding="utf-8") as f:
        f.write(text)
    print(text)
    return 0


if __name__ == "__main__":
    sys.exit(main())
