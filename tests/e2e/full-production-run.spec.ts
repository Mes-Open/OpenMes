import { test, expect, Page, Browser } from '@playwright/test';
import { execSync } from 'child_process';

// Full "produce a product end-to-end through the real UI" run, exercising every
// role the shop floor actually involves:
//
//   admin     builds line + product + active process template (with a step) +
//             work order + in-production QC trigger + EAN, then creates an
//             Operator account and assigns it to the line
//   operator  selects the line, opens the work order, creates a batch, starts a
//             step (→ work order enters production, the QC fires), then packs a
//             piece onto a pallet at the station and closes it
//   admin     performs the fired quality control linked to that pallet (→ the
//             pallet's quality flips to Passed) and finally ships it (the #106
//             ship-gate now opens)
//
// Every phase is a test.step so a failure pinpoints the exact production stage
// that blocks. Real finding baked in: the operator console only lists lines the
// user is ASSIGNED to, and only Operator-role users can be assigned — so the
// admin account alone cannot drive it; a dedicated operator is required.
const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
const BACKEND = `${process.env.OPENMES_NAME_PREFIX || 'openmmes'}-backend`;
const TS = Date.now().toString().slice(-6);

const LINE = `Run Line ${TS}`;
const PRODUCT = `Run Product ${TS}`;
const WO = `WO-RUN-${TS}`;
const TRIGGER = `Run in-prod QC ${TS}`;
const EAN = `59${TS}0000`.slice(0, 13).padEnd(13, '0');
const OP_USER = `op${TS}`;
const OP_PASS = 'Operator123!';

test.describe.configure({ mode: 'serial' });
test.setTimeout(240_000);

// Resolve an id the UI doesn't expose in a URL (navigation only — every write
// still goes through the forms). Looks it up in the running om106 database.
function dbValue(sql: string): string {
  const php = `echo DB::selectOne("${sql}")->v;`.replace(/"/g, '\\"');
  return execSync(`docker exec ${BACKEND} php artisan tinker --execute="${php}"`, { encoding: 'utf8' })
    .trim()
    .split('\n')
    .pop()!
    .trim();
}

function dbExec(php: string): void {
  execSync(`docker exec ${BACKEND} php artisan tinker --execute="${php.replace(/"/g, '\\"')}"`, {
    encoding: 'utf8',
  });
}

async function login(page: Page, user: string, pass: string) {
  await page.goto('/login');
  await page.fill('input[name="username"]', user);
  await page.fill('input[name="password"]', pass);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type="submit"]')]);
  await page.setViewportSize({ width: 1500, height: 1100 });
}

const createBtn = (page: Page) => page.getByRole('button', { name: 'Create', exact: true });

// Forms render selects as @openmes/ui Dropdowns (button[aria-haspopup=listbox]).
async function pickFormSelect(page: Page, index: number, optionName: string | RegExp) {
  await page.locator('form button[aria-haspopup="listbox"]').nth(index).click();
  await page.getByRole('option', { name: optionName, exact: typeof optionName === 'string' }).click();
}

async function pickDropdown(button, optionName: string | RegExp) {
  await button.click();
  await button.page().getByRole('option', { name: optionName, exact: typeof optionName === 'string' }).click();
}

