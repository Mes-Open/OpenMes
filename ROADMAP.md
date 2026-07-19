# OpenMES — Roadmap

The project roadmap is managed on the GitHub Project Board:

**[github.com/orgs/Mes-Open/projects/1](https://github.com/orgs/Mes-Open/projects/1)**

## Proposed future modules

Gaps identified against comparable MES/ERP systems (e.g. Qcadoo), not yet scheduled on the board above.

### Zaopatrzenie i zamówienia zakupu (Procurement)

OpenMES nie ma dziś żadnego modelu dostawcy ani zamówienia zakupu — istnieje wyłącznie referencyjne pole „źródło materiału”, które niczego nie zamawia ani nie planuje. To największa realna luka funkcjonalna względem klasycznego MES/ERP, bo bez niej system nie odpowiada na pytanie „kiedy i od kogo zamówić brakujący materiał".

Zakres modułu:
- Kartoteka dostawców (dane kontaktowe, warunki handlowe, przypisane materiały/ceny)
- Zapytania ofertowe (RFQ) generowane na podstawie braków z planowania zapotrzebowania
- Porównanie ofert wielu dostawców (cena, termin, MOQ) przed wyborem
- Zamówienia zakupu (PO) z powiązaniem do zlecenia produkcyjnego i statusem realizacji
- Planowanie i śledzenie terminów dostaw, z alertem przy zagrożonym terminie

### Śledzenie kooperacji / poddostawców (Subcontracting)

OpenMES nie rozróżnia dziś operacji wykonywanych we własnym zakładzie od tych zlecanych na zewnątrz — cała produkcja jest modelowana jako wewnętrzna. Brakuje więc pojęcia „kooperacji": wysłania półproduktu do poddostawcy, śledzenia go poza zakładem i przyjęcia z powrotem do dalszej produkcji.

Zakres modułu:
- Oznaczenie operacji/etapu technologii jako wykonywanego u poddostawcy
- Dokumenty wydania materiału do kooperanta i przyjęcia gotowego etapu z powrotem
- Śledzenie statusu i terminu realizacji zlecenia kooperacyjnego (poza własnym harmonogramem)
- Uwzględnienie czasu i kosztu kooperacji w koszcie całkowitym zlecenia produkcyjnego

### Rozliczenia kosztowe i rentowność zleceń (Production cost accounting)

OpenMES śledzi dziś przebieg produkcji operacyjnie (czas, materiał, braki), ale nie przelicza tego na koszt ani rentowność zlecenia. Brakuje warstwy „ile to nas kosztowało i czy się opłacało" — kluczowej dla działów produkcji i zarządu przy podejmowaniu decyzji cenowych.

Zakres modułu:
- Kalkulacja kosztu zlecenia: materiał (wg BOM/rzeczywistego zużycia), robocizna (wg czasu rejestrowanego na hali), narzuty maszynowe/ogólnozakładowe
- Porównanie kosztu planowanego (na podstawie technologii) z kosztem rzeczywistym
- Rentowność zlecenia względem ceny sprzedaży/wartości zamówienia klienta
- Raport odchyleń kosztowych do analizy przyczyn (materiał, czas, braki, kooperacja)

## Workflow & UX orchestration (field feedback)

From a high-volume, supervisor-driven production plant. The data model is considered strong; the opportunity is to reorganize the UI around the **decisions users make every day** rather than around the underlying entities. Guiding principle: *mirror what the user would be thinking if they were doing the task on paper, and reduce it to the fewest steps.* Items in this section are tagged **`probable`** — likely to be taken on, pending board prioritization.

### Unified production reporting `probable`

Today, reporting output and reporting scrap are two separate flows in different parts of the app (produced quantity on the workstation screen, scrap during the work-order release flow), and reaching them takes several tab switches (Lines → Workstation → Report → Queue → locate work order). In a plant that reports every couple of hours or every pallet, this repetition is the single largest source of daily friction and pushes people back to paper. The goal is one action that captures **good quantity + scrap (with reason) together** and submits in a single step, so the operator never leaves the screen they are working on.

### Role-based entry points `probable`

Operators, supervisors, schedulers and managers currently start from the same screens and the same mental model, even though they think about production differently. Each role should land on a home built around its own decisions: an **operator** lands directly on their assigned work order (with a simple selector when there is more than one) with "Report production" as the primary action; a **supervisor** starts from a dashboard of active work and drills into a line/order to report or adjust. The per-role tab-access matrix already exists as the technical base — what is missing is the dedicated, decision-first landing screens.

### Machine-counter production reporting `probable`

Many operators report output by reading a machine counter (a start value and an end value) rather than typing a running total, and some report by batch/pallet count. Reporting should offer a **counter mode** (enter start/end, the system computes the delta) alongside the existing manual-quantity and per-pallet modes, so the on-screen action matches how the number is actually produced on the floor.

### Shift board — live labor assignment & mid-shift adjustments `probable`

There is no fast path for the things that happen constantly during a shift: someone calls off, people need to be moved between lines, a line is restarted or shut down. A **shift board** would show lines as columns and assigned people as cards, with drag-and-drop to reassign an operator between lines, and one-gesture handling of a call-off (drop the person, pick a replacement). Line restart/stop would reuse the existing machine states (waiting / cleaning / maintenance) as quick actions instead of navigating into the Machine Monitor.

### Start-of-shift scheduling view `probable`

At the start of a shift the supervisor assigns labor from scratch and handles absences, and the key questions are not surfaced efficiently today: **what can run right now, who is available to run it, when will each job finish, and — if someone called off — how do I reassign quickly.** A dedicated start-of-shift view would answer these directly: a "ready to run" list (work orders whose BOM/material is ready), a pool of available people (wired to the existing worker-availability/absence service), and an estimated finish time per job. This is orchestration of primitives that already exist (work orders, lines, absences, crews), not a new data model.

### Multi-day scheduling — Gantt with readiness & dependencies `probable`

A production scheduler needs to see, over several days: what is already scheduled, how long each order will take, which lines are best suited to which jobs, what dependencies exist (materials, setup, BOM readiness), and when a line can realistically start. The current drag-to-place schedule planner should grow into a **line × time Gantt** where each order bar shows its duration and its **readiness/blocker signals** (e.g. missing material turns the bar red, using the MRP shortage data), so blockers are visible in place rather than in a separate report.

### Made-to-order vs stock order-entry paths `probable`

Order entry currently treats every order the same, but the plant runs two distinct modes: **made-to-order** units, where customizing the process per order matters, and **stock** products, where reusing an established setup matters. Order creation should split into two guided paths — "from template / stock" (fast reuse of a process template) and "made-to-order" (customize the steps) — building on the existing process templates and variant/optional steps, which today lack the UX that routes the user down the right path.
