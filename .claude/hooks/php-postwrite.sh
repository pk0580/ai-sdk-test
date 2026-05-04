#!/usr/bin/env bash
# PostToolUse hook for Write|Edit on .php files inside the project.
# Runs Pint (format) + php -l (syntax) — inside the configured PHP
# container if available, otherwise on the host.
# Silent on success; errors go to stderr so Claude sees them.

set -u

# Project root (the directory that contains this hooks/ dir, two levels up).
project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
project_root="$(dirname "$project_root")"

# Optional container override:
#   export CLAUDE_PHP_CONTAINER=my_php_container
container="${CLAUDE_PHP_CONTAINER:-}"

# Container src prefix on the host. Override via env when needed.
src_prefix="${CLAUDE_SRC_PREFIX:-${project_root}/src/}"

# Container path that maps to ${src_prefix} (defaults to /var/www/html/).
container_root="${CLAUDE_CONTAINER_ROOT:-/var/www/html/}"

# Read the file path from the JSON event.
f=$(python3 -c '
import json, sys
try:
    d = json.load(sys.stdin)
except Exception:
    sys.exit(0)
print((d.get("tool_response") or {}).get("filePath")
      or (d.get("tool_input") or {}).get("file_path") or "")
' 2>/dev/null)

case "$f" in
  *.php) ;;
  *) exit 0 ;;
esac

# Map host path → relative path under src_prefix; if the file lives
# elsewhere in the project, fall back to a host-side run.
rel=""
case "$f" in
  "$src_prefix"*) rel="${f#$src_prefix}" ;;
esac

run_in_container() {
  local container_name="$1" rel_path="$2"
  if ! docker ps --format '{{.Names}}' 2>/dev/null | grep -qx "$container_name"; then
    return 2
  fi
  docker exec "$container_name" sh -c \
    './vendor/bin/pint "$1" >/dev/null && php -l "$1" >/dev/null' \
    _ "${container_root}${rel_path}" 2>&1
}

run_on_host() {
  local file="$1"
  if [ -x "${project_root}/vendor/bin/pint" ]; then
    "${project_root}/vendor/bin/pint" "$file" >/dev/null 2>&1 || return 1
  fi
  php -l "$file" >/dev/null 2>&1
}

if [ -n "$container" ] && [ -n "$rel" ]; then
  if out=$(run_in_container "$container" "$rel"); then
    exit 0
  else
    rc=$?
    if [ "$rc" -eq 2 ]; then
      run_on_host "$f" && exit 0
      out=$(run_on_host "$f" 2>&1)
    fi
    echo "$out" >&2
    exit 1
  fi
fi

if ! out=$(run_on_host "$f" 2>&1); then
  echo "$out" >&2
  exit 1
fi

exit 0
