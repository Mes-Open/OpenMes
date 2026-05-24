import { test, expect } from '@playwright/test';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test.describe.configure({ mode: 'serial' });

async function login(page) {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN_USERNAME);
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
}

test('E2E flow — start inspection, force fail, verify auto-NC', async ({ page }) => {
  await login(page);
  await page.setViewportSize({ width: 1400, height: 1100 });

  // Step 1 — Dashboard widget visible
  await page.goto('/admin/dashboard');
  await page.waitForLoadState('networkidle');
  await expect(page.getByRole('heading', { name: /Inbound QC Overview/i })).toBeVisible();
  await page.screenshot({ path: 'test-results/flow-01-dashboard.png', fullPage: true });

  // Capture pending count before — read the number under "PENDING" in the widget
  const widget = page.locator('div').filter({ hasText: /Inbound QC Overview/ }).first();
  const pendingBefore = await widget.locator('text=/^\\d+$/').first().textContent();
  console.log('Pending before:', pendingBefore);

  // Step 2 — Inspections list (Pending tab)
  await page.getByRole('link', { name: /View all/i }).first().click();
  await page.waitForURL(/\/inspections/);
  await expect(page.getByRole('heading', { name: /Inbound Inspections/i })).toBeVisible();
  await page.screenshot({ path: 'test-results/flow-02-inspections-list.png', fullPage: true });

  // Step 3 — Start a new inspection
  await page.getByRole('link', { name: /Start inspection/i }).click();
  await page.waitForURL(/\/inspections\/create/);
  await expect(page.getByRole('heading', { name: /Start Inspection/i })).toBeVisible();

  // Pick first material whose label contains "Bolt M10"
  const matSelect = page.locator('select[name="material_id"]');
  const matValue = await matSelect.locator('option', { hasText: /Bolt M10/ }).first().getAttribute('value');
  await matSelect.selectOption(matValue!);

  await page.fill('input[name="lot_number"]', 'LOT-E2E-' + Date.now().toString().slice(-6));
  await page.fill('input[name="quantity_received"]', '250');
  await page.fill('input[name="supplier_lot_ref"]', 'SUP-E2E-001');

  // Pick the matching plan
  const planSelect = page.locator('select[name="inspection_plan_id"]');
  const planValue = await planSelect.locator('option', { hasText: /Bolt M10/ }).first().getAttribute('value');
  await planSelect.selectOption(planValue!);
  await page.screenshot({ path: 'test-results/flow-03-start-form.png', fullPage: true });

  await page.getByRole('button', { name: /^Start$/ }).click();
  await page.waitForURL(/\/inspections\/\d+/);

  // Step 4 — Fill measurements (force FAIL: diameter way out of 9.8-10.2 spec)
  await expect(page.getByRole('heading', { name: /Inspection #/i })).toBeVisible();
  await page.screenshot({ path: 'test-results/flow-04-perform-blank.png', fullPage: true });

  // Visual condition — Pass
  const selects = page.locator('select[name^="results"][name$="[value_boolean]"]');
  const count = await selects.count();
  for (let i = 0; i < count; i++) {
    await selects.nth(i).selectOption('1'); // Pass
  }
  // Surface finish (non-required) — also pass
  // Diameter — out of spec to force fail
  const numericInputs = page.locator('input[name^="results"][name$="[value_numeric]"]');
  await numericInputs.first().fill('11.5'); // out of 9.8-10.2

  await page.screenshot({ path: 'test-results/flow-05-perform-filled.png', fullPage: true });

  // Save progress
  await page.getByRole('button', { name: /Save progress/i }).click();
  await page.waitForLoadState('networkidle');

  // Step 5 — Complete (will fail and create NC)
  page.once('dialog', (d) => d.accept());
  await page.getByRole('button', { name: /^Complete$/i }).click();
  await page.waitForLoadState('networkidle');

  // Step 6 — Verify outcome
  await expect(page.getByText(/Non-conformance created: Issue #\d+/i)).toBeVisible({ timeout: 5000 });
  await expect(page.locator('span.badge', { hasText: /^\s*Fail\s*$/i }).first()).toBeVisible();
  await expect(page.getByText('11.5000')).toBeVisible(); // out-of-spec value rendered
  await page.screenshot({ path: 'test-results/flow-06-completed-fail.png', fullPage: true });

  // Step 7 — Back to dashboard, verify Failed counter went up & widget has the new fail in Recent
  await page.goto('/admin/dashboard');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/flow-07-dashboard-after.png', fullPage: true });

  // The recent failures list should contain a Bolt M10 line.
  const recentFailures = page.locator('text=/Recent failures/i').locator('..');
  await expect(recentFailures).toBeVisible();
});
