<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Line;
use App\Models\Workstation;
use App\Models\ProductType;
use App\Models\ProcessTemplate;
use App\Models\WorkOrder;
use Spatie\Permission\Models\Role;

/**
 * Demo data for a print-on-demand / garment decoration company.
 * Seeds: lines, workstations, product types, process templates
 * with steps, operators, supervisors, and example work orders.
 */
class PrintShopDemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Extra issue types for print shop ─────────────────────────────
        $this->seedIssueTypes();

        // ── 2. Production lines ──────────────────────────────────────────────
        $lines = $this->seedLines();

        // ── 3. Workstations ──────────────────────────────────────────────────
        $workstations = $this->seedWorkstations($lines);

        // ── 4. Product types ─────────────────────────────────────────────────
        $productTypes = $this->seedProductTypes();

        // ── 5. Process templates + steps ─────────────────────────────────────
        $this->seedProcessTemplates($productTypes, $workstations, $lines);

        // ── 6. Users (supervisor + operators) ────────────────────────────────
        $users = $this->seedUsers($lines);

        // ── 7. Example work orders ────────────────────────────────────────────
        $this->seedWorkOrders($productTypes, $lines);
    }

    // ── Issue types ──────────────────────────────────────────────────────────

    private function seedIssueTypes(): void
    {
        $types = [
            ['code' => 'PRINT_COLOR_MISMATCH', 'name' => 'Błąd koloru nadruku',           'severity' => 'HIGH',     'is_blocking' => false],
            ['code' => 'PRINT_SMEAR',          'name' => 'Rozmazanie nadruku',             'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'SUBSTRATE_DAMAGE',     'name' => 'Uszkodzenie podłoża (wyrób)',    'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'PRINT_HEAD_FAILURE',   'name' => 'Awaria głowicy drukarki DTG',   'severity' => 'CRITICAL', 'is_blocking' => true],
            ['code' => 'THREAD_BREAK',         'name' => 'Zerwanie nici hafciarskiej',     'severity' => 'MEDIUM',   'is_blocking' => false],
            ['code' => 'SCREEN_CLOGGED',       'name' => 'Zapchanie sita / matrycy',       'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'INK_SHORTAGE',         'name' => 'Brak farby / tuszów',            'severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'ARTWORK_ERROR',        'name' => 'Błąd pliku graficznego',         'severity' => 'MEDIUM',   'is_blocking' => true],
            ['code' => 'PRESS_TEMP_ERROR',     'name' => 'Nieprawidłowa temperatura prasy','severity' => 'HIGH',     'is_blocking' => true],
            ['code' => 'SIZE_MISMATCH',        'name' => 'Błędny rozmiar / pozycja nadruku','severity' => 'MEDIUM',  'is_blocking' => false],
        ];

        foreach ($types as $type) {
            DB::table('issue_types')->updateOrInsert(
                ['code' => $type['code']],
                array_merge($type, ['is_active' => true])
            );
        }
    }

    // ── Lines ────────────────────────────────────────────────────────────────

    private function seedLines(): array
    {
        $defs = [
            ['code' => 'DTG',       'name' => 'Druk DTG',          'description' => 'Druk cyfrowy bezpośredni na tkaninie (Direct-to-Garment)'],
            ['code' => 'SITO',      'name' => 'Sitodruk',          'description' => 'Druk sitowy (screen printing) — serie od 12 szt.'],
            ['code' => 'HAFT',      'name' => 'Haft Komputerowy',  'description' => 'Haft maszynowy — koszulki, czapki, bluzy'],
            ['code' => 'TRANSFER',  'name' => 'Transfer Termiczny', 'description' => 'Nadruk transferowy (flex, folia, sublimacja)'],
            ['code' => 'PAKOWANIE', 'name' => 'Pakowanie i Wysyłka','description' => 'Pakowanie gotowych wyrobów, etykietowanie, wysyłka'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $line = Line::updateOrCreate(['code' => $def['code']], array_merge($def, ['is_active' => true]));
            $result[$def['code']] = $line;
        }

        return $result;
    }

    // ── Workstations ─────────────────────────────────────────────────────────

    private function seedWorkstations(array $lines): array
    {
        $defs = [
            // DTG line
            ['line' => 'DTG',       'code' => 'DTG-PRE-1',  'name' => 'Stacja Pretreating #1',       'workstation_type' => 'pretreat'],
            ['line' => 'DTG',       'code' => 'DTG-1',       'name' => 'Drukarka DTG #1 (Epson F2100)','workstation_type' => 'printer'],
            ['line' => 'DTG',       'code' => 'DTG-2',       'name' => 'Drukarka DTG #2 (Epson F2100)','workstation_type' => 'printer'],
            ['line' => 'DTG',       'code' => 'DTG-CURE-1', 'name' => 'Piec Utrwalający #1',          'workstation_type' => 'curing'],
            // Sitodruk line
            ['line' => 'SITO',      'code' => 'SITO-EXP-1', 'name' => 'Naświetlarka Sita #1',        'workstation_type' => 'exposure'],
            ['line' => 'SITO',      'code' => 'SITO-1',      'name' => 'Stół Sitodruku #1',           'workstation_type' => 'press'],
            ['line' => 'SITO',      'code' => 'SITO-2',      'name' => 'Stół Sitodruku #2',           'workstation_type' => 'press'],
            ['line' => 'SITO',      'code' => 'SITO-DRY-1', 'name' => 'Suszarka Taśmowa',            'workstation_type' => 'dryer'],
            // Haft line
            ['line' => 'HAFT',      'code' => 'HAFT-1',      'name' => 'Maszyna Hafciarska #1 (Barudan)','workstation_type' => 'embroidery'],
            ['line' => 'HAFT',      'code' => 'HAFT-2',      'name' => 'Maszyna Hafciarska #2 (Barudan)','workstation_type' => 'embroidery'],
            ['line' => 'HAFT',      'code' => 'HAFT-3',      'name' => 'Maszyna Hafciarska #3 (Tajima)', 'workstation_type' => 'embroidery'],
            // Transfer line
            ['line' => 'TRANSFER',  'code' => 'TRANS-1',     'name' => 'Prasa Termotransferowa #1',   'workstation_type' => 'heat_press'],
            ['line' => 'TRANSFER',  'code' => 'TRANS-2',     'name' => 'Prasa Termotransferowa #2',   'workstation_type' => 'heat_press'],
            ['line' => 'TRANSFER',  'code' => 'TRANS-SUB-1','name' => 'Piec Sublimacyjny #1',         'workstation_type' => 'sublimation'],
            // Pakowanie
            ['line' => 'PAKOWANIE', 'code' => 'PAK-1',       'name' => 'Stanowisko Pakowania #1',     'workstation_type' => 'packing'],
            ['line' => 'PAKOWANIE', 'code' => 'PAK-2',       'name' => 'Stanowisko Pakowania #2',     'workstation_type' => 'packing'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $ws = Workstation::updateOrCreate(
                ['code' => $def['code']],
                [
                    'line_id'          => $lines[$def['line']]->id,
                    'name'             => $def['name'],
                    'workstation_type' => $def['workstation_type'],
                    'is_active'        => true,
                ]
            );
            $result[$def['code']] = $ws;
        }

        return $result;
    }

    // ── Product types ─────────────────────────────────────────────────────────

    private function seedProductTypes(): array
    {
        $defs = [
            ['code' => 'TSHIRT',     'name' => 'Koszulka T-Shirt',          'description' => 'Koszulka z krótkim rękawem, bawełna 100%',           'unit_of_measure' => 'szt'],
            ['code' => 'HOODIE',     'name' => 'Bluza z Kapturem',          'description' => 'Hoodie / bluza kangurka z kapturem',                  'unit_of_measure' => 'szt'],
            ['code' => 'SWEATSHIRT', 'name' => 'Bluza bez Kaptura',        'description' => 'Klasyczna bluza (crewneck), bawełna/poliester',        'unit_of_measure' => 'szt'],
            ['code' => 'POLO',       'name' => 'Koszulka Polo',            'description' => 'Polo z kołnierzykiem, piqué',                          'unit_of_measure' => 'szt'],
            ['code' => 'CAP',        'name' => 'Czapka z Daszkiem',        'description' => 'Czapka typu baseball / snapback',                      'unit_of_measure' => 'szt'],
            ['code' => 'BEANIE',     'name' => 'Czapka Zimowa (Beanie)',   'description' => 'Czapka dziana, haft lub naszywka',                     'unit_of_measure' => 'szt'],
            ['code' => 'TOTE',       'name' => 'Torba Bawełniana',         'description' => 'Torba tote bag, bawełna naturalna',                    'unit_of_measure' => 'szt'],
            ['code' => 'JACKET',     'name' => 'Kurtka / Wiatrówka',       'description' => 'Kurtka softshell lub wiatrówka z nadrukiem',           'unit_of_measure' => 'szt'],
            ['code' => 'MUG',        'name' => 'Kubek Sublimacyjny',       'description' => 'Kubek ceramiczny do sublimacji (330 ml)',               'unit_of_measure' => 'szt'],
            ['code' => 'PILLOW',     'name' => 'Poduszka z Nadrukiem',     'description' => 'Poszewka na poduszkę z nadrukiem sublimacyjnym',        'unit_of_measure' => 'szt'],
        ];

        $result = [];
        foreach ($defs as $def) {
            $pt = ProductType::updateOrCreate(['code' => $def['code']], array_merge($def, ['is_active' => true]));
            $result[$def['code']] = $pt;
        }

        return $result;
    }

    // ── Process templates ─────────────────────────────────────────────────────

    private function seedProcessTemplates(array $pt, array $ws, array $lines): void
    {
        // T-Shirt — druk DTG
        $this->createTemplate($pt['TSHIRT'], 'T-Shirt — Druk DTG', [
            [1, 'Weryfikacja pliku graficznego',   'Sprawdź rozdzielczość (min 150 dpi), profil kolorów, brak elementów zbyt blisko krawędzi.', 10, null],
            [2, 'Pranie i prasowanie podłoża',     'Wypierz koszulkę jeśli nowa z metką "wash before print". Wygładź gorącą prasą.', 5, $ws['DTG-PRE-1'] ?? null],
            [3, 'Pretreating',                     'Nałóż pretreat równomiernie na obszar nadruku. Wymieszaj butelkę przed użyciem.', 10, $ws['DTG-PRE-1'] ?? null],
            [4, 'Druk DTG',                        'Umieść koszulkę na palecie, wycentruj nadruk. Uruchom wydruk zgodnie z profilem koloru.', 15, $ws['DTG-1'] ?? null],
            [5, 'Utrwalanie w piecu',               'Przesuń przez piec taśmowy: temperatura 165°C, czas ok. 90 sek. Sprawdź wilgotność.', 8, $ws['DTG-CURE-1'] ?? null],
            [6, 'Kontrola jakości nadruku',        'Oceń krycie kolorów, ostrość krawędzi, brak smug. Odrzuć braki.', 5, null],
            [7, 'Pakowanie',                       'Złóż starannie, włóż do woreczka foliowego, naklejj etykietę z numerem zlecenia.', 5, $ws['PAK-1'] ?? null],
        ]);

        // Hoodie — haft
        $this->createTemplate($pt['HOODIE'], 'Bluza — Haft Komputerowy', [
            [1, 'Weryfikacja projektu haftu',       'Otwórz plik DST/PES, sprawdź liczbę kolorów nici, punkty startowe i zatrzymania.', 15, null],
            [2, 'Przygotowanie maszyny i nici',     'Nawlecz nici zgodnie z kartą kolorów. Zamontuj odpowiedni stabilizator (tearaway / cutaway).', 10, $ws['HAFT-1'] ?? null],
            [3, 'Napinanie materiału w tamborku',   'Naciągnij bluzę na tamborek. Materiał musi być równy, bez zagięć.', 8, $ws['HAFT-1'] ?? null],
            [4, 'Haft',                             'Uruchom maszynę. Monitoruj pierwsze 10 ściegów. Sprawdź naprężenie nici co 5 min.', 25, $ws['HAFT-1'] ?? null],
            [5, 'Czyszczenie i wykończenie haftu',  'Usuń nadmiar stabilizatora, wytnij luźne nici, wyparuj haft jeśli potrzeba.', 10, null],
            [6, 'Kontrola jakości haftu',           'Sprawdź gęstość ściegów, brak skoków, wyrównanie kolorów z projektem.', 5, null],
            [7, 'Pakowanie',                        'Złóż bluzę, woreczek foliowy, etykieta z zamówieniem.', 5, $ws['PAK-1'] ?? null],
        ]);

        // Polo — sitodruk
        $this->createTemplate($pt['POLO'], 'Polo — Sitodruk', [
            [1, 'Przygotowanie matrycy (sita)',     'Wywoła sitodruk z przygotowanego filmu. Sprawdź drożność sita po wywołaniu.', 20, $ws['SITO-EXP-1'] ?? null],
            [2, 'Rejestracja i pozycjonowanie',     'Zamocuj sito na stole. Ustaw rejestrację (pozycję) za pomocą linijek i taśmy.', 10, $ws['SITO-1'] ?? null],
            [3, 'Próbna odbitka',                   'Wykonaj 1 odbitkę próbną. Sprawdź krycie, rejestrację i kolor. Zatwierdź do produkcji.', 10, $ws['SITO-1'] ?? null],
            [4, 'Sitodruk — produkcja seryjna',    'Drukuj partię. Uzupełniaj farbę co ~30 szt. Sprawdzaj co 10. szt. jakość.', 30, $ws['SITO-1'] ?? null],
            [5, 'Suszenie',                         'Przepuść przez suszarkę taśmową: 160°C / 60 sek. Test zrywania taśmy klejącej.', 10, $ws['SITO-DRY-1'] ?? null],
            [6, 'Kontrola jakości',                 'Skontroluj próbkę co 20 szt.: krycie, ostrość, brak śladów fleksu/tuszu.', 5, null],
            [7, 'Pakowanie',                        'Złóż koszulki polo, pakuj po 12 szt. (1 tuzin). Naklejaj etykiety seryjne.', 8, $ws['PAK-2'] ?? null],
        ]);

        // Czapka — haft
        $this->createTemplate($pt['CAP'], 'Czapka — Haft na Daszku', [
            [1, 'Weryfikacja projektu haftu na czapkę', 'Sprawdź plik: projekt przystosowany do haftu na daszku (płaskie pole, maks 80 mm szerokości).', 10, null],
            [2, 'Montaż kaptura w rammie do czapek',    'Zamontuj ramę do czapek. Naciągnij daszek czapki.', 8, $ws['HAFT-2'] ?? null],
            [3, 'Haft',                                  'Uruchom program. Pilnuj stabilności materiału przy hafcie na krzywiźnie.', 20, $ws['HAFT-2'] ?? null],
            [4, 'Wykończenie i czyszczenie',             'Usuń stabilizator, wytnij nici, sprawdź odwrotną stronę daszka.', 5, null],
            [5, 'Kontrola jakości',                      'Sprawdź centrowanie haftu na daszku, krycie kolorów.', 5, null],
            [6, 'Pakowanie',                             'Umieść czapkę w woreczku, naklejj etykietę z zamówieniem.', 3, $ws['PAK-1'] ?? null],
        ]);

        // Torba — sitodruk lub transfer
        $this->createTemplate($pt['TOTE'], 'Torba Bawełniana — Transfer Termiczny', [
            [1, 'Wydruk transferu (folia/flex)',   'Wydrukuj transfer na ploterze lub w druku transferowym. Poczekaj na wyschnięcie.', 10, null],
            [2, 'Przygotowanie prasy termicznej',  'Ustaw temperaturę: 160°C, czas 15 sek., nacisk średni. Rozgrzej prasę 5 min.', 5, $ws['TRANS-1'] ?? null],
            [3, 'Pozycjonowanie na torbie',        'Rozłóż torbę na płycie prasy, wycentruj transfer. Użyj linijki lub szablonu.', 5, $ws['TRANS-1'] ?? null],
            [4, 'Prasowanie transferu',            'Przyłóż folię ochronną, zamknij prasę. Odczekaj 15 sek. Oderwij folię zimno lub gorąco wg. instrukcji producenta.', 5, $ws['TRANS-1'] ?? null],
            [5, 'Kontrola jakości',                'Sprawdź krawędzie transferu, brak pęcherzy powietrza, krycie.', 3, null],
            [6, 'Pakowanie',                       'Złóż torbę, włóż do woreczka z nadrukiem na wierzchu.', 3, $ws['PAK-2'] ?? null],
        ]);

        // Kubek — sublimacja
        $this->createTemplate($pt['MUG'], 'Kubek — Sublimacja', [
            [1, 'Wydruk transferu sublimacyjnego', 'Wydrukuj projekt lustrzanie na papierze sublimacyjnym. Przytnij z zapasem 5 mm.', 10, null],
            [2, 'Owijanie kubka transferem',       'Owij kubek papierem, dociskaj taśmą termoodporną. Papier musi przylegać bez fałd.', 5, $ws['TRANS-SUB-1'] ?? null],
            [3, 'Sublimacja w piecu',              'Umieść w piecu sublimacyjnym: 200°C / 4 min. Nie otwierać przedwcześnie.', 5, $ws['TRANS-SUB-1'] ?? null],
            [4, 'Chłodzenie i zdejmowanie papieru','Wyjmij kubek, odstaw do przestygnięcia 2 min. Zdejmij papier.', 3, null],
            [5, 'Kontrola jakości',                'Sprawdź nasycenie kolorów, brak białych plam (niedostateczny nacisk), ostrość.', 5, null],
            [6, 'Pakowanie',                       'Umieść kubek w pudełku z wypełnieniem chroniącym przed stłuczeniem.', 3, $ws['PAK-1'] ?? null],
        ]);

        // Bluza bez kaptura — DTG
        $this->createTemplate($pt['SWEATSHIRT'], 'Bluza bez Kaptura — Druk DTG', [
            [1, 'Weryfikacja pliku graficznego',   'Sprawdź rozdzielczość, profil kolorów, wymiary nadruku (max A3).', 10, null],
            [2, 'Pretreating',                     'Nałóż pretreat na bluzę. Uwaga na grubszy materiał — zwiększ dawkę 15%.', 12, $ws['DTG-PRE-1'] ?? null],
            [3, 'Druk DTG',                        'Ustaw bluzę na palecie, wycentruj. Użyj profilu dla grubszych tkanin.', 18, $ws['DTG-2'] ?? null],
            [4, 'Utrwalanie',                      'Przesuń przez piec: 165°C, 100 sek. (dłużej niż koszulka — grubszy materiał).', 10, $ws['DTG-CURE-1'] ?? null],
            [5, 'Kontrola jakości',                'Sprawdź krycie, brak smug, brak przerw w nadruku.', 5, null],
            [6, 'Pakowanie',                       'Złóż, zapakuj w woreczek foliowy, naklejj etykietę.', 5, $ws['PAK-1'] ?? null],
        ]);
    }

    private function createTemplate(ProductType $productType, string $name, array $steps): void
    {
        $template = ProcessTemplate::updateOrCreate(
            ['product_type_id' => $productType->id, 'version' => 1],
            ['name' => $name, 'is_active' => true]
        );

        foreach ($steps as [$stepNo, $stepName, $instruction, $duration, $workstation]) {
            DB::table('template_steps')->updateOrInsert(
                ['process_template_id' => $template->id, 'step_number' => $stepNo],
                [
                    'name'                       => $stepName,
                    'instruction'                => $instruction,
                    'estimated_duration_minutes' => $duration,
                    'workstation_id'             => $workstation?->id,
                    'created_at'                 => now(),
                ]
            );
        }
    }

    // ── Users ─────────────────────────────────────────────────────────────────

    private function seedUsers(array $lines): array
    {
        $supervisorRole = Role::where('name', 'Supervisor')->first();
        $operatorRole   = Role::where('name', 'Operator')->first();

        $users = [];

        // Supervisor
        $supervisor = User::updateOrCreate(
            ['username' => 'piotr.wisniewsk'],
            [
                'name'                  => 'Piotr Wiśniewski',
                'email'                 => 'piotr.wisniewsk@printshop.local',
                'password'              => Hash::make('Supervisor1!'),
                'account_type'          => 'user',
                'force_password_change' => false,
            ]
        );
        if ($supervisorRole && !$supervisor->hasRole('Supervisor')) {
            $supervisor->assignRole($supervisorRole);
        }
        // Assign all lines
        $supervisor->lines()->syncWithoutDetaching(array_map(fn($l) => $l->id, $lines));
        $users[] = $supervisor;

        // Operators
        $operatorDefs = [
            ['username' => 'anna.kowalczyk',  'name' => 'Anna Kowalczyk',  'email' => 'anna.kowalczyk@printshop.local',  'lines' => ['DTG', 'TRANSFER']],
            ['username' => 'marek.nowak',     'name' => 'Marek Nowak',     'email' => 'marek.nowak@printshop.local',     'lines' => ['SITO']],
            ['username' => 'julia.kaminska',  'name' => 'Julia Kamińska',  'email' => 'julia.kaminska@printshop.local',  'lines' => ['HAFT']],
            ['username' => 'tomasz.zielinski','name' => 'Tomasz Zieliński','email' => 'tomasz.zielinski@printshop.local','lines' => ['PAKOWANIE', 'DTG']],
        ];

        foreach ($operatorDefs as $def) {
            $user = User::updateOrCreate(
                ['username' => $def['username']],
                [
                    'name'                  => $def['name'],
                    'email'                 => $def['email'],
                    'password'              => Hash::make('Operator1!'),
                    'account_type'          => 'user',
                    'force_password_change' => false,
                ]
            );
            if ($operatorRole && !$user->hasRole('Operator')) {
                $user->assignRole($operatorRole);
            }
            $lineIds = array_map(fn($code) => $lines[$code]->id, $def['lines']);
            $user->lines()->syncWithoutDetaching($lineIds);
            $users[] = $user;
        }

        return $users;
    }

    // ── Example work orders ───────────────────────────────────────────────────

    private function seedWorkOrders(array $pt, array $lines): void
    {
        $orders = [
            [
                'order_no'    => 'WO-2026-001',
                'line_id'     => $lines['DTG']->id,
                'product_type_id' => $pt['TSHIRT']->id,
                'planned_qty' => 50,
                'status'      => WorkOrder::STATUS_IN_PROGRESS,
                'priority'    => 3,
                'due_date'    => now()->addDays(2),
                'description' => 'Koszulki firmowe — logo klienta XYZ Sp. z o.o. (druk DTG, kolor biały, rozmiary M/L/XL)',
            ],
            [
                'order_no'    => 'WO-2026-002',
                'line_id'     => $lines['HAFT']->id,
                'product_type_id' => $pt['CAP']->id,
                'planned_qty' => 30,
                'status'      => WorkOrder::STATUS_PENDING,
                'priority'    => 2,
                'due_date'    => now()->addDays(5),
                'description' => 'Czapki z haftem — logo drużyny sportowej, haft 3D, kolor granatowy',
            ],
            [
                'order_no'    => 'WO-2026-003',
                'line_id'     => $lines['SITO']->id,
                'product_type_id' => $pt['POLO']->id,
                'planned_qty' => 100,
                'status'      => WorkOrder::STATUS_ACCEPTED,
                'priority'    => 4,
                'due_date'    => now()->addDay(),
                'description' => 'Koszulki polo sitodruk 2 kolory — odzież robocza firma budowlana',
            ],
            [
                'order_no'    => 'WO-2026-004',
                'line_id'     => $lines['TRANSFER']->id,
                'product_type_id' => $pt['TOTE']->id,
                'planned_qty' => 200,
                'status'      => WorkOrder::STATUS_PENDING,
                'priority'    => 1,
                'due_date'    => now()->addDays(7),
                'description' => 'Torby bawełniane transfer flex — gadżety konferencyjne, nadruk 1 kolor',
            ],
            [
                'order_no'    => 'WO-2026-005',
                'line_id'     => $lines['DTG']->id,
                'product_type_id' => $pt['HOODIE']->id,
                'planned_qty' => 25,
                'status'      => WorkOrder::STATUS_DONE,
                'priority'    => 2,
                'due_date'    => now()->subDay(),
                'description' => 'Bluzy z kapturem — projekt artystyczny, limitowana edycja, druk full-color DTG',
                'completed_at' => now()->subHours(3),
            ],
            [
                'order_no'    => 'WO-2026-006',
                'line_id'     => $lines['TRANSFER']->id,
                'product_type_id' => $pt['MUG']->id,
                'planned_qty' => 48,
                'status'      => WorkOrder::STATUS_IN_PROGRESS,
                'priority'    => 3,
                'due_date'    => now()->addDays(3),
                'description' => 'Kubki sublimacyjne — zdjęcia klientów indywidualnych (zamówienie świąteczne)',
            ],
            [
                'order_no'    => 'WO-2026-007',
                'line_id'     => $lines['HAFT']->id,
                'product_type_id' => $pt['SWEATSHIRT']->id,
                'planned_qty' => 15,
                'status'      => WorkOrder::STATUS_PENDING,
                'priority'    => 2,
                'due_date'    => now()->addDays(4),
                'description' => 'Bluzy bez kaptura — haft logo uczelni, kolor czarny, rozmiary S-XXL',
            ],
        ];

        foreach ($orders as $orderData) {
            WorkOrder::updateOrCreate(
                ['order_no' => $orderData['order_no']],
                array_merge($orderData, ['produced_qty' => 0])
            );
        }
    }
}
