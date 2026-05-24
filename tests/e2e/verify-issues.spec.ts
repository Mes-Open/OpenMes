import { test, expect } from '@playwright/test';

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('admin/issues renders inbound NCs without WO (regression: UrlGenerationException)', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);

  await page.setViewportSize({ width: 1400, height: 1100 });
  const resp = await page.goto('/admin/issues');
  expect(resp?.status()).toBe(200);

  await expect(page.locator('text=/UrlGenerationException|Whoops/i')).toHaveCount(0);
  await expect(page.getByRole('heading', { name: /^Issues$/i })).toBeVisible();

  const issueCards = page.locator('div.card').filter({ hasText: /Inbound QC/ });
  await expect(issueCards.first()).toBeVisible();
  expect(await issueCards.count()).toBeGreaterThan(0);

  const cardBody = issueCards.first();
  await expect(cardBody.locator('text=/Material:/')).toBeVisible();
  // Badge ze źródłem — wyłącznie span z klasą uppercase
  await expect(cardBody.locator('span.uppercase').filter({ hasText: /inbound inspection/i })).toBeVisible();

  await page.screenshot({ path: 'test-results/admin-issues-fixed.png', fullPage: true });
});
