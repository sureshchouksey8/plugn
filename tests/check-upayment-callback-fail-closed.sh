#!/usr/bin/env bash
set -euo pipefail

target="api/modules/v2/controllers/payment/UpaymentController.php"

callback_block="$(awk '
  /public function actionCallback\(\)/{in_block=1}
  in_block{print}
  in_block && /^    }$/ {exit}
' "$target")"

if grep -q 'wrong track id' <<<"$callback_block"; then
  echo "UPayment callback must not echo raw gateway validation failures." >&2
  exit 1
fi

if grep -q 'die();' <<<"$callback_block"; then
  echo "UPayment callback must not terminate with die() paths." >&2
  exit 1
fi

grep -q 'UPayment callback rejected because the gateway status lookup did not validate the track id' <<<"$callback_block"
grep -q "payment-failed" <<<"$callback_block"

if ! grep -Fq '($response['\''status'\''] ?? null) !== "1"' <<<"$callback_block"; then
  echo "UPayment callback must fail closed when gateway status is empty or malformed." >&2
  exit 1
fi

grep -Fq "'gateway_status' => \$response['status'] ?? null" <<<"$callback_block"

