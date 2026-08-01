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
# Portable copy — no rsync: it is not installed on the Windows git-bash CI
# runner (the desktop-windows build failed with "rsync: command not found").
# vendor IS copied (needed at runtime); node_modules is NOT (assets ship in
# public/build). Copy each top-level entry except the excluded ones, then prune
# the runtime-only storage state rsync used to skip. cp -R works on Linux,
# macOS and Windows git-bash alike.
shopt -s dotglob nullglob
for item in "$BACKEND_SRC"/*; do
  case "$(basename "$item")" in
    node_modules|.git|.env) continue ;;
  esac
  cp -R "$item" "$DEST/"
done
shopt -u dotglob nullglob
rm -rf \
  "$DEST/storage/logs/"* \
  "$DEST/storage/framework/cache/"* \
  "$DEST/storage/framework/sessions/"* \
  "$DEST/storage/framework/views/"* \
  "$DEST/storage/installed"

echo "Backend: $(du -sh "$DEST" | cut -f1)"
if [ -f "$HERE/src-tauri/resources/php/php" ] || [ -f "$HERE/src-tauri/resources/php/php.exe" ]; then
  echo "PHP w zasobach: OK"
else
  echo "UWAGA: brak PHP w src-tauri/resources/php/ — instalator nie będzie samodzielny." >&2
fi
echo "Gotowe. Teraz: npm run tauri build"
