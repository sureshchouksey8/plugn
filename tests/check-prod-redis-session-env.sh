#!/usr/bin/env bash
set -euo pipefail

files=(
  environments/prod-railway/common/config/main-local.php
  environments/prod-railway/backend/config/main-local.php
  environments/prod-railway/frontend/config/main-local.php
  environments/prod-railway/partner/config/main-local.php
)

if command -v php >/dev/null 2>&1; then
  for file in "${files[@]}"; do
    php -l "$file" >/dev/null
  done
else
  echo "php not found; skipping PHP lint and running static Redis config checks." >&2
fi

if grep -RInE "'password'[[:space:]]*=>[[:space:]]*['\"][^'$]" "${files[@]}"; then
  echo "prod-railway Redis password must not be a committed literal." >&2
  exit 1
fi

for file in "${files[@]}"; do
  if ! grep -q "PLUGN_REDIS_PASSWORD" "$file"; then
    echo "$file must read the Redis password from PLUGN_REDIS_PASSWORD." >&2
    exit 1
  fi

  if ! grep -q "RuntimeException" "$file"; then
    echo "$file must fail fast when PLUGN_REDIS_PASSWORD is missing." >&2
    exit 1
  fi
done

if ! grep -q "PLUGN_REDIS_PASSWORD" PRODUCTION_READINESS.md; then
  echo "PRODUCTION_READINESS.md must document PLUGN_REDIS_PASSWORD." >&2
  exit 1
fi

if ! grep -q "tests/check-prod-redis-session-env.sh" PRODUCTION_READINESS.md; then
  echo "PRODUCTION_READINESS.md must reference this validation script." >&2
  exit 1
fi
