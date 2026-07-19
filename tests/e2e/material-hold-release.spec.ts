import { test, expect } from '@playwright/test';
import * as childProc from 'child_process';
import * as fs from 'fs';

// E2E for the non-conformity workflow (#107) — manual quality hold/release on a
// material lot, driven through Admin → Material Lots. The hold/release/batch-gate
// business rules are unit/feature-tested in PHP (MaterialHoldTest); this asserts
// the UI wiring: row actions + live status flip.

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
const LOT = 'LOT-E2E-HOLD';

const BACKEND = `${process.env.OPENMES_NAME_PREFIX || 'openmmes'}-backend`;

// Seed a single received lot with a known number (idempotent: cleaned first).
// Explicit Eloquent — no factories: Faker is require-dev and absent from the
// production image, so `fake()` is undefined inside the running container.
const SEED_PHP = `<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
use App\\Models\\Material; use App\\Models\\MaterialType; use App\\Models\\MaterialLot;
MaterialLot::withTrashed()->where('lot_number', '${LOT}')->forceDelete();
$type = MaterialType::query()->first() ?: MaterialType::create(['code' => 'E2E', 'name' => 'E2E Type']);
$m = Material::query()->first() ?: Material::create([
    'code' => 'E2E-MAT', 'name' => 'E2E Material', 'material_type_id' => $type->id, 'unit_of_measure' => 'pcs',
]);
MaterialLot::create([
    'lot_number' => '${LOT}',
    'material_id' => $m->id,
    'quantity_received' => 100,
    'quantity_available' => 100,
    'unit_of_measure' => 'pcs',
    'received_at' => now(),
    'status' => MaterialLot::STATUS_RECEIVED,
]);
`;

const CLEAR_PHP = `<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
App\\Models\\MaterialLot::withTrashed()->where('lot_number', '${LOT}')->forceDelete();
`;

test.describe('material hold/release flow', () => {

function runInContainer(php: string): boolean {
  const tmp = `/tmp/pw-hold-${Date.now()}-${Math.random().toString(36).slice(2)}.php`;
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

async function login(page) {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
}

test('material lot can be put on quality hold and released (#107)', async ({ page, context }) => {
  test.skip(!seeded, `docker exec seed failed — requires ${BACKEND} running`);
  await context.clearCookies();
  await login(page);
  await page.setViewportSize({ width: 1500, height: 1100 });

  await page.goto('/admin/material-lots');
  await expect(page.getByRole('heading', { name: /^Material Lots$/i })).toBeVisible();

  // Locate the seeded lot's row (live shape may take a moment to stream in).
  const row = page.locator('tr', { hasText: LOT });
  await expect(row).toBeVisible({ timeout: 15_000 });

  // A received lot offers Hold (not Release).
  await expect(row.locator('[data-action="Hold"]')).toBeVisible();
  await expect(row.locator('[data-action="Release"]')).toHaveCount(0);

  // Hold → modal asks for a reason → submit → status flips to Quarantine.
  await row.locator('[data-action="Hold"]').click();
  const holdModal = page.getByRole('dialog');
  await expect(holdModal).toBeVisible();
  await holdModal.locator('textarea').fill('E2E suspected defect');
  await holdModal.getByRole('button', { name: 'Place on hold' }).click();

  const heldRow = page.locator('tr', { hasText: LOT });
  await expect(heldRow.getByText(/quarantine/i)).toBeVisible({ timeout: 15_000 });
  await expect(heldRow.locator('[data-action="Release"]')).toBeVisible();

  await page.screenshot({ path: 'test-results/107-lot-on-hold.png', fullPage: true });

  // Release → confirm dialog → status flips back to Released, Hold offered again.
  await heldRow.locator('[data-action="Release"]').click();
  const releaseDialog = page.getByRole('alertdialog');
  await expect(releaseDialog).toBeVisible();
  await releaseDialog.getByRole('button', { name: /^Release$/ }).click();

  const releasedRow = page.locator('tr', { hasText: LOT });
  await expect(releasedRow.getByText(/released/i)).toBeVisible({ timeout: 15_000 });
  await expect(releasedRow.locator('[data-action="Hold"]')).toBeVisible();

  await page.screenshot({ path: 'test-results/107-lot-released.png', fullPage: true });
});
});
