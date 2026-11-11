import { test, expect } from '@playwright/test';

const iso = (d: Date) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

test('an absence the day after a night shift starts does not block that night', async ({ page }, testInfo) => {
  test.setTimeout(290_000);
  page.setDefaultTimeout(30_000);
  await page.setViewportSize({ width: 1440, height: 900 });
  let n = 0;
  const shot = async (name: string) => {
    n += 1;
    const path = testInfo.outputPath(`${String(n).padStart(2, '0')}-${name}.png`);
    if (n > 0) await page.screenshot({ path, fullPage: true });
    else await page.screenshot({ path });
  };

  // The night that starts tonight (or tomorrow when it is already late) and the
  // day after it — the "Tuesday" of the story.
  const now = new Date();

  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME!);
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD!);
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();
  await expect(page.getByText('Welcome to OpenMES').first()).toBeVisible({ timeout: 60_000 });
  const shop = page.getByText('Precision machine shop').locator('xpath=ancestor::*[.//input[@type="radio"]][1]').getByRole('radio');
  await shop.click();
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByText('Welcome to OpenMES')).toHaveCount(0, { timeout: 200_000 });
  await page.waitForLoadState('networkidle', { timeout: 60_000 }).catch(() => {});
  await expect(page.getByRole('button', { name: /^Admin$/i })).toBeVisible({ timeout: 60_000 });
  await shot('installed');

  // ── Admin → Shifts ──
  await page.getByRole('button', { name: /^Production$/i }).click();
  await page.getByRole('link', { name: 'Shifts', exact: true }).click();
  await expect(page.getByRole('row').filter({ hasText: 'SM-TURN' })).toBeVisible();
  await page.waitForTimeout(2000);
  await shot('shifts-list');
  await page.getByPlaceholder('Filter…').first().fill('TURN');
  await page.waitForTimeout(800);

  // Take the day and night shifts of CNC Turning out of play.
  for (const code of ['SM-TURN', 'SA-TURN', 'SN-TURN']) {
    const row = page.getByRole('row').filter({ hasText: code });
    await expect(row).toBeVisible();
    await row.getByRole('button', { name: 'Edit' }).click();
    const dlg = page.getByRole('dialog').first();
    await expect(dlg.getByLabel(/^Code/)).toBeVisible();
    await dlg.getByRole('checkbox', { name: 'Active' }).click();
    await dlg.getByRole('button', { name: /^(Save|Update)/ }).click();
    await expect(dlg).toHaveCount(0);
    await page.waitForTimeout(800);
  }
  await shot('day-shifts-inactive');

  await page.getByText('New Shift', { exact: true }).click();
  const sd = page.getByRole('dialog').first();
  await sd.getByLabel(/^Code/).fill('NIGHT-TURN');
  await sd.getByLabel(/^Name/).fill('Night');
  await sd.getByLabel(/^Line/).click();
  await page.getByRole('option', { name: 'CNC Turning' }).click();
  await sd.getByLabel(/^Start Time/).fill('22:00');
  await sd.getByLabel(/^End Time/).fill('06:00');
  await shot('new-night-shift');
  await sd.getByRole('button', { name: /^(Create|Save)/ }).click();
  await expect(page.getByText('Shift created.')).toBeVisible();
  await page.getByRole('link', { name: 'Product Types', exact: true }).click();
  await page.getByRole('link', { name: 'Shifts', exact: true }).click();
  await page.waitForTimeout(2500);
  await page.getByPlaceholder('Filter…').first().fill('TURN');
  await expect(page.getByRole('row').filter({ hasText: 'NIGHT-TURN' })).toContainText('22:00');
  await page.waitForTimeout(800);
  await shot('night-shift-listed');

  // ── Production → Production Lines → CNC Turning: nobody assigned directly ──
  if (!(await page.getByRole('button', { name: /Production Lines/i }).isVisible())) {
    await page.getByRole('button', { name: /^Production$/i }).click();
  }
  await page.getByRole('button', { name: /Production Lines/i }).click();
  await page.getByRole('link', { name: 'All Production Lines' }).click();
  await page.getByRole('link', { name: /CNC Turning/ }).first().click();
  await expect(page.getByText('Assigned Operators').first()).toBeVisible();
  while ((await page.getByRole('button', { name: 'Remove operator' }).count()) > 0) {
    await page.getByRole('button', { name: 'Remove operator' }).first().click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Confirm' }).click();
    await page.waitForTimeout(1200);
  }
  await expect(page.getByText('No operators assigned yet')).toBeVisible();
  await shot('line-no-operators');

  // ── HR → Crews: a night crew for CNC Turning ──
  await page.getByRole('button', { name: /^HR/ }).click();
  await shot('hr-menu');
  await page.getByRole('link', { name: 'Crews' }).click();
  await page.waitForTimeout(2000);
  await shot('crews');
  await page.getByText('New Crew', { exact: true }).click();
  const cd = page.getByRole('dialog').first();
  await cd.getByLabel(/^Code/).fill('NIGHT-CREW');
  await cd.getByLabel(/^Name/).fill('Night crew');
  await cd.getByRole('button', { name: 'CNC Turning' }).click();
  await shot('new-crew');
  await cd.getByRole('button', { name: /^(Create|Save)/ }).click();
  await expect(page.getByText(/Crew created/i)).toBeVisible();

  // ── HR → Workers: the one person on that crew ──
  await page.getByRole('link', { name: 'Workers', exact: true }).click();
  await page.getByText('New Worker', { exact: true }).first().click();
  await expect(page.getByRole('heading', { name: 'New Worker' })).toBeVisible();
  await page.waitForTimeout(2000);
  await page.getByLabel(/^Code/).fill('W-NIGHT');
  await page.getByLabel(/^Name/).fill('Nina Night');
  await page.getByLabel(/^Crew/).click();
  await page.getByRole('option', { name: 'Night crew' }).click();
  await shot('new-worker');
  await page.getByRole('button', { name: 'Create Worker' }).click();
  await expect(page.getByText(/Worker created|Nina Night/i).first()).toBeVisible();

  // ── Production → Product Types → Bronze Bushing: make step 1 an 8 h step ──
  await page.getByRole('button', { name: /^Production$/i }).click();
  await page.getByRole('link', { name: 'Product Types', exact: true }).click();
  await page.getByPlaceholder('Search…').first().fill('Bronze').catch(() => {});
  await page.waitForTimeout(1500);
  await shot('product-types');
  await page.getByRole('button', { name: 'View Details' }).first().click();
  await page.waitForTimeout(2000);
  await shot('product-type');
  await page.getByRole('link', { name: /Bronze Bushing — turning v1/ }).click();
  await expect(page.getByText('Bar setup').first()).toBeVisible();
  await page.waitForTimeout(1500);
  await shot('template');
  await page.getByRole('button', { name: 'Edit', exact: true }).nth(1).click();
  const std = page.getByRole('dialog').first();
  await std.getByLabel(/Estimated Duration/).fill('480');
  await shot('step-480');
  await std.getByRole('button', { name: /Update|Save/ }).click();
  await expect(page.getByText('~480 min').first()).toBeVisible();
  await page.waitForTimeout(800);

  // ── Orders → All Orders → New Work Order ──
  await page.getByRole('button', { name: /^Orders$/i }).click();
  await page.getByRole('link', { name: 'All Orders' }).click();
  await expect(page).toHaveURL(/work-orders/);
  await page.waitForTimeout(2500);
  await page.getByText('New Work Order', { exact: true }).click();
  const wo = page.getByRole('dialog').first();
  await expect(wo.getByLabel(/^Order No/i)).toBeVisible();
  await page.waitForTimeout(1500);
  await wo.getByLabel(/^Order No/i).fill('WO-NIGHT-01');
  await wo.getByLabel(/^Line/i).click();
  await page.getByRole('option', { name: 'CNC Turning' }).click();
  await wo.getByLabel(/^Product Type/i).click();
  await page.getByRole('option', { name: /Bronze Bushing/ }).click();
  await wo.getByLabel(/^Planned Qty/i).fill('10');
  await wo.getByRole('button', { name: /^(Create|Save)/ }).click();
  await page.waitForTimeout(2000);
  await shot('wo-created');

  // ── Scheduler → Automatic scheduling → Simulate ──
  await page.getByRole('link', { name: 'Scheduler' }).first().click();
  await page.getByRole('link', { name: /Automatic scheduling/ }).click();
  await expect(page.getByText('New simulation')).toBeVisible();
  await shot('auto-schedule');
  await page.getByRole('button', { name: /^Simulate/ }).click();
  await expect(page.getByRole('button', { name: 'Apply', exact: true })).toBeVisible({ timeout: 90000 });
  await page.waitForTimeout(1000);
  await shot('draft');

  // Where the 8 h night operation lands before anyone is absent.
    const row = page.getByRole('row').filter({ hasText: 'WO-NIGHT-01' }).first();
  await expect(row).toContainText(/\d\d\.\d\d 22:00\d\d\.\d\d 06:00/);
  await expect(row).toContainText('8 h');
  const text = (await row.innerText()).replace(/\s+/g, '');
  const m = text.match(/(\d\d)\.(\d\d)22:00(\d\d)\.(\d\d)06:00/)!;
  const planned = `${m[1]}.${m[2]} 22:00`;
  const plannedEnd = `${m[3]}.${m[4]} 06:00`;
  const absenceDay2 = new Date(now.getFullYear(), Number(m[4]) - 1, Number(m[3]));
  await shot('night-op-planned-before-absence');

  // ── HR → Worker Absences: Nina is away the day after the night starts ──
  if (!(await page.getByRole('link', { name: 'Worker Absences' }).isVisible())) {
    await page.getByRole('button', { name: /^HR/ }).click();
  }
  await page.getByRole('link', { name: 'Worker Absences' }).click();
  await page.waitForTimeout(1500);
  await page.getByText('New Absence', { exact: true }).click();
  const ad = page.getByRole('dialog').first();
  await ad.getByLabel(/^Worker/).click();
  await page.getByRole('option', { name: /Nina Night/ }).click();
  const dayName = `${WEEKDAYS[absenceDay2.getDay()]}, ${absenceDay2.getDate()} ${MONTHS[absenceDay2.getMonth()]} ${absenceDay2.getFullYear()}`;
  for (const field of [/^Starts on/, /^Ends on/]) {
    await ad.getByLabel(field).click();
    const dayBtn = page.getByRole('button', { name: dayName });
    await expect(page.getByRole('grid').first()).toBeVisible();
    await page.waitForTimeout(700);
    if ((await dayBtn.count()) === 0) await page.getByRole('button', { name: 'Next month' }).click();
    await dayBtn.click();
    await page.waitForTimeout(500);
  }
  await shot('new-absence');
  await ad.getByRole('button', { name: /^(Create|Save)/ }).click();
  await page.waitForTimeout(1500);
  await expect(page.getByText('Absence recorded successfully.')).toBeVisible();


  // ── Scheduler again: the same night is still free ──
  await page.getByRole('link', { name: 'Scheduler' }).first().click();
  await page.getByRole('link', { name: /Automatic scheduling/ }).click();
  await expect(page.getByText('New simulation')).toBeVisible();
  await page.getByRole('button', { name: /^Simulate/ }).click();
  await expect(page.getByRole('button', { name: 'Apply', exact: true })).toBeVisible({ timeout: 90000 });
  await page.waitForTimeout(1000);
  const row2 = page.getByRole('row').filter({ hasText: 'WO-NIGHT-01' }).first();
  await expect(row2).toContainText(planned);
  await expect(row2).toContainText(plannedEnd);
  await expect(page.getByText(/Not enough operators/i)).toHaveCount(0);
  await page.waitForTimeout(1000);
  await shot('night-op-planned-with-absence');
});
