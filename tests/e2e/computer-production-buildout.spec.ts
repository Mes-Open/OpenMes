import { test, expect, Page, Browser } from '@playwright/test';
import { execSync } from 'child_process';

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
  return execSync(`docker exec openmes-backend php artisan tinker --execute="${escaped}"`, {
    encoding: 'utf8',
  }).trim().split('\n').pop()!.trim();
}

async function login(page: Page, user: string, pass: string) {
  await page.goto('/login');
  await page.fill('input[name="username"]', user);
  await page.fill('input[name="password"]', pass);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type="submit"]')]);
  await page.setViewportSize({ width: 1500, height: 1100 });
}

const createBtn = (page: Page) => page.getByRole('button', { name: 'Create', exact: true });

async function pickFormSelect(page: Page, index: number, optionName: string | RegExp) {
  await page.locator('form button[aria-haspopup="listbox"]').nth(index).click();
  await page.getByRole('option', { name: optionName, exact: typeof optionName === 'string' }).click();
}
async function pickDropdown(button, optionName: string | RegExp) {
  await button.click();
  await button.page().getByRole('option', { name: optionName, exact: typeof optionName === 'string' }).click();
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
    await page.goto(`/admin/product-types/${productTypeId}/process-templates/create`);
    await page.fill('input#name', `PC Assembly Process ${TS}`);
    await page.getByRole('button', { name: 'Create Template', exact: true }).click();
    await page.waitForURL(new RegExp(`/admin/product-types/${productTypeId}/process-templates/\\d+`));
    await page.getByRole('button', { name: 'Add Step', exact: true }).first().click();
    await page.locator('input[placeholder*="Attach component"]').fill('Assemble & test PC');
    await page.locator('form').getByRole('button', { name: 'Add Step', exact: true }).click();
    await expect(page.getByText('Assemble & test PC')).toBeVisible({ timeout: 15_000 });
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
    await pickFormSelect(page, 0, LINE);
    await pickFormSelect(page, 1, PRODUCT);
    await page.fill('input[name="planned_qty"]', '100');
    await createBtn(page).click();
    await page.waitForURL(/\/admin\/work-orders$/);
  });
  // Make the WO due within the MRP window (next 30 days).
  tinker(`App\\\\Models\\\\WorkOrder::where('order_no','WO-PC-${TS}')->update(['due_date'=>now()->addDays(5)]);`);

  // ── 5. EAN + QC trigger ─────────────────────────────────────────────────
  await test.step('register EAN', async () => {
    await page.goto('/packaging/eans');
    await pickDropdown(page.locator('form button[aria-haspopup="listbox"]').first(), new RegExp(WO));
    await page.locator('input[placeholder*="5901234123457"]').fill(EAN);
    await page.getByRole('button', { name: /Dodaj EAN/ }).click();
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
    await expect(page.getByRole('heading', { name: 'Net requirements', exact: true })).toBeVisible({ timeout: 15_000 });
    // RAM: 2/unit × 100 = 200 required, stock 4 → short. MB: 1×100=100, stock 3 → short.
    const ramRow = page.locator('tr', { hasText: `RAM-${TS}` }).first();
    await expect(ramRow).toBeVisible({ timeout: 15_000 });
    await expect(ramRow).toContainText(WO); // driving work order
  });

  // ── 7. Operator account + line assignment ───────────────────────────────
  await test.step('create operator + assign to line', async () => {
    await page.goto('/admin/users/create');
    await page.locator('input[type="text"]').nth(0).fill(`PC Operator ${TS}`);
    await page.locator('input[type="text"]').nth(1).fill(OP_USER);
    await page.locator('input[type="email"]').fill(`${OP_USER}@example.test`);
    await page.locator('input[type="password"]').nth(0).fill(OP_PASS);
    await page.locator('input[type="password"]').nth(1).fill(OP_PASS);
    await pickFormSelect(page, 0, 'Operator');
    await page.locator('form').getByRole('button', { name: /Create|Save/ }).first().click();
    await page.waitForURL(/\/admin\/users(\?.*)?$/);

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
    const select = op.locator(`select[aria-label="Set state for PC Bench ${TS}"]`);
    await expect(select).toBeVisible({ timeout: 15_000 });
    await select.selectOption('MAINTENANCE');
    await op.waitForTimeout(800);
    await select.selectOption('RUNNING');
    await op.waitForTimeout(800);
  });

  await test.step('operator starts a batch step', async () => {
    await op.goto('/operator/select-line');
    await op.locator('form', { hasText: LINE }).getByRole('button', { name: /Select/ }).first().click();
    await op.waitForURL(/\/operator\/queue/);
    await op.getByText(WO, { exact: false }).first().click();
    await op.waitForURL(/\/operator\/work-order\/\d+/);
    await op.getByRole('button', { name: '+ Create Batch', exact: true }).click();
    await op.getByRole('button', { name: 'Create Batch', exact: true }).click();
    await op.getByRole('button', { name: 'Start', exact: true }).first().click();
    await expect(op.getByRole('button', { name: 'Complete', exact: true }).first()).toBeVisible({ timeout: 15_000 });
  });

  // ── 9. Packaging: open pallet, scan a PC, close ─────────────────────────
  let palletNo = '';
  await test.step('package a PC onto a pallet', async () => {
    await op.goto('/packaging/station');
    await pickDropdown(op.locator('button[aria-haspopup="listbox"]').first(), new RegExp(WO));
    await op.getByRole('button', { name: /Create pallet/ }).click();
    await expect(op.getByText('Active pallet')).toBeVisible({ timeout: 15_000 });
    palletNo = (await op.locator('text=/PAL-\\d{6}/').first().innerText()).trim();
    await op.getByRole('heading', { name: /Packing Station/ }).click();
    await op.keyboard.type(EAN);
    await op.keyboard.press('Enter');
    await expect(op.getByText('Scanned!')).toBeVisible({ timeout: 15_000 });
    await op.getByRole('button', { name: /Close pallet/ }).click();
    await expect(op.getByText('Active pallet')).toBeHidden({ timeout: 15_000 });
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
    await page.waitForURL(/\/admin\/pallets\/\d+\/edit/);
    // Status is the last dropdown on the pallet form (work order → [batch] → status).
    await page.locator('form button[aria-haspopup="listbox"]').last().click();
    await page.getByRole('option', { name: 'Shipped', exact: true }).click();
    await page.locator('form').getByRole('button', { name: /Save|Create/ }).first().click();
    await page.waitForURL(/\/admin\/pallets$/);
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
