import { test, expect } from '@playwright/test';
import { login as signIn } from './support/app';
import * as childProc from 'child_process';
import * as fs from 'fs';

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';

const SEED_PHP = `<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
use Illuminate\\Support\\Facades\\Cache;
Cache::put('update_check_result', [
    'version' => 'v999.0.0',
    'name' => 'OpenMES v999.0.0 (TEST)',
    'release_url' => 'https://example.com/r',
    'zip_url' => 'https://example.com/z.zip',
], 3600);
Cache::forget('update_apply_status');
Cache::lock('update_apply_lock')->forceRelease();
`;

const CLEAR_PHP = `<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
\\Illuminate\\Support\\Facades\\Cache::forget('update_check_result');
`;

// Container name follows the compose prefix (default "openmmes").
const BACKEND = `${process.env.OPENMES_NAME_PREFIX || 'openmmes'}-backend`;

function runInContainer(php: string): boolean {
  const tmp = `/tmp/pw-update-banner-${Date.now()}-${Math.random().toString(36).slice(2)}.php`;
  try {
    fs.writeFileSync(tmp, php);
    childProc.execSync(`docker cp ${tmp} ${BACKEND}:/tmp/seed.php`, { stdio: 'ignore' });
    childProc.execSync(`docker exec ${BACKEND} php /tmp/seed.php`, { stdio: 'ignore' });
    return true;
  } catch {
    return false;
  } finally {
    try { fs.unlinkSync(tmp); } catch {}
  }
}

let seeded = false;
test.beforeAll(() => { seeded = runInContainer(SEED_PHP); });
test.afterAll(() => { if (seeded) runInContainer(CLEAR_PHP); });

test('updater banner: shows update available when remote is newer', async ({ page, context }) => {
  // The React layout has no update banner yet: the Blade one was dropped in the
  // React migration and nothing calls /admin/update/check any more. Re-enable
  // this once the banner is back.
  test.fixme(true, 'No update banner in the React layout yet.');
  test.skip(!seeded, `docker exec seed failed — requires ${BACKEND} running`);
  await context.clearCookies();
  await signIn(page, ADMIN, PASS);
  await page.evaluate(() => sessionStorage.clear());
  await page.setViewportSize({ width: 1400, height: 900 });
  await page.goto('/admin/dashboard');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  await expect(page.locator('text=/Update available/i')).toBeVisible({ timeout: 5000 });
  await expect(page.locator('text=/OpenMES v999.0.0/')).toBeVisible();
  await expect(page.getByRole('button', { name: /Update now/i })).toBeVisible();
  await page.screenshot({ path: 'test-results/updater-banner-available.png', clip: { x: 0, y: 0, width: 1400, height: 80 } });
});
