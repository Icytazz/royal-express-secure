#!/usr/bin/env bash
# Pin every action reference in the workflow to a commit SHA.
#
# A tag is mutable. `uses: actions/checkout@v4` means "whatever the owner has
# tagged v4 today", so the code the pipeline executes can change without this
# repository changing. That is an unverified dependency, OWASP A03:2025
# Software Supply Chain Failures. Pinning to a commit SHA makes the reference
# immutable; Dependabot then proposes updates as reviewable pull requests.
#
# Uses git ls-remote rather than the GitHub API: no authentication, no rate
# limit, and ^{} dereferences annotated tags to the commit they point at.
# Without ^{} you get the tag object's own SHA, which is not a valid target
# for `uses:` and fails at runtime with an unhelpful message.
#
# References already pinned to a 40-character SHA are left alone, so this is
# safe to re-run.
#
# Usage:  bash pin-actions.sh [.github/workflows/secure-pipeline.yml]
# Then:   git diff   - review every SHA before committing.

set -euo pipefail

WORKFLOW="${1:-.github/workflows/secure-pipeline.yml}"

[[ -f "$WORKFLOW" ]] || { echo "Not found: $WORKFLOW" >&2; exit 1; }

resolve() {
  local repo="$1" ref="$2" sha=""
  for candidate in "refs/tags/${ref}^{}" "refs/tags/${ref}" "refs/heads/${ref}"; do
    sha=$(git ls-remote "https://github.com/${repo}" "$candidate" 2>/dev/null \
          | awk '{print $1}' | head -1)
    [[ -n "$sha" ]] && { echo "$sha"; return 0; }
  done
  return 1
}

# Collect every `uses: owner/repo[/path]@ref` where ref is not already a SHA.
mapfile -t REFS < <(
  grep -oE 'uses:[[:space:]]+[A-Za-z0-9_.-]+/[A-Za-z0-9_./-]+@[A-Za-z0-9_.-]+' "$WORKFLOW" \
  | sed 's/uses:[[:space:]]*//' \
  | grep -vE '@[0-9a-f]{40}$' \
  | sort -u
)

if [[ ${#REFS[@]} -eq 0 ]]; then
  echo "Every action is already pinned to a commit SHA."
  exit 0
fi

echo "Pinning ${#REFS[@]} action reference(s):"
echo

failed=0
for full in "${REFS[@]}"; do
  action="${full%@*}"          # owner/repo or owner/repo/subpath
  ref="${full##*@}"
  # A subpath such as github/codeql-action/upload-sarif is not a repository;
  # the SHA belongs to the repository that contains it.
  repo=$(echo "$action" | cut -d/ -f1,2)

  printf '  %-56s %-10s ... ' "$action" "$ref"
  if sha=$(resolve "$repo" "$ref"); then
    echo "$sha"
    # BSD and GNU sed disagree on -i; write through a temp file instead.
    sed "s|uses:\([[:space:]]*\)${action}@${ref}\b|uses:\1${action}@${sha}   # ${ref}|g" \
        "$WORKFLOW" > "${WORKFLOW}.tmp"
    mv "${WORKFLOW}.tmp" "$WORKFLOW"
  else
    echo "FAILED - pin this one by hand"
    failed=1
  fi
done

# The TODO markers are no longer accurate once the pins are in.
sed 's|[[:space:]]*# TODO pin: run pin-actions.sh||' "$WORKFLOW" > "${WORKFLOW}.tmp"
mv "${WORKFLOW}.tmp" "$WORKFLOW"

echo
remaining=$(grep -cE 'uses:[[:space:]]+[A-Za-z0-9_.-]+/[A-Za-z0-9_./-]+@[A-Za-z0-9_.-]+' "$WORKFLOW" \
            | head -1 || true)
unpinned=$(grep -oE 'uses:[[:space:]]+[A-Za-z0-9_.-]+/[A-Za-z0-9_./-]+@[A-Za-z0-9_.-]+' "$WORKFLOW" \
           | grep -vcE '@[0-9a-f]{40}' || true)
echo "Action references: ${remaining}, still unpinned: ${unpinned:-0}"
echo "Review with: git diff $WORKFLOW"
exit $failed
