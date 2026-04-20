# OpenMES — Roadmap

## v0.6.0 — Onboarding Wizard

Kreator pierwszej konfiguracji po instalacji, prowadzący użytkownika krok po kroku:

1. **Utwórz linię produkcyjną** — nazwa, kod, opis
2. **Dodaj typ produktu** — nazwa, przypisanie do linii
3. **Zdefiniuj szablon procesu** — kroki technologiczne z kolejnością i czasem
4. **Utwórz pierwsze zlecenie** — numer, ilość, przypisanie do linii i szablonu

Pomijany przy kolejnych logowaniach. Dostępny ponownie z ustawień.

---

## v0.7.0 — Magazyn & Materiały (BOM)

| Feature | Opis |
|---|---|
| **Bill of Materials (BOM)** | Struktura materiałowa produktu — jakie surowce/komponenty na 1 sztukę. Modele: `Material`, `BomItem`. |
| **Rezerwacja materiałów** | Automatyczne sprawdzenie dostępności przy akceptacji zlecenia. Brak materiału blokuje start. |
| **Wydanie materiałów na zlecenie** | Rejestracja faktycznego zużycia vs planowanego. Raport odchyleń. |
| **Magazyn minimalny** | Alerty gdy stan spada poniżej minimum. |

---

## v0.8.0 — Integracje & IoT

| Feature | Opis |
|---|---|
| **OPC UA connector** | Drugi protokół komunikacji z maszynami (standard przemysłowy). |
| **Webhook outgoing** | Powiadomienia HTTP przy zmianach statusu zleceń, usterkach, zakończeniu partii. |
| **REST API dla ERP** | Synchronizacja z SAP, Comarch, Enova — import zleceń, eksport raportów. |
| **Modbus TCP** | Trzeci protokół connectivity dla starszych maszyn. |
| **Etykiety / Druk** | Generowanie etykiet produkcyjnych (PDF/ZPL) z kodem kreskowym. |
