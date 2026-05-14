#!/usr/bin/env bash
set -euo pipefail

compose_file="docker-compose-local.yml"
dump_file="docker-entrypoint-initdb.d/plugn.sql"
deployment_script="environments/dev-krushn-docker/deployments/05_Aug_2025_fix_emoji_encoding_01.sh"

missing=0
for file in "$compose_file" "$dump_file" "$deployment_script"; do
  if [[ ! -f "$file" ]]; then
    echo "Missing required local Docker DB import file: $file" >&2
    missing=1
  fi
done

if [[ "$missing" -ne 0 ]]; then
  exit 1
fi

if ! grep -q "image: mysql:5.7.44" "$compose_file"; then
  echo "Local Docker MySQL image must stay pinned to mysql:5.7.44 for the bundled MySQL 5.7 dump." >&2
  exit 1
fi

if grep -n "SET GLOBAL FOREIGN_KEY_CHECKS" "$dump_file"; then
  echo "The bundled MySQL dump must use session-level FOREIGN_KEY_CHECKS, not GLOBAL." >&2
  exit 1
fi

if ! grep -q "SET FOREIGN_KEY_CHECKS = 0;" "$dump_file" || ! grep -q "SET FOREIGN_KEY_CHECKS = 1;" "$dump_file"; then
  echo "The bundled MySQL dump must disable and re-enable session-level FOREIGN_KEY_CHECKS." >&2
  exit 1
fi

mysql_call_count="$(grep -c "mysql --ssl=0" "$deployment_script" || true)"
if [[ "$mysql_call_count" -lt 2 ]]; then
  echo "Local Docker deployment MySQL client calls must disable SSL for the compose MySQL connection." >&2
  exit 1
fi

echo "local Docker DB import compatibility checks passed"
