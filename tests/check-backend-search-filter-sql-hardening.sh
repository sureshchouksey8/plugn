#!/bin/sh
set -eu

payment_search="backend/models/PaymentSearch.php"
order_search="backend/models/OrderSearch.php"
restaurant_search="backend/models/RestaurantSearch.php"

grep -q "DATE(payment_created_at) >= DATE(:date_from)" "$payment_search"
grep -q "DATE(payment_created_at) <= DATE(:date_to)" "$payment_search"
grep -q "':date_from' => \$this->date_from" "$payment_search"
grep -q "':date_to' => \$this->date_to" "$payment_search"

grep -q "NUMERIC_FILTER_PATTERN" "$order_search"
grep -q "NUMERIC_FILTER_PATTERN" "$restaurant_search"
grep -q "applyNumericFilter(\$query, 'total_price', \$this->total_price)" "$order_search"
grep -q "applyNumericFilter(\$query, 'total_orders', \$this->total_orders)" "$restaurant_search"
grep -q "\$query->andWhere('0=1')" "$order_search"
grep -q "\$query->andWhere('0=1')" "$restaurant_search"

if grep -n 'DATE(payment_created_at).*\\.\\$this->date_' "$payment_search"; then
  echo "PaymentSearch date filters must not concatenate request values into SQL." >&2
  exit 1
fi

if grep -n 'new Expression("total_price " \\.\\|new Expression("total_orders " \\.' "$order_search" "$restaurant_search"; then
  echo "Backend numeric search filters must not concatenate request values into SQL expressions." >&2
  exit 1
fi

echo "PASS backend search filter SQL hardening checks"
