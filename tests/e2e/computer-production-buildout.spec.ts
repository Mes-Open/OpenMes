import { test, expect, Page, Browser } from '@playwright/test';
import { execSync } from 'child_process';
import { choose as pickFormSelect, chooseIn as pickDropdown, expectPage, login as signIn } from './support/app';

// ─────────────────────────────────────────────────────────────────────────────
// HUGE manual buildout: stand up a brand-new "Desktop PC" production
// configuration from zero and run it end-to-end, exercising the freshly merged
// features — MRP/net-requirements (#90), machine states waiting/cleaning/
// maintenance (#87), pallet quality + ship-gate (#106) and the non-conformance
// disposition workflow (#11). Each phase is a test.step so a failure pinpoints
// the exact stage.
//
// UI drives every operator/admin-facing flow. A few structural fixtures the UI
// doesn't expose cheaply (a workstation on the line, the BOM lines + component
// stock, a seeded non-conformance) are created via tinker and clearly labelled
// SETUP — everything user-facing is clicked.
// ─────────────────────────────────────────────────────────────────────────────

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
const BACKEND = `${process.env.OPENMES_NAME_PREFIX || 'openmmes'}-backend`;
const TS = Date.now().toString().slice(-6);

const LINE = `PC Assembly ${TS}`;
const PRODUCT = `Desktop PC ${TS}`;
const WO = `WO-PC-${TS}`;
const TRIGGER = `PC in-production QC ${TS}`;
const EAN = `59${TS}0000`.slice(0, 13).padEnd(13, '0');
const OP_USER = `pcop${TS}`;
const OP_PASS = 'Operator123!';

test.describe.configure({ mode: 'serial' });
test.setTimeout(300_000);

