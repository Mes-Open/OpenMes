import { test, expect, type Browser, type Page, type Locator } from '@playwright/test';
import { execSync } from 'node:child_process';
import path from 'node:path';

/**
 * Screenshots for index.html, taken on a CLEAN instance loaded with generic
 * demo data (doc-seed.php) - never on a database holding real or imported
 * data, so nothing but neutral names ends up in the repository.
 *
 * Opt-in (it writes into screenshots/ next to this file):
 *
 *   DOC_SCREENSHOTS=1 DOC_BASE_URL=http://localhost:8095 DOC_CONTAINER=openmes-clean \
 *     npx playwright test tests/e2e/traceability-flow/doc-screenshots.spec.ts
 *
 * DOC_CONTAINER is the app container of that clean instance (the data is
 * seeded and read through `php` inside it). Run it on a fresh database: the
 * flow creates the order, the units and the pallet from scratch.
 */

const BASE = process.env.DOC_BASE_URL ?? 'http://localhost:8095';
const CONTAINER = process.env.DOC_CONTAINER ?? 'openmes-clean';
const PASSWORD = process.env.DOC_PASSWORD ?? 'demo-pass-2026';
const DIR = path.join(__dirname, 'screenshots');
const ORDER = 'ZL-0001';
const FAIL_SN = 'SN-000099'; // a unit the tester fails three times

test.skip(!process.env.DOC_SCREENSHOTS, 'Set DOC_SCREENSHOTS=1 to regenerate the walkthrough screenshots.');
test.use({ channel: 'chromium', baseURL: BASE, viewport: { width: 1440, height: 1000 }, locale: 'pl-PL', actionTimeout: 20_000 });

/** Run PHP inside the app container with the application booted; returns the last line of output. */
function php(code: string): string {
  const script = `<?php
$base = getenv('OPENMES_APP_PATH') ?: '/work';
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
${code}`;
  const out = execSync(`docker exec -i ${CONTAINER} php`, { input: script, encoding: 'utf8' });
  return out.trim().split('\n').pop() ?? '';
}
const phpJson = (code: string) => JSON.parse(php(code));

async function shot(target: Page | Locator, name: string, fullPage = false) {
  const file = path.join(DIR, `${name}.png`);
  if ('goto' in target) await target.screenshot({ path: file, fullPage });
  else await target.screenshot({ path: file });
}

async function login(browser: Browser, username: string): Promise<Page> {
  const ctx = await browser.newContext({ baseURL: BASE, viewport: { width: 1440, height: 1000 }, locale: 'pl-PL' });
  ctx.setDefaultTimeout(20_000);
  const page = await ctx.newPage();
  await page.goto('/login');
  await page.getByLabel(/Nazwa użytkownika|Username|Login|E-mail/i).first().fill(username);
  await page.locator('input[type="password"]').first().fill(PASSWORD);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.locator('button[type="submit"]').first().click()]);
  return page;
}

