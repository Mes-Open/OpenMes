import { test, expect, type Page } from '@playwright/test';

test('Alert badge counts overdue orders by the plant (Europe/Warsaw) date', async ({ page }, testInfo) => {
  test.setTimeout(290_000);
  page.setDefaultTimeout(30_000);
  const shot = (name: string) => page.screenshot({ path: testInfo.outputPath(name), fullPage: true });

  // Pick a day in the date picker drawer: 'today' or 'yesterday' (calendar based).
  const pickDay = async (which: 'today' | 'yesterday') => {
    await page.getByRole('button', { name: /select date/i }).last().click();
    const todayCell = page.locator('[role="dialog"] button[aria-current="date"], [role="grid"] button[aria-current="date"]').first();
    await expect(todayCell).toBeVisible();
    if (which === 'today') {
      await todayCell.click();
      return;
    }
    const prev = todayCell.locator('xpath=ancestor::div[@role="gridcell"]/preceding-sibling::div[@role="gridcell"][1]//button');
    if (await prev.count()) {
      await prev.first().click();
    } else {
      // today is the first of its week row, step to the previous week row's last cell
      const prevRow = todayCell.locator('xpath=ancestor::div[@role="row"]/preceding-sibling::div[@role="row"][1]//div[@role="gridcell"][7]//button');
      if (await prevRow.count()) await prevRow.first().click();
      else {
        await page.getByRole('button', { name: /previous month|prev/i }).first().click();
        await page.locator('[role="grid"] button[aria-label]:not([disabled])').last().click();
      }
    }
  };

  const createOrder = async (orderNo: string, which: 'today' | 'yesterday') => {
    await page.getByRole('button', { name: 'New Work Order' }).first().click();
    const drawer = page.getByRole('dialog');
    await expect(drawer.getByText('New Work Order').first()).toBeVisible();
    await page.getByRole('textbox', { name: /^Order No/ }).fill(orderNo);
    await page.getByRole('spinbutton', { name: /^Planned Qty/ }).fill('10');
    await pickDay(which);
    await page.screenshot({ path: testInfo.outputPath(`form-${orderNo}.png`), fullPage: true });
    await page.getByRole('button', { name: 'Create', exact: true }).click();
    await expect(page.getByText(orderNo).first()).toBeVisible({ timeout: 20_000 });
  };

  // 1. Login
  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME!);
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD!);
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();
  await expect(page.getByText('Welcome to OpenMES').first()).toBeVisible({ timeout: 30_000 });
  await page.getByRole('button', { name: 'Start with an empty system' }).click();
  await expect(page.getByText('Welcome to OpenMES')).toHaveCount(0, { timeout: 60_000 });
  await page.waitForLoadState('networkidle', { timeout: 60_000 }).catch(() => {});
  await expect(page.getByRole('button', { name: /^Settings$/i })).toBeVisible({ timeout: 60_000 });
  await shot('01-dashboard.png');

  // 2. Settings -> System -> timezone Europe/Warsaw
  await page.getByRole('button', { name: /^Settings$/i }).click();
  await page.getByRole('button', { name: 'System Settings' }).click();
  await page.getByRole('link', { name: 'General', exact: true }).last().click();
  await expect(page.getByRole('heading', { name: 'Timezone' })).toBeVisible({ timeout: 20_000 });
  await page.waitForLoadState('networkidle', { timeout: 30_000 }).catch(() => {});
  await expect(async () => {
    await page.getByLabel('Region').click();
    await page.getByRole('option', { name: /^Europe/ }).click({ timeout: 5_000 });
  }).toPass({ timeout: 40_000 });
  await expect(async () => {
    await page.getByLabel('Timezone', { exact: true }).click();
    await page.getByRole('option', { name: /^Warsaw/ }).click({ timeout: 5_000 });
  }).toPass({ timeout: 40_000 });
  await shot('02-timezone-warsaw.png');
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await page.waitForLoadState('load');
  await page.waitForTimeout(2500);
  await expect(page.getByRole('heading', { name: 'Timezone' })).toBeVisible({ timeout: 30_000 });
  await shot('03-saved.png');

  // 3. Work orders
  await page.getByRole('button', { name: /^Orders$/i }).first().click();
  await page.getByRole('link', { name: 'All Orders', exact: true }).click();
  await expect(page.getByRole('button', { name: 'New Work Order' }).first()).toBeVisible({ timeout: 20_000 });
  await createOrder('DEMO-TODAY', 'today');
  await shot('04-today-created.png');
  await createOrder('DEMO-YESTERDAY', 'yesterday');
  await shot('05-yesterday-created.png');

  // The demo instance has no live-sync socket: reload like a user would so the lists load fresh.
  await page.reload();
  await expect(page.getByText('DEMO-YESTERDAY').first()).toBeVisible({ timeout: 30_000 });
  await expect(page.getByText('DEMO-TODAY').first()).toBeVisible();
  await shot('05b-both-orders-listed.png');

  // 4. Sidebar badge: only the yesterday order is overdue -> 1
  const alertsLink = page.getByRole('link', { name: /Alerts/ }).first();
  await expect(alertsLink).toContainText('1', { timeout: 30_000 });
  await alertsLink.hover();
  await expect(page.getByText(/Latest alerts/i).first()).toBeVisible();
  await page.waitForTimeout(800);
  await shot('06-badge-and-panel.png');

  // 5. Alerts page
  await alertsLink.click();
  await expect(page.getByText('Overdue orders').first()).toBeVisible({ timeout: 20_000 });
  await expect(page.getByText('DEMO-YESTERDAY').first()).toBeVisible();
  await expect(page.getByText('DEMO-TODAY')).toHaveCount(0);
  await page.waitForTimeout(800);
  await shot('07-alerts-page.png');

  // 6. Open in work orders
  await page.getByRole('link', { name: /Open in work orders/ }).click();
  await expect(page.getByText('Overdue').first()).toBeVisible({ timeout: 20_000 });
  await expect(page.getByText('DEMO-YESTERDAY').first()).toBeVisible();
  await expect(page.getByText('DEMO-TODAY')).toHaveCount(0);
  await page.waitForTimeout(800);
  await shot('08-overdue-filter.png');
});
