#!/usr/bin/env sh
set -eu

fail() {
  echo "payment callback hardening check failed: $1" >&2
  exit 1
}

upayment_controller="api/modules/v2/controllers/payment/UpaymentController.php"
tabby_controller="api/modules/v2/controllers/payment/TabbyController.php"
tabby_model="common/models/Tabby.php"
order_history_model="common/models/OrderHistory.php"

grep -q "BadRequestHttpException('Invalid payment callback status.')" "$upayment_controller" \
  || fail "Upayment invalid callback status must be rejected through Yii exception handling"

grep -Fq 'empty($response) || !isset($response' "$upayment_controller" \
  || fail "Upayment callback must reject empty or malformed gateway responses"

if grep -Fq 'wrong track id?' "$upayment_controller" \
  || grep -Fq 'echo "wrong track id' "$upayment_controller" \
  || grep -Fq 'die();' "$upayment_controller"; then
  fail "Upayment callback must not echo/die on invalid gateway status"
fi

grep -q "Unable to persist Tabby webhook transaction" "$tabby_controller" \
  || fail "Tabby webhook save failures must return a structured error response"

grep -Fq '$tabbyTransactionPersisted = false;' "$tabby_controller" \
  || fail "Tabby checkout persistence failures must mark the local transaction as unpersisted"

awk '
  /Unable to update Tabby transaction after checkout authorization/ { in_failure = 1 }
  in_failure && /return \$json;/ { found_return = 1 }
  in_failure && /OrderHistory::addOrderHistory/ { reached_history = 1; exit }
  END { exit (in_failure && reached_history && !found_return ? 0 : 1) }
' "$tabby_controller" \
  || fail "Tabby checkout persistence failures must continue into the existing redirect flow without adding order history first"

grep -Fq 'if ($tabbyTransactionPersisted) {' "$tabby_controller" \
  || fail "Tabby checkout order history and capture must be gated on transaction persistence"

grep -Fq '$dbTransaction = Yii::$app->db->beginTransaction();' "$tabby_controller" \
  || fail "Tabby webhook callback must wrap persistence in a database transaction"

grep -Fq '$dbTransaction->commit();' "$tabby_controller" \
  || fail "Tabby webhook callback must commit transaction and order history together"

grep -Fq '$dbTransaction->rollBack();' "$tabby_controller" \
  || fail "Tabby webhook callback must roll back persistence failures"

grep -Fq "throw new \\RuntimeException('Unable to update order status while adding order history.')" "$order_history_model" \
  || fail "OrderHistory status update failures must throw into caller transaction handling"

grep -Fq "throw new \\RuntimeException('Unable to save order history.')" "$order_history_model" \
  || fail "OrderHistory save failures must throw into caller transaction handling"

if awk '/public static function addOrderHistory/,/^    }/' "$order_history_model" | grep -Fq 'print_r'; then
  fail "OrderHistory::addOrderHistory must not print inside transaction callers"
fi

if awk '/public static function addOrderHistory/,/^    }/' "$order_history_model" | grep -Fq 'die('; then
  fail "OrderHistory::addOrderHistory must not print/die inside transaction callers"
fi

if grep -Fq 'print_r($tt->errors)' "$tabby_controller" \
  || grep -Fq 'die();' "$tabby_controller"; then
  fail "Tabby webhook callback must not print/die on transaction persistence errors"
fi

awk '
  /Unable to create Tabby transaction\./ { in_create_failure = 1; lines = 0 }
  in_create_failure {
    if (/return false;/) { found = 1; exit }
    if (lines++ > 12) { exit }
  }
  END { exit found ? 0 : 1 }
' "$tabby_model" \
  || fail "Tabby create transaction persistence failures must return false"

awk '
  /Unable to update Tabby transaction\./ { in_update_failure = 1; lines = 0 }
  in_update_failure {
    if (/return false;/) { found = 1; exit }
    if (lines++ > 12) { exit }
  }
  END { exit found ? 0 : 1 }
' "$tabby_model" \
  || fail "Tabby update transaction persistence failures must return false"

if grep -Fq 'echo "<pre' "$tabby_model" \
  || grep -Fq 'print_r($tt->errors)' "$tabby_model" \
  || grep -Fq 'die();' "$tabby_model"; then
  fail "Tabby model must not echo/print/die on transaction persistence errors"
fi

echo "payment callback hardening check passed"
