import { test } from '@playwright/test';

async function login(page: any, ctx: any) {
  await ctx.clearCookies();
  await page.goto('/login');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'Admin1234!');
  await Promise.all([
    page.waitForURL((url: URL) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
}

test('activity logs: full filter UI', async ({ page, context }) => {
  await login(page, context);
  await page.setViewportSize({ width: 1500, height: 900 });

  // 1. Filter panel — domyślny widok
  await page.goto('/admin/logs/activity');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/activity-filters-default.png', clip: { x: 240, y: 0, width: 1260, height: 280 } });

  // 2. Filtruj tylko Login events
  await page.goto('/admin/logs/activity?action=login');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/activity-filter-login-only.png', fullPage: false });

  // 3. Filtruj tylko Navigation (request_logs)
  await page.goto('/admin/logs/activity?source=request');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/activity-filter-source-request.png', fullPage: false });

  // 4. Filtruj data ostatni dzień
  const today = new Date().toISOString().slice(0, 10);
  await page.goto(`/admin/logs/activity?from=${today}&to=${today}`);
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/activity-filter-today.png', fullPage: false });
});

test('system logs: tabs + filters', async ({ page, context }) => {
  await login(page, context);
  await page.setViewportSize({ width: 1500, height: 900 });

  // Application log z level=error filtrem
  await page.goto('/admin/logs/system?tab=app&level=error');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/system-app-level-error.png', fullPage: false });

  // Application log z search
  await page.goto('/admin/logs/system?tab=app&search=SQLSTATE');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/system-app-search.png', fullPage: false });

  // Failed jobs tab
  await page.goto('/admin/logs/system?tab=failed_jobs');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/system-failed-jobs.png', fullPage: false });

  // Deployments tab (info card bo brak system_updates na develop)
  await page.goto('/admin/logs/system?tab=deployments');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/system-deployments.png', fullPage: false });
});
