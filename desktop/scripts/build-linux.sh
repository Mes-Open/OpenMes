#!/usr/bin/env bash
# Buduje samodzielny instalator Linux (deb/rpm) z wbudowanym static-PHP +
# backendem (baza: SQLite). Resources wstrzykiwane przez --config, żeby domyślny
# tauri.conf.json pozostał czysty (świeży klon kompiluje się bez runtime'ów).
# Wymaga wcześniej: bash scripts/fetch-runtimes.sh
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$HERE"

[ -f src-tauri/resources/php/php ] || { echo "Brak Linux PHP — uruchom scripts/fetch-runtimes.sh" >&2; exit 1; }

bash scripts/bundle-resources.sh "$@"

echo "Buduję deb/rpm…"
APPIMAGE_EXTRACT_AND_RUN=1 npm run tauri build -- --bundles deb,rpm \
  --config '{"bundle":{"resources":{"resources/backend":"backend","resources/php":"php"}}}'

echo "Gotowe — artefakty w src-tauri/target/release/bundle/."