function tinker(php: string): string {
  // Escape for the shell double-quoted --execute="..." wrapper: protect PHP
  // strings (") AND PHP variables ($) from being expanded by /bin/sh.
  const escaped = php.replace(/"/g, '\\"').replace(/\$/g, '\\$');
  return execSync(`docker exec ${BACKEND} php artisan tinker --execute="${escaped}"`, {
    encoding: 'utf8',
  }).trim().split('\n').pop()!.trim();
}

async function login(page: Page, user: string, pass: string) {
  await signIn(page, user, pass);
  await page.setViewportSize({ width: 1500, height: 1100 });
}

const createBtn = (page: Page) => page.getByRole('button', { name: 'Create', exact: true });


/** The packing station offers a handful of orders as chips and a long list as a dropdown. */
async function pickPalletOrder(page: Page, order: RegExp, name = 'Create pallet for order') {
  const chips = page.getByRole('radiogroup', { name });
  const dropdown = page.getByRole('combobox', { name });
  await expect(chips.or(dropdown)).toBeVisible({ timeout: 15_000 });
  if (await chips.count()) await chips.getByRole('radio', { name: order }).click();
  else await pickDropdown(dropdown, order);
}

test('build a Desktop PC production configuration from zero and run it', async ({ page, browser }: { page: Page; browser: Browser }) => {
  // Deterministic slate: silence leftover triggers/tasks from earlier runs.
  tinker("App\\\\Models\\\\QualityControlTrigger::query()->update(['is_active'=>false]); App\\\\Models\\\\QualityControlTask::where('status','due')->update(['status'=>'skipped']);");

  await login(page, ADMIN, PASS);

  // ── 1. Production line ──────────────────────────────────────────────────
  await test.step('create production line', async () => {
    await page.goto('/admin/lines/create');
    await page.fill('input[name="code"]', `PCL-${TS}`);
    await page.fill('input[name="name"]', LINE);
    await createBtn(page).click();
    await page.waitForURL(/\/admin\/lines$/);
  });
  const lineId = tinker(`echo App\\\\Models\\\\Line::where('code','PCL-${TS}')->value('id');`);

  // ── 2. Product type (the PC) ────────────────────────────────────────────
  await test.step('create product type', async () => {
    await page.goto('/admin/product-types/create');
    await page.fill('input[name="code"]', `PC-${TS}`);
    await page.fill('input[name="name"]', PRODUCT);
    await page.fill('input[name="unit_of_measure"]', 'pcs');
    await createBtn(page).click();
    await page.waitForURL(/\/admin\/product-types$/);
  });
  const productTypeId = tinker(`echo App\\\\Models\\\\ProductType::where('code','PC-${TS}')->value('id');`);

  // ── 3. Process template + assembly step ─────────────────────────────────
  await test.step('create active process template with a step', async () => {
    await page.goto(`/admin/product-types/${productTypeId}/process-templates`);
    await page.getByRole('button', { name: 'Create Template' }).first().click();
    const create = page.getByRole('dialog', { name: 'Create Process Template' });
    await create.getByLabel('Template Name').fill(`PC Assembly Process ${TS}`); // Active is on by default
    await create.getByRole('button', { name: 'Create Template' }).click();
    await page.waitForURL(new RegExp(`/admin/product-types/${productTypeId}/process-templates/\\d+`));
    await page.getByRole('button', { name: 'Add First Step' }).click();
    const step = page.getByRole('dialog', { name: 'Add New Step' });
    await step.getByLabel('Step Name').fill('Assemble & test PC');
    await step.getByRole('button', { name: 'Add Step', exact: true }).click();
    await expect(step).toBeHidden();
    await expect(page.getByRole('heading', { name: 'Assemble & test PC' })).toBeVisible({ timeout: 15_000 });
  });
  const templateId = tinker(`echo App\\\\Models\\\\ProcessTemplate::where('product_type_id',${productTypeId})->where('is_active',true)->orderByDesc('version')->value('id');`);

  // ── SETUP: a workstation on the line, the BOM (6 PC components) with low
  //    stock, so MRP shows shortages and #87 has a workstation to drive. ────
  await test.step('SETUP: workstation + BOM components with low stock', async () => {
    const php = [
      `$ws = App\\Models\\Workstation::create(['line_id'=>${lineId},'code'=>'WS-${TS}','name'=>'PC Bench ${TS}','is_active'=>true]);`,
      // 6 components: CPU/RAM/SSD/Mainboard/PSU/Case, low stock to force shortages.
      `$defs = [['CPU',1,5],['RAM',2,4],['SSD',1,50],['MB',1,3],['PSU',1,40],['CASE',1,60]];`,
      `foreach ($defs as [$c,$q,$stock]) { $m = App\\Models\\Material::create(['code'=>$c.'-${TS}','name'=>$c.' ${TS}','unit_of_measure'=>'pcs','stock_quantity'=>$stock]); App\\Models\\BomItem::create(['process_template_id'=>${templateId},'material_id'=>$m->id,'quantity_per_unit'=>$q,'scrap_percentage'=>0,'consumed_at'=>'start','sort_order'=>0]); }`,
      `echo $ws->id;`,
    ].join(' ');
    const wsId = tinker(php);
    expect(Number(wsId)).toBeGreaterThan(0);
  });

  // ── 4. Work order (planned PC build of 100) ─────────────────────────────
  await test.step('create planned work order', async () => {
    await page.goto('/admin/work-orders/create');
    await page.fill('input[name="order_no"]', WO);
    await pickFormSelect(page, 'Line', LINE);
    await pickFormSelect(page, 'Product Type', PRODUCT);
    await page.fill('input[name="planned_qty"]', '100');
    await createBtn(page).click();
    await page.waitForURL(/\/admin\/work-orders$/);
  });
  // Make the WO due within the MRP window (next 30 days).
  tinker(`App\\\\Models\\\\WorkOrder::where('order_no','WO-PC-${TS}')->update(['due_date'=>now()->addDays(5)]);`);

  // ── 5. EAN + QC trigger ─────────────────────────────────────────────────
  await test.step('register EAN', async () => {
    await page.goto('/packaging/eans');
    await pickFormSelect(page, 'Production work order', new RegExp(WO));
    await page.getByRole('textbox', { name: 'EAN code' }).fill(EAN);
    await page.getByRole('button', { name: 'Add EAN' }).click();
    await expect(page.getByText(EAN)).toBeVisible({ timeout: 15_000 });
  });
  await test.step('create in-production QC trigger', async () => {
    await page.goto('/admin/quality-control-triggers/create');
    await page.fill('input[name="name"]', TRIGGER);
    await createBtn(page).click();
    await page.waitForURL(/\/admin\/quality-control-triggers$/);
  });

  // ── 6. MRP: the net-requirements report shows the PC component shortages ─
  await test.step('MRP net requirements shows component shortages', async () => {
    await page.goto('/admin/net-requirements');
    await expectPage(page, 'Net requirements');
    // RAM: 2/unit × 100 = 200 required, stock 4 → short. MB: 1×100=100, stock 3 → short.
    const ramRow = page.locator('tr', { hasText: `RAM-${TS}` }).first();
    await expect(ramRow).toBeVisible({ timeout: 15_000 });
    await expect(ramRow).toContainText(WO); // driving work order
  });

  // ── 7. Operator account + line assignment ───────────────────────────────
  await test.step('create operator + assign to line', async () => {
    await page.goto('/admin/users');
    await page.getByRole('button', { name: 'New Account' }).click();
    const account = page.getByRole('dialog', { name: 'New Account' });
    await account.getByLabel('Name', { exact: true }).fill(`PC Operator ${TS}`);
    await account.getByLabel('Username', { exact: true }).fill(OP_USER);
    await account.getByLabel('Email', { exact: true }).fill(`${OP_USER}@example.test`);
    await account.getByLabel('Password', { exact: true }).fill(OP_PASS);
    await account.getByLabel('Confirm Password', { exact: true }).fill(OP_PASS);
    await pickFormSelect(account, 'Role', 'Operator');
    await account.getByRole('button', { name: /^(Create|Save)/ }).click();
    await expect(account).toBeHidden();

    await page.goto(`/admin/lines/${lineId}`);
    const opForm = page.locator('form').filter({ has: page.getByRole('button', { name: 'Assign', exact: true }) });
    await opForm.locator('button[aria-haspopup="listbox"]').click();
    await page.getByRole('option', { name: new RegExp(OP_USER) }).click();
    await opForm.getByRole('button', { name: 'Assign', exact: true }).click();
    await expect(page.getByText(`PC Operator ${TS}`)).toBeVisible({ timeout: 15_000 });
  });

  // ── 8. Operator session: machine states (#87) + run the batch ───────────
  const opCtx = await browser.newContext({ ignoreHTTPSErrors: true });
  const op = await opCtx.newPage();
  await login(op, OP_USER, OP_PASS);

  await test.step('operator sets machine state to maintenance then running (#87)', async () => {
    await op.goto(`/operator/workstation?line=${lineId}`);
    // The machine-state panel lists the line's workstation with a <select>.
    // The state panel offers one Select per workstation; a stop asks for an
    // optional note before it is recorded.
    const state = op.getByRole('combobox', { name: new RegExp(`state: PC Bench ${TS}$`) });
    await expect(state).toBeVisible({ timeout: 15_000 });
    await pickDropdown(state, 'Maintenance');
    const note = op.getByRole('dialog', { name: new RegExp(`PC Bench ${TS}`) });
    await note.getByRole('textbox').fill('Planned cleaning before the run');
    await note.getByRole('button', { name: 'Confirm' }).click();
    await expect(note).toBeHidden();
    await expect(state).toContainText('Maintenance', { timeout: 15_000 });
    await pickDropdown(state, 'Running');
    await expect(state).toContainText('Running', { timeout: 15_000 });
  });

  await test.step('operator starts a batch step', async () => {
    // A single assigned line skips the picker and opens its queue directly.
    await op.goto('/operator/select-line');
    if (!/\/operator\/queue/.test(op.url())) {
      await op.locator('form', { hasText: LINE }).getByRole('button', { name: /Select/ }).first().click();
    }
    await op.waitForURL(/\/operator\/queue/);
    await op.getByText(WO, { exact: false }).first().click();
    await op.waitForURL(/\/operator\/work-order\/\d+/);
    // Opens the "Create New Batch" modal; its own Create Batch button is the second one.
    await op.getByRole('button', { name: 'Create Batch', exact: true }).click();
    await expect(op.getByText('Create New Batch')).toBeVisible();
    await op.getByRole('button', { name: 'Create Batch', exact: true }).last().click();
    await expect(op.getByText('No batches created yet.')).toBeHidden({ timeout: 15_000 });
    await op.getByRole('button', { name: 'Start', exact: true }).first().click();
    await expect(op.getByRole('button', { name: 'Complete', exact: true }).first()).toBeVisible({ timeout: 15_000 });
  });

  // ── 9. Packaging: open pallet, scan a PC, close ─────────────────────────
  let palletNo = '';
  await test.step('package a PC onto a pallet', async () => {
    await op.goto('/operator/packaging');
    await pickPalletOrder(op, new RegExp(WO));
    await op.getByRole('button', { name: /Create pallet/ }).click();
    await expect(op.getByRole('button', { name: /Close pallet/ })).toBeVisible({ timeout: 15_000 });
    palletNo = (await op.locator('text=/PAL-\\d{6}/').first().innerText()).match(/PAL-\d{6}/)![0]; // the Pack-into menu names it too ("My pallet PAL-…")
    await op.getByRole('heading', { name: /Packing Station/ }).click();
    await op.keyboard.type(EAN);
    await op.keyboard.press('Enter');
    await expect(op.getByText('Scanned!')).toBeVisible({ timeout: 15_000 });
    await op.getByRole('button', { name: /Close pallet/ }).click();
    await expect(op.getByRole('button', { name: /Close pallet/ })).toBeHidden({ timeout: 15_000 });
  });

  // ── 10. Quality: perform the fired QC linked to the pallet (#106) ────────
  await test.step('perform quality control linked to the pallet', async () => {
    await page.goto('/admin/quality-tasks');
    const row = page.locator('tr', { hasText: WO }).first();
    await expect(row).toBeVisible({ timeout: 45_000 });
    await page.waitForTimeout(1500);
    const dialog = page.getByRole('dialog');
    await expect(async () => {
      await row.locator('[data-action="Perform"]').first().click();
      await expect(dialog).toBeVisible({ timeout: 3_000 });
    }).toPass({ timeout: 30_000 });
    await pickDropdown(dialog.locator('button[aria-haspopup="listbox"]').last(), palletNo);
    await page.getByRole('button', { name: 'Record result', exact: true }).click();
    await expect(dialog).toBeHidden({ timeout: 15_000 });
  });

  // ── 11. Pallet is Passed → ship it (#106 gate) ──────────────────────────
  await test.step('ship the passed pallet', async () => {
    await page.goto('/admin/pallets');
    const palletRow = page.locator('tr', { hasText: palletNo });
    await expect(palletRow).toBeVisible({ timeout: 15_000 });
    await expect(palletRow).toContainText('Passed');
    await palletRow.locator('[data-action="Edit"]').click();
    const edit = page.getByRole('dialog', { name: 'Edit Pallet' });
    // Status is the last dropdown on the pallet form (work order → [batch] → status).
    await pickFormSelect(edit, 'Status', 'Shipped');
    await edit.getByRole('button', { name: 'Save Changes' }).click();
    await expect(edit).toBeHidden();
    await expect(page.getByText(/Cannot ship pallet/i)).toHaveCount(0);
    await expect(page.locator('tr', { hasText: palletNo })).toContainText('Shipped');
  });

  // ── 12. Non-conformance disposition (#11): set a disposition on an issue ─
  await test.step('set disposition on a non-conformance (#11)', async () => {
    // Seed a non-conformance for this work order (SETUP), then disposition it via UI.
    tinker(`$wo=App\\\\Models\\\\WorkOrder::where('order_no','WO-PC-${TS}')->first(); $it=App\\\\Models\\\\IssueType::first() ?? App\\\\Models\\\\IssueType::create(['name'=>'Defect ${TS}','is_blocking'=>false]); App\\\\Models\\\\Issue::create(['work_order_id'=>$wo->id,'issue_type_id'=>$it->id,'title'=>'PC NCR ${TS}','status'=>'OPEN','disposition'=>'pending','reported_at'=>now()]);`);
    await page.goto('/admin/issues');
    const row = page.locator('tr', { hasText: `PC NCR ${TS}` }).first();
    await expect(row).toBeVisible({ timeout: 30_000 });
    await row.locator('[data-action="Disposition"]').first().click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible({ timeout: 10_000 });
    await pickDropdown(dialog.locator('button[aria-haspopup="listbox"]').first(), 'Scrap');
    await page.getByRole('button', { name: 'Record disposition', exact: true }).click();
    await expect(dialog).toBeHidden({ timeout: 15_000 });
    await expect(page.locator('tr', { hasText: `PC NCR ${TS}` }).first()).toContainText('Scrap');
  });

  // ── 13. Verify #87: the maintenance state opened a planned downtime ──────
  await test.step('verify machine-state downtime was recorded (#87)', async () => {
    const planned = tinker(`echo App\\\\Models\\\\ProductionDowntime::where('line_id',${lineId})->whereHas('reason',fn($q)=>$q->where('kind','planned'))->count();`);
    expect(Number(planned)).toBeGreaterThan(0);
    const manual = tinker(`echo App\\\\Models\\\\WorkstationState::where('source','manual')->whereHas('workstation',fn($q)=>$q->where('line_id',${lineId}))->count();`);
    expect(Number(manual)).toBeGreaterThan(0);
  });

  await page.screenshot({ path: 'test-results/computer-production-buildout.png', fullPage: true });
  await opCtx.close();
});
