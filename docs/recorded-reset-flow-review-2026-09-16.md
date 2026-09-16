# Test produkcji od resetu — 16.09.2026

## Artefakty

- [Raport HTML — 171 ponumerowanych kroków i osadzone zrzuty](../output/flow-review-reset-2026-09-16/index.html)
- [Administrator — nagranie ok. 15 min](../output/flow-review-reset-2026-09-16/admin.webm)
- [Operator Cięcie — nagranie ok. 6 min 24 s](../output/flow-review-reset-2026-09-16/op1.webm)
- [Operator Pakowanie — nagranie ok. 6 min 21 s](../output/flow-review-reset-2026-09-16/op2.webm)
- [Metadane i tekstowe dowody](../output/flow-review-reset-2026-09-16/steps.json)

HTML otwiera się bez serwera i internetu. Zrzuty są osadzone; filmy są osobnymi plikami w tym samym katalogu. W galerii jest wyszukiwanie, numery, adresy, czas UTC, powiększanie i nawigacja klawiaturą. Zrzut wykonano po każdej udanej akcji; wypełnienie powiązanych pól jest jednym krokiem. Nieudane dopasowania selektorów, które nie powodowały kliknięcia, nie są akcjami aplikacji.

## Warunki i metoda

Zgodnie z poleceniem użytkownika wykonano Resetuj system w ustawieniach localhost:8080, z potwierdzeniem RESET. Wszystkie nowe dane utworzono przez formularze strony, bez SQL, seederów ani bezpośrednich wywołań API. Trzy niezależne konteksty Playwright nagrywały administratora i dwóch operatorów. Strefa zakładu UTC; data 16.09.2026.

Konfiguracja: Na operację, Przekazywanie, routing stanowisk włączony, wymuszanie kolejności włączone, nadprodukcja wyłączona, ostrzeganie o materiałach zamiast blokowania. Po resecie routing nie jest włączony — samo przypisanie stanowisk nie wystarcza.

Dane pozostawione do dalszych prób:

- Produkt PANEL-TEST / Panel testowy, proces Cięcie i pakowanie v1.
- Kroki Cięcie i Pakowanie, po 2 min; stanowiska CUT-01 i PACK-02 na LINIA-PANEL.
- Materiał PLYTA, bez śledzenia partii; BOM 1 szt. na panel przy Cięciu; przyjęcie 10 szt.
- Konta operator i operator2, profile EMP-CUT i EMP-PACK; oba przypisane do linii, osobno do stanowisk.
- Zlecenie PANEL-001, 3 szt., partia 1; zakończone.

## Wyniki obserwowane w przeglądarce

| Sprawdzenie | Wynik |
|---|---|
| Zlecenie przesunięte w tygodniowym harmonogramie na 17.09 | Niewidoczne u obu operatorów 16.09 |
| Start 16.09 o 17:34 UTC | Przed godziną niewidoczne; pojawiło się automatycznie na otwartym ekranie Cięcia po starcie |
| +1 przed rozpoczęciem kroku | Przycisk zablokowany |
| Start Cięcia, następnie pierwsze +1 | Pakowanie dostało zlecenie automatycznie; szczegóły: Przychodzące 1, Oczekiwanie 1, Zaliczono 0 |
| Start Pakowania, następnie +1 | Gotowa produkcja 1/3; nie podwojono wyniku przez liczenie obu stanowisk |
| Pakowanie po wykorzystaniu jedynej dostępnej sztuki | Kolejne +1 zablokowane |
| Kolejne 2 sztuki na obu stanowiskach | Gotowa produkcja 3/3 |
| Zakończenie obu kroków | Zlecenie i partia zakończone; 2/2 kroków |
| Magazyn | Przyjęcie +10, jeden ruch allocation −3, saldo 7, rezerwacje 0 |
| Drawer konta — kolejne otwarcie i duplikat loginu/email | Role dostępne, błędy walidacji widoczne, dane formularza zachowane |
| Bezpośrednie adresy obu /create | Nowe drawery otwierają się i dają się zamknąć |

