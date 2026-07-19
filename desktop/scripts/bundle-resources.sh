#!/usr/bin/env bash
# Kopiuje backend OpenMES do zasobów aplikacji (wbudowywane w instalator).
# PHP musi już leżeć w src-tauri/resources/php/ (patrz README sekcja "Bundling").
# Uruchom przed `npm run tauri build`.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# Monorepo: backend leży obok desktopu (../backend). Można nadpisać argumentem.
BACKEND_SRC="${1:-$HERE/../backend}"
DEST="$HERE/src-tauri/resources/backend"

if [ ! -f "$BACKEND_SRC/artisan" ]; then
  echo "Nie znaleziono backendu w: $BACKEND_SRC" >&2
  echo "Użycie: $0 [ścieżka-do-backendu]" >&2
  exit 1
fi

echo "Kopiuję backend z $BACKEND_SRC → $DEST (bez node_modules/storage/.git)…"
rm -rf "$DEST"
mkdir -p "$DEST"
# vendor JEST kopiowany (wymagany w runtime); node_modules NIE (assety są w public/build).
rsync -a --delete \
  --exclude 'node_modules' \
  --exclude '.git' \
  --exclude 'storage/logs/*' \
  --exclude 'storage/framework/cache/*' \
  --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' \
  --exclude 'storage/installed' \
  --exclude '.env' \
  "$BACKEND_SRC/" "$DEST/"

echo "Backend: $(du -sh "$DEST" | cut -f1)"
if [ -f "$HERE/src-tauri/resources/php/php" ] || [ -f "$HERE/src-tauri/resources/php/php.exe" ]; then
  echo "PHP w zasobach: OK"
else
  echo "UWAGA: brak PHP w src-tauri/resources/php/ — instalator nie będzie samodzielny." >&2
fi
echo "Gotowe. Teraz: npm run tauri build"
