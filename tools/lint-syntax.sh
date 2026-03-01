#!/usr/bin/env bash
set -euo pipefail

find . -type f -name "*.php" \
  -not -path "./vendor/*" \
  -not -path "./node_modules/*" \
  -not -path "./storage/*" \
  -print0 | xargs -0 -n1 -P4 php -l
