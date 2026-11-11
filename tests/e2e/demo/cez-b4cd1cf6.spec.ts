import { test, expect, type Page } from '@playwright/test';

test('one operator on two lines is not booked on both lines at once', async ({ page }, testInfo) => {
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

  // ── Each of the two lines: only Tomasz Lis stays assigned ──
  const onlyTomasz = async (lineName: RegExp, first: boolean) => {
    if (!(await page.getByRole('button', { name: /Production Lines/i }).isVisible())) {
      await page.getByRole('button', { name: /^Production$/i }).click();
    }
    await page.getByRole('button', { name: /Production Lines/i }).click();
    await page.getByRole('link', { name: 'All Production Lines' }).click();
    await expect(page).toHaveURL(/admin\/lines/);
    await page.waitForTimeout(1500);
    await page.getByRole('main').getByRole('link', { name: lineName }).first().click();
    await expect(page.getByText('Assigned Operators').first()).toBeVisible();
    await page.waitForTimeout(1200);
    await shot('line-before');
    for (let i = 0; i < 10; i++) {
      const others = page.locator('div').filter({ has: page.getByRole('button', { name: 'Remove operator' }) }).filter({ hasNotText: 'Tomasz Lis' });
      const btns = page.getByRole('button', { name: 'Remove operator' });
      const cnt = await btns.count();
      let removed = false;
      for (let k = 0; k < cnt; k++) {
        const row = btns.nth(k).locator('xpath=ancestor::div[contains(@class,"h-[52px]")][1]');
        if (!(await row.innerText()).includes('Tomasz Lis')) {
          await btns.nth(k).click();
          await page.getByRole('alertdialog').getByRole('button', { name: 'Confirm' }).click();
          await page.waitForTimeout(1200);
          removed = true;
          break;
        }
      }
      void others;
      if (!removed) break;
    }
    await expect(page.getByRole('button', { name: 'Remove operator' })).toHaveCount(1);
    await expect(page.getByText('Tomasz Lis').first()).toBeVisible();
    await shot('line-only-tomasz');
  };
  await onlyTomasz(/CNC Turning/, true);
  await onlyTomasz(/Sawing/, false);

  // ── Orders → All Orders: one order per line ──
  const newOrder = async (no: string, line: string, product: RegExp, qty: string) => {
    if (!(await page.getByRole('link', { name: 'All Orders' }).isVisible())) {
      await page.getByRole('button', { name: /^Orders$/i }).click();
    }
    await page.getByRole('link', { name: 'All Orders' }).click();
    await expect(page).toHaveURL(/work-orders/);
    await page.waitForTimeout(2500);
    await page.getByText('New Work Order', { exact: true }).click();
    const wo = page.getByRole('dialog').first();
    await expect(wo.getByLabel(/^Order No/i)).toBeVisible();
    await page.waitForTimeout(1200);
    await wo.getByLabel(/^Order No/i).fill(no);
    await wo.getByLabel(/^Line/i).click();
    await page.getByRole('option', { name: line }).click();
    await wo.getByLabel(/^Product Type/i).click();
    await page.getByRole('option', { name: product }).click();
    await wo.getByLabel(/^Planned Qty/i).fill(qty);
    await shot(`new-${no}`);
    await wo.getByRole('button', { name: /^(Create|Save)/ }).click();
    await page.waitForTimeout(2000);
  };
  await newOrder('WO-A', 'CNC Turning', /Bronze Bushing/, '10');
  await newOrder('WO-B', 'Sawing & Billet Prep', /Sawn Billet/, '10');
  await shot('orders-created');

  // ── Scheduler → Automatic scheduling ──
  await page.getByRole('link', { name: 'Scheduler' }).first().click();
  await page.getByRole('link', { name: /Automatic scheduling/ }).click();
  await expect(page.getByText('New simulation')).toBeVisible();
  await page.waitForTimeout(1000);
  await shot('auto-schedule');
  await page.getByRole('button', { name: /^Simulate/ }).click();
  await expect(page.getByRole('button', { name: 'Apply', exact: true })).toBeVisible({ timeout: 90000 });
  await page.waitForTimeout(1000);
  await shot('draft');
  await expect(page.getByText('CNC Turning · 1 operators assigned')).toBeVisible();
  await expect(page.getByText('Sawing & Billet Prep · 1 operators assigned')).toBeVisible();

  // WO-A runs first on CNC Turning; WO-B (Sawing) starts only when Tomasz is free again.
  const rowA = page.getByRole('row').filter({ hasText: 'WO-A' }).first();
  const rowB = page.getByRole('row').filter({ hasText: 'WO-B' }).first();
  await expect(rowA).toContainText('14:00');
  await expect(rowB).toContainText('14:56');
  await expect(rowB).not.toContainText('14:00');
  await expect(rowB).toContainText('Waited for operators busy on CNC Turning.');
  await rowB.scrollIntoViewIfNeeded();
  await page.waitForTimeout(1500);
  await shot('wo-b-waits-for-the-shared-operator');
});
