#!/bin/sh

# Database configuration for dev-krushn-docker
MYSQL_HOST="mysql"  # Using service name from docker-compose
MYSQL_PORT=3306
MYSQL_USER="plugnuser"
MYSQL_PASSWORD="plugn"  # Default root password, update if different
MYSQL_DATABASE="plugn"  # Update with your actual database name

echo "Waiting for MySQL at $MYSQL_HOST:$MYSQL_PORT..."
for attempt in $(seq 1 60); do
  mysql --ssl=0 -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SELECT 1" >/dev/null 2>&1 && break
  echo "Attempt $attempt: retrying in 1s..."
  sleep 1
  [ "$attempt" -eq 60 ] && echo "MySQL not responding." && exit 1
done

echo "Converting database tables to utf8mb4 for emoji support..."

# Update order table columns
mysql --ssl=0 -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" -e "
  ALTER TABLE \`order\`
  MODIFY special_directions VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  MODIFY order_instruction VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

  ALTER TABLE \`item\`
  MODIFY item_description VARCHAR(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  MODIFY item_description_ar VARCHAR(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"