#!/usr/bin/env bash
set -euo pipefail

target="api/modules/v2/controllers/payment/UpaymentController.php"

if grep -n 'wrong track id' "$target"; then
  echo "UPayment callback must not echo raw gateway validation failures." >&2
  exit 1
fi

if grep -n 'echo "wrong track id' "$target" || grep -n 'die();' "$target"; then
  echo "UPayment callback must not terminate with raw echo/die paths." >&2
  exit 1
fi

grep -q 'UPayment callback rejected because the gateway status lookup did not validate the track id' "$target"
grep -q "payment-failed" "$target"
