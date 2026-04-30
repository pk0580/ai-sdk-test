#!/usr/bin/env bash
# PostToolUse hook for Write|Edit on .php files under src/.
# Runs Pint (format) + php -l (syntax) inside shop_php container.
# Silent on success; errors go to stderr so Claude sees them.

set -u

f=$(python3 -c '
import json, sys
try:
    d = json.load(sys.stdin)
except Exception:
    sys.exit(0)
print((d.get("tool_response") or {}).get("filePath")
      or (d.get("tool_input") or {}).get("file_path") or "")
' 2>/dev/null)

prefix="/home/pavel/projects/tests/shop-test/src/"
case "$f" in
  "$prefix"*.php) rel="${f#$prefix}" ;;
  *) exit 0 ;;
esac

if ! docker ps --format '{{.Names}}' 2>/dev/null | grep -qx shop_php; then
  exit 0
fi

if ! out=$(docker exec shop_php sh -c './vendor/bin/pint "$1" && php -l "$1"' _ "/var/www/html/$rel" 2>&1); then
  echo "$out" >&2
  exit 1
fi
exit 0
