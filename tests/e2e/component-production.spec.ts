import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const backend = `${process.env.OPENMES_NAME_PREFIX || 'openmmes'}-backend`;
const orderNo = `WO-SOFA-DEMO-${Date.now()}`;

test('preview sofa components, create eight parts, and complete assembly', async ({ page }) => {
  test.setTimeout(120_000);
  page.setDefaultTimeout(15_000);
  execFileSync('docker', ['exec', backend, 'php', 'artisan', 'db:seed', '--class=FurnitureComponentDemoSeeder', '--force'], { stdio: 'pipe' });
  await page.goto('/login');
  await page.fill('input[name="username"]', process.env.ADMIN_USERNAME || 'admin');
  await page.fill('input[name="password"]', process.env.ADMIN_PASSWORD || 'Admin1234!');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await page.waitForURL((url) => !url.pathname.startsWith('/login'));
  await page.setViewportSize({ width: 1450, height: 1000 });
  await page.goto('/admin/work-orders/create');
  await page.locator('input[name=order_no]').fill(orderNo);
  await page.locator('input[name=customer_order_no]').fill('SO1435-DEMO');
  await page.getByRole('combobox', { name: 'Product Type', exact: true }).click();
  await page.getByRole('option', { name: /Sofa 2 Seater — BOM demo/ }).click();
  await page.getByRole('combobox', { name: 'Line', exact: true }).click();
  await page.getByRole('option', { name: /Component production demo/ }).click();
  await page.locator('input[name=planned_qty]').fill('2');
  await expect(page.getByRole('checkbox', { name: 'Generate component work orders', exact: true })).toBeChecked();
  await expect(page.getByRole('checkbox', { name: 'Assembly v1', exact: true })).toBeChecked();
  await expect(page.getByRole('button', { name: 'Hide component preview', exact: true })).toBeVisible();
  await expect(page.getByText('Component jobs: 1', { exact: true })).toBeVisible();
  await expect(page.getByText('Required: 8 · Available: 0 · From stock: 0 · To produce: 8 pcs', { exact: true })).toBeVisible();
  await page.screenshot({ path: 'test-results/component-preview.png', fullPage: true });
  await page.getByRole('button', { name: 'Create', exact: true }).click();
  await page.waitForURL(/\/admin\/work-orders$/);

  const id = execFileSync('docker', ['exec', backend, 'php', 'artisan', 'tinker', '--execute',
    `echo App\\Models\\WorkOrder::where('order_no', '${orderNo}')->value('id');`], { encoding: 'utf8' }).trim();
  expect(Number(id)).toBeGreaterThan(0);
  await page.goto(`/admin/work-orders/${id}`);
  await expect(page.getByRole('heading', { name: 'Component production', exact: true })).toBeVisible();
  await expect(page.getByText('VB 18/40 · 1,985 mm · 100 mm · 10 mm', { exact: true })).toBeVisible();
  const component = page.locator('tr').filter({ hasText: 'DEMO-FOAM-PART' });
  await expect(component).toContainText('8 pcs');
  const childHref = await component.getByRole('link').getAttribute('href');
  const childId = Number(childHref!.split('/').pop());
  await page.getByRole('button', { name: 'Accept', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Accept', exact: true })).toHaveCount(0);

  // Batch setup uses the same authenticated endpoints as mobile/operator clients.
  const post = async (url: string, data = {}) => page.evaluate(async ({ url, data }) => {
    const response = await fetch(url, { method: 'POST', headers: {
      'Content-Type': 'application/json', Accept: 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
      'X-Requested-With': 'XMLHttpRequest',
    }, body: JSON.stringify(data) });
    return { status: response.status, body: await response.json() };
  }, { url, data });
  const assemblyBatch = await post(`/api/v1/work-orders/${id}/batches`, { target_qty: 2 });
  expect(assemblyBatch.status).toBe(201);
  const componentBatch = await post(`/api/v1/work-orders/${childId}/batches`, { target_qty: 8 });
  expect(componentBatch.status).toBe(201);
  const assemblyStepId = assemblyBatch.body.data.steps[0].id;
  const blocked = await post(`/api/v1/batch-steps/${assemblyStepId}/start`);
  expect(blocked.status).toBe(422);

  const batchesSection = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Batches', exact: true }) });
  await page.goto(childHref!);
  await page.getByRole('button', { name: 'Accept', exact: true }).click();
  await batchesSection.getByRole('button', { name: 'Start', exact: true }).click();
  await expect(batchesSection.getByRole('button', { name: 'Complete', exact: true })).toHaveCount(1);
  await batchesSection.getByRole('button', { name: 'Complete', exact: true }).click();
  await expect(page.getByText('Ready for assembly', { exact: true })).toBeVisible();
  await page.goto(`/admin/work-orders/${id}`);
  await expect(page.getByText('Ready for assembly', { exact: true })).toBeVisible();
  await batchesSection.getByRole('button', { name: 'Start', exact: true }).click();
  await batchesSection.getByRole('button', { name: 'Complete', exact: true }).last().click();
  await expect.poll(async () => page.evaluate(async (id) => { const r = await fetch(`/api/v1/work-orders/${id}`, { headers: { Accept: 'application/json' } }); return Number((await r.json()).data.produced_qty); }, id)).toBe(2);
  await page.goto(`/admin/work-orders/${id}`);
  await expect(page.getByRole('heading', { name: 'Component production', exact: true })).toBeVisible();
  await page.screenshot({ path: 'test-results/component-production-complete.png', fullPage: true });
  console.log(`Sofa demo: /admin/work-orders/${id}; component: ${childHref}`);
});
