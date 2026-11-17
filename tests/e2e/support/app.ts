import { expect, test, type Locator, type Page } from '@playwright/test';
import { execFileSync } from 'child_process';
import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';

// Shared plumbing for the e2e specs: signing in, asserting which page is open,
// and seeding data straight into the running backend container.

export const ADMIN = process.env.ADMIN_USERNAME || 'admin';
export const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
export const BACKEND =
  process.env.E2E_BACKEND_CONTAINER || `${process.env.OPENMES_NAME_PREFIX || 'openmmes'}-backend`;

/**
 * Sign in through the login form. POST /login allows 10 attempts a minute, and a
 * full run signs in far more often than that, so a 429 waits the window out and
 * tries again instead of failing the spec that happened to hit it.
 */
export async function login(page: Page, username = ADMIN, password = PASS): Promise<void> {
  for (let attempt = 1; attempt <= 3; attempt++) {
    await page.goto('/login');
    await page.fill('input[name="username"]', username);
    await page.fill('input[name="password"]', password);
    const [response] = await Promise.all([
      page.waitForResponse((r) => r.request().method() === 'POST' && new URL(r.url()).pathname === '/login'),
      page.click('button[type="submit"]'),
    ]);
    if (response.status() !== 429) {
      await page.waitForURL((u) => !u.pathname.startsWith('/login'));
      return;
    }
    test.info().setTimeout(test.info().timeout + 65_000);
    await page.waitForTimeout(61_000);
  }
  throw new Error(`Login for ${username} is still throttled after three attempts.`);
}

/**
 * Assert which page is open. CRUD lists name the page only in the layout
 * breadcrumb; reports and forms carry their own <h1>. Either one counts.
 */
export async function expectPage(page: Page, title: string | RegExp): Promise<void> {
  await expect(
    page.locator('nav[aria-label="Breadcrumb"]:visible, main h1:visible').filter({ hasText: title }).first(),
  ).toBeVisible();
}

/**
 * Run a PHP snippet inside the backend container with the app booted and return
 * what it echoes. Use plain Eloquent: Faker is require-dev and absent from the
 * production image, so factories do not work there.
 */
export function runPhp(body: string): string {
  const script = `<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
${body}
`;
  const local = path.join(os.tmpdir(), `openmes-e2e-${process.pid}-${Date.now()}.php`);
  const remote = `/tmp/${path.basename(local)}`;
  fs.writeFileSync(local, script);
  try {
    execFileSync('docker', ['cp', local, `${BACKEND}:${remote}`], { stdio: 'ignore' });
    return execFileSync('docker', ['exec', BACKEND, 'php', remote], { encoding: 'utf8' }).trim();
  } finally {
    fs.unlinkSync(local);
    try {
      execFileSync('docker', ['exec', BACKEND, 'rm', '-f', remote], { stdio: 'ignore' });
    } catch {
      // best effort
    }
  }
}

/** A short suffix that keeps records from separate runs apart. */
export const runId = (): string => Date.now().toString(36).slice(-5).toUpperCase();

// Option names carry the selected-tick glyph, so a string matches as a prefix.
const optionNamed = (name: string | RegExp): RegExp =>
  typeof name === 'string' ? new RegExp(`^${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`) : name;

/**
 * Pick an option from the UI-kit Select. It is a button with role=combobox,
 * named after its field label, that opens a portaled listbox, so Playwright's
 * selectOption() does not apply.
 */
export async function choose(scope: Page | Locator, label: string | RegExp, option: string | RegExp): Promise<void> {
  const page = 'page' in scope ? scope.page() : scope;
  await scope.getByRole('combobox', { name: label, exact: typeof label === 'string' }).click();
  await page.getByRole('option', { name: optionNamed(option) }).first().click();
}

/** Same, for a Select found some other way than by its label. */
export async function chooseIn(trigger: Locator, option: string | RegExp): Promise<void> {
  await trigger.click();
  await trigger.page().getByRole('option', { name: optionNamed(option) }).first().click();
}

/** Set a UI-kit DatePicker (by its field label) to today through its calendar. */
export async function pickToday(scope: Page | Locator, label: string): Promise<void> {
  const page = 'page' in scope ? scope.page() : scope;
  await scope.getByRole('button', { name: new RegExp(`^${label} `) }).click();
  const calendar = page.getByRole('dialog', { name: 'Choose date' });
  await calendar.getByRole('button', { name: 'Today' }).click();
  await expect(calendar).toBeHidden();
}
