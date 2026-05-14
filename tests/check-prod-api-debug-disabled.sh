#!/usr/bin/env bash
set -euo pipefail

files=(
  environments/prod/api/web/index.php
  environments/prod-docker/api/web/index.php
  environments/prod-railway/api/web/index.php
)

for file in "${files[@]}"; do
  if grep -n "define('YII_DEBUG', true)" "$file"; then
    echo "$file enables Yii debug in production." >&2
    exit 1
  fi

  grep -q "define('YII_DEBUG', false)" "$file"
  grep -q "define('YII_ENV', 'prod')" "$file"
done
