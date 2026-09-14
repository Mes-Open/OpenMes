import { test, expect, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * The admin work-order list is a synced collection: a create or delete must show
 * up without a browser refresh — in the tab that made it (whose drawer submit
 * redirects back to the same list, remounting it) and in any other open tab.
 *
 * Regression: the remount built a new collection while the old one was still
 * shutting down, and the old one's echo.leave() unsubscribed the channel both
 * shared, so the list went deaf until a manual refresh.
 */

const backend = `${process.env.OPENMES_NAME_PREFIX || 'openmmes'}-backend`;
if (process.env.OPENMES_BROWSER_STORAGE_STATE) {
  test.use({ storageState: process.env.OPENMES_BROWSER_STORAGE_STATE });
}

// Digits sort before letters, so the order lands at the top of the list (order_no asc).
const orderNo = `000-E2E-LIVE-${Date.now()}`;

async function login(page: Page) {
  await page.goto('/admin/work-orders');
  if (page.url().includes('/login')) {
    await page.locator('input[name=username]').fill(process.env.ADMIN_USERNAME || 'admin');
    await page.locator('input[name=password]').fill(process.env.ADMIN_PASSWORD || 'Admin1234!');
    await page.locator('button[type=submit]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));
  }
  await page.goto('/locale/en');
}

/** Open the list and mark the document, so a full reload would be detectable. */
async function openList(page: Page) {
  await page.goto('/admin/work-orders');
  await expect(page.getByRole('button', { name: 'New Work Order', exact: true })).toBeVisible();
  await expect(page.getByRole('row').nth(2)).toBeVisible();
  // Let the snapshot land and the channel subscription settle.
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => { (window as any).__noReload = true; });
}

const rowFor = (page: Page) => page.getByRole('row').filter({ hasText: orderNo });

const dbOrderId = () => execFileSync('docker', [
  'exec', backend, 'php', 'artisan', 'tinker', '--execute',
  `echo App\\Models\\WorkOrder::where('order_no', '${orderNo}')->value('id');`,
], { encoding: 'utf8' }).trim();

/** A server-side change the lists can only learn about through a live delta. */
const setPlannedQty = (qty: number) => execFileSync('docker', [
  'exec', backend, 'php', 'artisan', 'tinker', '--execute',
  `App\\Models\\WorkOrder::where('order_no', '${orderNo}')->firstOrFail()->update(['planned_qty' => ${qty}]);`,
]);

test.afterAll(() => {
  // The UI delete is a soft delete; remove the test row for good so repeated runs
  // leave nothing in the dev database or the Trash page.
  execFileSync('docker', [
    'exec', backend, 'php', 'artisan', 'tinker', '--execute',
    `App\\Models\\WorkOrder::withTrashed()->where('order_no', '${orderNo}')->get()->each->forceDelete();`,
  ]);
});

test('a created and deleted work order appears and disappears live in every open list', async ({ page, context }) => {
  await login(page);
  const observer = await context.newPage();

  await openList(page);
  await openList(observer);
  await expect(rowFor(page)).toHaveCount(0);

  // Create through the list's drawer — its submit redirects back to this list.
  await page.getByRole('button', { name: 'New Work Order', exact: true }).click();
  await page.locator('input[name=order_no]').fill(orderNo);
  await page.locator('input[name=planned_qty]').fill('3');
  await page.getByRole('button', { name: 'Create', exact: true }).click();

  await expect(rowFor(page)).toHaveCount(1, { timeout: 10_000 });
  await expect(rowFor(observer)).toHaveCount(1, { timeout: 10_000 });
  expect(dbOrderId(), 'the row is committed, not just broadcast').toMatch(/^\d+$/);

  // Every step below remounts a list (a redirect back to it, or a nav click to the
  // page you're on). The old collection shuts down ~1s later and used to
  // unsubscribe the channel the new one shares, so wait past that and then change
  // the order on the server: only a live delta can bring the new value in, since
  // no list reloads its snapshot on its own.
  const settle = () => page.waitForTimeout(2500);

  // The creating tab remounted on the create's redirect.
  await settle();
  setPlannedQty(7);
  await expect(rowFor(page)).toContainText('/ 7', { timeout: 10_000 });
  await expect(rowFor(observer)).toContainText('/ 7', { timeout: 10_000 });

  // The observer re-opens the page it is on from the sidebar.
  await observer.locator('a[href="/admin/work-orders"]').first().click();
  await expect(rowFor(observer)).toContainText('/ 7');
  await settle();
  setPlannedQty(9);
  await expect(rowFor(observer)).toContainText('/ 9', { timeout: 10_000 });
  await expect(rowFor(page)).toContainText('/ 9', { timeout: 10_000 });

  // Delete from the creating tab (router.delete redirects back — another remount).
  const row = rowFor(page);
  await row.getByRole('button', { name: 'More actions' }).click();
  await page.getByRole('menuitem', { name: /Delete/ }).click();
  await page.getByRole('button', { name: 'Delete order', exact: true }).click();

  await expect(rowFor(page)).toHaveCount(0, { timeout: 10_000 });
  // The observer only hears about it live.
  await expect(rowFor(observer)).toHaveCount(0, { timeout: 10_000 });
  expect(dbOrderId(), 'soft-deleted').toBe('');

  // Neither tab was reloaded to get there.
  expect(await page.evaluate(() => (window as any).__noReload)).toBe(true);
  expect(await observer.evaluate(() => (window as any).__noReload)).toBe(true);
});

