# Magazyn komponentów i terminy produkcji — wykonany plan

Stan: 12.09.2026. Rozszerzenie wielopoziomowego BOM-u zostało wdrożone i sprawdzone w lokalnej aplikacji. Szczegóły kontraktu API i ograniczeń: [component-production.md](component-production.md#component-inventory-and-production-timing).

## Co się zmieniło

Wcześniej zlecenie produkowało całą ilość z BOM-u niezależnie od zapasu komponentów. Teraz można wybrać magazyny, zarezerwować zgodny zapas i produkować tylko brakującą ilość. Zapotrzebowanie dzieci liczymy po uwzględnieniu zapasu rodzica: gotowy stelaż eliminuje potrzebę produkcji jego części.

## Zrealizowane kroki

- [x] **Magazyn i rezerwacje:** osobne rekordy rezerwacji dla wystąpień BOM-u, stany held/issued/released, wybrane aktywne magazyny, pomniejszenie dostępności o cudze rezerwacje. Zgodność indeksu, jednostki, przypiętej wersji i specyfikacji; kontrola jakości i ważności partii materiałowych.
- [x] **Przeliczenie drzewa:** produkcja wyłącznie braków na każdym poziomie; pełne pokrycie magazynowe nie tworzy zlecenia komponentu. Odznaczenie produkcji nie usuwa wymagania BOM-u. Zakupione materiały pozostają w dotychczasowym obiegu.
- [x] **Bezpieczny zapis:** podgląd bez rezerwacji, weryfikacja jego aktualności przy zapisie, blokady bazodanowe przeciw podwójnemu przydziałowi. Anulowanie zwalnia niewydany zapas; zmiana ilości przed utworzeniem partii tworzy nową wersję planu na podstawie zamrożonego BOM-u.
- [x] **Wykonanie:** suma wydanego/zarezerwowanego zapasu i dobrych sztuk z zakończonych partii odblokowuje operację. Pierwsze rozpoczęcie operacji wydaje jej rezerwację dokumentem magazynowym, jednokrotnie dla całego zlecenia. Zwykłe wydania nie mogą zabrać zarezerwowanego zapasu.
- [x] **Terminy:** data i godzina planowanego rozpoczęcia/zakończenia w formularzu oraz integracjach. Rezerwacja ma termin rozpoczęcia zlecenia, które zużywa komponent. Przesunięcie terminu aktualizuje rezerwację. Planer i szczegóły zgłaszają brak harmonogramu, spóźniony komponent lub utratę dostępności rezerwacji. Timestampy z offsetem zachowują ten sam moment po zapisie. Faktyczne rozpoczęcie nadal zapisują partie/operacje.
- [x] **Interfejs:** wspólny formularz również w planerze; ilość przed podglądem; drzewo z wymaganiem, dostępnością, magazynem i ilością do produkcji; odświeżanie i ukrywanie podglądu; status rezerwacji i link do dokumentu. Polskie tłumaczenia, daty w strefie zakładu, ilości magazynowe również w szczegółach mobilnych.
- [x] **Integracje:** wspólny mechanizm dla formularza, API, ERP i importu plikowego; mapowanie dat/godzin i opcje magazynu. Import ERP stanów obsługuje jakość i specyfikację całego salda.
- [x] **Uwagi z review:** podsumowanie filtruje zlecenia według widoczności linii operatora; zlecenia komponentów zachowują wydane dokumenty techniczne produktu również po zmianie ilości.

## Wyniki weryfikacji

- Szeroki zestaw PHP/SQLite: **957 testów, 3533 asercje, 1 pominięty**, bez błędów. Po końcowej normalizacji stref czasowych: **99 testów, 435 asercji**, bez błędów.
- PostgreSQL: **147 testów, 575 asercji**, bez błędów.
- Osobny test rzeczywistej równoległości PostgreSQL: **1 test, 8 asercji**. Dwa procesy jednocześnie czekały na ten sam stan 50 sztuk; powstały zlecenia na 70 i 120 sztuk, a łączne rezerwacje wyniosły 50.
- Frontend/Vitest: **97 testów**; TypeScript aplikacji mobilnej i build Vite: bez błędów. Pint i `git diff --check`: OK.
- PHPUnit zgłasza cztery istniejące ostrzeżenia deprecation; Vite nadal zgłasza duże chunki. Nie zmieniano tych niezwiązanych elementów.

Testy obejmują m.in. pełny i częściowy zapas podzespołu, brak zapisów przy podglądzie, dwa zlecenia konkurujące o zapas, niezgodną specyfikację, przeterminowanie, blokadę jakości, zmianę stanów po podglądzie, cofnięcie rezerwacji, zmianę ilości, pełny przebieg zapas + produkcja w dwóch partiach bez podwójnego rozchodu, dokumenty, widoczność operatora oraz terminy i offsety.

Powtórzenie testu równoległości: uruchomić `php vendor/bin/phpunit tests/Integration/ComponentStockConcurrencyTest.php` w środowisku `APP_ENV=testing`, `DB_CONNECTION=pgsql`, na **osobnej pustej bazie z `_test` w nazwie**. Ten test wykonuje `migrate:fresh`. Użyta do sprawdzenia baza oraz pomocniczy kontener testowy zostały usunięte; baza aplikacji pozostała zachowana.

## Przykład w przeglądarce

W widocznym Chrome, sterowanym przez Playwright MCP:

1. Utworzono **EX-MAGAZYN-20260912-01**: EX-SOFA, 20 sztuk, start 15.09.2026 10:00 i koniec 14:00 w strefie zakładu (lokalna konfiguracja: UTC).
2. Wybrano EX-STOCK-TEST. Podgląd pokazał dla EX-BEAM **120 wymaganych / 50 z magazynu / 70 do produkcji**. Zapis utworzył rezerwację bez rozchodu.
3. W planerze przypisano linię EX, przesunięto stelaż za termin montażu i sprawdzono ostrzeżenie. Następnie poprawiono jego termin na 08:00–09:00. Montaż boku ustawiono na 07:00–08:00; termin rezerwacji belek został zaktualizowany.
4. Przez przyciski operacji wykonano 70 belek. Partię do demonstracji utworzono przez uwierzytelniony endpoint aplikacji.
5. Rozpoczęto montaż boku. System wydał **50 belek** dokumentem **COMP-ISSUE-1**, stan magazynowy spadł z 50 do 0. Podsumowanie pokazało **50 wydanych + 70 dobrych = 120 wymaganych**.

Pozostawione rekordy:

- Zlecenie główne: `/admin/work-orders/569` — nadal oczekujące; sofa nie została ukończona.
- Belki: `/admin/work-orders/572` — wykonano 70 sztuk.
- Bok stelaża: `/admin/work-orders/571` — w toku, po wydaniu zapasu.
- Dokument rozchodu: `/admin/stock-documents/9` — zaksięgowane wydanie 50 sztuk.

Lokalne nagrania i zrzuty (katalog ignorowany przez Git): `test-results/component-stock/`. `preview.webm` pokazuje podgląd i zapis, `production-issue.webm` — wykonanie i wydanie, `full-demo.webm` zawiera całe nagranie z przerwami na pracę nad kodem.

## Granice działania

Nie dodano automatycznego układania terminów wszystkich dzieci ani optymalizacji obciążenia linii. Planer wykrywa zależności i ostrzega, a przyszła planowana produkcja nie jest traktowana jak dostępny zapas. Gotowe komponenty ze zleceń potomnych są dedykowane rodzicowi, bez automatycznego przyjęcia do magazynu sprzedażowego. Nie dodano numerów seryjnych pojedynczych sztuk ani częściowego zwalniania montażu.

Rezerwacja blokuje zapas od chwili zapisu i nie zmienia właściciela automatycznie przy przesuwaniu harmonogramu. Specyfikacja magazynowa dotyczy całego salda danego indeksu/lokalizacji; różne warianty wymagają oddzielnych indeksów lub lokalizacji. Po wydaniu rezerwacji jej zwrot wymaga odrębnego procesu rozliczenia — anulowanie zlecenia nie odwraca fizycznego rozchodu.
