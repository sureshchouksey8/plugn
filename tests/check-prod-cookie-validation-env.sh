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
  if ! grep -q "$variable" environments/prod/common/config/cookie-validation-key.php; then
    echo "Missing $variable support in production cookie validation helper." >&2
    exit 1
  fi
done

grep -q "PLUGN_COOKIE_VALIDATION_KEY" environments/prod/common/config/cookie-validation-key.php
