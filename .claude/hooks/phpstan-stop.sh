#!/usr/bin/env bash
# Stop hook: runs PHPStan / Larastan over the project at the end of
# a turn. Silent on success; errors go to stderr so Claude sees them
# and can iterate. Disabled if no phpstan binary or no phpstan config
# is present.
#
# Override behaviour via env:
#   CLAUDE_PHP_CONTAINER     — run inside this docker container
#   CLAUDE_PHPSTAN_LEVEL     — phpstan level (default: 8)
#   CLAUDE_PHPSTAN_MEMORY    — memory limit (default: 512M)

set -u

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
project_root="$(dirname "$project_root")"

container="${CLAUDE_PHP_CONTAINER:-}"
level="${CLAUDE_PHPSTAN_LEVEL:-8}"
memory="${CLAUDE_PHPSTAN_MEMORY:-512M}"

has_config() {
  for f in phpstan.neon phpstan.neon.dist phpstan.dist.neon; do
    [ -f "${project_root}/${f}" ] && return 0
  done
  return 1
}

if ! has_config; then
  exit 0
fi

if [ -n "$container" ] \
   && docker ps --format '{{.Names}}' 2>/dev/null | grep -qx "$container"; then
  out=$(docker exec "$container" sh -c \
    "./vendor/bin/phpstan analyse --no-progress --memory-limit=${memory} --level=${level}" 2>&1)
  rc=$?
elif [ -x "${project_root}/vendor/bin/phpstan" ]; then
  out=$(cd "$project_root" \
        && ./vendor/bin/phpstan analyse \
            --no-progress --memory-limit="${memory}" --level="${level}" 2>&1)
  rc=$?
else
  exit 0
fi

if [ "$rc" -ne 0 ]; then
  printf 'PHPStan failed:\n%s\n' "$out" >&2
  exit 1
fi

exit 0
