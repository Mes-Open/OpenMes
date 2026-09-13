# Planer zapotrzebowań i zależności produkcyjnych

Propozycja z 13.09.2026, na podstawie aktualnego kodu. To plan następnego rozszerzenia; poniższe widoki i mechanizmy nie są jeszcze wdrożone.

## Cel

Planista powinien na jednym ekranie ustalić: co można rozpocząć, czego brakuje, na kiedy jest potrzebne, co zapewni pokrycie oraz które zlecenia ucierpią po zmianie terminu. Zachowujemy istniejący planer linii i dokładamy informacje o zaopatrzeniu i zależnościach.

## Obecny stan potwierdzony w kodzie

- `SchedulePlannerService::flattenOrder()` przekazuje terminy i postęp, lecz nie gotowość komponentów ani relacje rodzic–dziecko.
- `componentWarnings()` wykrywa spóźnienia, brak harmonogramu i utratę dostępności rezerwacji; komunikaty trafiają głównie do powiadomień po zapisie/przesunięciu.
- `ComponentWorkOrderService` ma zamrożone wystąpienia BOM-u, przypisane operacje zużycia, zlecenia potomne i podsumowania dostępności.
- Rezerwacje i dokumenty rozchodu działają. Gotowa produkcja potomna jest dedykowana rodzicowi, a przyszły plan nie jest stanem magazynowym.
- `NetRequirementsService::report()` wybiera oczekujące/zaakceptowane zlecenia po `due_date` i rozlicza materiały względem `Material.stock_quantity`. Nie jest kompletną projekcją zaopatrzenia konkretnej operacji na konkretną godzinę.
- Odświeżanie planera sprawdza m.in. `WorkOrder::max(updated_at)`. Sama zmiana jakości, salda magazynu lub rezerwacji nie musi zmienić tego znacznika.

## Zasady liczenia

1. Rozdzielamy **gotowość teraz** od **przewidywanego pokrycia na termin**. Zlecenie z brakującymi dziś belkami może mieć prawidłowy harmonogram ich wykonania, ale nadal nie jest gotowe do rozpoczęcia montażu.
2. Pokrycie teraz obejmuje własne ważne rezerwacje, wydane komponenty i dopuszczony jakościowo, niewliczony wcześniej wynik produkcji dedykowanej. Wolny zapas pokazujemy osobno — bez rezerwacji nie stanowi gwarantowanego pokrycia zlecenia.
3. Planowana produkcja pokazuje jedynie pozostałą oczekiwaną ilość, po odjęciu wykonanego wyniku, i ma status pewności. Nie dodajemy wykonanych 70 sztuk ponownie jako planowanych 70. Prognozowany termin uwzględnia zależności producenta; sam wpisany koniec zlecenia z brakującymi wejściami nie daje potwierdzonego pokrycia.
4. `needed_at` oznacza rozpoczęcie zużywającej operacji. Do czasu wdrożenia harmonogramowania operacji używamy startu jej zlecenia i opisujemy tę zachowawczą dokładność. `due_date` pozostaje terminem realizacji/dostawy.
5. Nieznany termin lub czas wykonania pozostaje oznaczony jako nieznany. Zlecenia bez daty mają osobną grupę, a nie znikają z raportu.
6. Zakupione materiały uwzględniamy przez ich istniejące przydziały, pobrania, zużycie i partie. Nie dokładamy drugiej rezerwacji na tę samą ilość. Rozpoczętych zleceń nie pomijamy bez sprawdzenia, czy pozostają niepokryte potrzeby przyszłych operacji.
7. Sumujemy tylko ten sam indeks, zgodną specyfikację/wersję i jednostkę. Pokazujemy źródła zapotrzebowania; nie sumujemy ilości sztuk, metrów i kilogramów w jednym KPI.
8. Odczyt planera nie przydziela zapasu i nie tworzy zleceń. Zmiany źródła zaopatrzenia oraz produkcja dodatkowa wymagają jawnej akcji i zachowania historii. Nie naruszamy zamrożonych ilości zleceń z partiami.
9. Całe drzewo, liczniki, szczegóły i eksport przestrzegają uprawnień. Ukryte zlecenia nie mogą wyciekać przez nazwy, ilości ani zagregowane statystyki.

## Projekt interfejsu

### 1. Oś czasu i karty