test('produce a product end-to-end through the UI', async ({ page, browser }: { page: Page; browser: Browser }) => {
  // Deterministic slate: silence any leftover triggers/tasks from earlier runs
  // so this run's single in-production control is the only one outstanding.
  dbExec("App\\Models\\QualityControlTrigger::query()->update(['is_active'=>false]); App\\Models\\QualityControlTask::where('status','due')->update(['status'=>'skipped']);");

  await login(page, ADMIN, PASS);

  // ── 1. Production line ──────────────────────────────────────────────────
  await test.step('create production line', async () => {
    await page.goto('/admin/lines/create');
    await page.fill('input[name="code"]', `RL-${TS}`);
    await page.fill('input[name="name"]', LINE);
    await createBtn(page).click();
    await page.waitForURL(/\/admin\/lines$/); // redirect == created (Electric list lags)
  });
  const lineId = dbValue(`select id as v from lines where code='RL-${TS}'`);

  // ── 2. Product type ─────────────────────────────────────────────────────
  await test.step('create product type', async () => {
    await page.goto('/admin/product-types/create');
    await page.fill('input[name="code"]', `RP-${TS}`);
    await page.fill('input[name="name"]', PRODUCT);
    await page.fill('input[name="unit_of_measure"]', 'pcs');
    await createBtn(page).click();
    await page.waitForURL(/\/admin\/product-types$/); // redirect == created
  });
  const productTypeId = dbValue(`select id as v from product_types where code='RP-${TS}'`);

  // ── 3. Active process template + one step ───────────────────────────────
  await test.step('create active process template with a step', async () => {
    await page.goto(`/admin/product-types/${productTypeId}/process-templates/create`);
    await page.fill('input#name', `Standard Assembly ${TS}`); // is_active defaults on
    await page.getByRole('button', { name: 'Create Template', exact: true }).click();
    await page.waitForURL(new RegExp(`/admin/product-types/${productTypeId}/process-templates/\\d+`));
    await page.getByRole('button', { name: 'Add Step', exact: true }).first().click();
    await page.locator('input[placeholder*="Attach component"]').fill('Assemble headphones');
    await page.locator('form').getByRole('button', { name: 'Add Step', exact: true }).click();
    await expect(page.getByText('Assemble headphones')).toBeVisible({ timeout: 15_000 });
  });

  // ── 4. Work order (captures the active template's snapshot → batch steps) ─
  await test.step('create work order', async () => {
    await page.goto('/admin/work-orders/create');
    await page.fill('input[name="order_no"]', WO);
    await pickFormSelect(page, 0, LINE);
    await pickFormSelect(page, 1, PRODUCT);
    await page.fill('input[name="planned_qty"]', '10');
    await createBtn(page).click();
    // The redirect to the index only happens on success (a 422 keeps us on
    // /create); the list itself is Electric-synced and can lag, so the redirect
    // is the reliable success signal — the operator queue confirms the WO later.
    await page.waitForURL(/\/admin\/work-orders$/);
  });

  // ── 5. In-production QC trigger (unscoped → fires for any batch) ─────────
  await test.step('create in-production QC trigger', async () => {
    await page.goto('/admin/quality-control-triggers/create');
    await page.fill('input[name="name"]', TRIGGER);
    await createBtn(page).click();
    await page.waitForURL(/\/admin\/quality-control-triggers$/); // redirect == created
  });

  // ── 6. Register an EAN for the work order (needed for station scanning) ──
  await test.step('register work-order EAN', async () => {
    await page.goto('/packaging/eans');
    await pickDropdown(page.locator('form button[aria-haspopup="listbox"]').first(), new RegExp(WO));
    await page.locator('input[placeholder*="5901234123457"]').fill(EAN);
    await page.getByRole('button', { name: /Dodaj EAN/ }).click();
    await expect(page.getByText(EAN)).toBeVisible({ timeout: 15_000 });
  });

  // ── 7. Create an Operator account (the operator console needs a real one) ─
  await test.step('create operator account', async () => {
    await page.goto('/admin/users/create');
    await page.locator('input[type="text"]').nth(0).fill(`Operator ${TS}`); // Name
    await page.locator('input[type="text"]').nth(1).fill(OP_USER);          // Username
    await page.locator('input[type="email"]').fill(`${OP_USER}@example.test`);
    await page.locator('input[type="password"]').nth(0).fill(OP_PASS);
    await page.locator('input[type="password"]').nth(1).fill(OP_PASS);
    await pickFormSelect(page, 0, 'Operator'); // Role
    await page.locator('form').getByRole('button', { name: /Create|Save/ }).first().click();
    await page.waitForURL(/\/admin\/users(\?.*)?$/);
  });

  // ── 8. Assign the operator to the line (line Show → Operators card) ──────
  await test.step('assign operator to the line', async () => {
    await page.goto(`/admin/lines/${lineId}`);
    // Scope to the Operators card's form (the one holding the "Assign" button).
    const opForm = page.locator('form').filter({ has: page.getByRole('button', { name: 'Assign', exact: true }) });
    await opForm.scrollIntoViewIfNeeded();
    await opForm.locator('button[aria-haspopup="listbox"]').click();
    await page.getByRole('option', { name: new RegExp(OP_USER) }).click();
    await opForm.getByRole('button', { name: 'Assign', exact: true }).click();
    await expect(page.getByText(`Operator ${TS}`)).toBeVisible({ timeout: 15_000 });
  });

  // ─────────────────────────────────────────────────────────────────────────
  // Operator session — a separate browser context logged in as the operator.
  // ─────────────────────────────────────────────────────────────────────────
  const opCtx = await browser.newContext({ ignoreHTTPSErrors: true });
  const op = await opCtx.newPage();
  await login(op, OP_USER, OP_PASS);

  // ── 9. Operator: open WO → create batch → start the step (→ WO in prod) ─
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

  // ── 10. Packaging station: open a pallet, scan a piece, close it ────────
  let palletNo = '';
  await test.step('operator packages a piece onto a pallet', async () => {
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

  // ── 11. Admin performs the fired QC linked to the pallet (→ Passed) ─────
  await test.step('perform quality control linked to the pallet', async () => {
    await page.goto('/admin/quality-tasks');
    const row = page.locator('tr', { hasText: WO }).first();
    await expect(row).toBeVisible({ timeout: 45_000 }); // due-tasks Electric shape can lag under load
    await page.waitForTimeout(1500); // let the Electric-synced table stop re-rendering
    const dialog = page.getByRole('dialog');
    // The synced table re-renders as rows stream in, which can detach the button
    // mid-click — retry until the modal actually opens.
    await expect(async () => {
      await row.locator('[data-action="Perform"]').first().click();
      await expect(dialog).toBeVisible({ timeout: 3_000 });
    }).toPass({ timeout: 30_000 });
    // Link the pallet (its dropdown is the last listbox in the modal).
    await pickDropdown(dialog.locator('button[aria-haspopup="listbox"]').last(), palletNo);
    await page.getByRole('button', { name: 'Record result', exact: true }).click();
    await expect(dialog).toBeHidden({ timeout: 15_000 });
  });

  // ── 12. Pallet is now Passed and can be shipped (#106 ship-gate opens) ──
  await test.step('ship the passed pallet', async () => {
    await page.goto('/admin/pallets');
    const palletRow = page.locator('tr', { hasText: palletNo });
    await expect(palletRow).toBeVisible({ timeout: 15_000 });
    await expect(palletRow).toContainText('Passed');

    await palletRow.locator('[data-action="Edit"]').click();
    await page.waitForURL(/\/admin\/pallets\/\d+\/edit/);
    await pickFormSelect(page, 1, 'Shipped');
    await page.locator('form').getByRole('button', { name: /Save|Create/ }).first().click();
    await page.waitForURL(/\/admin\/pallets$/);
    await expect(page.getByText(/Cannot ship pallet/i)).toHaveCount(0);
    await expect(page.locator('tr', { hasText: palletNo })).toContainText('Shipped');
  });

  await page.screenshot({ path: 'test-results/full-production-run.png', fullPage: true });
  await opCtx.close();
});
