import { test, expect, Page, Browser, Locator } from '@playwright/test';
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

// The serialised-unit flow, end to end, through the real UI - with a screenshot
// of every step. The pictures feed ./index.html, the step-by-step description of
// the process this build supports:
//
//   admin      settings (serial formats), number sequences, label templates,
//              the routing with a *packing* step (units per carton, cartons per
//              pallet), the work order
//   operator   label station: issue PSN + SN, apply the label; bind a component
//              lot to the unit
//   tester     a pass verdict per unit (here recorded the way the JSONL import
//              records one - the tester itself is outside the browser)
//   operator   packing station: pallet, carton, scan units; the carton closes
//              itself at 2 units and prints, the full pallet closes itself and
//              prints
//   admin      ships the pallet (the quality gate reads the units' verdicts),
//              then reads the unit's and the pallet's trace
//
// Foundation records (line, product, routing, sequences, work order, material
// lot, operator account) are seeded through tinker like the other long specs
// do; every action the process is about goes through the forms and stations.
//
// Run:  npx playwright test tests/e2e/traceability-flow
//       (stack up on E2E_BASE_URL, default http://localhost:8080; the backend
//        container name comes from OPENMES_NAME_PREFIX, default openmmes, or
//        E2E_BACKEND_CONTAINER; E2E_NO_REALTIME=1 when the instance has no
//        websocket server, so live lists are re-read instead)

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
// E2E_BACKEND_CONTAINER names the container outright (a throwaway instance).
const BACKEND = process.env.E2E_BACKEND_CONTAINER || `${process.env.OPENMES_NAME_PREFIX || 'openmmes'}-backend`;
const TS = Date.now().toString().slice(-6);
// The spec's own screenshots go to test-results (gitignored); the curated
// walkthrough pictures in ./screenshots are taken by hand and kept.
const SHOTS = path.join(__dirname, '..', '..', '..', 'test-results', 'traceability-flow');

const LINE = `Trace Line ${TS}`;
const PRODUCT = `Trace Product ${TS}`;
const WO = `WO-TRC-${TS}`;
const LOT = `CMP-${TS}`;
const OP_USER = `optrc${TS}`;
const OP_PASS = 'Operator123!';
// Sequences restart per product type, and process serials are unique across the
// instance: start this run's counters where no earlier run's numbers are.
const SEQ_START = Number(TS.slice(-3)) * 10 + 1;

test.describe.configure({ mode: 'serial' });
test.setTimeout(300_000);
// The label previews are PDFs in an iframe. Playwright's default headless
// shell has no PDF viewer and leaves them blank; the full Chromium build
// (new headless mode) renders them, so the screenshots show the labels.
test.use({ channel: 'chromium' });

function tinker(php: string): string {
  const escaped = php.replace(/"/g, '\\"').replace(/\$/g, '\\$');
  return execSync(`docker exec ${BACKEND} php artisan tinker --execute="${escaped}"`, { encoding: 'utf8' })
    .trim()
    .split('\n')
    .pop()!
    .trim();
}

async function login(page: Page, user: string, pass: string) {
  // The spec reads the UI in English, whatever the instance's default language.
  await page.goto('/locale/en');
  await page.goto('/login');
  await page.fill('input[name="username"]', user);
  await page.fill('input[name="password"]', pass);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type="submit"]')]);
  await page.setViewportSize({ width: 1500, height: 1000 });
  page.setDefaultTimeout(20_000); // a missing element fails the step, not the whole 5-minute budget
}

let shot = 0;
async function snap(page: Page, name: string, opts: { fullPage?: boolean } = {}) {
  shot += 1;
  fs.mkdirSync(SHOTS, { recursive: true });
  await page.waitForTimeout(400); // let the last render settle
  await page.screenshot({ path: path.join(SHOTS, `${String(shot).padStart(2, '0')}-${name}.png`), fullPage: opts.fullPage ?? false });
}

// @openmes/ui Dropdown: a combobox button that opens a listbox of options.
async function pick(button: Locator, optionName: string | RegExp) {
  await button.click();
  // An option's accessible name carries its check mark ("Shipped ✓"), so a
  // string matches the start of the name rather than the whole of it.
  const name = typeof optionName === 'string' ? new RegExp(`^${optionName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`) : optionName;
  await button.page().getByRole('option', { name }).click();
}

