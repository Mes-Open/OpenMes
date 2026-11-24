import { expect, test, type Browser, type Page } from '@playwright/test';
import { ADMIN, PASS, choose, login } from './support/app';

/**
 * Several plants in one installation (OpenMES Enterprise, multi-site).
 *
 * Needs the Enterprise module, licensed, with two active sites that each have an
 * area and a line, plus one line in no site — and existing orders on them:
 *
 *   E2E_SITE_A="Gdansk Plant"  E2E_SITE_A_AREA="Assembly"  E2E_SITE_A_LINE=GDA-L1  E2E_SITE_A_ORDER=WO-GDA-001
 *   E2E_SITE_B="Krakow Plant"  E2E_SITE_B_AREA="Packing"   E2E_SITE_B_LINE=KRK-L1  E2E_SITE_B_ORDER=WO-KRK-001
 *   E2E_SHARED_LINE=SHR-L1     E2E_SHARED_ORDER=WO-POOL-001
 *
 * Every write goes through the UI. The run covers:
 *   - a line placed in a site from the line form;
 *   - a supervisor confined to one site from the user form;
 *   - the header switcher narrowing what an admin sees;
 *   - head-office comparison;
 *   - a per-site setting;
 *   - live sync reaching only the right site;
 *   - an ERP key bound to a site;
 *   - the attempts to break each of these.
 */

const A = process.env.E2E_SITE_A || 'Gdansk Plant';
const A_AREA = process.env.E2E_SITE_A_AREA || 'Assembly';
const A_LINE = process.env.E2E_SITE_A_LINE || 'GDA-L1';
const A_ORDER = process.env.E2E_SITE_A_ORDER || 'WO-GDA-001';
const B = process.env.E2E_SITE_B || 'Krakow Plant';
const B_AREA = process.env.E2E_SITE_B_AREA || 'Packing';
const B_LINE = process.env.E2E_SITE_B_LINE || 'KRK-L1';
const B_ORDER = process.env.E2E_SITE_B_ORDER || 'WO-KRK-001';
const SHARED_LINE = process.env.E2E_SHARED_LINE || 'SHR-L1';
const SHARED_ORDER = process.env.E2E_SHARED_ORDER || 'WO-POOL-001';

const TS = Date.now().toString().slice(-6);
const NEW_LINE = `MS-${TS}`;
const SUPERVISOR = `ms-sup-${TS}`;
const SUPERVISOR_PASS = 'E2e-Supervisor-2026!';

test.describe.configure({ mode: 'serial' });

/** Rows of a synced collection, read as the signed-in user (reads only). */
async function rows(page: Page, collection: string): Promise<{ rows: Record<string, unknown>[]; channel: string }> {
  return page.evaluate(async (name) => {
    const r = await fetch(`/api/collections/${name}`, { headers: { Accept: 'application/json' } });
    // An unknown collection (the module is not installed) reads as empty.
    return r.ok ? r.json() : { rows: [], channel: '' };
  }, collection);
}

async function siteSwitcher(page: Page) {
  // The header renders it twice (mobile bar and desktop bar); one is visible.
  return page.locator('[data-testid="scope-switcher"]:visible').first();
}

async function switchSite(page: Page, label: string) {
  const select = await siteSwitcher(page);
  // A plain form POST: the page reloads into the chosen site.
  await Promise.all([page.waitForNavigation(), select.selectOption({ label })]);
}

async function asSupervisor(browser: Browser): Promise<Page> {
  const context = await browser.newContext();
  const page = await context.newPage();
  await login(page, SUPERVISOR, SUPERVISOR_PASS);
  return page;
}

/**
 * The other half of the promise: without the module (or without its licence)
 * core looks and behaves exactly as a single-plant installation.
 */
