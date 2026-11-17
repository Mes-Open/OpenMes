import { test, expect } from '@playwright/test';

test('admin configures timestamp-signed webhooks with new events and sees signing info in deliveries', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME || 'admin');
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD || 'Admin1234!');
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();

  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeVisible({ timeout: 60000 });
  await page.getByRole('button', { name: 'Start with an empty system' }).click();
  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeHidden({ timeout: 120000 });
  await page.screenshot({ path: testInfo.outputPath('01-dashboard.png'), fullPage: true });

  // Sidebar → Webhooks → Endpoints
  await page.getByText('Webhooks', { exact: true }).first().click();
  await page.getByRole('link', { name: 'Endpoints' }).first().click();
  await expect(page.getByText('No webhooks yet.')).toBeVisible({ timeout: 30000 });
  await page.screenshot({ path: testInfo.outputPath('02-webhooks-empty.png'), fullPage: true });

  // New webhook (timestamped signature by default)
  await page.getByRole('button', { name: /New Webhook/ }).first().click();
  const dlg = page.getByRole('dialog');
  await expect(dlg).toBeVisible();
  await dlg.getByLabel('Name').fill('ERP progress feed');
  await dlg.getByLabel('Endpoint URL').fill('https://httpbin.org/post');
  for (const ev of ['Work order created', 'Work order status changed', 'Work order progress', 'Quality issue created', 'Stock document posted']) {
    await expect(dlg.getByRole('button', { name: ev, exact: true })).toBeVisible();
  }
  await dlg.getByRole('button', { name: 'Work order progress', exact: true }).click();
  await dlg.getByRole('button', { name: 'Stock document posted', exact: true }).click();
  await expect(dlg.getByRole('button', { name: 'Stock document posted', exact: true })).toHaveAttribute('aria-pressed', 'true');
  const legacy = dlg.getByRole('checkbox', { name: 'Legacy signature' });
  await legacy.scrollIntoViewIfNeeded();
  await expect(legacy).not.toBeChecked();
  await expect(dlg.getByText(/Sign the request body only, without the timestamp/)).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('03-new-webhook-form.png'), fullPage: true });
  await dlg.getByRole('button', { name: /^(Save|Create)/ }).click();
  await expect(page.getByText('Webhook created successfully.')).toBeVisible({ timeout: 30000 });
  // No live websocket on this instance: refresh so the list refetches.
  await page.reload();
  await expect(page.getByText('ERP progress feed')).toBeVisible({ timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('04-created.png'), fullPage: true });

  // Second webhook that keeps the legacy signature
  await page.getByRole('button', { name: /New Webhook/ }).first().click();
  const dlg2 = page.getByRole('dialog');
  await expect(dlg2).toBeVisible();
  await dlg2.getByLabel('Name').fill('Old integration');
  await dlg2.getByLabel('Endpoint URL').fill('https://postman-echo.com/post');
  await dlg2.getByRole('button', { name: 'Work order status changed', exact: true }).click();
  await dlg2.getByRole('checkbox', { name: 'Legacy signature' }).click();
  await expect(dlg2.getByRole('checkbox', { name: 'Legacy signature' })).toBeChecked();
  await page.screenshot({ path: testInfo.outputPath('05-legacy-form.png'), fullPage: true });
  await dlg2.getByRole('button', { name: /^(Save|Create)/ }).click();
  await expect(page.getByText('Webhook created successfully.')).toBeVisible({ timeout: 30000 });
  await page.reload();
  await expect(page.getByRole('row', { name: /Old integration/ })).toBeVisible({ timeout: 30000 });

  // Edit the legacy one: checkbox persisted
  const legacyRow = page.getByRole('row', { name: /Old integration/ });
  await legacyRow.getByRole('button', { name: /Edit/ }).click();
  const dlg3 = page.getByRole('dialog');
  await expect(dlg3.getByRole('checkbox', { name: 'Legacy signature' })).toBeChecked();
  await dlg3.getByRole('checkbox', { name: 'Legacy signature' }).scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-edit-legacy.png'), fullPage: true });
  await page.keyboard.press('Escape');

  // Send test on the v1 webhook
  const v1Row = page.getByRole('row', { name: /ERP progress feed/ });
  await v1Row.getByRole('button', { name: 'Send test' }).click();
  await expect(page.getByText('Deliveries for ERP progress feed').last()).toBeVisible({ timeout: 30000 });
  await expect(page.getByText('Test delivery queued.')).toBeVisible();
  await expect(page.getByText('Signed at (X-OpenMES-Timestamp)')).toBeVisible();
  await expect(page.getByText('webhook.test').first()).toBeVisible({ timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('07-deliveries-v1.png'), fullPage: true });
  await expect(page.getByRole('cell', { name: 'v1', exact: true })).toBeVisible({ timeout: 60000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('08-deliveries-v1-signed.png'), fullPage: true });

  // Same for legacy webhook
  await page.getByRole('link', { name: '‹ Webhooks' }).click();
  await expect(page.getByText('Old integration')).toBeVisible({ timeout: 30000 });
  const lRow = page.getByRole('row', { name: /Old integration/ });
  await lRow.getByRole('button', { name: 'Send test' }).click();
  await expect(page.getByText('Deliveries for Old integration').last()).toBeVisible({ timeout: 30000 });
  await expect(page.getByRole('cell', { name: /^legacy$/i })).toBeVisible({ timeout: 60000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('09-deliveries-legacy.png'), fullPage: true });
});
