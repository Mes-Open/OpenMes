# OpenMES — Baza wiedzy domenowej

Plik lokalny, nigdy nie trafia do repo. Pisany po polsku.
Źródło wiedzy: analiza systemu Qcadoo MES (referencyjna implementacja enterprise MES).

---

## 1. Czym jest OpenMES

OpenMES to uproszczony, webowy MES (Manufacturing Execution System) pisany w Laravel 12 + Blade + Alpine.js + Tailwind CSS.
Cel: śledzenie i zarządzanie zleceniami produkcyjnymi, liniami, operatorami i jakością — bez nadmiernej złożoności enterprise.

---

## 2. Słownik domenowy (PL → nazwa w kodzie)

| Pojęcie PL | Nazwa w kodzie | Opis |
|---|---|---|
| Zlecenie produkcyjne | `WorkOrder` | Główna jednostka — co produkujemy, ile, gdzie, kiedy |
| Linia produkcyjna | `Line` | Fizyczne miejsce produkcji |
| Stanowisko | `Workstation` | Konkretna maszyna/miejsce pracy na linii |
| Typ produktu | `ProductType` | Kategoria/rodzaj produktu |
| Szablon procesu | `ProcessTemplate` | Definicja kroków technologicznych dla produktu |
| Krok szablonu | `TemplateStep` | Pojedyncza operacja w szablonie (np. spawanie, malowanie) |
| Partia | `Batch` | Realizacja zlecenia — rzeczywiste wykonanie produkcji |
| Krok partii | `BatchStep` | Wykonanie konkretnego kroku dla danej partii |
| Usterka / problem | `Issue` | Zgłoszony problem jakościowy lub awaria |
| Typ usterki | `IssueType` | Kategoria problemu (awaria, braki, jakość...) |
| Import CSV/XLS | `CsvImport` | Import zleceń produkcyjnych z pliku |
| Mapowanie importu | `CsvImportMapping` | Zapamiętany profil mapowania kolumn pliku |
| Ustawienia systemu | `system_settings` (tabela key/value) | Konfiguracja globalna (okres produkcji, reguły) |

---

## 3. Cykl życia zlecenia produkcyjnego (WorkOrder)

Wzorowany na Qcadoo, uproszczony:

```
oczekujące → zaakceptowane → w toku → zakończone
                           ↘ wstrzymane → wznowione → w toku
                           ↘ odrzucone
```

Statusy w kodzie (do ustalenia/rozbudowy):
- `pending` — nowe zlecenie
- `accepted` — zatwierdzone do produkcji
- `in_progress` — produkcja trwa
- `completed` — zakończone
- `paused` — wstrzymane
- `rejected` — odrzucone

Przy każdej zmianie statusu warto logować: kto, kiedy, z jakiego statusu, do jakiego.

---

## 4. Kluczowe encje i relacje (docelowe)

### WorkOrder
- `order_no` — unikalny numer zlecenia (wymagany przy imporcie)
- `product_name` — nazwa produktu
- `quantity` — ilość do wyprodukowania (wymagana przy imporcie)
- `line_id` → `Line` — linia produkcyjna
- `product_type_id` → `ProductType`
- `process_template_id` → `ProcessTemplate`
- `priority` — priorytet (np. 1-5)
- `due_date` — termin realizacji
- `status` — aktualny status zlecenia
- `week_number` — numer tygodnia produkcyjnego
- `month_number` — numer miesiąca produkcyjnego
- `production_year` — rok produkcji

### Line (Linia produkcyjna)
- `name`, `code`, `description`
- `is_active` — czy linia aktywna
- Może mieć wiele stanowisk (`Workstation`)
- Zlecenia są przypisywane do linii

### ProcessTemplate (Szablon procesu / Technologia)
- Odpowiednik `technologies_technology` z Qcadoo
- Definiuje kolejność kroków produkcyjnych dla danego produktu
- Ma wiele `TemplateStep` (kroków)

### TemplateStep (Krok procesu)
- Odpowiednik `technologies_operation` z Qcadoo
- `name`, `order` (kolejność), `required_role`
- Może wymagać potwierdzenia przez operatora

