#!/usr/bin/env bash
set -euo pipefail
if [[ $# -ne 1 ]]; then
  echo "Usage: $0 <html-file>" >&2
  exit 1
fi
file=$1
if [[ ! -f "$file" ]]; then
  echo "File not found: $file" >&2
  exit 1
fi
# Remove class/style attributes regardless of spacing, quote style, or casing.
# Example matches: class="x" class = 'x' CLASS=x style="color:red"
perl -0pi -e 's/\s+(?i:(class|style))\s*=\s*(?:"[^"]*"|'"'"'[^'"'"']*'"'"'|[^\s>]+)//g' "$file"