- Zachowujemy kolory statusów produkcji. Osobny niewielki znacznik z ikoną i tekstem opisuje zaopatrzenie: „Gotowe”, „Oczekuje na komponenty”, „Zagrożony termin”, „Wymaga zaplanowania”. Dla wybranego zlecenia pokazujemy dokładniej oba wymiary gotowości.
- Przykład skrótu na karcie: „2 braki · najbliższe zapotrzebowanie 15.09, 07:00”. Brak oznacza pozycję wymagania, nie sumę różnych jednostek.
- Kliknięcie znacznika otwiera panel Zapotrzebowania. Tooltip podaje najważniejszy powód blokady.
- Filtry: gotowe do rozpoczęcia, z brakami, zagrożone, bez planu, zlecenia główne/komponenty, rodzina wybranego zlecenia.
- Trwała lista problemów z możliwością przejścia do źródła. Powiadomienie po zapisie jest tylko dodatkową informacją.

### 2. Panel wybranego zlecenia

Zakładki „Zlecenie”, „Zapotrzebowania”, „Zależności”. W Zapotrzebowaniach ponownie wykorzystujemy drzewo BOM-u, ale pokazujemy stan realizacji zamiast formularza generowania.

Kolumny/wartości: komponent, wymagane, zarezerwowane/wydane, wykonane i dopuszczone, brak teraz, oczekiwane z produkcji, potrzebne na, przewidywane dostępne od, źródło oraz przyczyna ryzyka. Na wąskim ekranie szczegóły ilości rozwijają się pod wierszem.

Źródło prowadzi do zlecenia producenta, rezerwacji albo dokumentu magazynowego, zgodnie z uprawnieniami. Domyślnie wyróżniamy braki; pełne drzewo jest nadal dostępne. Przykład: EX-BEAM — wymagane 120, własna rezerwacja 50, dobre sztuki 0, brak teraz 70, planowane 70 na 06:00, potrzebne na 07:00. Opis: „Czeka na produkcję; przewidywane pokrycie na czas”, o ile także zależności producenta są wykonalne.

### 3. Rodzina zlecenia i zależności na osi

Po wybraniu sofy: wyróżnienie jej zleceń komponentów na odpowiednich liniach, przygaszenie pozostałych, rozwijane poziomy oraz strzałki producent → konsument. Strzałki pokazujemy dla wybranej rodziny, aby cała hala pozostała czytelna.

Zlecenie poza widocznym okresem/na ukrytej linii ma znacznik „poza widokiem” i nawigację. Stan magazynowy jest źródłem w panelu, bez fikcyjnego paska produkcyjnego. W osobnym widoku rodziny można rozwinąć wszystkie poziomy, niezależnie od podziału na linie.

### 4. Zbiorcze Zapotrzebowania

Przełącznik obok widoków planera: „Harmonogram / Zapotrzebowania”. Tabela wg daty/godziny potrzeby, z grupowaniem na dzień lub zmianę i rozwijaniem źródłowych zleceń. Zakresy: bieżący widok, zmiana, tydzień, bez ustalonego terminu. Filtry magazynu, linii, rodziny, komponentów wytwarzanych i materiałów kupowanych.

Wiersz pokazuje potrzebną ilość, pokrycie przydzielone, wolny zgodny zapas, oczekiwaną produkcję oraz przewidywany brak na termin. Lista priorytetów wynika z terminu potrzeby i priorytetu zleceń. Istniejących rezerwacji nie przenosimy automatycznie między zleceniami.

### 5. Przesuwanie i propozycje harmonogramu

Przesunięcie paska najpierw ocenia wpływ: spóźnione wejścia, zagrożeni odbiorcy, kolizje linii i granice kalendarza. Zmiana bez konfliktu zapisuje się zwyczajnie. Przy konflikcie pokazujemy konkretny podgląd zmian, nie ogólne pytanie bez listy skutków.

Późniejsza akcja „Zaproponuj terminy komponentów” liczy od momentu potrzeby wstecz, uwzględniając czasy wykonania, ilości, przezbrojenia, przekazanie między operacjami, kalendarze zmian, utrzymanie i obciążenie. Brak danych czasu ma być widoczny; nie zakładamy arbitralnie godziny na każdy komponent. Rozpoczęte i przypięte zlecenia pozostają na miejscu. Przed zapisem: lista proponowanych przesunięć i konfliktów; zapis atomowy z ponowną kontrolą aktualności i historią zmian.

## Kolejność realizacji

