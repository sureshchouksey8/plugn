#!/usr/bin/env bash
set -euo pipefail

target="common/config/main.php"

required_envs=(
  CLOUDINARY_API_KEY
  CLOUDINARY_API_SECRET
  IPSTACK_ACCESS_KEY
  RECAPTCHA_SECRET_KEY
  TEMPORARY_BUCKET_ACCESS_KEY_ID
  TEMPORARY_BUCKET_SECRET_ACCESS_KEY
  TAP_PLUGN_LIVE_API_KEY
  TAP_PLUGN_TEST_API_KEY
  TAP_PLUGN_DESTINATION_ID
  MYFATOORAH_KUWAIT_LIVE_API_KEY
  MYFATOORAH_KUWAIT_TEST_API_KEY
  MYFATOORAH_SAUDI_LIVE_API_KEY
  GOOGLE_MAPS_API_KEY
  NETLIFY_TOKEN
  PLUGN_GITHUB_TOKEN
  SLACK_ACTIVITY_WEBHOOK_URL
  SLACK_TAP_OPERATION_WEBHOOK_URL
  SLACK_ERROR_WEBHOOK_URL
)

for env_name in "${required_envs[@]}"; do
  if ! grep -Fq "\$env('$env_name')" "$target"; then
    echo "Missing runtime env lookup for $env_name" >&2
    exit 1
  fi
done

blocked_patterns=(
  "ghp_"
  "nfp_"
  "sk_live_"
  "sk_test_"
  "hooks.slack.com/services/"
  "AKIA"
  "AIza"
  "699963168546398"
  "SH2PbVs"
  "911bdd76f42e7f"
  "6LcEKx8p"
  "fac3c2117d877e078e3e8fa7839d8204"
)

for pattern in "${blocked_patterns[@]}"; do
  if grep -Fq "$pattern" "$target"; then
    echo "Found checked-in shared credential pattern in $target: $pattern" >&2
    exit 1
  fi
done
