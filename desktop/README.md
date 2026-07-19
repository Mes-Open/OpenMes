# OpenMES Desktop

Samodzielna aplikacja desktopowa (Tauri 2) uruchamiająca OpenMES lokalnie i
udostępniająca go w sieci lokalnej. Wszystko jest wbudowane w instalator — PHP,
baza danych i backend — więc użytkownik **nie instaluje niczego ręcznie**.

Po uruchomieniu: splash → wybór bazy → rejestracja pierwszego administratora.
Klienci (telefony, tablety, inne PC) łączą się przez przeglądarkę po HTTP.

## Architektura

```
[PC z aplikacją] = serwer
  ├── Tauri (Rust) — okno + zarządzanie procesami, tray
  ├── PHP (wbudowany) — serwuje backend OpenMES
  ├── baza danych:
  │     • Windows → wbudowany PostgreSQL (sidecar, dane w %APPDATA%)
  │     • Linux   → SQLite (static-PHP nie ma pdo_pgsql)
  └── serwer HTTP na 0.0.0.0:<port>  ← sieć lokalna (telefony/tablety)
```

Zamknięcie okna **nie wyłącza serwera** — aplikacja chowa się do traya.
Pełne wyłączenie: tray → „Zakończ".

Dane runtime (baza, logi, sesje, marker instalacji) trzymane są w katalogu
danych aplikacji (`%APPDATA%` / `~/.local/share`), nie w repozytorium —
backend wbudowany w instalator jest kopiowany tam przy pierwszym uruchomieniu.

## Build

Runtime'y (PHP, Postgres) **nie są w repo** (setki MB) — pobiera je skrypt:

```bash
cd desktop
npm install
bash scripts/fetch-runtimes.sh        # PHP (Linux+Windows) + Postgres (Windows)
```

### Linux (deb/rpm, SQLite)
```bash
bash scripts/build-linux.sh           # kopiuje ../backend, wstrzykuje resources, buduje
```

### Windows (instalator NSIS, PostgreSQL)
```bash
bash scripts/build-windows.sh         # podmienia PHP/Postgres na Win, buduje, sprząta
```

Resources (PHP/Postgres/backend) wstrzykiwane są przez `--config` tylko przy
buildzie release — domyślny `tauri.conf.json` jest czysty, więc `cargo check`
i `npm run tauri dev` działają bez pobierania runtime'ów.
Cross-build z Linuksa wymaga: `cargo-xwin`, `makensis` (NSIS), `llvm-rc`
(np. `clang-16`/`llvm-16`). Skrypt ustawia `PATH` do `~/.local/bin` i `llvm-16`.

Gotowe artefakty lądują w `release/` (gitignorowane).

## Jak to działa pod spodem

- **`ensure_runtime`** (Rust) namierza wbudowany backend (kopiuje do danych
  aplikacji przy 1. starcie) i PHP (wbudowany → systemowy → awaryjnie pobrany).
- **Tryb lokalny** = wbudowany Postgres (Windows) albo SQLite (Linux). Backend
  OpenMES startuje w trybie `INSTALLER_PRESET`, więc pomija kreator i pokazuje
  od razu rejestrację admina.
- **Tryb zdalny** = aplikacja skanuje LAN po IP w poszukiwaniu Postgres/MySQL;
  jak nie znajdzie, prosi o dane połączenia ręcznie.
- Procesy (PHP, Postgres) startują w osobnej grupie (`setsid`/job) i są
  zatrzymywane razem z aplikacją — zero osieroconych procesów.

## Znane ograniczenia

- **Linux + zdalny Postgres**: żaden gotowy static-PHP nie ma `pdo_pgsql` —
  wymaga customowego builda PHP (static-php-cli). Lokalny SQLite i zdalny MySQL
  działają.
- **Windows**: oficjalny PHP (NTS/VS16) wymaga *Visual C++ Redistributable
  2015–2022 x64*. Instalator i `postgres.exe` są niepodpisane (SmartScreen →
  „Więcej informacji → Uruchom mimo to"). Nie uruchamiać jako Administrator
  (Postgres odmawia startu z konta admina).
- Skanowanie kamerą telefonu wymaga HTTPS — w LAN po HTTP działają skanery
  sprzętowe (BT/USB) i ręczne wpisywanie.
