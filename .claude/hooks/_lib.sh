#!/usr/bin/env bash
# Shared helpers for Claude Code hooks.
# Usage: . "$(dirname "${BASH_SOURCE[0]}")/_lib.sh"

# extract_file_path JSON
# Reads tool_input.file_path from a JSON string and normalizes the result:
#   - Windows / UNC paths (\\wsl.localhost\...) → Linux paths via wslpath -u
# Prints the Linux path; empty string on failure.
extract_file_path() {
  local path
  path=$(printf '%s' "$1" | python3 -c '
import json, sys
try:
    d = json.load(sys.stdin)
except Exception:
    sys.exit(0)
print((d.get("tool_input") or {}).get("file_path") or "")
' 2>/dev/null)

  if [ -n "$path" ] && command -v wslpath &>/dev/null; then
    path=$(wslpath -u "$path" 2>/dev/null || printf '%s' "$path")
  fi

  printf '%s' "$path"
}
