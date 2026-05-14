#!/usr/bin/env bash
set -euo pipefail

file=environments/prod-railway/common/config/main-local.php

if command -v php >/dev/null 2>&1; then
  php -l "$file" >/dev/null
else
  echo "php not found; skipping PHP lint and running static prod-railway common config checks." >&2
fi

required_vars=(
  PLUGN_RAILWAY_DB_DSN
  PLUGN_RAILWAY_DB_USERNAME
  PLUGN_RAILWAY_DB_PASSWORD
  PLUGN_RAILWAY_SQS_REGION
  PLUGN_RAILWAY_SQS_KEY
  PLUGN_RAILWAY_SQS_SECRET
  PLUGN_RAILWAY_SQS_QUEUE
  PLUGN_WALLET_API_KEY
  PLUGN_WALLET_API_ENDPOINT
  PLUGN_RAILWAY_S3_REGION
  PLUGN_RAILWAY_S3_BUCKET
  PLUGN_RAILWAY_S3_KEY
  PLUGN_RAILWAY_S3_SECRET
  PLUGN_SENTRY_DSN
  PLUGN_REDIS_HOST
  PLUGN_REDIS_USERNAME
  PLUGN_REDIS_PASSWORD
  PLUGN_REDIS_PORT
  PLUGN_REDIS_DATABASE
  PLUGN_SMTP_HOST
  PLUGN_SMTP_USERNAME
  PLUGN_SMTP_PASSWORD
  PLUGN_SMTP_PORT
  PLUGN_BLOG_MANAGER_API_ENDPOINT
  PLUGN_BLOG_MANAGER_TOKEN
  PLUGN_GPT_TOKEN
  PLUGN_GPT_API_ENDPOINT
)

for var in "${required_vars[@]}"; do
  if ! grep -q "$var" "$file"; then
    echo "$file must read $var from the environment." >&2
    exit 1
  fi
done

if ! grep -q "RuntimeException" "$file"; then
  echo "$file must fail fast when required prod-railway env vars are missing." >&2
  exit 1
fi

if grep -nE "AKIA[[:alnum:]]{16}|SG\\.[A-Za-z0-9_-]+\\.|https://[0-9a-f]{32}:[0-9a-f]{32}@|['\"]password['\"][[:space:]]*=>[[:space:]]*['\"][^'\"]+['\"]|['\"]secret['\"][[:space:]]*=>[[:space:]]*['\"][^'\"]+['\"]|['\"]apiKey['\"][[:space:]]*=>[[:space:]]*['\"][^'\"]+['\"]|['\"]token['\"][[:space:]]*=>[[:space:]]*['\"][^'\"]+['\"]|\"sqsKey\"[[:space:]]*=>[[:space:]]*\"[^\"]+\"|\"sqsSecret\"[[:space:]]*=>[[:space:]]*\"[^\"]+\"" "$file"; then
  echo "$file must not contain committed credential-shaped literals." >&2
  exit 1
fi

if ! grep -q "tests/check-prod-railway-common-env.sh" PRODUCTION_READINESS.md; then
  echo "PRODUCTION_READINESS.md must reference this validation script." >&2
  exit 1
fi
