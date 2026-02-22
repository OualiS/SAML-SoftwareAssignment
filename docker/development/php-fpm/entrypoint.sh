#!/usr/bin/env bash
set -euo pipefail

if [ ! -f /var/www/.env ] && [ -f /var/www/.env.example ]; then
  cp /var/www/.env.example /var/www/.env
fi

exec "$@"
