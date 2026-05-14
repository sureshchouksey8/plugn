#!/usr/bin/env bash
set -euo pipefail

files=(
  environments/prod/api/config/main-local.php
  environments/prod/frontend/config/main-local.php
  environments/prod/backend/config/main-local.php
  environments/prod/partner/config/main-local.php
  environments/prod/agent/config/main-local.php
  environments/prod/crm/config/main-local.php
  environments/prod/shortner/config/main-local.php
  environments/prod/remail/config/main-local.php
  environments/prod/common/config/cookie-validation-key.php
  environments/prod-docker/api/config/main-local.php
  environments/prod-docker/frontend/config/main-local.php
  environments/prod-docker/backend/config/main-local.php
  environments/prod-docker/partner/config/main-local.php
  environments/prod-docker/agent/config/main-local.php
  environments/prod-docker/crm/config/main-local.php
  environments/prod-docker/shortner/config/main-local.php
  environments/prod-docker/remail/config/main-local.php
  environments/prod-docker/common/config/cookie-validation-key.php
  environments/prod-railway/api/config/main-local.php
  environments/prod-railway/frontend/config/main-local.php
  environments/prod-railway/backend/config/main-local.php
  environments/prod-railway/partner/config/main-local.php
  environments/prod-railway/agent/config/main-local.php
  environments/prod-railway/crm/config/main-local.php
  environments/prod-railway/shortner/config/main-local.php
  environments/prod-railway/remail/config/main-local.php
  environments/prod-railway/common/config/cookie-validation-key.php
)

helpers=(
  environments/prod/common/config/cookie-validation-key.php
  environments/prod-docker/common/config/cookie-validation-key.php
  environments/prod-railway/common/config/cookie-validation-key.php
)

if command -v php >/dev/null 2>&1; then
  for file in "${files[@]}"; do
    php -l "$file" >/dev/null
  done
else
  echo "php not found; skipping PHP lint and running static cookie key checks." >&2
fi

if grep -RInE "'cookieValidationKey'[[:space:]]*=>[[:space:]]*('IPzstcYT6LrNZ7AsUzf8Zz5XtEtX1'|''|\"\")" "${files[@]}"; then
  echo "Production cookieValidationKey values must come from environment variables." >&2
  exit 1
fi

for app in api frontend backend partner agent crm shortner remail; do
  variable="PLUGN_$(printf '%s' "$app" | tr '[:lower:]-' '[:upper:]_')_COOKIE_VALIDATION_KEY"
  for helper in "${helpers[@]}"; do
    if ! grep -q "$variable" "$helper"; then
      echo "Missing $variable support in $helper." >&2
      exit 1
    fi
  done
done

for helper in "${helpers[@]}"; do
  if ! grep -q "PLUGN_COOKIE_VALIDATION_KEY" "$helper"; then
    echo "Missing PLUGN_COOKIE_VALIDATION_KEY fallback in $helper." >&2
    exit 1
  fi
done

if ! grep -q "PLUGN_COOKIE_VALIDATION_KEY" PRODUCTION_READINESS.md; then
  echo "PRODUCTION_READINESS.md must document PLUGN_COOKIE_VALIDATION_KEY." >&2
  exit 1
fi
if ! grep -q "PLUGN_REMAIL_COOKIE_VALIDATION_KEY" PRODUCTION_READINESS.md; then
  echo "PRODUCTION_READINESS.md must document PLUGN_REMAIL_COOKIE_VALIDATION_KEY." >&2
  exit 1
fi
if ! grep -q "tests/check-prod-cookie-validation-env.sh" PRODUCTION_READINESS.md; then
  echo "PRODUCTION_READINESS.md must reference this validation script." >&2
  exit 1
fi