test('without multi-site, core stays a single plant', async ({ page }) => {
  await login(page, ADMIN, PASS);
  const sites = await page.evaluate(async () => (await fetch('/api/collections/sites', { headers: { Accept: 'application/json' } })).status);
  test.skip(sites === 200, 'The Enterprise module is installed; the multi-site flow below covers that case.');

  await test.step('no site switcher, every line listed on the unpartitioned channel', async () => {
    await page.goto('/admin/lines');
    await expect(page.locator('[data-testid="scope-switcher"]')).toHaveCount(0);
    const { rows: lines, channel } = await rows(page, 'lines_all');
    expect(channel).toMatch(/^col\.[^@]+\.lines_all$/);
    // Lines of every plant (and none) side by side, as before multi-site existed.
    for (const code of [A_LINE, B_LINE, SHARED_LINE].filter((c) => lines.some((l) => l.code === c))) {
      await expect(page.getByRole('cell', { name: code, exact: true })).toBeVisible();
    }
  });

  await test.step('the line, user and ERP key forms carry no module fields', async () => {
    await page.getByRole('button', { name: 'New Line' }).click();
    const drawer = page.getByRole('dialog', { name: 'New Production Line' });
    await expect(drawer.getByLabel('Code')).toBeVisible();
    await expect(drawer.getByText('Site / area')).toHaveCount(0);

    await page.goto('/admin/users/create');
    const dialog = page.getByRole('dialog', { name: 'New Account' });
    await expect(dialog.getByRole('textbox', { name: 'Username' })).toBeVisible();
    await expect(dialog.getByRole('group', { name: 'Sites' })).toHaveCount(0);
    await expect(dialog.getByText('Additional fields')).toHaveCount(0);

    await page.goto('/settings/api-tokens');
    await page.getByRole('button', { name: 'Create key' }).click();
    const keyForm = page.locator('form').filter({ has: page.getByLabel('Key name') });
    await expect(keyForm.getByRole('combobox', { name: 'Site', exact: true })).toHaveCount(0);
  });

  await test.step('multi-site screens and the site switch endpoint do not exist', async () => {
    for (const url of ['/admin/site-comparison', '/admin/linked-instances']) {
      const response = await page.goto(url);
      expect(response?.status(), url).toBe(404);
    }
  });
});