## Zmiany wykonane

- Tworzenie szablonu z produktu oraz listy szablonów w prawym drawerze; pozostawiono działający stary adres /create z listą w tle.
- Tworzenie konta z listy w prawym drawerze; opcje formularza pobierane na żądanie i zachowywane przez walidację.
- Padding list kont i szablonów; spójne przyciski; podpowiedź wersji procesu bez wyróżnionego bloku Uwaga.
- Polski komunikat utworzenia konta.
- Pusta lista produktów również otwiera drawer tworzenia.

W trakcie weryfikacji nowego drawera konta wykryto TypeError po pierwszym udanym zapisie (znikające opcjonalne propsy list wyboru). Poprawiono przechowywanie opcji i bezpieczne wartości domyślne. Kolejne konto oraz walidacja duplikatu przeszły poprawnie. Galeria zachowuje ślad błędu i ponownego testu.

Jedna odpowiedź 500 z /operator/workstation wynikała z lokalnej przebudowy: log 17:33:25 UTC wskazuje ViteManifestNotFoundException, public/build/manifest.json. Po zakończeniu buildu strona działała. To nie był błąd logiki zleceń. Automatyczne pojawienie się na Cięciu i przekazanie do Pakowania sprawdzono później na działających stronach. Pierwotna sesja przed resetem ma oddzielne nagranie, nie należy do tego raportu.

## Rekomendacje

1. **Prowadzenie przez konfigurację**: połączyć ustawienia trybu pracy, profile, dostęp do linii i wybór stanowiska; domyślnie sugerować operatorowi przypisane stanowisko. Dziś potrzebne są osobne kroki w kilku miejscach.
2. **Dostępne sztuki na stanowisku**: tabela pokazuje plan całego zlecenia 3, a wejście 1 dopiero w szczegółach. Pokazać Przychodzące / Do obróbki / Wykonane na stanowisku.
3. **Czytelność zużycia BOM**: w zleceniu Rozliczenie materiałów (0), mimo ruchu −3 w magazynie. Pokazać powiązaną alokację albo jasno opisać zakres tej sekcji. Sam ruch magazynowy i saldo są prawidłowe; nie stwierdzono utraty materiału.
4. **BOM dziedziczący filtr kroku**: formularz dla Cięcia nadal domyślnie wskazuje Wszystkie kroki / ogólne, co sprzyja pomyłkom.
5. **Tłumaczenia**: materiały (Raw Material, None, Details, Recent stock movements), Step completed., Failure i walidacja konta nadal po angielsku.
6. **Ekran operatora**: podwójna nawigacja, instrukcja o nieobecnych w tym trybie Z1/Z2, ogólna podpowiedź zablokowanego +1; warto dać bezpośredni Start kroku i konkretny powód blokady.
7. **Kontrola jakości**: bez skonfigurowanej kontroli zakończone zlecenie pokazuje potrzeba jeszcze 3. Rozdzielić wymagane i opcjonalne działania; nie traktować samego komunikatu jako dowodu błędu zamknięcia.

## Walidacja i granice

- Build frontendu przeszedł.
- 149 testów JavaScript przeszło.
- 11 testów PHP, 65 asercji: UserWorkerProfileTest i ProcessTemplateStepWebTest.
- HTML sprawdzony przez Playwright: 171 pozycji, filtrowanie, nawigacja, obraz 1440 px, brak poziomego przepełnienia przy szerokości 390 px; metadane trzech filmów wczytują się poprawnie.
- To jeden pełny scenariusz pozytywny i kontrole dostępności/blokady/duplikatu. Nie testowano niskiego stanu magazynowego, braków produkcyjnych, nadprodukcji, wymuszonej jakości, śledzenia partii/seriali, wyścigów wielu operatorów ani obciążenia.
