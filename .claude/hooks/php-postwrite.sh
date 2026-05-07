#!/usr/bin/env bash
# Docker-only PostToolUse hook for Write|Edit on .php files (WSL2 +
# Laravel src/ layout).
# Maps a host path under ${CLAUDE_SRC_PREFIX} to the corresponding
# path inside ${CLAUDE_PHP_CONTAINER} and runs Pint + php -l there.
# Silent on success; errors go to stderr so Claude sees them.
#
# Required env (set in .claude/settings.local.json):
#   CLAUDE_PHP_CONTAINER     — name of the running PHP container
# Optional env:
#   CLAUDE_SRC_PREFIX        — host path mounted into the container
#                              (default: <project_root>/src/)
#   CLAUDE_CONTAINER_ROOT    — container path that maps to SRC_PREFIX
#                              (default: /var/www/html)

set -euo pipefail

# shellcheck source=_lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/_lib.sh"

project_root="$(git rev-parse --show-toplevel 2>/dev/null || (cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd))"

container="${CLAUDE_PHP_CONTAINER:-}"
src_prefix="${CLAUDE_SRC_PREFIX:-${project_root}/src/}"
container_root="${CLAUDE_CONTAINER_ROOT:-/var/www/html}"

payload=$(cat)
f=$(extract_file_path "$payload")

# Normalize src_prefix to end with a slash if it doesn't already
[[ "$src_prefix" != */ ]] && src_prefix="${src_prefix}/"

# Only handle PHP files.
case "$f" in
  *.php) ;;
  *) exit 0 ;;
esac

# Only handle files that are mounted into the container.
case "$f" in
  "${src_prefix}"*) rel="${f#"${src_prefix}"}" ;;
  *) exit 0 ;;
esac

# Skip silently if the container is missing or not running. Don't
# block the user's flow because their dev container was stopped.
if [ -z "$container" ] \
   || ! docker ps --format '{{.Names}}' 2>/dev/null | grep -qx "$container"; then
  exit 0
fi

target="${container_root%/}/${rel}"

if ! out=$(docker exec --workdir "$container_root" "$container" \
            sh -c '[ -f ./vendor/bin/pint ] && ./vendor/bin/pint "$1" 2>&1; php -l "$1" 2>&1' \
            _ "$target" 2>&1); then
  echo "$out" >&2
  exit 1
fi

exit 0
