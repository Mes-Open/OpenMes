#!/usr/bin/env bash
# Pobiera przenośne runtime'y wbudowywane w instalatory (NIE są commitowane):
#   - Linux:   static PHP 8.3 (static-php.dev)            -> src-tauri/resources/php/php
#   - Windows: oficjalny PHP 8.3 NTS (windows.php.net)    -> winphp-staging/php/
#   - Windows: PostgreSQL 16 (zonky embedded)             -> winphp-staging/pgsql/
# Uruchom raz po sklonowaniu, przed buildem.
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$HERE"

PHP_LINUX_URL="https://dl.static-php.dev/static-php-cli/common/php-8.3.31-cli-linux-x86_64.tar.gz"
PHP_WIN_URL="https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip"
PG_WIN_URL="https://repo1.maven.org/maven2/io/zonky/test/postgres/embedded-postgres-binaries-windows-amd64/16.4.0/embedded-postgres-binaries-windows-amd64-16.4.0.jar"

# ── Linux PHP (do buildu Linux) ──────────────────────────────────────────────
echo "[1/3] Linux static PHP…"
mkdir -p src-tauri/resources/php
if [ ! -f src-tauri/resources/php/php ]; then
  curl -fL --retry 2 -o /tmp/php-linux.tar.gz "$PHP_LINUX_URL"
  tar xzf /tmp/php-linux.tar.gz -C src-tauri/resources/php && rm /tmp/php-linux.tar.gz
  chmod +x src-tauri/resources/php/php
fi

# ── Windows PHP (do buildu Windows) ──────────────────────────────────────────
echo "[2/3] Windows PHP…"
mkdir -p winphp-staging
if [ ! -f winphp-staging/php/php.exe ]; then
  curl -fL --retry 2 -o /tmp/php-win.zip "$PHP_WIN_URL"
  rm -rf winphp-staging/php && unzip -q /tmp/php-win.zip -d winphp-staging/php && rm /tmp/php-win.zip
fi

# ── Windows PostgreSQL (zonky) ───────────────────────────────────────────────
echo "[3/3] Windows PostgreSQL…"
if [ ! -f winphp-staging/pgsql/bin/postgres.exe ]; then
  curl -fL --retry 2 -o /tmp/pg-win.jar "$PG_WIN_URL"
  unzip -o -q /tmp/pg-win.jar postgres-windows-x86_64.txz -d /tmp
  mkdir -p winphp-staging/pgsql
  tar xf /tmp/postgres-windows-x86_64.txz -C winphp-staging/pgsql
  rm /tmp/pg-win.jar /tmp/postgres-windows-x86_64.txz
fi

echo "Gotowe. Linux PHP + Windows PHP + Windows Postgres pobrane."
echo "Build Linux:   bash scripts/bundle-resources.sh && npm run tauri build"
echo "Build Windows: bash scripts/build-windows.sh"
