import { test, expect, Page } from '@playwright/test';
import { execSync } from 'child_process';
import { login as signIn } from './support/app';

// Manual test for #144 — disabling an optional module from Settings → System →
// Modules hides its tab from the sidebar AND 404s its routes, then re-enabling
// restores it. Runs against a live (already-installed) stack; the installer step
// shares the same ModuleRegistry enforcement.
const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
const BACKEND = `${process.env.OPENMES_NAME_PREFIX || 'openmmes'}-backend`;

function tinker(php: string): string {
  const e = php.replace(/"/g, '\\"').replace(/\$/g, '\\$');
  return execSync(`docker exec ${BACKEND} php artisan tinker --execute="${e}"`, { encoding: 'utf8' })
    .trim().split('\n').pop()!.trim();
}

async function login(page: Page) {
  await signIn(page, ADMIN, PASS);
  await page.setViewportSize({ width: 1500, height: 1100 });
}

// Collapsed nav groups do not render their links, so look an entry up through
// the sidebar's own menu search.
async function sidebarFinds(page: Page, term: string, href: string) {
  await page.getByRole('searchbox', { name: 'Search menu…' }).fill(term);
  return page.locator(`aside a[href="${href}"]`);
}

test('disable a module → tab hidden + route 404, then re-enable', async ({ page }) => {
  // Start from a known state: all modules enabled.
  tinker("App\\\\Support\\\\ModuleRegistry::save(App\\\\Support\\\\ModuleRegistry::optionalKeys());");

  await login(page);

  // Packaging is reachable to begin with, and listed in the sidebar.
  await page.goto('/admin/pallets');
  await expect(page).toHaveURL(/\/admin\/pallets/);
  await expect(page.getByText(/Page not found/i)).toHaveCount(0);
  await expect(await sidebarFinds(page, 'Pallets', '/admin/pallets')).toBeVisible();

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
  await expect(await sidebarFinds(page, 'Pallets', '/admin/pallets')).toHaveCount(0);
  const sidebar = page.locator('aside').first();
  await page.getByRole('searchbox', { name: 'Search menu…' }).fill('');
  await expect(sidebar.getByRole('button', { name: 'Admin', exact: true })).toBeVisible();
  await expect(sidebar.getByRole('button', { name: 'Analytics', exact: true })).toBeVisible();

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
