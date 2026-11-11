import { test, expect } from '@playwright/test';

test('a multi-skilled operator is not booked for two skilled steps at once', async ({ page }, testInfo) => {
  test.setTimeout(290_000);
  page.setDefaultTimeout(30_000);
  await page.setViewportSize({ width: 1440, height: 900 });
  let n = 0;
  const shot = async (name: string) => {
    n += 1;
    await page.screenshot({ path: testInfo.outputPath(`${String(n).padStart(2, '0')}-${name}.png`), fullPage: true });
  };
  const openGroup = async (group: RegExp, link: string | RegExp) => {
    const l = page.getByRole('complementary').getByRole('link', { name: link }).first();
    if (!(await l.isVisible())) await page.getByRole('button', { name: group }).first().click();
    await expect(l).toBeVisible();
    await l.click();
    await page.waitForTimeout(2000);
  };

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
  await expect(page.getByRole('button', { name: /^Admin$/i }).first()).toBeVisible({ timeout: 60_000 });

  // ── Skills: Welding and Painting ──
  await openGroup(/^HR/, 'Skills');
  for (const [code, name] of [['WELD', 'Welding'], ['PAINT', 'Painting']]) {
    await page.getByRole('button', { name: 'New Skill' }).click();
    const d = page.getByRole('dialog').first();
    await expect(d.getByLabel(/^Code/)).toBeVisible();
    await page.waitForTimeout(1500);
    await d.getByLabel(/^Code/).fill(code);
    await d.getByLabel(/^Name/).fill(name);
    await d.getByRole('button', { name: 'Create' }).click();
    await expect(page.getByText('Skill created successfully.')).toBeVisible({ timeout: 15000 });
    await page.waitForTimeout(2500);
    if (await page.getByRole('dialog').count()) await page.keyboard.press('Escape');
    await page.waitForTimeout(500);
  }
  await page.screenshot({ path: testInfo.outputPath('00-skills.png'), fullPage: true });
  await shot('skills');

  // ── Worker holding both skills ──
  await page.getByRole('complementary').getByRole('link', { name: 'Workers' }).first().click();
  await page.getByText('New Worker').click();
  await expect(page.getByText('Skills & level (1-5)')).toBeVisible();
  await page.waitForTimeout(1500);
  await page.getByLabel(/^Code/).fill('W-TOM');
  await page.getByLabel(/^Name/).fill('Tomasz Lis');
  await page.getByText('Welding', { exact: true }).click();
  await page.getByText('Painting', { exact: true }).click();
  await shot('worker-with-both-skills');
  await page.getByRole('button', { name: 'Create Worker' }).click();
  await expect(page.getByText('Worker created successfully.')).toBeVisible();
  await page.waitForTimeout(1000);

  // ── Link the worker to Tomasz Lis' account (he is assigned to the Sawing line) ──
  await page.getByRole('complementary').getByRole('button', { name: /^Admin$/i }).click();
  await page.getByRole('complementary').getByRole('link', { name: 'Users & Accounts' }).click();
  await expect(page.getByRole('row').filter({ hasText: 'tomasz.lis' }).first()).toBeVisible();
  console.log('URL1 ' + page.url());
  await page.getByRole('row').filter({ hasText: 'tomasz.lis' }).first().getByRole('link', { name: 'Edit' }).click();
  await page.waitForTimeout(2500);
  console.log('URL2 ' + page.url());
  await page.getByRole('button', { name: /Worker profile/ }).click();
  await page.getByText('Link existing worker').click();
  await page.getByText('— Select worker —').click();
  await page.getByRole('option', { name: /W-TOM/ }).click();
  await shot('link-worker');
  await page.getByRole('button', { name: /^(Save|Update)/ }).first().click();
  await page.waitForTimeout(2500);
  await shot('worker-linked');

  // ── Segments: Sawing needs Welding, CNC Milling needs Painting ──
  const newSegment = async (code: string, name: string, skill: string) => {
    await openGroup(/^Production$/i, 'Process Segments');
    await page.getByText('New Segment', { exact: true }).click();
    await page.waitForTimeout(2000);
    await page.getByLabel(/^Code/).fill(code);
    await page.getByLabel(/^Name/).fill(name);
    await page.getByText(skill, { exact: true }).click();
    await shot(`segment-${code}`);
    await page.getByRole('button', { name: /^(Create|Save)/ }).first().click();
    await page.waitForTimeout(2500);
  };
  await newSegment('SEG-WELD', 'Welding step', 'Welding');
  await newSegment('SEG-PAINT', 'Painting step', 'Painting');

  // ── Link step 1 of the two sub-assembly routings to those segments ──
  const linkStep = async (product: string, stepName: string, seg: string) => {
    await openGroup(/^Production$/i, 'Product Types');
    await page.getByText(product).first().locator('xpath=ancestor::*[.//*[text()="View Details"]][1]').getByText('View Details').click();
    await page.waitForTimeout(2000);
    await page.getByRole('main').getByText(/ v1/).first().click();
    await page.waitForTimeout(2500);
    await page.getByText(stepName, { exact: true }).first().click();
    await page.waitForTimeout(1000);
    await page.getByRole('button', { name: /^Edit$/ }).last().click();
    await page.waitForTimeout(1500);
    await page.getByLabel('Linked Process Segment').click();
    await page.getByPlaceholder('Search…').fill(seg);
    await page.getByRole('option', { name: new RegExp(seg) }).click();
    await shot(`step-${seg}`);
    await page.getByRole('button', { name: /^(Save|Update)/ }).first().click();
    await page.waitForTimeout(2500);
  };
  await linkStep('Sawn Billet', 'Saw to length', 'SEG-WELD');
  await linkStep('Flame-cut Flange Blank', 'Cut blank', 'SEG-PAINT');

  await page.screenshot({ path: testInfo.outputPath('50-linked.png'), fullPage: true });

  // ── Two work orders, one per sub-assembly, on the Sawing line ──
  const newOrder = async (no: string, line: string, product: RegExp) => {
    await openGroup(/^Orders$/i, 'All Orders');
    await page.getByText('New Work Order', { exact: true }).click();
    const wo = page.getByRole('dialog').first();
    await expect(wo.getByLabel(/^Order No/i)).toBeVisible();
    await page.waitForTimeout(1200);
    await wo.getByLabel(/^Order No/i).fill(no);
    await wo.getByLabel(/^Line/i).click();
    await page.getByRole('option', { name: line }).click();
    await wo.getByLabel(/^Product Type/i).click();
    await page.getByRole('option', { name: product }).click();
    await wo.getByLabel(/^Planned Qty/i).fill('10');
    await shot(`new-${no}`);
    await wo.getByRole('button', { name: /^(Create|Save)/ }).click();
    await page.waitForTimeout(2500);
  };
  await newOrder('WO-1', 'Sawing & Billet Prep', /Sawn Billet/);
  await newOrder('WO-2', 'Sawing & Billet Prep', /Flame-cut Flange Blank/);

  // ── Scheduler → Automatic scheduling ──
  await page.getByRole('link', { name: 'Scheduler' }).first().click();
  await page.getByRole('link', { name: /Automatic scheduling/ }).click();
  await expect(page.getByText('New simulation')).toBeVisible();
  await page.waitForTimeout(1000);
  await page.getByRole('button', { name: /^Simulate/ }).click();
  await expect(page.getByRole('button', { name: 'Apply', exact: true })).toBeVisible({ timeout: 90000 });
  await page.waitForTimeout(1000);
  await shot('draft');
  const row1 = page.getByRole('row').filter({ hasText: 'WO-1' }).first();
  const row2 = page.getByRole('row').filter({ hasText: 'WO-2' }).first();
  await expect(row1).toContainText('07:00');
  await expect(row2).not.toContainText('07:00');
  await expect(row2).toContainText('Waited');
  await expect(page.getByText('Welding — single qualified operator: Tomasz Lis').first()).toBeVisible();
  await expect(page.getByText('Painting — single qualified operator: Tomasz Lis').first()).toBeVisible();
  await row2.scrollIntoViewIfNeeded();
  await page.waitForTimeout(1500);
  await page.screenshot({ path: testInfo.outputPath('90-result.png'), fullPage: true });
  void row1;
});