/** The packing station offers a handful of orders as chips and a long list as a dropdown. */
async function pickPalletOrder(page: Page, order: RegExp, name = 'Create pallet for order') {
  const chips = page.getByRole('radiogroup', { name });
  const dropdown = page.getByRole('combobox', { name });
  await expect(chips.or(dropdown)).toBeVisible({ timeout: 15_000 });
  if (await chips.count()) await chips.getByRole('radio', { name: order }).click();
  else await pick(dropdown, order);
}

const closeDialog = async (page: Page, { expectHidden = true } = {}) => {
  await page.getByRole('dialog').getByRole('button', { name: 'Close', exact: true }).last().click();
  if (expectHidden) await expect(page.getByRole('dialog')).toBeHidden({ timeout: 10_000 });
};

let ids: { line: number; product: number; template: number; wo: number; cartonTemplate: number };

test.beforeAll(() => {
  const php = [
    `$admin = App\\Models\\User::where('username', '${ADMIN}')->first() ?? App\\Models\\User::first();`,
    `$line = App\\Models\\Line::create(['code'=>'TRC-${TS}','name'=>'${LINE}','is_active'=>true]);`,
    `App\\Models\\Workstation::create(['line_id'=>$line->id,'code'=>'TRC-PACK-${TS}','name'=>'Packing bench ${TS}','is_active'=>true]);`,
    `$pt = App\\Models\\ProductType::create(['code'=>'TRP-${TS}','name'=>'${PRODUCT}','unit_of_measure'=>'pcs','is_active'=>true]);`,
    // The routing: one production step, then packing - 2 units per carton, 1 carton per pallet.
    `$tpl = App\\Models\\ProcessTemplate::create(['product_type_id'=>$pt->id,'name'=>'Trace routing ${TS}','version'=>1,'is_active'=>true]);`,
    `App\\Models\\TemplateStep::create(['process_template_id'=>$tpl->id,'step_number'=>1,'name'=>'Assemble','estimated_duration_minutes'=>5]);`,
    `App\\Models\\TemplateStep::create(['process_template_id'=>$tpl->id,'step_number'=>2,'name'=>'Pack','kind'=>'packing','config'=>['unit'=>'carton','carton_capacity'=>2,'pallet_capacity'=>1],'estimated_duration_minutes'=>2]);`,
    // Number sequences: a process serial and a unit serial, both from date tokens and a counter.
    `App\\Models\\LotSequence::create(['name'=>'PSN ${TS}','product_type_id'=>$pt->id,'purpose'=>'process_serial','prefix'=>'','pattern'=>'PSN-[year2][doy]-[seq]','pad_size'=>4,'next_number'=>${SEQ_START},'reset_period'=>'none']);`,
    `App\\Models\\LotSequence::create(['name'=>'SN ${TS}','product_type_id'=>$pt->id,'purpose'=>'unit_serial','prefix'=>'','pattern'=>'SN[year1][week][weekday]-[seq]','pad_size'=>4,'next_number'=>${SEQ_START},'reset_period'=>'none']);`,
    // The work order freezes the routing; assembly is done so the packing step is reachable.
    `$wo = App\\Models\\WorkOrder::create(['order_no'=>'${WO}','product_type_id'=>$pt->id,'line_id'=>$line->id,'planned_qty'=>4,'status'=>'IN_PROGRESS','process_snapshot'=>$tpl->fresh()->toSnapshot(),'due_date'=>now()->addDays(3)]);`,
    `$batch = app(App\\Services\\WorkOrder\\WorkOrderService::class)->createBatch($wo, 4);`,
    `$svc = app(App\\Services\\WorkOrder\\BatchService::class); $s1 = $batch->steps()->where('step_number',1)->first();`,
    `$svc->startStep($s1, $admin); $svc->recordQuantity($s1->fresh(), $admin, 4, 0); $svc->completeStep($s1->fresh(), $admin);`,
    // A component lot the operator binds into the unit.
    `$mt = App\\Models\\MaterialType::firstOrCreate(['code'=>'E2E-COMP'], ['name'=>'E2E components']);`,
    `$mat = App\\Models\\Material::create(['code'=>'CMP-${TS}','name'=>'Component','material_type_id'=>$mt->id,'unit_of_measure'=>'pcs','tracking_type'=>'lot']);`,
    `App\\Models\\MaterialLot::create(['lot_number'=>'${LOT}','material_id'=>$mat->id,'quantity_received'=>100,'quantity_available'=>100,'unit_of_measure'=>'pcs','received_at'=>now(),'status'=>'received']);`,
    // The operator account, assigned to the line.
    `$op = App\\Models\\User::create(['name'=>'Operator ${TS}','username'=>'${OP_USER}','email'=>'${OP_USER}@example.test','password'=>bcrypt('${OP_PASS}')]);`,
    `$op->assignRole('Operator'); $op->lines()->attach($line->id);`,
    `echo json_encode(['line'=>$line->id,'product'=>$pt->id,'template'=>$tpl->id,'wo'=>$wo->id,'cartonTemplate'=>(int) App\\Models\\LabelTemplate::where('type','carton')->value('id')]);`,
  ].join(' ');
  ids = JSON.parse(tinker(php));
});

