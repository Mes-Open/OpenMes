import { test, expect } from '@playwright/test';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

async function login(page) {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN_USERNAME);
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 10_000 }),
    page.click('button[type="submit"]'),
  ]);
}

test.describe('OEE feature — issue #12', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('admin dashboard shows OEE gauge widget', async ({ page }) => {
    await page.goto('/admin/dashboard');

    // Gauge SVG should render somewhere on the page (multiple if many lines).
    const gauges = page.locator('svg[viewBox="0 0 100 60"]');
    await expect(gauges.first()).toBeVisible({ timeout: 5_000 });
  });

  test('/admin/oee renders gauge, trend chart, and granularity toggle', async ({ page }) => {
    await page.goto('/admin/oee');

    // Granularity toggle present
    await expect(page.getByRole('link', { name: 'Daily' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Weekly' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Monthly' })).toBeVisible();

    // Threshold legend reflects 65/85 (not legacy 60)
    const body = await page.content();
    expect(body).toContain('65');
    expect(body).toContain('85');
    expect(body).not.toMatch(/60-84%|60-85%/);
  });

  test('switching granularity updates the URL', async ({ page }) => {
    await page.goto('/admin/oee');

    await page.getByRole('link', { name: 'Weekly', exact: true }).click();
    await expect(page).toHaveURL(/granularity=week/);

    await page.getByRole('link', { name: 'Monthly', exact: true }).click();
    await expect(page).toHaveURL(/granularity=month/);

    await page.getByRole('link', { name: 'Daily', exact: true }).click();
    await expect(page).toHaveURL(/granularity=day/);
  });

  test('downtime reasons endpoint is reachable', async ({ request }) => {
    const response = await request.get('/api/v1/downtime-reasons', {
      headers: { Accept: 'application/json' },
    });

    // Sanctum requires bearer token; cookie-less call expected to 401.
    // Schema (with 'kind' field) is asserted in PHPUnit OeeApiTest.
    expect([200, 401, 403]).toContain(response.status());
  });
});
