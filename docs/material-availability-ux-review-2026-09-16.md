# Materiały i czytelność konfiguracji — poprawki po E2E

Data: 2026-09-16. Kontynuacja raportu
[fresh-install-manual-flow-review-2026-09-16.md](fresh-install-manual-flow-review-2026-09-16.md).

## Decyzja i wyjaśnienie salda

Użytkownik wybrał **ostrzeganie bez blokowania produkcji**. Ustawienie
`block_negative_stock=false` zapisano przez formularz ustawień; nie zmieniono
domyślnego zachowania istniejących instalacji.

Saldo −10 wynikało z wydania 10 sztuk bez wcześniejszego przyjęcia do stanu
ogólnego. Dodanie rekordu partii materiału z ilością 100 nie było przyjęciem
magazynowym. Śledzenie partii jest osobno konfigurowane; w tym scenariuszu nie
było włączonego pobierania materiału z partii.

## Zmiany

- Ostrzeżenia pokazują minimum 0 dostępnych sztuk, zachowując podpisane saldo
  w ewidencji. Nie pokazują braków dla zakończonych/anulowanych/odrzuconych zleceń.
- Przydziały materiału do tego zlecenia pomniejszają pozostające zapotrzebowanie.
  Zwrócone przydziały i przydziały anulowanych partii nie pokrywają zapotrzebowania.
- Powtarzające się pozycje tego samego materiału w BOM są sumowane przed
  porównaniem z zapasem. Zlecenia nadal oceniane są niezależnie od siebie.
- Administrator może przyjąć dostawę na karcie materiału. Wymagane dodatnia
  ilość i numer dostawy; ruch zapisuje użytkownika, ilość, saldo i numer.
  Powtórzenie tego samego numeru dla materiału z tą samą ilością nie księguje
  ponownie. Inna ilość dla tego numeru jest odrzucana.
- W ustawieniach produkcji dostępny jest jawny wybór ostrzegania lub blokowania.
- Dodano wskazówkę przy niedostępnym +1, tłumaczenia statusów i przycisków,
  nazwę „Partia” oraz „Zarejestruj wynik” zamiast „Zaloguj”.
- Nazwisko pracownika przełącza checkbox przypisania do stanowiska.
- Karta produktu prowadzi przez konfigurację procesu, BOM, materiałów, linii,
  stanowisk, operatorów i harmonogramu. Konto operatora pokazuje przypisania.
- Onboarding przypomina o strefie zakładu przed planowaniem.

## Walidacja

- Backend SQLite: 131 testów, 623 asercje, bez błędów.
- Backend PostgreSQL 17 w osobnym kontenerze: 131 testów, 623 asercje, bez błędów.
- Zakres: materiały, alokacje, przyjęcia, ustawienia, profile operatorów,
  ilości kroków i bezpieczeństwo ich zmian, planowany start.
- Frontend: 139 testów, bez błędów; build produkcyjny poprawny.
- Widoczny playwright-iso:
  - przyjęcie 100 sztuk PLYTA, numer PZ-LOT-PLYTA-001: saldo −10 → 90;
  - ponowienie identycznego przyjęcia: nadal 90 i jeden ruch przyjęcia;
  - próba 50 sztuk z tym samym numerem: błąd walidacji, saldo nadal 90;
  - karta produktu i link do BOM działają;
  - konto operator2 pokazuje linię i Pakowanie paneli;
  - kliknięcie nazwiska przełącza zaznaczenie; przywrócono i zapisano przypisanie;
  - ustawiono ostrzeganie bez blokady;
  - zakończone PANEL-001 pokazuje 10/10, polskie statusy i brak ostrzeżenia.

## Granice i stan danych testowych

Przyjęcie dotyczy **ogólnego stanu**, nie sald poszczególnych lokalizacji ani
partii materiałowych. Nie powtarzać nim dokumentów zaksięgowanych przez ERP lub
moduł magazynowy. Nie dodano automatycznego przeksięgowania istniejących partii.
LOT-PLYTA-001 nadal ma 100; globalny stan PLYTA wynosi 90 po zużyciu 10.

Pełny przepływ dwóch operatorów zakończono w poprzednim raporcie. W tej poprawce
wykonano regresję obszarów zmienionych, bez ponownego resetu bazy i bez pełnego
testu wdrożenia produkcyjnego. Nie wdrażano ani nie wypychano zmian.