| Etap | Zakres | Warunek ukończenia |
|---|---|---|
| A — wiarygodne dane i widoczność | Wspólna projekcja potrzeb/pokrycia, statusy na kartach, trwałe problemy, panel drzewa, odświeżanie po zmianach magazynu/jakości/produkcji | Ten sam brak i gotowość w karcie, panelu i szczegółach zlecenia; EX 120/50/70; widoczność działa po odświeżeniu bez przesuwania paska |
| B — kontekst całej produkcji | Fokus rodziny, zależności i widok zbiorczy zapotrzebowań; adapter istniejących przydziałów materiałowych | Brak podwójnego liczenia materiałów/komponentów; źródła każdej ilości dostępne; zlecenia poza okresem i bez terminów widoczne we właściwy sposób |
| C — świadome zmiany terminów | Podgląd skutków przesunięcia, zależności przechodnie, ostrzeżenia o dostawach i obciążeniu, zapis z kontrolą wersji | Przesunięcie belki ujawnia wpływ na bok, stelaż i sofę; konkurencyjna zmiana blokuje zapis nieaktualnej propozycji |
| D — propozycje automatyczne | Planowanie wstecz dla wybranej rodziny, kalendarze i dane czasowe, przypinanie zleceń | Plan wykonalny lub konkretna lista powodów niewykonalności; brak zmian do chwili zaakceptowania propozycji |

Rekomendacja: jako najbliższy przyrost wdrożyć etap A, a następnie B. Nie uzależniać użytecznej widoczności braków od gotowości automatycznego planowania.

## Technicznie

- Wspólna usługa odczytowa projekcji potrzeb, używana przez karty, szczegóły i tabelę. Korzysta z zamrożonego BOM-u i istniejących mechanizmów kwalifikacji zapasu/wyniku; frontend nie powiela zasad liczenia.
- Zbiorcze ładowanie rezerwacji, partii, jakości i powiązań. Karty otrzymują małe podsumowania; pełne drzewo ładowane po wybraniu zlecenia. Nie wywołujemy zapytaniowego podsumowania całej rodziny oddzielnie dla każdej karty.
- Prognoza zależności wyliczana od liści do korzenia; ilości planowane i faktyczne mają osobne pola. Ewentualne potwierdzone dostawy z ERP będą osobnym źródłem prognozy — dopiero po ustaleniu kontraktu danych, bez udawania magazynu przed przyjęciem i kontrolą jakości.
- Odświeżanie również po zmianie salda, rezerwacji, statusu partii i kontroli jakości; uwzględnić importy masowe. Pokazać aktualność danych. Nie przerywać przeciągania kart odświeżeniem w jego trakcie.
- Wspólny kontrakt czasu/strefy zakładu dla UI i API, jawna dokładność terminu. Dla nieznanych dat/czasów osobne stany.
- Endpoint podglądu wpływu zmian jest bez zapisu; endpoint zastosowania weryfikuje wersje, uprawnienia i kolizje w transakcji. Istniejącą historię przesunięć rozszerzyć tak, aby zmiana wielu zleceń była jedną czytelną operacją.
- Nowej projekcji nie podstawiać bez porównania pod stary raport MRP: ma inne źródła i zakres terminów. Pokryć regresją obie ścieżki i dopiero świadomie ujednolicić definicje.

## Scenariusze odbioru

1. EX-BEAM 120: rezerwacja 50, produkcja 70. Przed ukończeniem brak teraz 70; prognoza może być na czas. Po dopuszczeniu 70 brak teraz 0; po wydaniu 50 nadal pełne pokrycie, bez podwójnego liczenia.
2. Gotowe stelaże w magazynie pomniejszają popyt na boki i belki na wszystkich ekranach.
3. Spóźniona belka zagraża kolejnym poziomom nawet wtedy, gdy ich wpisane terminy wyglądają poprawnie.
4. Zmiana jakości/rezerwacji/salda odświeża otwarty planer bez dotykania harmonogramu.
5. Dwa zlecenia konkurujące o te same 50 sztuk nie pokazują obu własnego potwierdzonego pokrycia.
6. Kupiony materiał pobrany lub już przydzielony nie jest drugi raz odejmowany od magazynu; otwarte potrzeby rozpoczętej operacji są poprawnie prezentowane.
7. Zlecenie bez `due_date`, ale z planowanym rozpoczęciem, trafia do właściwego okresu zapotrzebowań. Nieznany start pozostaje w „Bez terminu”.
8. Operator nie widzi danych nieprzypisanych linii ani przez drzewo, ani przez agregaty.
9. Widok jednej rodziny pozostaje czytelny na kilku liniach i na wąskim ekranie; komponent poza horyzontem ma działające przejście.
10. Podgląd zmiany niczego nie zapisuje; konflikt wersji wymaga odświeżenia; anulowanie propozycji pozostawia plan i rezerwacje bez zmian.
