#!/usr/bin/env bash
set -euo pipefail

echo "Updating git repository..."
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    if git remote get-url origin >/dev/null 2>&1; then
        git pull --ff-only
    else
        echo "No git remote configured; skipping git pull."
    fi
else
    echo "Not a git repository; skipping git pull."
fi

if command -v composer >/dev/null 2>&1; then
    echo "Installing PHP dependencies..."
    composer install --no-interaction --prefer-dist --no-progress
else
    echo "Composer not found; skipping PHP dependencies installation."
fi

if [ -f package.json ]; then
    if command -v npm >/dev/null 2>&1; then
        echo "Installing Node dependencies..."
        npm install --no-progress
    else
        echo "npm not found; skipping Node dependencies installation."
    fi
fi
