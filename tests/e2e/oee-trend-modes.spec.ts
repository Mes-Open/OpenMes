import { test } from '@playwright/test';
import { login as signIn } from './support/app';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('screenshot Combined and Per-line trend modes', async ({ page }) => {
  await signIn(page, ADMIN_USERNAME, ADMIN_PASSWORD);

  await page.setViewportSize({ width: 1400, height: 900 });

  await page.goto('/admin/oee');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/oee-combined.png', fullPage: true });

  await page.getByRole('button', { name: 'Per line' }).click();
  await page.waitForTimeout(300);
  await page.screenshot({ path: 'test-results/oee-per-line.png', fullPage: true });

  // Show "All" shift breakdown by visiting per-line detail
  await page.goto('/admin/oee/1');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/oee-line-detail-shifts.png', fullPage: true });
});
