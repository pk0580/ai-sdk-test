#!/usr/bin/env bash
# PreToolUse hook for Write|Edit. Blocks the tool call when the new
# file content looks like it contains a hard-coded secret, .env file
# leak, or a checked-in credential.
#
# Errors are written to stderr; non-zero exit blocks the tool call.

set -u

payload=$(cat)

f=$(python3 -c '
import json, sys
try:
    d = json.loads(sys.argv[1])
except Exception:
    sys.exit(0)
print((d.get("tool_input") or {}).get("file_path") or "")
' "$payload" 2>/dev/null)

content=$(python3 -c '
import json, sys
try:
    d = json.loads(sys.argv[1])
except Exception:
    sys.exit(0)
ti = d.get("tool_input") or {}
print(ti.get("content") or ti.get("new_string") or "")
' "$payload" 2>/dev/null)

# 1. Hard block on obvious secret-looking files.
case "$f" in
  *.env|*.env.local|*.env.production|*.env.*|*id_rsa*|*id_dsa*|*.pem|*.key|*credentials.json|*secrets.json)
    echo "secret-guard: refusing to write to '$f' — looks like a secret file." >&2
    exit 1
    ;;
esac

# 2. Pattern-based heuristics on the new content.
if [ -n "$content" ]; then
  if printf '%s' "$content" | grep -Eq \
       '(AKIA[0-9A-Z]{16}|ASIA[0-9A-Z]{16}|AIza[0-9A-Za-z_-]{35}|sk_live_[0-9A-Za-z]{24,}|xox[baprs]-[0-9A-Za-z-]{10,}|ghp_[0-9A-Za-z]{36}|github_pat_[0-9A-Za-z_]{82}|-----BEGIN (RSA|EC|OPENSSH|PRIVATE) KEY-----)'
  then
    echo "secret-guard: refusing to write '$f' — content matches a known secret pattern." >&2
    exit 1
  fi
fi

exit 0