### Batch (Partia / Realizacja)
- Odpowiednik `productioncounting_productiontracking` z Qcadoo
- Tworzona gdy zlecenie przechodzi do `in_progress`
- Przechodzi przez kroki (`BatchStep`) odpowiadające krokom szablonu

### BatchStep
- Stan: `pending` → `in_progress` → `done` / `failed`
- Kto wykonał, kiedy, ile czasu

### Issue (Usterka / Problem)
- Powiązana z zleceniem lub krokiem partii
- `issue_type_id` → `IssueType`
- Opis, status, kto zgłosił

---

## 5. Logika importu (CSV/XLS/XLSX)

Import zleceń produkcyjnych z pliku zewnętrznego (np. export z ERP/Excel).

**Przepływ:**
1. Upload pliku → `CsvImportController::upload()` → parsuje nagłówki → widok mapowania
2. Mapowanie kolumn → `CsvImportController::process()` → walidacja → zapis `WorkOrder`
3. Pola wymagane: `order_no` + `quantity`
4. Opcjonalne: przypisanie do linii, tygodnia/miesiąca/roku produkcji

**Strategie importu:**
- `insert_only` — tylko nowe rekordy
- `update_only` — tylko aktualizacja istniejących
- `insert_or_update` — upsert

**Ustawienia produkcji (system_settings):**
- `production_period`: `none` / `weekly` / `monthly` — czy import wymaga podania numeru tygodnia lub miesiąca
- `allow_overproduction`: czy można przekroczyć zaplanowaną ilość
- `force_sequential_steps`: czy kroki muszą być wykonywane w kolejności

---

## 6. Wzorce z Qcadoo do zastosowania w OpenMES

### Śledzenie czasu operacji
- TPZ — czas przygotowania/przezbrojenia stanowiska (setup time)
- TJ — czas jednostkowy operacji (czas na 1 sztukę)
- Czas maszyny vs. czas pracy ludzkiej (mogą się różnić)

### Rodzaje rejestracji produkcji
- **Skumulowana** — jeden rekord dla całego zlecenia (proste zlecenia)
- **Na każdą operację** — szczegółowe śledzenie każdego kroku

### Typy przepływu materiału
- W ramach procesu (produkty nie opuszczają linii)
- Między operacjami (półprodukty trafiają do magazynu między krokami)
- Partiami (produkcja wsadowa — chemia, spożywka)

### Genealogia / Traceability (do rozbudowy w przyszłości)
- Śledzenie z której partii surowca powstał produkt
- `Batch` z numerem partii, dostawcą, datą
- Powiązanie batch → zlecenie → gotowy produkt

### Anomalie
- Odchylenie od planu (wyprodukowano więcej/mniej niż planowano)
- Wymaga wyjaśnienia / korekty
- W Qcadoo: `productioncounting_anomaly`

---

## 7. Role użytkowników

| Rola | Dostęp |
|---|---|
| `Admin` | Pełny dostęp: ustawienia, import, zarządzanie wszystkim |
| `Supervisor` | Zarządzanie zleceniami, liniami, raportami |
| `Operator` | Widok swoich zadań, rejestracja kroków, zgłaszanie usterek |
| `Workstation` | Konto dla stanowiska (bez logowania użytkownika), ograniczony widok |

Implementacja: Spatie Laravel Permission (`hasRole`, `hasPermissionTo`).

---

## 8. Moduły systemu (pluginy)

OpenMES wspiera system modułów (`modules/` w katalogu projektu).
Moduł to rozszerzenie funkcjonalności — może dodawać widoki, trasy, logikę.
Moduły są włączane/wyłączane przez Admin w ustawieniach.

---

## 9. Priorytety rozwoju (kolejność implementacji)

1. **Stabilny import** — CSV/XLS, mapowanie, przypisanie do linii/tygodnia ✅
2. **Statusy zleceń** — pełny workflow ze zmianami statusu i logiem
3. **Partia i kroki** — Batch + BatchStep, rejestracja wykonania przez operatora
4. **Usterki** — Issue z linkiem do zlecenia/kroku
5. **Dashboard** — przegląd statusów linii, zleceń w toku, alertów
6. **Raporty** — produkcja na zmianę/tydzień/miesiąc, wydajność linii
7. **Czas operacji** — rejestracja czasu pracy przy kroku
8. **Genealogia batch** — traceability z/do surowców

---

