#!/usr/bin/env bash
set -euo pipefail

files=(
  environments/prod/agent/web/index.php
  environments/prod/api/web/index.php
  environments/prod/backend/web/index.php
  environments/prod/crm/web/index.php
  environments/prod/frontend/web/index.php
  environments/prod/partner/web/index.php
  environments/prod/remail/web/index.php
  environments/prod/shortner/web/index.php
  environments/prod-docker/agent/web/index.php
  environments/prod-docker/api/web/index.php
  environments/prod-docker/backend/web/index.php
  environments/prod-docker/crm/web/index.php
  environments/prod-docker/frontend/web/index.php
  environments/prod-docker/partner/web/index.php
  environments/prod-docker/remail/web/index.php
  environments/prod-docker/shortner/web/index.php
  environments/prod-railway/agent/web/index.php
  environments/prod-railway/api/web/index.php
  environments/prod-railway/backend/web/index.php
  environments/prod-railway/crm/web/index.php
  environments/prod-railway/frontend/web/index.php
  environments/prod-railway/partner/web/index.php
  environments/prod-railway/remail/web/index.php
  environments/prod-railway/shortner/web/index.php
)

for file in "${files[@]}"; do
  if grep -n "define('YII_DEBUG', true)" "$file"; then
    echo "$file enables Yii debug in production." >&2
    exit 1
  fi

  grep -q "define('YII_DEBUG', false)" "$file"
  grep -q "define('YII_ENV', 'prod')" "$file"
done
