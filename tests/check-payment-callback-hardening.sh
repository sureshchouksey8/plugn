#!/usr/bin/env sh
set -eu

fail() {
  echo "payment callback hardening check failed: $1" >&2
  exit 1
}

upayment_controller="api/modules/v2/controllers/payment/UpaymentController.php"
tabby_controller="api/modules/v2/controllers/payment/TabbyController.php"
tabby_model="common/models/Tabby.php"

grep -q "BadRequestHttpException('Invalid payment callback status.')" "$upayment_controller" \
  || fail "Upayment invalid callback status must be rejected through Yii exception handling"

grep -Fq '!is_array($response)' "$upayment_controller" \
  || fail "Upayment callback must reject empty or malformed gateway responses"

if grep -Fq 'wrong track id?' "$upayment_controller" \
  || grep -Fq 'echo "wrong track id' "$upayment_controller" \
  || grep -Fq 'die();' "$upayment_controller"; then
  fail "Upayment callback must not echo/die on invalid gateway status"
fi

grep -q "Unable to persist Tabby webhook transaction" "$tabby_controller" \
  || fail "Tabby webhook save failures must return a structured error response"

grep -Fq 'return $json;' "$tabby_controller" \
  || fail "Tabby checkout persistence failures must stop before capture"

if grep -Fq 'print_r($tt->errors)' "$tabby_controller" \
  || grep -Fq 'die();' "$tabby_controller"; then
  fail "Tabby webhook callback must not print/die on transaction persistence errors"
fi

grep -q "return false;" "$tabby_model" \
  || fail "Tabby transaction persistence failures must return false"

if grep -Fq 'echo "<pre' "$tabby_model" \
  || grep -Fq 'print_r($tt->errors)' "$tabby_model" \
  || grep -Fq 'die();' "$tabby_model"; then
  fail "Tabby model must not echo/print/die on transaction persistence errors"
fi

echo "payment callback hardening check passed"