test('multi-site: one installation, several plants', async ({ page, browser }) => {
  test.setTimeout(240_000);
  await login(page, ADMIN, PASS);

  const sites = (await rows(page, 'sites')).rows as { id: number; name: string }[];
  test.skip(sites.length < 2, 'Needs the Enterprise module with two active sites.');
  const siteA = sites.find((s) => s.name === A)!;

  await test.step('the admin sees every site and can pick one in the header', async () => {
    await page.goto('/admin/lines');
    const select = await siteSwitcher(page);
    await expect(select).toBeVisible();
    await expect(select.locator('option')).toHaveText(['All sites', A, B]);
    await expect(page.getByRole('cell', { name: A_LINE, exact: true })).toBeVisible();
    await expect(page.getByRole('cell', { name: B_LINE, exact: true })).toBeVisible();
  });

  await test.step('a new line is placed in a site from the line form', async () => {
    await page.getByRole('button', { name: 'New Line' }).click();
    const drawer = page.getByRole('dialog', { name: 'New Production Line' });
    await drawer.getByLabel('Code').fill(NEW_LINE);
    await drawer.getByLabel('Name').fill(`Multi-site line ${TS}`);
    await choose(drawer, 'Site / area', `${B} / ${B_AREA}`);
    await drawer.getByRole('button', { name: 'Create' }).click();
    await expect(page.getByRole('cell', { name: NEW_LINE, exact: true })).toBeVisible();
    const line = (await rows(page, 'lines_all')).rows.find((l) => l.code === NEW_LINE);
    expect(line?.area_id).not.toBeNull();
  });

  await test.step('switching to one site narrows every list to it and what is shared', async () => {
    await switchSite(page, B);
    await expect(await siteSwitcher(page)).toHaveValue(/\d+/);
    await expect(page.getByRole('cell', { name: B_LINE, exact: true })).toBeVisible();
    await expect(page.getByRole('cell', { name: NEW_LINE, exact: true })).toBeVisible();
    await expect(page.getByRole('cell', { name: SHARED_LINE, exact: true })).toBeVisible();
    await expect(page.getByRole('cell', { name: A_LINE, exact: true })).toHaveCount(0);

    const { channel } = await rows(page, 'lines_all');
    expect(channel).toMatch(/@s\d+\.lines_all$/);

    await switchSite(page, 'All sites');
    await expect(page.getByRole('cell', { name: A_LINE, exact: true })).toBeVisible();
  });

  await test.step('a supervisor is confined to one site from the user form', async () => {
    await page.goto('/admin/users/create');
    const dialog = page.getByRole('dialog', { name: 'New Account' });
    await dialog.getByRole('textbox', { name: 'Name', exact: true }).fill(`Site Supervisor ${TS}`);
    await dialog.getByRole('textbox', { name: 'Username' }).fill(SUPERVISOR);
    await dialog.getByRole('textbox', { name: 'Email' }).fill(`${SUPERVISOR}@example.test`);
    await dialog.getByRole('textbox', { name: 'Password', exact: true }).fill(SUPERVISOR_PASS);
    await dialog.getByRole('textbox', { name: 'Confirm Password' }).fill(SUPERVISOR_PASS);
    const forceChange = dialog.getByRole('checkbox', { name: 'Require password change at next login' });
    if (await forceChange.isChecked()) await forceChange.click();
    await choose(dialog, 'Role', 'Supervisor');
    await dialog.getByRole('group', { name: 'Sites' }).getByRole('checkbox', { name: A }).click();
    await dialog.getByRole('button', { name: 'Create Account' }).click();
    await expect(dialog).toBeHidden();
  });

  await test.step('head office compares the plants side by side', async () => {
    await page.goto('/admin/site-comparison');
    const table = page.getByRole('table').first();
    await expect(table.getByRole('row', { name: new RegExp(A) })).toBeVisible();
    await expect(table.getByRole('row', { name: new RegExp(B) })).toBeVisible();
    await expect(table.getByRole('row', { name: /Not in a site/ })).toBeVisible();
  });

  await test.step('a site customizes a target; out-of-range values are refused', async () => {
    await page.goto(`/admin/sites/${siteA.id}/settings`);
    const scrap = page.locator('[data-setting="target_max_scrap_rate_pct"]');
    const customize = scrap.getByRole('switch', { name: /Customize for this site/ });
    if ((await customize.getAttribute('aria-checked')) !== 'true') await customize.click();
    await scrap.getByRole('spinbutton').fill('150');
    await page.getByRole('button', { name: 'Save' }).click();
    // The browser holds the form back (max=100) …
    expect(await scrap.getByRole('spinbutton').evaluate((el: HTMLInputElement) => el.validity.rangeOverflow)).toBe(true);
    // … and the server refuses it on its own when the browser is bypassed.
    const status = await page.evaluate(async (id) => {
      const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
      const r = await fetch(`/admin/sites/${id}/settings`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token, Accept: 'application/json' },
        body: JSON.stringify({ customize: { target_max_scrap_rate_pct: true }, values: { target_max_scrap_rate_pct: 150 } }),
      });
      return r.status;
    }, siteA.id);
    expect(status).toBe(422);

    await scrap.getByRole('spinbutton').fill('5.5');
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(scrap.getByText('Customized')).toBeVisible();
    await page.reload();
    await expect(page.locator('[data-setting="target_max_scrap_rate_pct"]').getByRole('spinbutton')).toHaveValue('5.5');
  });

  const orders = (await rows(page, 'work_orders_all')).rows as { id: number; order_no: string }[];
  const orderB = orders.find((o) => o.order_no === B_ORDER)!;

  const sup = await asSupervisor(browser);
  const subscribed: string[] = [];
  sup.on('websocket', (ws) => ws.on('framesent', (f) => {
    const m = String(f.payload).match(/"channel":"([^"]+)"/);
    if (m && String(f.payload).includes('pusher:subscribe')) subscribed.push(m[1]);
  }));

  await test.step('the supervisor sees only their site — no switcher, other plants hidden', async () => {
    await sup.goto('/supervisor/work-orders');
    await expect(sup.getByRole('cell', { name: A_ORDER, exact: true })).toBeVisible();
    await expect(sup.getByRole('cell', { name: SHARED_ORDER, exact: true })).toBeVisible();
    await expect(sup.getByRole('cell', { name: B_ORDER, exact: true })).toHaveCount(0);
    await expect(sup.getByTestId('scope-switcher')).toHaveCount(0);
    await expect.poll(() => subscribed.some((c) => c.includes(`@s${siteA.id}.work_orders_all`))).toBe(true);
  });

  await test.step('another site\'s order cannot be opened by URL, nor its site picked', async () => {
    const response = await sup.goto(`/supervisor/work-orders/${orderB.id}`);
    expect(response?.status()).toBe(404);

    const refused = await sup.evaluate(async () => {
      const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
      const r = await fetch('/enterprise/site-context', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-XSRF-TOKEN': token, Accept: 'application/json' },
        body: 'scope=all',
      });
      return r.status;
    });
    expect(refused).toBe(422);

    // Subscribing to every plant's channel is refused by the broadcaster auth.
    const auth = await sup.evaluate(async () => {
      const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
      const r = await fetch('/broadcasting/auth', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token, Accept: 'application/json' },
        body: JSON.stringify({ socket_id: '1234.5678', channel_name: 'private-col.1.work_orders_all' }),
      });
      return r.status;
    });
    expect(auth).toBe(403);
  });

  await test.step('a change in their site reaches the supervisor live; another site\'s never does', async () => {
    // An in-progress order of site A, paused or resumed from the admin's screen
    // (whichever applies, so the step can run again on the same data).
    const running = ((await rows(page, 'work_orders_all')).rows as { id: number; order_no: string; status: string; line_id: number }[])
      .find((o) => o.order_no !== A_ORDER && o.order_no.startsWith(A_ORDER.replace(/\d+$/, '')) && ['IN_PROGRESS', 'PAUSED'].includes(o.status))!;
    expect(running, 'site A needs an in-progress or paused order').toBeTruthy();
    const [action, after] = running.status === 'IN_PROGRESS' ? ['Pause', /Paused/i] : ['Resume', /In progress/i];

    await sup.goto('/supervisor/work-orders');
    const row = sup.getByRole('row', { name: new RegExp(running.order_no) });
    await expect(row).toBeVisible();

    await page.goto(`/admin/work-orders/${running.id}`);
    await page.getByRole('button', { name: action, exact: true }).first().click();
    const confirm = page.getByRole('dialog').getByRole('button', { name: new RegExp(action) });
    if (await confirm.count()) await confirm.first().click();

    // No reload on the supervisor's side: the delta arrives on their site's channel.
    await expect(row.getByText(after)).toBeVisible({ timeout: 15_000 });
    await expect(sup.getByRole('cell', { name: B_ORDER, exact: true })).toHaveCount(0);
  });

  await test.step('an ERP key bound to a site reads that site only', async () => {
    await page.goto('/settings/api-tokens');
    await page.getByRole('button', { name: 'Create key' }).click();
    const keyForm = page.locator('form').filter({ has: page.getByLabel('Key name') });
    await keyForm.getByLabel('Key name').fill(`Plant ERP ${TS}`);
    await keyForm.locator('[data-scope="erp:masterdata:read"]').click();
    await keyForm.locator('[data-scope="erp:production:read"]').click();
    // Not choose(): the header's site switcher is a native <select> whose
    // options share these names, so pick from the open listbox itself.
    await keyForm.getByRole('combobox', { name: 'Site', exact: true }).click();
    await page.getByRole('listbox').getByRole('option', { name: new RegExp(`^${A}`) }).first().click();
    await keyForm.getByRole('button', { name: 'Create', exact: true }).click();
    const key = (await page.locator('code.select-all').innerText()).trim();
    await expect(page.getByText(`Site: ${A}`).first()).toBeVisible();

    const lines = await page.request.get('/api/v1/erp/lines', { headers: { 'X-Api-Key': key, Accept: 'application/json' } });
    expect(lines.status()).toBe(200);
    const codes = ((await lines.json()).data as { code: string }[]).map((l) => l.code);
    expect(codes).toContain(A_LINE);
    expect(codes).toContain(SHARED_LINE);
    expect(codes).not.toContain(B_LINE);

    const summary = await page.request.get('/api/v1/erp/sites/summary', { headers: { 'X-Api-Key': key, Accept: 'application/json' } });
    expect(((await summary.json()).sites as { name: string }[]).map((s) => s.name)).toEqual([A]);
  });

  await test.step('a link to a loopback address is refused like an unsafe webhook', async () => {
    await page.goto('/admin/linked-instances');
    await page.getByRole('button', { name: 'Link an installation' }).click();
    const form = page.getByRole('dialog', { name: 'Link an installation' });
    await form.getByLabel('Name').fill(`Loop ${TS}`);
    await form.getByLabel('Address').fill('http://127.0.0.1:8091');
    await form.getByLabel('API key').fill('anything');
    await form.getByRole('button', { name: 'Create' }).click();
    // The field's own error (ResourceForm renders it as an alert), and nothing saved.
    await expect(form.getByRole('alert').first()).toBeVisible();
    await expect(form.getByRole('alert').first()).not.toHaveText('');
    await form.getByRole('button', { name: 'Cancel' }).click();
    await expect(page.getByRole('cell', { name: `Loop ${TS}`, exact: true })).toHaveCount(0);
  });

  await sup.context().close();
});
