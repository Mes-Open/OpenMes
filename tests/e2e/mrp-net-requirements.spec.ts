import { test, expect, Page } from '@playwright/test';

// Manual click-through for #90 — MRP net requirements & shortage report.
// Drives the real admin UI on a running stack with a seeded scenario:
//   product BOM: Driver ×2 (stock 50), PCB ×1 (stock 500); planned WO qty 100
//   → Driver short by 150, PCB covered.
const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
const TS = process.env.MRP_TS || '';

async function login(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type="submit"]')]);
  await page.setViewportSize({ width: 1500, height: 1100 });
}

test('MRP net requirements report shows the shortage', async ({ page }) => {
  await login(page);

  await page.goto('/admin/net-requirements');
  await expect(page.getByRole('heading', { name: 'Net requirements', exact: true })).toBeVisible({ timeout: 15_000 });

  // KPI: at least one component short.
  await expect(page.getByText('Components short')).toBeVisible();

  // The driver shortage row: component short by 150, driven by the planned WO.
  const driverRow = page.locator('tr', { hasText: `DRV-${TS}` }).first();
  await expect(driverRow).toBeVisible({ timeout: 15_000 });
  await expect(driverRow).toContainText('150'); // shortfall
  await expect(driverRow).toContainText(`WO-MRP-${TS}`); // driving work order

  await page.screenshot({ path: 'test-results/mrp-net-requirements.png', fullPage: true });
});
