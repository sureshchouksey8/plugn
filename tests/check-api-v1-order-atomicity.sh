#!/usr/bin/env sh
set -eu

controller="api/modules/v1/controllers/OrderController.php"

grep -q 'Yii::\$app->db->beginTransaction()' "$controller"
grep -q '\$transaction->commit();' "$controller"
grep -q '\$transaction->rollBack();' "$controller"
grep -q '\$orderAssemblyCommitted = true;' "$controller"
grep -q '!\$orderAssemblyCommitted && \$transaction->getIsActive()' "$controller"
grep -q '\$orderAssemblyCommitted && is_array(\$response)' "$controller"
grep -q 'if (\$restaurant) {' "$controller"

if grep -n 'if (!\$order->updateOrderTotalPrice())' "$controller" | grep -q .; then
    start_line=$(grep -n 'if (!\$order->updateOrderTotalPrice())' "$controller" | head -1 | cut -d: -f1)
    end_line=$((start_line + 8))
    if sed -n "${start_line},${end_line}p" "$controller" | grep -q 'return \['; then
        echo "v1 order total failure still returns before transaction cleanup" >&2
        exit 1
    fi
fi

echo "api v1 order atomicity guard passed"
