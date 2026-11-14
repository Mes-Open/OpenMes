import { test, expect } from '@playwright/test';
import { choose, expectPage, pickToday, login, runId, runPhp } from './support/app';

// Maintenance: plan an event and a recurring schedule against a tool, then edit
// the event. Replaces the old per-page screenshot checks, which looked for the
// Blade-era section headings and assumed events already existed.

const ID = runId();
const TOOL = `E2E Press ${ID}`;
const EVENT = `Replace seals ${ID}`;
const SCHEDULE = `Monthly seal check ${ID}`;

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
  runPhp(`App\\Models\\Tool::create(['code' => 'E2E-${ID}', 'name' => '${TOOL}', 'status' => 'available']);`);
});

test('plan a maintenance event for a tool, then edit it', async ({ page }) => {
  await login(page);
  await page.goto('/admin/maintenance-events');
  await expectPage(page, 'Maintenance Events');

  await page.getByRole('button', { name: 'New Event' }).click();
  const create = page.getByRole('dialog', { name: 'New Maintenance Event' });
  await expect(create).toBeVisible();

  // Saving an incomplete event is refused and the form says why.
  await create.getByLabel('Title').fill(EVENT);
  await create.getByRole('button', { name: 'Create' }).click();
  await expect(create.getByRole('alert').first()).toBeVisible();

  await choose(create, 'Tool', TOOL);
  await pickToday(create, 'Scheduled At');
  await create.getByRole('button', { name: 'Create' }).click();
  await expect(create).toBeHidden();

  const row = page.getByRole('row').filter({ hasText: EVENT });
  await expect(row).toBeVisible();
  await expect(row).toContainText(TOOL);

  await row.getByRole('button', { name: 'Edit' }).click();
  const edit = page.getByRole('dialog');
  await expect(edit.getByLabel('Title')).toHaveValue(EVENT);
  await edit.getByLabel('Title').fill(`${EVENT} (done)`);
  await edit.getByRole('button', { name: /^(Save|Update)/ }).click();
  await expect(edit).toBeHidden();
  await expect(page.getByRole('row').filter({ hasText: `${EVENT} (done)` })).toBeVisible();
});

test('set up a recurring maintenance schedule', async ({ page }) => {
  await login(page);
  await page.goto('/admin/maintenance-schedules');
  await expectPage(page, 'Maintenance Schedules');

  await page.goto('/admin/maintenance-schedules/create');
  await expect(page.getByRole('heading', { name: 'New Maintenance Schedule' })).toBeVisible();
  await page.getByLabel('Name').fill(SCHEDULE);
  await choose(page, 'Tool', TOOL);
  await choose(page, 'Frequency', /monthly/i);
  await pickToday(page, 'Next Due At');
  await page.getByRole('button', { name: 'Create' }).click();
  await page.waitForURL(/\/admin\/maintenance-schedules$/);

  await expect(page.getByRole('row').filter({ hasText: SCHEDULE })).toBeVisible();
});