test('serialised unit: from configuration to the shipped pallet, one screenshot per step', async ({ page, browser }: { page: Page; browser: Browser }) => {
  await login(page, ADMIN, PASS);

  // ── Admin: configuration ────────────────────────────────────────────────
  await test.step('01 serial formats in system settings', async () => {
    await page.goto('/settings/system?tab=production');
    const heading = page.getByRole('heading', { name: 'Unit serialisation' });
    await expect(heading).toBeVisible({ timeout: 15_000 });
    await heading.scrollIntoViewIfNeeded();
    await snap(page, 'settings-unit-serialisation');
  });

  await test.step('02 number sequences for PSN and SN', async () => {
    await page.goto('/admin/lot-sequences');
    await expect(page.getByText(`PSN ${TS}`)).toBeVisible({ timeout: 15_000 });
    await snap(page, 'lot-sequences');
  });

  await test.step('03 label templates, carton template in the drawer', async () => {
    await page.goto(ids.cartonTemplate ? `/packaging/label-templates?edit=${ids.cartonTemplate}` : '/packaging/label-templates');
    if (ids.cartonTemplate) await expect(page.getByRole('dialog')).toBeVisible({ timeout: 15_000 });
    await snap(page, 'label-templates');
    if (ids.cartonTemplate) await page.getByRole('dialog').getByRole('button', { name: 'Cancel' }).click();
  });

  await test.step('04 routing with a packing step', async () => {
    await page.goto(`/admin/product-types/${ids.product}/process-templates/${ids.template}`);
    await page.getByRole('button', { name: /^⠿ 2 Pack/ }).click();
    await page.getByLabel('Edit', { exact: true }).click(); // the step card's edit icon
    await expect(page.getByRole('combobox', { name: 'Step kind' })).toBeVisible({ timeout: 15_000 });
    await snap(page, 'routing-packing-step');
    await page.getByRole('dialog').getByRole('button', { name: 'Cancel' }).click();
  });

  await test.step('05 the work order', async () => {
    await page.goto(`/admin/work-orders/${ids.wo}`);
    await expect(page.getByRole('heading', { name: new RegExp(WO) }).first()).toBeVisible({ timeout: 15_000 });
    await snap(page, 'work-order');
  });

  // ── Operator: label station ─────────────────────────────────────────────
  const opCtx = await browser.newContext({ ignoreHTTPSErrors: true });
  const op = await opCtx.newPage();
  await login(op, OP_USER, OP_PASS);

  await test.step('06 operator picks the line', async () => {
    await op.goto('/operator/select-line');
    await snap(op, 'operator-select-line');
    // An operator of one line is taken straight to its queue; the picker shows with several.
    if (op.url().includes('select-line')) {
      await op.locator('form', { hasText: LINE }).getByRole('button', { name: /Select/ }).first().click();
      await op.waitForURL((u) => !u.pathname.includes('select-line'));
    }
  });

  const units: { psn: string; sn: string }[] = [];
  await test.step('07 label station: issue PSN and SN, apply the label', async () => {
    await op.goto('/operator/unit-labels/station');
    await pick(op.getByRole('combobox', { name: 'Work order' }), new RegExp(WO));
    await snap(op, 'label-station');

    for (let i = 0; i < 2; i += 1) {
      await op.getByRole('button', { name: 'Issue number' }).nth(0).click();
      await expect(op.getByLabel('Process serial (PSN)')).not.toHaveValue('', { timeout: 15_000 });
      await op.getByRole('button', { name: 'Issue number' }).nth(1).click();
      await expect(op.getByLabel('Serial number (label)')).not.toHaveValue('', { timeout: 15_000 });
      const psn = await op.getByLabel('Process serial (PSN)').inputValue();
      const sn = await op.getByLabel('Serial number (label)').inputValue();
      if (i === 0) await snap(op, 'label-station-numbers-issued');
      await op.getByRole('button', { name: 'Apply label' }).click();
      // The unit lands in "Recent units"; its row prints the label again on demand.
      const row = op.getByRole('row').filter({ hasText: sn });
      if (i === 0) await snap(op, 'label-applied');
      await expect(row).toBeVisible({ timeout: 15_000 });
      if (i === 0) {
        await row.getByRole('button', { name: 'Label', exact: true }).click();
        await expect(op.getByRole('dialog')).toBeVisible({ timeout: 15_000 });
        await snap(op, 'unit-label-printed');
        await closeDialog(op);
      }
      units.push({ psn, sn });
    }
    expect(units[0].psn).toMatch(/^PSN-\d{5}-\d{4}$/);
    expect(units[0].sn).toMatch(/^SN\d{4}-\d{4}$/);
  });

  await test.step('08 bind a component lot to the unit', async () => {
    await op.getByRole('radio', { name: 'Components' }).click();
    await op.getByLabel('Unit (SN or PSN)').fill(units[0].sn);
    await op.getByLabel('Unit (SN or PSN)').press('Enter');
    await op.getByLabel('Component', { exact: true }).fill(LOT);
    await op.getByRole('button', { name: 'Bind component' }).click();
    await expect(op.getByText(LOT).first()).toBeVisible({ timeout: 15_000 });
    await snap(op, 'component-bound');
  });

  await test.step('08b a wrong scan comes out again, with a reason', async () => {
    // Bind a second identifier, then remove it: the unit keeps the battery only.
    await op.getByLabel('Component', { exact: true }).fill(`WRONG-${TS}`);
    await op.getByRole('button', { name: 'Bind component' }).click();
    await expect(op.getByText(`WRONG-${TS}`).first()).toBeVisible({ timeout: 15_000 });
    await op.getByRole('button', { name: `Remove component WRONG-${TS}` }).click();
    await op.getByRole('dialog').getByLabel('Reason (optional)').fill('Scanned the wrong part');
    await snap(op, 'component-remove-reason');
    await op.getByRole('dialog').getByRole('button', { name: 'Remove', exact: true }).click();
    // The badge (and its remove button) is gone; the success message still names the identifier.
    await expect(op.getByRole('button', { name: `Remove component WRONG-${TS}` })).toHaveCount(0, { timeout: 15_000 });
  });

  await test.step('08c numbers issued in a batch ahead of the line, labels in one file', async () => {
    await op.getByRole('radio', { name: 'Serial label' }).click();
    await op.getByRole('button', { name: 'Issue batch…' }).click();
    await op.getByRole('dialog').getByLabel('How many units?').fill('2');
    await op.getByRole('dialog').getByRole('button', { name: 'Issue and print' }).click();
    await expect(op.getByRole('dialog', { name: /Unit labels \(2\)/ })).toBeVisible({ timeout: 15_000 });
    await snap(op, 'batch-labels');
    await closeDialog(op);
  });

  await test.step('09 tester verdicts (as the JSONL import records them)', async () => {
    tinker(
      `$u = App\\Models\\User::where('username','${OP_USER}')->first(); $svc = app(App\\Services\\Traceability\\SerialTraceService::class);` +
        `foreach (App\\Models\\SerialUnit::where('work_order_id', ${ids.wo})->get() as $unit) { $svc->recordStep($unit, $u, null, ['result'=>'pass','parameters'=>['event'=>'test','station'=>'EOL tester','cycle'=>1]]); } echo 'ok';`,
    );
  });

  // ── Operator: packing station ───────────────────────────────────────────
  let palletNo = '';
  await test.step('10 packing station: open a pallet for the order', async () => {
    await op.goto('/operator/packaging');
    await expect(op.getByText('Packing steps')).toBeVisible({ timeout: 15_000 });
    await pickPalletOrder(op, new RegExp(WO));
    await op.getByRole('button', { name: /Create pallet/ }).click();
    await expect(op.getByRole('button', { name: /Close pallet/ })).toBeVisible({ timeout: 15_000 });
    palletNo = (await op.locator('text=/PAL-\\d{6}/').first().innerText()).match(/PAL-\d{6}/)![0]; // the Pack-into menu names it too ("My pallet PAL-…")
    await snap(op, 'packing-station-pallet', { fullPage: true });
  });

  await test.step('11 open a carton on the pallet', async () => {
    await op.getByRole('button', { name: 'New carton' }).click();
    await expect(op.getByText(/CTN-\d{6}/).first()).toBeVisible({ timeout: 15_000 });
    await snap(op, 'packing-station-carton');
  });

  await test.step('12 scan the first unit into the carton', async () => {
    await op.getByLabel('PSN or serial number').fill(units[0].psn);
    await op.getByLabel('PSN or serial number').press('Enter');
    await expect(op.getByRole('dialog', { name: 'Unit label' })).toBeVisible({ timeout: 15_000 });
    await snap(op, 'packing-unit-label');
    await closeDialog(op);
    await expect(op.getByText('1 / 4')).toBeVisible({ timeout: 15_000 }); // the packing step counts it
    await snap(op, 'packing-step-counting');
  });

  await test.step('13 second unit fills the carton: it closes and prints', async () => {
    await op.getByLabel('PSN or serial number').fill(units[1].psn);
    await op.getByLabel('PSN or serial number').press('Enter');
    // The unit's own label first; the carton's queues behind it.
    await expect(op.getByRole('dialog', { name: 'Unit label' })).toBeVisible({ timeout: 15_000 });
    await closeDialog(op, { expectHidden: false });
    await expect(op.getByRole('dialog', { name: 'Carton label' })).toBeVisible({ timeout: 15_000 });
    await snap(op, 'carton-label-auto-closed');
    await closeDialog(op, { expectHidden: false }); // the pallet label is queued right behind it
  });

  await test.step('14 the full pallet closes itself and prints', async () => {
    await expect(op.getByRole('dialog', { name: 'Pallet label' })).toBeVisible({ timeout: 15_000 });
    await snap(op, 'pallet-label-auto-closed');
    await closeDialog(op);
    await expect(op.getByRole('button', { name: /Close pallet/ })).toBeHidden({ timeout: 15_000 });
  });
  await opCtx.close();

  // ── Admin: shipping and the trace ───────────────────────────────────────
  await test.step('15 ship the pallet (quality gate reads the unit verdicts)', async () => {
    await page.goto('/admin/pallets');
    const row = page.locator('tr', { hasText: palletNo });
    await expect(row).toBeVisible({ timeout: 15_000 });
    await expect(row).toContainText('Passed');
    await row.locator('[data-action="Edit"]').click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible({ timeout: 15_000 });
    await pick(dialog.getByRole('combobox', { name: 'Status' }), 'Shipped');
    await snap(page, 'pallet-ship-drawer');
    await dialog.getByRole('button', { name: /Save/ }).click();
    await expect(dialog).toBeHidden({ timeout: 15_000 });
    // The list updates live over the websocket; a throwaway instance without one
    // (E2E_NO_REALTIME) reads it again instead.
    if (process.env.E2E_NO_REALTIME) await page.reload();
    await expect(page.locator('tr', { hasText: palletNo })).toContainText('Shipped', { timeout: 15_000 });
    await snap(page, 'pallet-shipped');
  });

  await test.step('15b the packing list that travels with the pallet', async () => {
    const palletId = tinker(`echo App\\Models\\Pallet::where('pallet_no','${palletNo}')->value('id');`);
    await page.goto(`/packaging/labels/pallet/${palletId}/packing-list`);
    await page.waitForTimeout(1500); // the PDF viewer draws after load
    await snap(page, 'packing-list', { fullPage: true });
  });

  await test.step('16 trace the unit by its process serial', async () => {
    await page.goto('/admin/traceability');
    await page.getByLabel('Identifier to trace').fill(units[0].psn);
    await page.getByRole('button', { name: 'Trace', exact: true }).click();
    await expect(page.getByText(units[0].sn).first()).toBeVisible({ timeout: 15_000 });
    await expect(page.getByText(LOT).first()).toBeVisible({ timeout: 15_000 });
    await snap(page, 'trace-unit', { fullPage: true });
  });

  await test.step('17 trace the pallet', async () => {
    await page.goto('/admin/traceability');
    await page.getByLabel('Identifier to trace').fill(palletNo);
    await page.getByRole('button', { name: 'Trace', exact: true }).click();
    await expect(page.getByRole('heading', { name: palletNo })).toBeVisible({ timeout: 15_000 });
    await snap(page, 'trace-pallet', { fullPage: true });
  });
});
