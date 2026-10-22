#!/usr/bin/env bash
# Decides whether a `php artisan test` run passes CI.
#   usage: check-test-output.sh <exit code> <test output file>
# Passes when the run exited 0, or when it finished (summary line present, no PHP fatal error) and every
# failure is a known environment-dependent test (missing API keys in CI). A run that died before the summary
# — a PHP fatal error, a crash — fails, even though it prints no "FAILED" line.
set -u

EXIT_CODE="$1"
OUTPUT="$2"
KNOWN="PlanEnforcementTest|CountrySlugSupportTest"

if grep -qE "PHP Fatal error|Fatal error:" "$OUTPUT"; then
  echo "::error::The test run hit a PHP fatal error:"
  grep -E "PHP Fatal error|Fatal error:" "$OUTPUT" | head -5
  exit 1
fi

SUMMARY=$(grep -E "^[[:space:]]*Tests:" "$OUTPUT" | tail -1)
if [ -z "$SUMMARY" ]; then
  echo "::error::The test run did not finish (no 'Tests:' summary line, exit code $EXIT_CODE)."
  exit 1
fi
echo "$SUMMARY"

if [ "$EXIT_CODE" -eq 0 ]; then
  exit 0
fi

FAILED=$(echo "$SUMMARY" | grep -oE "[0-9]+ failed" | grep -oE "[0-9]+" || true)
FAILED=${FAILED:-0}
FAILED_LINES=$(grep -E "^[[:space:]]*FAILED[[:space:]]" "$OUTPUT" || true)
UNEXPECTED=$(echo "$FAILED_LINES" | grep -vE "$KNOWN" | grep -v '^$' || true)
KNOWN_COUNT=$(echo "$FAILED_LINES" | grep -cE "$KNOWN" || true)

if [ -n "$UNEXPECTED" ]; then
  echo "::error::Unexpected failures:"
  echo "$UNEXPECTED"
  exit 1
fi

if [ "$FAILED" -eq 0 ] || [ "$FAILED" -ne "$KNOWN_COUNT" ]; then
  echo "::error::Exit code $EXIT_CODE with $FAILED failed in the summary but $KNOWN_COUNT known failures listed."
  exit 1
fi

echo "All $FAILED failures are environment-dependent (missing API keys). Proceeding."
exit 0