## 10. Konwencje techniczne projektu

- **Backend**: Laravel 12, PHP 8.3, PostgreSQL
- **Frontend**: Blade + Alpine.js + Tailwind CSS 4 (Vite)
- **Auth**: Laravel Sanctum, session-based
- **Role**: Spatie Permission
- **Import**: PhpOffice\PhpSpreadsheet (przez maatwebsite/excel)
- **Walidacja**: Form Requests (nigdy tylko frontend)
- **Baza**: Eloquent/Query Builder — zero raw SQL z userinputem
- **Docker (dev/prod)**: `docker compose up -d` — tryb sterowany przez `.env` (`APP_ENV=local` lub `production`)
- **Testy**: `php artisan test`
- **Lint**: `./vendor/bin/pint`

### Język interfejsu (i18n)

**Domyślny język aplikacji to angielski (EN).**

Wszystkie teksty w kodzie — widoki Blade, kontrolery (flash messages), walidacja (Form Requests), seedery (nazwy domyślnych rekordów), etykiety formularzy, buttony, breadcrumbs, tooltips — **muszą być napisane po angielsku**.

Tłumaczenie na polski (i inne języki) będzie realizowane przez system tłumaczeń Laravel (`lang/`), ale **kod źródłowy zawsze zawiera angielskie stringi jako bazę**.

**Zasada:** English first → Polish (and other languages) via translation files.

---

## 11. Środowisko testowe (docker lokalny)

- **Ścieżka**: `/home/themreviil/Documents/test/omtest`
- **Klon z**: `main` branch repozytorium
- **Konfiguracja**: `.env` z `APP_ENV=local`, `APP_PORT=8080`
- **Uruchamianie**: `docker compose up -d` w katalogu `/home/themreviil/Documents/test/omtest`
- **Dostęp**: http://localhost:8080
- **Aktualizacja**: `git pull && docker compose up -d --build`
- **Cel**: manualne testowanie przed pushem do main

---

## 12. Wdrożenie na Apache2 (bez Dockera)

Alternatywna metoda instalacji — bezpośrednio na serwerze z PHP i Apache2.

**Wymagania:** PHP 8.3, rozszerzenia: `pdo_pgsql`, `mbstring`, `zip`, `gd`, `opcache`, Node.js (do builda assetów), Composer, PostgreSQL

**Kroki:**
```bash
# 1. Sklonuj repo
git clone https://github.com/Mes-Open/OpenMes.git /var/www/openmmes
cd /var/www/openmmes/backend

# 2. Zainstaluj zależności i zbuduj assety
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Uprawnienia
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 4. Skonfiguruj .env
cp .env.example .env
php artisan key:generate
# → edytuj .env: DB_*, APP_URL
```

**Virtual host Apache2** (`/etc/apache2/sites-available/openmmes.conf`):
```apache
<VirtualHost *:80>
    ServerName openmmes.local
    DocumentRoot /var/www/openmmes/backend/public

    <Directory /var/www/openmmes/backend/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

```bash
a2enmod rewrite
a2ensite openmmes
systemctl reload apache2
```

**Uwaga:** `public/.htaccess` jest już w repo — Laravel routing działa bez żadnych dodatkowych zmian.

---

## 13. Prywatne moduły (NIGDY na publicznym repo)

Moduły komercyjne/klienckie **NIGDY** nie trafiają do publicznego repo `Mes-Open/OpenMes`.
Mają osobne prywatne repozytoria:

| Moduł | Repo (private) | Opis |
|---|---|---|
| SubiektNexo | `Mes-Open/subiekt-nexo-module` | Integracja Sfera API / Subiekt nexo |

**Zasady:**
- Moduły klienckie rozwijaj TYLKO w instancji klienta (`openmes-pulemqu/`) lub w prywatnym repo
- NIGDY nie commituj do `Mes-Open/OpenMes` plików z `modules/SubiektNexo/` ani podobnych
- Pre-commit hook blokuje takie commity automatycznie
- `.gitignore` ignoruje katalogi prywatnych modułów

**Jeśli klient potrzebuje nowego modułu:**
1. Stwórz prywatne repo `Mes-Open/<nazwa-modulu>`
2. Rozwijaj tam
3. Na instancji klienta klonuj do `modules/`
