# Raport E2E: czysta instalacja → dwaj operatorzy → gotowy produkt

Data: 16 września 2026. Środowisko: lokalne `http://localhost:8080`, PostgreSQL,
widoczny przeglądarkowy MCP `playwright-iso`. Baza została zresetowana przez
**Ustawienia → Dane → Resetuj system**, za zgodą użytkownika. Wybrano pustą
instalację, bez przykładowej firmy. Dane scenariusza wprowadzano formularzami
aplikacji; nie używano seedera, SQL ani API do tworzenia konfiguracji.
Końcowy stan dodatkowo potwierdzono odczytem modeli z bazy.

## Wynik

Przepływ produkcji zakończył się prawidłowo: dwa stanowiska zaliczyły po 10 sztuk,
a zlecenie ma **10 sztuk gotowych, nie 20**. Start z harmonogramu, przekazywanie
pojedynczych sztuk, blokada pracy bez wejścia oraz zakończenie partii działają.

W trakcie konfiguracji odtworzono i naprawiono błędy 500 dotyczące profili
pracowników bez opcjonalnego modułu kadrowego. Test **nie daje pełnej zgody na
wdrożenie przepływu magazynowego**: utworzenie partii materiału nie zasiliło
ogólnego stanu magazynowego, a produkcja z domyślnymi ustawieniami dopuściła stan
ujemny. Szczegóły i zalecane dalsze działania poniżej.

## Utworzona konfiguracja

| Element | Wartość |
| --- | --- |
| Produkt | `E2E-PANEL` — Panel testowy |
| Szablon procesu | Cięcie i pakowanie panelu, wersja 1 |
| Kroki | 1. Cięcie → 2. Pakowanie |
| BOM | 1 pcs materiału `PLYTA` na panel, przypisane do kroku Cięcie, zużycie na początku |
| Materiał | Płyta surowa, typ Surowiec, śledzenie Brak |
| Partia materiału | `LOT-PLYTA-001`, 100 pcs, ostatecznie status Zwolniono |
| Linia | `LINIA-PANEL` — Linia paneli; przypisany Panel testowy |
| Stanowiska | `CUT-01` — Cięcie paneli; `PACK-02` — Pakowanie paneli |
| Operatorzy | `operator` / Operator Cięcie; `operator2` / Operator Pakowanie |
| Profile pracowników | `EMP-CUT` → CUT-01; `EMP-PACK` → PACK-02 |
| Tryb | Na operację; Przekazywanie; routing stanowisk włączony |
| Zlecenie 1 | `PANEL-001`, 10 sztuk, źródło Operator, termin 18.09.2026 |
| Zlecenie kontrolne | `PANEL-002-JUTRO`, 5 sztuk, start 17.09.2026 08:00 UTC |

Strefa zakładu po resecie to UTC. W tym teście 11:55 UTC odpowiadało 13:55
Europe/Warsaw. Nie zmieniano strefy zakładu w trakcie scenariusza.

## Przebieg i wyniki w przeglądarce

1. Utworzono produkt i aktywny szablon z dwoma krokami.
2. Utworzono materiał, partię materiału i BOM; sprawdzono BOM w szczegółach zlecenia.
3. Utworzono linię i dwa stanowiska, następnie przypisano istniejące stanowiska
   do kroków szablonu. Jest to konieczna kolejność zależności — przed utworzeniem
   stanowisk kroki mogą pozostawać bez konkretnego stanowiska.
4. Utworzono dwa osobiste konta Operator, przypisano oba do linii. Zalogowano je
   w osobnych kontekstach przeglądarki i wybrano różne stanowiska.
5. `PANEL-001` początkowo zaplanowano na 17 września. Obaj operatorzy nie widzieli
   zlecenia w kolejce.
6. W planerze zmieniono start na **16.09.2026 11:55 UTC**. Termin realizacji
   **18 września pozostał bez zmian**. Utworzono drugie zlecenie na jutro jako kontrolę.
