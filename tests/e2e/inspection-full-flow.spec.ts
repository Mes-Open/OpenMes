import { test, expect } from '@playwright/test';
import { choose, expectPage, login, runId, runPhp } from './support/app';

// Inbound inspection that fails: an out-of-spec measurement completes the
// inspection as Fail and raises a non-conformance. The NC has no work order, so
// it also covers the old /admin/issues regression (UrlGenerationException on
// inbound NCs without a WO).

const ID = runId();
const MATERIAL = `E2E Bolt ${ID}`;
const LOT = `LOT-E2E-${ID}`;

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
  runPhp(`
$type = App\\Models\\MaterialType::firstOrCreate(['code' => 'E2E-RAW'], ['name' => 'E2E raw material']);
$material = App\\Models\\Material::create(['code' => 'E2E-BOLT-${ID}', 'name' => '${MATERIAL}', 'material_type_id' => $type->id, 'unit_of_measure' => 'pcs']);
App\\Models\\InspectionPlan::create([
    'name' => '${MATERIAL} incoming',
    'material_id' => $material->id,
    'criteria' => [
        ['name' => 'Visual', 'type' => 'pass_fail', 'required' => true],
        ['name' => 'Diameter', 'type' => 'measurement', 'spec_min' => 9.8, 'spec_max' => 10.2, 'required' => true],
    ],
    'is_active' => true,
    'published_at' => now(),
]);`);
});

test('an out-of-spec inbound inspection fails and raises a non-conformance', async ({ page }) => {
  await login(page);
  await page.goto('/operator/inspections');
  await expect(page.getByRole('heading', { name: 'Inspections', level: 1 })).toBeVisible();

  await choose(page, 'Material', new RegExp(MATERIAL)); // options read "CODE — Name"
  await page.getByLabel('Lot number').fill(LOT);
  await page.getByLabel('Quantity received').fill('250');
  await page.getByRole('button', { name: 'Start inspection' }).click();
  await page.waitForURL(/\/operator\/inspections\/\d+/);
  await expect(page.getByRole('heading', { name: MATERIAL, level: 1 })).toBeVisible();

  const visual = page.getByRole('listitem').filter({ hasText: 'Visual' });
  await visual.getByRole('button', { name: 'Pass' }).click();
  const diameter = page.getByRole('listitem').filter({ hasText: 'Diameter' });
  await diameter.getByRole('spinbutton', { name: 'Diameter' }).fill('11.5');
  await diameter.getByRole('button', { name: 'Record result' }).click();
  await expect(diameter).toContainText(/fail/i);

  await page.getByRole('button', { name: 'Complete inspection' }).click();
  const confirm = page.getByRole('alertdialog');
  await expect(confirm).toContainText('Results can no longer be changed');
  await confirm.getByRole('button', { name: 'Complete inspection' }).click();
  await expect(page.getByText('Inspection completed.')).toBeVisible();
  await expect(page.getByRole('list').getByText('Pass')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Disposition' })).toBeVisible();

  // The failure raised a non-conformance with no work order behind it.
  const response = await page.goto('/admin/issues');
  expect(response?.status()).toBe(200);
  await expectPage(page, 'Issues');
  const nc = page.getByRole('row').filter({ hasText: `Inbound QC fail: ${MATERIAL} lot ${LOT}` });
  await expect(nc).toBeVisible();
  await expect(page.getByText(/UrlGenerationException|Whoops/)).toHaveCount(0);
});
