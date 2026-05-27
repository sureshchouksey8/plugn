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

if ! grep -q 'UPayment callback rejected because the gateway status lookup did not validate the track id' <<<"$callback_block"; then
  echo "UPayment callback must log the structured gateway rejection warning." >&2
  exit 1
fi

if ! grep -q "payment-failed" <<<"$callback_block"; then
  echo "UPayment callback must redirect rejected callbacks to the payment-failed flow." >&2
  exit 1
fi

if ! grep -Fq 'is_array($response)' <<<"$callback_block"; then
  echo "UPayment callback must reject non-array gateway responses." >&2
  exit 1
fi

if ! grep -Fq '$gatewayStatus !== "1"' <<<"$callback_block"; then
  echo "UPayment callback must fail closed when gateway status is missing, empty, or invalid." >&2
  exit 1
fi

if ! grep -Fq "'gateway_status' => \$gatewayStatus" <<<"$callback_block"; then
  echo "UPayment callback must log gateway_status in the rejection warning." >&2
  exit 1
fi

if ! grep -Fq "'gateway_message' => \$gatewayMessage" <<<"$callback_block"; then
  echo "UPayment callback must log gateway_message in the rejection warning." >&2
  exit 1
fi