7. Pozostawiono otwarte stanowisko cięcia. O **11:55:16 UTC** `PANEL-001` pojawiło
   się automatycznie, bez ręcznego odświeżenia. Jutrzejsze zlecenie pozostało ukryte.
8. Przed rozpoczęciem kroku przycisk `+1` był nieaktywny. Operator otworzył
   „Rejestruj wynik” i rozpoczął Cięcie. Jego szczegóły pokazywały tylko krok 1/2
   oraz „Dalej: Pakowanie paneli · Linia: Linia paneli”.
9. Operator Pakowanie widział tylko krok 2/2, 0 sztuk na wejściu oraz
   „Oczekiwanie na poprzedni krok”. Nie mógł rozpocząć pakowania.
10. Operator Cięcie nacisnął `+1` na stanowisku. Operator Pakowanie otrzymał
    **Przychodzące: 1,00 / Oczekiwanie: 1,00 / Zaliczono: 0,00** i aktywny Start.
    Nie kończono wcześniej całej partii na cięciu.
11. Operator Pakowanie rozpoczął swój krok i nacisnął `+1`. Zlecenie pokazało
    **1/10**, a pakowanie: wejście 1, oczekiwanie 0, zaliczone 1. Kolejne `+1`
    było zablokowane — nie można zaliczyć sztuki, której poprzedni krok nie dostarczył.
12. Podczas uzupełniania stałych profili pracowników wystąpiły błędy 500 opisane
    poniżej. Po poprawkach powtórzono zapis profili i edycję stanowisk. Każde
    stanowisko ma trwale przypisanego jednego pracownika.
13. Operator Cięcie zgłosił pozostałe 9 dobrych sztuk, następnie zakończył krok
    z potwierdzeniem rzeczywistych czasów. Pakowanie zobaczyło 10 sztuk
    przychodzących, z czego 9 jeszcze oczekujących.
14. Operator Pakowanie zgłosił pozostałe 9 dobrych sztuk i zakończył krok.
    Oba kroki, partia i zlecenie mają status DONE; zlecenie ma 10/10, braków 0.
15. W obu kolejkach zlecenie znajduje się w „Ostatnio zakończone”. Aktywnych
    zleceń jest 0, a `PANEL-002-JUTRO` wciąż nie jest udostępnione.

## Wykonane poprawki

### 1. Błąd 500 przy tworzeniu lub zapisywaniu profilu pracownika

**Odtworzenie:** Użytkownicy i konta → edycja operatora → Profil pracownika →
wpisać kod pracownika → Zapisz zmiany, na instalacji bez modułu umiejętności.

**Log:** `Call to undefined method App\Models\Worker::skills()`.

**Przyczyna:** kontroler bezwarunkowo wywoływał relację `skills()`, mimo że tę
relację rejestruje opcjonalny moduł. Aktualizacja wycofywała się w transakcji,
więc profil nie był zapisywany.

**Poprawka:** tworzenie, aktualizacja i otwieranie edycji sprawdzają dostępność
relacji przez istniejące `Worker::hasModuleRelation('skills')`. Bez modułu profil
działa z pustą listą umiejętności. Tworzenie konta, roli i profilu objęto jedną
transakcją, aby awaria profilu nie pozostawiała częściowo utworzonego konta.

### 2. Błąd 500 przy wejściu na `/admin/users/{id}`

**Log:** `Call to undefined method ...UserManagementController::show()`.

**Poprawka:** istniejąca trasa prowadzi teraz do edycji wskazanego konta.
Sprawdzono ponownie `/admin/users/3`: przekierowanie do formularza działa.

### 3. Edycja stanowiska z pracownikami bez modułu brygad

**Przyczyna:** bezwarunkowe eager loading relacji `crew`, również należącej do
opcjonalnego modułu. Problem ujawnia się dopiero po dodaniu pracowników.

**Poprawka:** relacja brygady jest pobierana tylko wtedy, gdy istnieje. Bez modułu
lista pracowników i zapis przypisań działają normalnie. Zweryfikowano przypisania
obu operatorów w przeglądarce i ponownym odczytem z bazy.

