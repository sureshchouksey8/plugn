#!/usr/bin/env sh
set -eu

docker compose -f docker-compose-local.yml -p plugn-local-server run --rm app vendor/bin/codecept run --fail-fast --html report-web.html