/** Pick in one of the app's dropdowns: open it, type into its search box when it has one, choose the option. */
async function pick(combobox: Locator, option: string | RegExp) {
  await combobox.click();
  const page = combobox.page();
  const name = typeof option === 'string' ? new RegExp(option.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')) : option;
  await page.getByRole('option', { name }).first().click();
}

/** The packing station offers a handful of orders as chips and a long list as a dropdown. */
async function pickPalletOrder(page: Page, order: RegExp, name = 'Utwórz paletę dla zlecenia') {
  const chips = page.getByRole('radiogroup', { name });
  const dropdown = page.getByRole('combobox', { name });
  await expect(chips.or(dropdown)).toBeVisible({ timeout: 15_000 });
  if (await chips.count()) await chips.getByRole('radio', { name: order }).click();
  else await pick(dropdown, order);
}

async function closeDialog(page: Page) {
  await page.getByRole('dialog').getByRole('button', { name: 'Zamknij', exact: true }).last().click();
  await page.waitForTimeout(400);
}

const settle = (page: Page, ms = 800) => page.waitForTimeout(ms);

test('traceability walkthrough screenshots on generic data', async ({ browser, request }) => {
  test.setTimeout(15 * 60_000);

  const seedFile = path.join(__dirname, 'doc-seed.php');
  const ids = JSON.parse(execSync(`docker exec -i ${CONTAINER} php`, { input: require('node:fs').readFileSync(seedFile, 'utf8'), encoding: 'utf8' }).trim().split('\n').pop()!);

  // ── Administrator: configuration ─────────────────────────────────────────
  const admin = await login(browser, 'demo-admin');

  await admin.goto('/settings/system?tab=data');
  await settle(admin, 1500);
  const importCard = admin.getByRole('heading', { name: 'Import ustawień' }).locator('..');
  await importCard.scrollIntoViewIfNeeded();
  await shot(importCard, '37-import-konfiguracji');

  await admin.goto('/settings/system?tab=production');
  await admin.getByText(/Numery seryjne|seryjn/i).first().scrollIntoViewIfNeeded();
  await settle(admin);
  await shot(admin, '01-ustawienia-serializacja');

  await admin.goto('/admin/lot-sequences');
  await settle(admin, 1500);
  await shot(admin, '03-sekwencje-lista');
  const psnRow = admin.getByRole('row', { name: /PSN produktu A/ }).first();
  const editAction = psnRow.locator('[data-action="Edit"], button[aria-label*="Edytuj"]').first();
  if (await editAction.count()) await editAction.click(); else await psnRow.dblclick();
  await settle(admin, 1200);
  await shot(admin, '02-sekwencja-psn');

  await admin.goto(`/packaging/label-templates?edit=${ids.cartonTemplate}`);
  await settle(admin, 2500);
  await shot(admin, '04-szablony-etykiet');

  await admin.goto(`/admin/product-types/${ids.product}/process-templates/${ids.template}`);
  await settle(admin, 1500);
  // The packing step from the step rail, then its edit form.
  await admin.getByText('Pakowanie · 3m · pakowanie').click();
  await settle(admin, 600);
  await admin.locator('main button[aria-label="Edit"]').first().click();
  await settle(admin, 800);
  await shot(admin, '05-marszruta-krok-pakowania');

  await admin.goto('/admin/work-orders');
  await settle(admin, 1200);
  await admin.getByRole('button', { name: 'Nowe zlecenie' }).click();
  const woDialog = admin.getByRole('dialog');
  await woDialog.locator('input[name="order_no"]').fill(ORDER);
  await pick(woDialog.getByRole('combobox', { name: 'Linia' }), 'Linia 1');
  await pick(woDialog.getByRole('combobox', { name: 'Typ produktu' }), 'Produkt A');
  await woDialog.locator('input[name="planned_qty"]').fill('4');
  await settle(admin, 300);
  await shot(admin, '06-nowe-zlecenie');
  await woDialog.getByRole('button', { name: 'Utwórz', exact: true }).click();
  await settle(admin, 1500);
  const woId = phpJson(`echo json_encode(App\\Models\\WorkOrder::where('order_no', '${ORDER}')->value('id'));`);
  expect(woId).toBeTruthy();

  // The tester bench does not label units: its tabs are pinned by hand.
  await admin.goto(`/admin/lines/${ids.line}/workstations`);
  await settle(admin, 1200);
  // Workstations are cards; the test bench's card is the one carrying its code.
  await admin.locator('div').filter({ hasText: /^Test.*ST-TEST/s }).filter({ has: admin.getByRole('button', { name: 'Edytuj' }) }).last().getByRole('button', { name: 'Edytuj' }).click();
  const screens = admin.getByRole('dialog').getByTestId('operator-screens');
  await screens.scrollIntoViewIfNeeded();
  await screens.getByRole('checkbox', { name: /Automatycznie/ }).click();
  await screens.getByRole('checkbox', { name: 'Etykiety SN' }).click();
  await settle(admin, 300);
  await shot(screens, '32-ekrany-stanowiska-admin');
  await admin.getByRole('dialog').getByRole('button', { name: /Zapisz|Zaktualizuj/ }).first().click();
  await settle(admin, 1200);

  // ── Operator: line, tabs, the batch ──────────────────────────────────────
  const op = await login(browser, 'demo-operator');
  await op.goto('/operator/select-line');
  await settle(op);
  await shot(op, '08-operator-wybor-linii');
  const lineForm = op.locator('main form').filter({ hasText: 'Linia 1' }).first();
  await pick(lineForm.getByRole('combobox', { name: 'Stanowisko' }), /^Montaż/);
  await lineForm.getByRole('button', { name: 'Wybierz' }).click();
  await op.waitForURL(/\/operator\/queue/);
  await settle(op);
  await shot(op.locator('header'), '31-zakladki-montaz');
  await op.goto(`/operator/queue?line=${ids.line}&workstation=${ids.packing}`);
  await settle(op);
  await shot(op.locator('header'), '30-zakladki-pakowanie');

  await op.goto(`/operator/work-order/${woId}?line=${ids.line}&workstation=all`);
  await settle(op, 1200);
  await op.getByRole('button', { name: /Utwórz partię/ }).first().click();
  await settle(op, 500);
  // The batch takes the next number from the product's LOT sequence.
  await op.getByText(/Automatycznie generuj numer LOT/).click();
  await op.getByRole('button', { name: 'Utwórz partię' }).last().click();
  await settle(op, 2000);
  await shot(op, '07-zlecenie');

  const stepCard = async (n: number) => {
    const idsOnPage = await op.locator('[id^="batch-step-"]').evaluateAll((els) => els.map((e) => e.id));
    return op.locator(`#${idsOnPage[n]}`);
  };
  for (const n of [0, 1]) {
    await (await stepCard(n)).getByRole('button', { name: /^(Start|Rozpocznij)$/ }).click();
    await settle(op, 1500);
    await (await stepCard(n)).getByLabel('Dobre').fill('4');
    if (n === 0) await shot(await stepCard(0), '07b-krok-partii');
    await (await stepCard(n)).getByRole('button', { name: 'Zarejestruj wynik' }).click();
    await settle(op, 1500);
    await (await stepCard(n)).getByRole('button', { name: 'Zakończ' }).click();
    await settle(op, 1500);
  }

  // ── Label station: PSN first, components, SN label, batch, hold ──────────
  await op.goto(`/operator/unit-labels/station?line=${ids.line}&workstation=${ids.assembly}&work_order=${woId}`);
  await settle(op, 1500);
  await shot(op, '09-stacja-etykiet');

  await op.getByTestId('start-unit').click();
  await expect(op.getByRole('dialog')).toBeVisible({ timeout: 15_000 });
  await settle(op, 2500);
  await shot(op, '33-nowa-sztuka-psn');
  await closeDialog(op);
  const first = phpJson(`echo json_encode(App\\Models\\SerialUnit::where('work_order_id', ${woId})->orderBy('id')->first(['id','psn']));`);

  await op.getByRole('radio', { name: 'Komponenty' }).click();
  await op.getByLabel('Numer sztuki (SN lub PSN)').fill(first.psn);
  await op.getByLabel('Numer sztuki (SN lub PSN)').press('Enter');
  await settle(op, 600);
  await op.getByLabel('Komponent', { exact: true }).fill('LOT-A-001');
  await op.getByLabel('Komponent', { exact: true }).press('Enter');
  await settle(op, 1500);
  await shot(op, '13-komponent-powiazany');

  await op.getByLabel('Komponent', { exact: true }).fill('LOT-X-999');
  await op.getByLabel('Komponent', { exact: true }).press('Enter');
  await settle(op, 1500);
  await op.getByRole('button', { name: 'Usuń komponent LOT-X-999' }).click();
  await op.getByRole('dialog').getByLabel('Powód (opcjonalnie)').fill('Błędny skan');
  await shot(op, '14-usuniecie-komponentu');
  await op.getByRole('dialog').getByRole('button', { name: 'Usuń', exact: true }).click();
  await settle(op, 1200);

  await op.getByRole('radio', { name: 'Etykieta seryjna' }).click();
  await op.getByLabel(/Numer seryjny procesu/).fill(first.psn);
  await op.getByLabel(/Numer seryjny procesu/).press('Enter');
  await op.getByRole('button', { name: 'Wydaj numer' }).nth(1).click();
  await settle(op, 1200);
  await op.getByRole('button', { name: 'Zastosuj etykietę' }).click();
  await settle(op, 2000);
  await shot(op, '11-etykieta-naklejona');

  const unit1 = phpJson(`echo json_encode(App\\Models\\SerialUnit::find(${first.id}, ['id','serial_no','psn']));`);
  await op.getByRole('row', { name: new RegExp(unit1.serial_no) }).getByRole('button', { name: 'Etykieta', exact: true }).click();
  await expect(op.getByRole('dialog')).toBeVisible();
  await settle(op, 2500);
  await shot(op, '12-etykieta-sztuki');
  await closeDialog(op);

  await op.getByRole('button', { name: 'Wydaj partię…', exact: true }).click();
  await op.getByRole('dialog').locator('input').first().fill('3');
  await op.getByRole('dialog').getByRole('button', { name: 'Wydaj i drukuj' }).click();
  await settle(op, 3500);
  await shot(op, '15-etykiety-partii');
  await closeDialog(op);

  const units = phpJson(`echo json_encode(App\\Models\\SerialUnit::where('work_order_id', ${woId})->orderBy('id')->get(['id','serial_no','psn']));`);
  const held = units[units.length - 1];
  await op.getByRole('row', { name: new RegExp(held.serial_no) }).getByRole('button', { name: 'Zablokuj' }).click();
  const holdDialog = op.getByRole('dialog');
  await holdDialog.getByRole('radio', { name: /WIZ-01/ }).click();
  await holdDialog.getByLabel('Notatka (opcjonalnie)').fill('Uszkodzenie widoczne na obudowie');
  await settle(op, 300);
  await shot(holdDialog, '34-blokada-sztuki');
  await holdDialog.getByTestId('confirm-block').click();
  await settle(op, 1500);
  await shot(op, '10-wydane-numery');

  // The issued labels go onto the units at the line: PSN and SN scanned.
  await op.getByRole('radio', { name: 'Etykieta seryjna' }).click();
  for (const u of units.slice(1, 3)) {
    await op.getByLabel(/Numer seryjny procesu/).fill(u.psn);
    await op.getByLabel(/Numer seryjny procesu/).press('Enter');
    await op.getByLabel(/Numer seryjny \(etykieta\)/).fill(u.serial_no);
    await op.getByLabel(/Numer seryjny \(etykieta\)/).press('Enter');
    await settle(op, 1200);
  }

  // ── Tester: verdicts through the API ─────────────────────────────────────
  const now = Date.now();
  for (const [i, u] of units.slice(0, 3).entries()) {
    // Just finished, after the units were started and labelled: each 40 s run ends seconds ago.
    const start = new Date(now - (3 - i) * 1000 - 40_000);
    const res = await request.post(`${BASE}/api/v1/test-runs`, {
      headers: { Authorization: `Bearer ${ids.testerToken}`, Accept: 'application/json' },
      data: {
        serial_no: u.serial_no, psn: u.psn, run_id: `RUN-${u.id}`, verdict: 'pass', station: 'ST-TEST',
        started_at: start.toISOString(), ended_at: new Date(start.getTime() + 40_000).toISOString(),
        steps: [{ id: '1', name: 'Pomiar napięcia', verdict: 'pass', measurements: [{ name: 'U', value: 5.02, unit: 'V', low: 4.75, high: 5.25 }] }],
      },
    });
    expect(res.status()).toBe(201);
  }

  // One more unit fails three times, sent as the tester's own JSONL lines (one run per
  // call, limits as lo/hi). The third failed verdict holds it (policy from the seed:
  // block after 3 attempts). The compact layout carries plant time without a zone;
  // the clean instance runs on UTC.
  const plant = (d: Date) => d.toISOString().slice(0, 23);
  const clock = (d: Date) => d.toISOString().slice(11, 23);
  for (let n = 1; n <= 3; n++) {
    const start = new Date(now - (7 - n * 2) * 60_000);
    const at = (s: number) => new Date(start.getTime() + s * 1000);
    const lines = [
      { t: 'cycle', sn: FAIL_SN, id: `RUN-FAIL-${n}`, start: plant(start), station: 'ST-TEST', line: 'L1', sw: 'Tester v1.0' },
      { t: 's', ts: clock(at(5)), n: 1, cmd: 'Napięcie', v: 5.02, u: 'V', r: 'P', lo: 4.75, hi: 5.25 },
      { t: 's', ts: clock(at(12)), n: 2, cmd: 'Prąd spoczynkowy', v: 0.61, u: 'A', r: 'F', lo: 0.2, hi: 0.5 },
      { t: 'v', ts: clock(at(20)), r: 'F', fail: [2] },
    ];
    const res = await request.post(`${BASE}/api/v1/test-runs?workstation=ST-TEST&work_order=${encodeURIComponent(ORDER)}`, {
      headers: { Authorization: `Bearer ${ids.testerToken}`, Accept: 'application/json', 'Content-Type': 'application/x-ndjson' },
      data: lines.map((l) => JSON.stringify(l)).join('\n') + '\n',
    });
    expect(res.status()).toBe(201);
    expect((await res.json()).data.unit.status).toBe(n < 3 ? 'in_production' : 'blocked');
  }

  // ── Packing: pallet, weight, cartons open and close themselves ───────────
  await op.goto(`/operator/packaging?line=${ids.line}&workstation=${ids.packing}`);
  await settle(op, 2000);
  await pickPalletOrder(op, new RegExp(ORDER));
  await settle(op, 300);
  await shot(op, '16-stacja-pakowania-paleta');
  await op.getByRole('button', { name: /Utwórz paletę/ }).click();
  await settle(op, 2000);

  const scan = async (identifier: string) => {
    await op.getByLabel(/PSN lub numer seryjny/).fill(identifier);
    await op.getByLabel(/PSN lub numer seryjny/).press('Enter');
    await settle(op, 1500);
  };
  await scan(units[0].serial_no);
  await expect(op.getByTestId('weight-input')).toBeFocused();
  await op.getByTestId('weight-input').fill('530');
  await op.getByTestId('weight-input').press('Enter');
  await settle(op, 1500);
  await shot(op, '35-kontrola-wagi');
  await op.getByTestId('weight-input').fill('498');
  await op.getByTestId('weight-input').press('Enter');
  await settle(op, 2000);
  await shot(op, '17-nowy-karton');
  await shot(op.locator('form').filter({ has: op.getByLabel(/PSN lub numer seryjny/) }).locator('..'), '18-skan-etykieta-sztuki');

  await scan(units[1].serial_no);
  await op.getByTestId('weight-input').fill('503');
  await op.getByTestId('weight-input').press('Enter');
  // The full carton closes and prints, then the full pallet: the labels queue up.
  for (const [title, name] of [['Etykieta kartonu', '20-etykieta-kartonu-auto'], ['Etykieta palety', '21-etykieta-palety-auto']] as const) {
    await expect(op.getByRole('dialog').filter({ hasText: title })).toBeVisible({ timeout: 15_000 });
    await settle(op, 2500);
    await shot(op, name);
    await closeDialog(op);
  }
  await settle(op, 1500);
  await shot(op, '19-krok-pakowania-liczy');

  // ── Shipping with the quality gate, packing list ─────────────────────────
  const palletRow = phpJson(`echo json_encode(App\\Models\\Pallet::where('work_order_id', ${woId})->first(['id','pallet_no']));`);
  await admin.goto('/admin/pallets');
  await settle(admin, 2000);
  await admin.getByRole('row', { name: new RegExp(palletRow.pallet_no) }).locator('[data-action="Edit"], button[aria-label*="Edytuj"]').first().click();
  const palletDialog = admin.getByRole('dialog');
  await pick(palletDialog.getByRole('combobox', { name: 'Status' }), /Wysłan/);
  await settle(admin, 300);
  await shot(admin, '22-paleta-wysylka-szuflada');
  await palletDialog.getByRole('button', { name: /Zapisz/ }).click();
  await settle(admin, 2000);
  await shot(admin, '23-paleta-wyslana');

  await admin.goto(`/packaging/labels/pallet/${palletRow.id}/packing-list`);
  await settle(admin, 3000);
  await shot(admin, '24-lista-pakowa');

  // ── Trace ────────────────────────────────────────────────────────────────
  await admin.goto(`/admin/traceability?q=${encodeURIComponent(units[0].psn)}`);
  await settle(admin, 2000);
  await shot(admin, '25-slad-sztuki');
  await shot(admin.locator('main'), '28-slad-sztuki-czasy');

  await admin.goto(`/admin/traceability?q=${encodeURIComponent(FAIL_SN)}`);
  await settle(admin, 2000);
  await shot(admin, '36-trzy-nieudane-testy');

  await admin.goto(`/admin/traceability?q=${encodeURIComponent(palletRow.pallet_no)}`);
  await settle(admin, 2000);
  await shot(admin, '26-slad-palety');

  await admin.goto('/admin/traceability?q=LOT-A-001');
  await settle(admin, 2000);
  await shot(admin, '27-slad-partii-komponentu');

  // The batch's steps, on a plausible clock (the run above takes minutes, a line takes hours).
  const batchLot = php(`
$b = App\\Models\\Batch::where('work_order_id', ${woId})->with('steps')->first();
$t = now()->subHours(3)->startOfMinute();
$plan = [1 => [0, 45], 2 => [60, 75], 3 => [95, 130]];
foreach ($b->steps as $s) { [$a, $z] = $plan[$s->step_number] ?? [0, 0]; $s->update(['started_at' => $t->copy()->addMinutes($a), 'completed_at' => $s->completed_at ? $t->copy()->addMinutes($z) : null, 'duration_minutes' => $s->completed_at ? $z - $a : null]); }
echo $b->lot_number;`);
  await admin.goto(`/admin/traceability?q=${encodeURIComponent(batchLot)}`);
  await settle(admin, 2000);
  await shot(admin, '29-slad-partii-czasy');
});

test('second example: sub-assemblies, finished goods by pallet, IQC label, production hold', async ({ browser, request }) => {
  test.setTimeout(10 * 60_000);
  const seedFile = path.join(__dirname, 'doc-seed.php');
  const ids = JSON.parse(execSync(`docker exec -i ${CONTAINER} php`, { input: require('node:fs').readFileSync(seedFile, 'utf8'), encoding: 'utf8' }).trim().split('\n').pop()!);
  const op = await login(browser, 'demo-operator');
  const admin = await login(browser, 'demo-admin');

  // ── Sub-assembly bench: each module registered by its own serial ─────────
  await op.goto(`/operator/unit-labels/station?line=${ids.line}&workstation=${ids.partsBench}&work_order=${ids.partsOrder}`);
  await settle(op, 1500);
  await op.getByRole('radio', { name: 'Podzespoły' }).click();
  for (const sn of ['MOD-B-0001', 'MOD-B-0002']) {
    await op.getByLabel('Numer seryjny podzespołu').fill(sn);
    await op.getByLabel('Numer seryjny podzespołu').press('Enter');
    await settle(op, 1500);
  }
  await expect(op.getByText(/Podzespół MOD-B-0002 zarejestrowany/)).toBeVisible();
  await shot(op, '38-podzespoly-rejestracja');

  // ── The module goes into a product unit: the unit is linked to it ────────
  await op.goto(`/operator/unit-labels/station?line=${ids.line}&workstation=${ids.assembly}&work_order=${ids.heldOrder}`);
  await settle(op, 1500);
  await op.getByTestId('start-unit').click();
  await expect(op.getByRole('dialog')).toBeVisible({ timeout: 15_000 });
  await closeDialog(op);
  const unit = phpJson(`echo json_encode(App\\Models\\SerialUnit::where('work_order_id', ${ids.heldOrder})->orderByDesc('id')->first(['id','psn']));`);
  await op.getByRole('radio', { name: 'Komponenty' }).click();
  await op.getByLabel('Numer sztuki (SN lub PSN)').fill(unit.psn);
  await op.getByLabel('Numer sztuki (SN lub PSN)').press('Enter');
  await settle(op, 600);
  await op.getByLabel('Komponent', { exact: true }).fill('MOD-B-0001');
  await op.getByLabel('Komponent', { exact: true }).press('Enter');
  await settle(op, 1500);
  await shot(op, '39-podzespol-w-sztuce');

  await admin.goto('/admin/traceability?q=MOD-B-0001');
  await settle(admin, 2000);
  await expect(admin.getByText(unit.psn).first()).toBeVisible();
  await shot(admin, '40-slad-podzespolu');

  // ── Production hold: stock does not cover the second order ───────────────
  await op.goto(`/operator/work-order/${ids.heldOrder}?line=${ids.line}&workstation=all`);
  await settle(op, 1500);
  const firstStep = op.locator('[id^="batch-step-"]').first();
  await firstStep.getByRole('button', { name: /^(Start|Rozpocznij)$/ }).click();
  await settle(op, 2000);
  await expect(op.getByText(/Produkcja wstrzymana/).first()).toBeVisible();
  await shot(op, '41-wstrzymanie-brak-materialu');

  // ── IQC label of the component lot ───────────────────────────────────────
  await admin.goto(`/admin/material-lots/${ids.lotA}`);
  await settle(admin, 1500);
  await shot(admin, '42-partia-etykieta-iqc');
  const pdf = await admin.request.get(`${BASE}/packaging/labels/material-lot/${ids.lotA}/pdf`);
  expect(pdf.status()).toBe(200);
  expect(pdf.headers()['content-type']).toContain('application/pdf');

  // ── Finished goods by pallet: the first example's pallet in and out ──────
  const docs = phpJson(`echo json_encode(App\\Models\\StockDocument::whereNotNull('pallet_id')->with('lines')->orderBy('id')->get()->map(fn ($d) => ['no' => $d->document_no, 'type' => $d->type, 'status' => $d->status, 'qty' => (float) $d->lines->sum('quantity')]));`);
  expect(docs.map((d: { type: string; status: string }) => `${d.type}:${d.status}`)).toEqual(['product_receipt:posted', 'product_issue:posted']);
});