## Pozostałe ustalenia — nie są naprawione w tej zmianie

### Wysoki priorytet: przyjęcie materiału i stan ogólny

Utworzenie partii 100 sztuk oraz ustawienie jej na Zwolniono nie zwiększyły
`Material.stock_quantity`. Materiał ma tryb śledzenia **Brak**; lista partii
i ogólny stan materiału są tu oddzielnymi zapisami.

Przed produkcją: partia 100, stan ogólny 0. Na początku cięcia przydzielono
10 sztuk zgodnie z BOM-em ustawionym „Początek kroku”. Zapis ruchu magazynowego
wyniósł -10, rezerwacja w trakcie partii 10, więc dostępne pokazywało -20.
Po zakończeniu partii rezerwacja została zwolniona, a stan ogólny pozostał -10.
Partia materiału nadal miała 100, ponieważ nie była śledzonym źródłem zużycia.

Nie traktować rejestracji partii jako potwierdzonego przyjęcia magazynowego.
W tym przebiegu nie znaleziono dostępnej w podstawowym UI ścieżki zasilenia
ogólnego stanu. Nie uzupełniano go poleceniem SQL ani nie zmieniano zasad
księgowania tylko po to, aby test wyglądał na poprawny.

**Zalecenie:** dopracować i przetestować jawne przyjęcie materiału do stanu
ogólnego, spójne z wybranym trybem śledzenia, wraz z ochroną przed podwójnym
naliczeniem dostawy. Osobno przetestować blokadę produkcji przy braku materiału.
Domyślne ustawienia użyte tutaj pozwoliły kontynuować mimo ostrzeżenia.

### Niższy priorytet: komunikaty

- Szczegóły zakończonego zlecenia nadal pokazują „Za mało materiału, aby wykonać
  to zlecenie”. Komunikat należałoby ograniczyć do pracy pozostającej do wykonania.
- W polskim interfejsie pozostały m.in. `Create`, `Save Changes`, `Batch`,
  `NOT STARTED`, `IN PROGRESS`, `DONE`, `Label` i komunikaty powodzenia po angielsku.
- Przy przypisaniu pracownika do stanowiska trzeba zaznaczyć sam checkbox;
  kliknięcie sąsiadującego nazwiska nie zmienia zaznaczenia.

## Walidacja kodu po poprawkach

- SQLite: **79 testów, 446 asercji, bez błędów**.
- PostgreSQL 17, osobny jednorazowy kontener: **79 testów, 446 asercji, bez błędów**.
- Zakres: profile użytkowników, start planowany, routing stanowisk, przepływ
  ilości i jego zabezpieczenia, API kroków partii.
- Nowe 4 testy regresji obejmują tworzenie i odczyt profilu, dodanie i ponowną
  edycję profilu istniejącego konta, przypisanie do stanowiska bez brygad oraz
  wycofanie tworzenia konta w razie awarii zapisu profilu.
- Pint dla zmienionego kontrolera użytkowników i nowego testu oraz
  `git diff --check`: poprawne.
- Nie powtarzano pełnej wielotysięcznej suity ani testów opcjonalnych modułów;
  podane liczby dotyczą wskazanego zestawu regresji.
- Nie wdrażano zmian na produkcję.

## Lokalne dowody

Zrzuty w katalogu `.playwright-mcp/` (lokalne artefakty, poza tym dokumentem):

- `fresh-flow-first-piece-operator2.png` — sztuka odebrana na drugim stanowisku.
- `fresh-flow-packed-piece.png` — pierwsza sztuka zaliczona jako gotowy produkt.
- `fresh-flow-user-profile-fixed.png` — formularz profilu po usunięciu 500.
- `fresh-flow-stations-assigned.png` — po jednym pracowniku na stanowisko.
- `fresh-flow-completed-10.png` — ukończone zlecenie 10/10, z widocznym nierozwiązanym ostrzeżeniem magazynowym.
