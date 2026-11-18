import { test, expect } from '@playwright/test';
import { expectPage, login } from './support/app';

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
    await expectPage(page, 'OEE Report');
    await expect(page.getByRole('heading', { name: 'OEE Trend' })).toBeVisible();

    // Granularity toggle present
    for (const name of ['Daily', 'Weekly', 'Monthly']) {
      await expect(page.getByRole('button', { name, exact: true })).toBeVisible();
    }

    // Threshold legend reflects 65/85 (not legacy 60)
    const body = await page.content();
    expect(body).toContain('65');
    expect(body).toContain('85');
    expect(body).not.toMatch(/60-84%|60-85%/);
  });

  test('switching granularity updates the URL', async ({ page }) => {
    await page.goto('/admin/oee');

    await page.getByRole('button', { name: 'Weekly', exact: true }).click();
    await expect(page).toHaveURL(/granularity=week/);

    await page.getByRole('button', { name: 'Monthly', exact: true }).click();
    await expect(page).toHaveURL(/granularity=month/);

    await page.getByRole('button', { name: 'Daily', exact: true }).click();
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
