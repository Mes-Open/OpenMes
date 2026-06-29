import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';

// Manual test for #144 — disabling an optional module from Settings → System →
// Modules hides its tab from the sidebar AND 404s its routes, then re-enabling
// restores it. Runs against a live (already-installed) stack; the installer step
// shares the same ModuleRegistry enforcement.
const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';

function tinker(php: string): string {
  const e = php.replace(/"/g, '\\"').replace(/\$/g, '\\$');
  return execSync(`docker exec openmes-backend php artisan tinker --execute="${e}"`, { encoding: 'utf8' })
    .trim().split('\n').pop()!.trim();
}

async function login(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type="submit"]')]);
  await page.setViewportSize({ width: 1500, height: 1100 });
}

test('disable a module → tab hidden + route 404, then re-enable', async ({ page }) => {
  // Start from a known state: all modules enabled.
  tinker("App\\\\Support\\\\ModuleRegistry::save(App\\\\Support\\\\ModuleRegistry::optionalKeys());");

  await login(page);

  // Packaging is reachable to begin with.
  await page.goto('/admin/pallets');
  await expect(page).toHaveURL(/\/admin\/pallets/);
  await expect(page.getByText(/Page not found/i)).toHaveCount(0);

  // Settings → System → Modules: uncheck Packaging, save.
  await page.goto('/settings/system');
  await page.getByRole('tab', { name: 'Modules' }).click();
  const packagingRow = page.locator('div', { hasText: /Pallets and the packaging station/ }).last();
  await packagingRow.getByRole('checkbox').uncheck();
  await page.getByRole('button', { name: /Save/i }).first().click();
  await page.waitForLoadState('networkidle');

  // Route is now 404.
  await page.goto('/admin/pallets');
  await expect(page.getByText(/Page not found/i)).toBeVisible({ timeout: 15_000 });

  // And the Packaging tab is gone from the sidebar — but other groups (a core
  // one and another optional module) remain.
  await page.goto('/admin/dashboard');
  const sidebar = page.locator('aside').first();
  await expect(sidebar.getByText('Packaging', { exact: true })).toHaveCount(0);
  await expect(sidebar.getByText('Admin', { exact: true }).first()).toBeVisible();
  await expect(sidebar.getByText('Reports', { exact: true }).first()).toBeVisible();

  await page.screenshot({ path: 'test-results/module-disabled.png', fullPage: true });

  // Re-enable from Settings → restored.
  await page.goto('/settings/system');
  await page.getByRole('tab', { name: 'Modules' }).click();
  const packagingRow2 = page.locator('div', { hasText: /Pallets and the packaging station/ }).last();
  await packagingRow2.getByRole('checkbox').check();
  await page.getByRole('button', { name: /Save/i }).first().click();
  await page.waitForLoadState('networkidle');

  await page.goto('/admin/pallets');
  await expect(page).toHaveURL(/\/admin\/pallets/);
  await expect(page.getByText(/Page not found/i)).toHaveCount(0);
});

test.afterAll(() => {
  tinker("App\\\\Support\\\\ModuleRegistry::save(App\\\\Support\\\\ModuleRegistry::optionalKeys());");
});
