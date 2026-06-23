import { test, expect, Page } from '@playwright/test';

// ─────────────────────────────────────────────────────────────────────────────
// ⚠️ CONSENT-REQUIRED MANUAL TEST — do not run unattended.
//
// Drives the WEB INSTALLER from zero against a FRESH, throwaway OpenMES instance
// (a separate `php artisan serve` on its own SQLite database — NOT the dev
// stack, whose Docker entrypoint auto-installs and skips the wizard). It runs
// `migrate:fresh`, seeds, creates an admin and writes `storage/installed` in
// that instance. It must point at a disposable install, never a real one.
//
// Setup (outside CI):
//   1. Copy backend to ~/Documents/openmes_<rand>/backend (vendor + public/build
//      symlinked), minimal bootable .env (sqlite, file sessions), no
//      storage/installed.
//   2. php artisan serve --port=8200 --no-reload from that copy.
//      ── --no-reload is REQUIRED: the DB step writes the app config to .env, and
//      the default file-watching serve restarts mid-request on that change,
//      resetting the connection (the modules step never renders). --no-reload
//      also lets PHP_CLI_SERVER_WORKERS=8 take effect so parallel asset
//      requests don't block the in-request migrate:fresh.
//   3. INSTALL_BASE_URL=http://127.0.0.1:8200 INSTALL_SQLITE=<abs path to db>
//      npx playwright test install-wizard
//
// Verifies the #144 module-selection step end-to-end: two modules are unchecked
// during install and are then genuinely off (hidden from nav + routes 404),
// while the rest of the install completes and the admin can log in.
// ─────────────────────────────────────────────────────────────────────────────

const BASE = process.env.INSTALL_BASE_URL || 'http://127.0.0.1:8200';
const ADMIN_USER = 'installadmin';
const ADMIN_PASS = 'Install1234!';

async function login(page: Page) {
  await page.goto(`${BASE}/login`);
  await page.fill('input[name="username"]', ADMIN_USER);
  await page.fill('input[name="password"]', ADMIN_PASS);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type="submit"]')]);
  await page.setViewportSize({ width: 1500, height: 1100 });
}

test('full web install with module selection (HR + Connectivity off)', async ({ page }) => {
  test.setTimeout(180_000);

  // ── Welcome → Database ──────────────────────────────────────────────────
  await page.goto(`${BASE}/install`);
  await expect(page.getByText('Installation Wizard')).toBeVisible({ timeout: 15_000 });
  await page.getByRole('link', { name: /Let.?s Begin/i }).click();

  // ── Database (SQLite, absolute path) → runs migrate:fresh + seeders ──────
  await expect(page.getByText('Database', { exact: false }).first()).toBeVisible({ timeout: 15_000 });
  await page.locator('#db_driver').selectOption('sqlite');
  const sqlitePath = process.env.INSTALL_SQLITE;
  if (sqlitePath) {
    await page.locator('#db_database_sqlite').fill(sqlitePath);
  }
  await page.getByRole('button', { name: /Continue/i }).click();

  // ── Modules: uncheck HR + Connectivity, keep the rest ───────────────────
  await expect(page.getByText('Choose the modules you need')).toBeVisible({ timeout: 30_000 });
  await page.locator('input[name="modules[]"][value="hr"]').uncheck();
  await page.locator('input[name="modules[]"][value="connectivity"]').uncheck();
  await page.getByRole('button', { name: /Continue/i }).click();

  // ── Admin account ───────────────────────────────────────────────────────
  await expect(page.getByText('Create Administrator Account')).toBeVisible({ timeout: 15_000 });
  await page.fill('input[name="site_name"]', 'Fresh Install Co');
  await page.fill('input[name="site_url"]', BASE);
  await page.fill('input[name="admin_username"]', ADMIN_USER);
  await page.fill('input[name="admin_email"]', 'admin@fresh.test');
  await page.fill('input[name="admin_password"]', ADMIN_PASS);
  await page.fill('input[name="admin_password_confirmation"]', ADMIN_PASS);
  await page.getByRole('button', { name: /Complete Installation/i }).click();

  // ── Complete ────────────────────────────────────────────────────────────
  // createAdmin() writes storage/installed *before* redirecting to
  // install.complete, and that route is behind CheckInstallation — so the
  // "Installation Complete!" screen is bounced to / → login. Landing on the
  // sign-in page is therefore the real, observable end-of-install state, and it
  // doubly proves the installer locked itself (install routes now 403/redirect).
  await page.waitForURL(/\/login$/, { timeout: 30_000 });
  await expect(page.getByRole('heading', { name: /Sign in/i })).toBeVisible({ timeout: 15_000 });
  await page.screenshot({ path: 'test-results/install-complete.png', fullPage: true });

  // Install routes are now locked.
  await page.goto(`${BASE}/install`);
  await expect(page).toHaveURL(/\/login$/);

  // ── Verify the install + the module choice took effect ──────────────────
  await login(page);

  // The fresh-install root lands on an onboarding screen ("Create a Production
  // Line"); the admin sidebar with the module groups lives under /admin.
  await page.goto(`${BASE}/admin/dashboard`);
  const sidebar = page.locator('aside').first();
  await expect(sidebar).toBeVisible({ timeout: 15_000 });
  // Kept modules are present…
  await expect(sidebar.getByText('Reports', { exact: true }).first()).toBeVisible();
  await expect(sidebar.getByText('Production', { exact: true }).first()).toBeVisible();
  // …unchecked ones are gone from the nav…
  await expect(sidebar.getByText('HR', { exact: true })).toHaveCount(0);
  await expect(sidebar.getByText('Connectivity', { exact: true })).toHaveCount(0);

  // …and their routes are blocked by TabAccessMiddleware (HTTP 404). Asserting
  // the status (not page text) is server-agnostic: a direct goto isn't an
  // Inertia request, so the framework renders a plain 404, not the React page.
  expect((await page.goto(`${BASE}/admin/workers`))?.status()).toBe(404);
  expect((await page.goto(`${BASE}/admin/connectivity`))?.status()).toBe(404);

  // A kept module's route still serves.
  expect((await page.goto(`${BASE}/admin/scrap-reports`))?.status()).toBe(200);
});
