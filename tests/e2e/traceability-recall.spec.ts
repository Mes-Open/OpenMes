import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

// Focused manual test for the newest change set (#135 traceability suite):
// the Admin → Traceability console resolving a CUSTOMER ORDER number and a
// PALLET number into their genealogy, plus the customer_order_no / pallet→batch
// links. Seeds a minimal WO → batch → pallet(linked) via tinker, then drives
// the console through the UI.
const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
const BACKEND = `${process.env.OPENMES_NAME_PREFIX || 'openmmes'}-backend`;
const TS = Date.now().toString().slice(-6);

const CUST = `CUST-PO-${TS}`;
const WO = `WO-TRACE-${TS}`;

function tinker(php: string): string {
  const escaped = php.replace(/"/g, '\\"').replace(/\$/g, '\\$');
  return execSync(`docker exec ${BACKEND} php artisan tinker --execute="${escaped}"`, {
    encoding: 'utf8',
  }).trim().split('\n').pop()!.trim();
}

async function login(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type="submit"]')]);
  await page.setViewportSize({ width: 1500, height: 1100 });
}

async function search(page: Page, term: string) {
  await page.goto('/admin/traceability');
  await page.locator('form input[placeholder*="Pallet no"]').fill(term);
  await page.locator('form').getByRole('button', { name: 'Trace', exact: true }).click();
  await page.waitForLoadState('networkidle');
}

test.describe.configure({ mode: 'serial' });

let palletNo = '';

test.beforeAll(() => {
  // Minimal genealogy: line, product, WO with a customer order number, one
  // batch, one pallet linked to that batch.
  const php = [
    `$line = App\\Models\\Line::create(['code'=>'TRL-${TS}','name'=>'Trace Line ${TS}','is_active'=>true]);`,
    `$pt = App\\Models\\ProductType::create(['code'=>'TRP-${TS}','name'=>'Trace Product ${TS}','unit_of_measure'=>'pcs','is_active'=>true]);`,
    `$wo = App\\Models\\WorkOrder::create(['order_no'=>'${WO}','customer_order_no'=>'${CUST}','product_type_id'=>$pt->id,'line_id'=>$line->id,'planned_qty'=>10,'status'=>'IN_PROGRESS','due_date'=>now()->addDays(3)]);`,
    `$b = App\\Models\\Batch::create(['work_order_id'=>$wo->id,'batch_number'=>1,'target_qty'=>10,'status'=>'IN_PROGRESS','started_at'=>now()]);`,
    `$p = App\\Models\\Pallet::create(['work_order_id'=>$wo->id,'batch_id'=>$b->id,'status'=>'closed','qty'=>10]);`,
    `echo $p->pallet_no;`,
  ].join(' ');
  palletNo = tinker(php);
});

test('traceability console resolves a customer order number', async ({ page }) => {
  await login(page);
  await search(page, CUST);

  // CustomerOrderResult shows the customer order number heading + the matching WO.
  await expect(page.getByRole('heading', { name: CUST })).toBeVisible({ timeout: 15_000 });
  await expect(page.getByText(WO).first()).toBeVisible({ timeout: 15_000 });
});

test('traceability console resolves a pallet number into its chain', async ({ page }) => {
  await login(page);
  expect(palletNo).toMatch(/^PAL-\d{6}$/);
  await search(page, palletNo);

  // PalletResult shows the pallet number + its work order + the linked customer order.
  await expect(page.getByRole('heading', { name: palletNo })).toBeVisible({ timeout: 15_000 });
  await expect(page.getByText(WO).first()).toBeVisible({ timeout: 15_000 });
  await expect(page.getByText(CUST).first()).toBeVisible({ timeout: 15_000 });

  await page.screenshot({ path: 'test-results/traceability-pallet.png', fullPage: true });
});
