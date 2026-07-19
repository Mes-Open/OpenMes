#!/usr/bin/env bash
# Buduje samodzielny instalator Windows z wbudowanym:
#  - PHP (oficjalny windows.php.net, ma pdo_pgsql)
#  - PostgreSQL (przenośny, zonky embedded)
#  - backendem OpenMES
# Resources wstrzykiwane przez --config; domyślny tauri.conf.json zostaje czysty.
# Po buildzie przywraca zasoby do stanu linuksowego.
# Wymaga wcześniej: bash scripts/fetch-runtimes.sh
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$HERE"

WINPHP="$HERE/winphp-staging/php"
WINPG="$HERE/winphp-staging/pgsql"
RESPHP="$HERE/src-tauri/resources/php"
RESPG="$HERE/src-tauri/resources/pgsql"
BACKUP="$HERE/.linux-php-backup"

[ -f "$WINPHP/php.exe" ] || { echo "Brak Windows PHP — uruchom scripts/fetch-runtimes.sh" >&2; exit 1; }
[ -f "$WINPG/bin/postgres.exe" ] || { echo "Brak Windows Postgres — uruchom scripts/fetch-runtimes.sh" >&2; exit 1; }

# backend musi być w zasobach (wspólne z buildem Linux)
bash scripts/bundle-resources.sh "$@"

restore() {
  [ -d "$BACKUP" ] && { rm -rf "$RESPHP"; mv "$BACKUP" "$RESPHP"; }
  rm -rf "$RESPG"
  echo "Przywrócono stan zasobów (Linux PHP, bez Postgresa)."
}
trap restore EXIT

echo "Podmieniam PHP na Windows + dokładam Postgres…"
rm -rf "$BACKUP"; mv "$RESPHP" "$BACKUP"; mkdir -p "$RESPHP"; cp -r "$WINPHP/." "$RESPHP/"
rm -rf "$RESPG"; mkdir -p "$RESPG"; cp -r "$WINPG/." "$RESPG/"

echo "Buduję instalator Windows (cargo-xwin + NSIS)…"
PATH="$HOME/.local/bin:/usr/lib/llvm-16/bin:$PATH" \
  npm run tauri build -- --runner cargo-xwin --target x86_64-pc-windows-msvc --bundles nsis \
  --config '{"bundle":{"resources":{"resources/backend":"backend","resources/php":"php","resources/pgsql":"pgsql"}}}'

echo "Gotowe — instalator w src-tauri/target/x86_64-pc-windows-msvc/release/bundle/nsis/."
