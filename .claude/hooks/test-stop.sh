#!/usr/bin/env bash
# Stop hook: runs the project's PHP test suite at the end of a turn.
# Detects Pest / PHPUnit / artisan test automatically. Silent on
# success; errors go to stderr so Claude sees them and can iterate.
#
# Skip the run by setting CLAUDE_SKIP_TESTS=1 (useful while drafting
# WIP code).
#
# Override the test runner inside docker via:
#   CLAUDE_PHP_CONTAINER=my_php_container

set -u

if [ "${CLAUDE_SKIP_TESTS:-0}" = "1" ]; then
  exit 0
fi

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
project_root="$(dirname "$project_root")"

container="${CLAUDE_PHP_CONTAINER:-}"

pick_command() {
  if [ -x "${project_root}/vendor/bin/pest" ]; then
    echo './vendor/bin/pest --parallel --bail'
    return
  fi
  if [ -f "${project_root}/artisan" ]; then
    echo 'php artisan test --parallel --stop-on-failure'
    return
  fi
  if [ -x "${project_root}/vendor/bin/phpunit" ]; then
    echo './vendor/bin/phpunit --stop-on-failure'
    return
  fi
  echo ''
}

cmd=$(pick_command)
[ -z "$cmd" ] && exit 0

if [ -n "$container" ] \
   && docker ps --format '{{.Names}}' 2>/dev/null | grep -qx "$container"; then
  out=$(docker exec "$container" sh -c "$cmd" 2>&1)
  rc=$?
else
  out=$(cd "$project_root" && sh -c "$cmd" 2>&1)
  rc=$?
fi

if [ "$rc" -ne 0 ]; then
  printf 'Tests failed:\n%s\n' "$out" >&2
  exit 1
fi

exit 0
