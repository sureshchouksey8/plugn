#!/usr/bin/env bash
set -euo pipefail

target="api/modules/v2/controllers/payment/UpaymentController.php"

if grep -n 'wrong track id' "$target"; then
  echo "UPayment callback must not echo raw gateway validation failures." >&2
  exit 1
fi

if grep -n 'die();' "$target"; then
  echo "UPayment callback must not terminate with die() paths." >&2
  exit 1
fi

grep -q 'UPayment callback rejected because the gateway status lookup did not validate the track id' "$target"
grep -q "payment-failed" "$target"

if ! grep -Fq '($response['\''status'\''] ?? null) !== "1"' "$target"; then
  echo "UPayment callback must fail closed when gateway status is empty or malformed." >&2
  exit 1
fi

grep -Fq "'gateway_status' => \$response['status'] ?? null" "$target"
