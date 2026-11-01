import { test, expect, Browser, Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';

/**
 * Operator console: what happens to scrap, short batches, packing and units
 * when somebody tries to get past the rules — through the screen and by a
 * hand-made request. One generic order (4 widgets: Assemble → Pack into
 * cartons of 5) is walked through in order, each test building on the last:
 *
 *   batch      capped at what the order still needs; none on a cancelled order
 *   packing    refused until the packing step has a piece waiting, then counted
 *   ledger     whole pieces only, never more than waiting, scrap leaves the flow,
 *              a double-click writes once
 *   units      scrapping a packed unit takes it out of its carton; a blocked unit
 *              is not packed and only a supervisor releases it; an operator of
 *              another line can do nothing to it
 *   edits      admin cannot strand the running order
 *
 * Data comes from seed.php (generic names, unique per run, soft-deleted at the
 * end unless E2E_KEEP=1). The spec needs the transfer production flow and
 * "Allow overproduction" off, and reads English UI text; it skips when the
 * instance is set up otherwise rather than changing global settings.
 *
 *   npx playwright test tests/e2e/operator-console
 *
 * A run logs in four accounts. The login form takes 10 attempts a minute per
 * address, so leave a minute between back-to-back runs.
 */
const CONTAINER = `${process.env.OPENMES_NAME_PREFIX || 'openmmes'}-backend`;
const RUN = Date.now().toString().slice(-7);

type Seed = {
  tag: string; password: string; line: number; otherLine: number; main: number; mainNo: string; cancelled: number;
  units: number[]; psns: string[]; reason: number;
  settings: { flow: string; overproduction: boolean; language: string };
};

function php(script: string, env: Record<string, string> = {}): string {
  const flags = Object.entries({ RUN, ...env }).map(([k, v]) => `-e ${k}=${v}`).join(' ');
  return execSync(`docker exec -i ${flags} ${CONTAINER} php`, { input: script, encoding: 'utf8' }).trim().split('\n').pop()!;
}

const SEED = readFileSync(path.join(__dirname, 'seed.php'), 'utf8');

/** One value out of the running app (ids and counters the UI does not put in a URL). */
function query<T = any>(expr: string): T {
  return JSON.parse(php(`<?php require '/var/www/html/vendor/autoload.php'; $app = require '/var/www/html/bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo json_encode(${expr}), "\\n";`));
}

let seed: Seed;

// One login per account for the whole run: the login form is rate-limited, and
// a fresh login per test trips it by the sixth test.
const pages = new Map<string, Page>();

async function session(browser: Browser, username: string, line?: number): Promise<Page> {
  let page = pages.get(username);
  if (!page) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    page = await ctx.newPage();
    await page.goto('/login');
    await page.fill('input[name="username"]', username);
    await page.fill('input[name="password"]', seed.password);
    // `commit`: an operator with no line lands on a page that never settles to `load`.
    await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login'), { waitUntil: 'commit' }), page.click('button[type="submit"]')]);
    // Let the landing page's requests finish: one still in flight writes the
    // session back late and drops the line the next page selects.
    await page.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => {});
    pages.set(username, page);
  }
  if (line) {
    await page.goto(`/operator/queue?line=${line}&workstation=all`);
    await page.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => {});
  }
  return page;
}

/** A request made by hand from the logged-in page — what the guards must hold against. */
async function send(page: Page, method: string, url: string, body: object = {}) {
  return page.evaluate(async ([method, url, body]) => {
    const token = document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content ?? '';
    const res = await fetch(url as string, {
      method: method as string,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body),
      redirect: 'manual', // keep a redirect's flash for the next page load
    });
    const text = await res.text();
    let json: any = null;
    try { json = JSON.parse(text); } catch { /* redirect or HTML */ }
    return { status: res.status, json };
  }, [method, url, body] as const);
}

/**
 * A form post the way Inertia sends it, following the redirect: the answer is
 * the page it lands on, flash message included. Reading the flash from this
 * response rather than from the next page load keeps a background request
 * from consuming it first.
 */
async function sendForm(page: Page, url: string, body: object): Promise<{ success?: string; error?: string }> {
  return page.evaluate(async ([url, body]) => {
    const token = document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content ?? '';
    // Inertia v3 keeps the page in <script data-page>; older versions in #app[data-page].
    const raw = document.querySelector('script[data-page]')?.textContent || document.querySelector<HTMLElement>('#app')?.dataset.page || '{}';
    const version = JSON.parse(raw).version ?? '';
    const res = await fetch(url as string, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'text/html, application/xhtml+xml', 'X-CSRF-TOKEN': token, 'X-Inertia': 'true', 'X-Inertia-Version': version, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body),
    });
    const text = await res.text();
    try { return JSON.parse(text).props?.flash ?? {}; } catch { return { error: `HTTP ${res.status}: ${text.slice(0, 120)}` }; }
  }, [url, body] as const);
}

const card = (page: Page) => page.getByTestId('step-card').first();
const ledger = async (page: Page) => {
  const t = await card(page).innerText();
  const m = t.match(/INCOMING\s+([\d.]+)[\s\S]*?WAITING\s+([\d.]+)[\s\S]*?PASSED\s+([\d.]+)[\s\S]*?SCRAP\s+([\d.]+)/i);
  return m ? { incoming: +m[1], waiting: +m[2], passed: +m[3], scrap: +m[4] } : null;
};
const stepId = (n: number): number => query(`App\\Models\\BatchStep::whereHas('batch', fn ($q) => $q->where('work_order_id', ${seed.main}))->where('step_number', ${n})->value('id')`);

test.describe.configure({ mode: 'serial' });
test.setTimeout(120_000);
// A navigation that never settles fails in 30 s with its URL, not at the test's timeout.
test.use({ navigationTimeout: 30_000, actionTimeout: 15_000 });

test.beforeAll(() => {
  seed = JSON.parse(php(SEED));
});

test.afterAll(async () => {
  for (const page of pages.values()) await page.context().close();
  if (seed && !process.env.E2E_KEEP) php(SEED, { CLEANUP: '1' });
});

test.beforeEach(() => {
  test.skip(seed.settings.flow !== 'transfer', 'needs Settings → Production → flow "transfer"');
  test.skip(seed.settings.overproduction, 'needs "Allow overproduction" off');
  test.skip(seed.settings.language !== 'en', 'reads English UI text');
});

test('a batch is capped at what the order still needs, and a closed order takes none', async ({ browser }) => {
  const op = await session(browser, 'e2e-guards-op', seed.line);

  // Over the plan by hand: refused with the reason, nothing created.
  await op.goto(`/operator/work-order/${seed.main}`);
  expect((await sendForm(op, '/operator/batch', { work_order_id: seed.main, target_qty: 50 })).error)
    .toContain('Batch quantity 50 exceeds what the order still needs (4).');
  // Half a widget is not a batch either.
  expect((await sendForm(op, '/operator/batch', { work_order_id: seed.main, target_qty: 2.5 })).error).toContain('Enter whole pieces');

  // A cancelled order takes no batch, whatever the quantity.
  expect((await sendForm(op, '/operator/batch', { work_order_id: seed.cancelled, target_qty: 1 })).error).toContain('no new batches can be created');
  expect(query(`App\\Models\\Batch::whereIn('work_order_id', [${seed.main}, ${seed.cancelled}])->count()`)).toBe(0);

  // The dialog prefills and caps with the same figure; once covered, no button.
  await op.goto(`/operator/work-order/${seed.main}`);
  await op.getByTestId('wo-hero').getByRole('button', { name: 'Create batch' }).click();
  await expect(op.getByText('Remaining: 4', { exact: false })).toBeVisible();
  await expect(op.getByLabel('Quantity')).toHaveValue('4');
  await op.locator('form').getByRole('button', { name: /^Create Batch$/i }).click();
  await expect(op.getByText('Batch created', { exact: false })).toBeVisible();
  await expect(op.getByTestId('wo-hero').getByRole('button', { name: 'Create batch' })).toHaveCount(0);
});

test('packing refuses a unit until a piece has reached the packing step', async ({ browser }) => {
  const op = await session(browser, 'e2e-guards-op', seed.line);
  await op.goto('/operator/packaging');
  const scan = op.getByPlaceholder('Scan the PSN or the serial number…');
  await scan.fill(seed.psns[0]);
  await scan.press('Enter');
  await expect(op.getByText(/cannot be packed yet: nothing has reached packing step Pack/)).toBeVisible();
  expect(query(`App\\Models\\SerialUnit::find(${seed.units[0]})->packed_at`)).toBeNull();
});

test('the step ledger takes whole pieces, never more than waiting, and writes a double-click once', async ({ browser }) => {
  const op = await session(browser, 'e2e-guards-op', seed.line);
  await op.goto(`/operator/work-order/${seed.main}?workstation=all`);
  await card(op).getByRole('button', { name: 'Start', exact: true }).click();
  await expect.poll(() => ledger(op)).toMatchObject({ incoming: 4, waiting: 4 });
  const assemble = stepId(1);
  const record = card(op).locator('form button[type=submit]');

  // Half a piece and more than waiting: the button says why, the server refuses.
  await card(op).getByLabel('Good', { exact: true }).fill('1.5');
  await expect(record).toHaveText('Whole pieces only');
  await expect(record).toBeDisabled();
  await card(op).getByLabel('Good', { exact: true }).fill('5');
  await expect(record).toHaveText('More than waiting (4)');
  // Form-rule refusals answer 422; the ledger's own ("only 4 waiting") goes back
  // to the page as the field error (a redirect). Either way nothing is written.
  for (const body of [{ good_qty: 1.5 }, { good_qty: 5 }, { good_qty: -1 }, { scrap_qty: 0.5 }]) {
    const res = await send(op, 'POST', `/operator/batch-step/${assemble}/quantity`, body);
    expect([422, 0], JSON.stringify(body)).toContain(res.status);
    expect(query(`App\\Models\\BatchStep::find(${assemble})->only(['passed_qty', 'scrap_qty'])`), JSON.stringify(body)).toEqual({ passed_qty: '0.00', scrap_qty: '0.00' });
  }

  // One scrapped widget leaves the flow: waiting drops, the order counts it.
  await card(op).getByLabel('Good', { exact: true }).fill('');
  await card(op).getByLabel('Scrap', { exact: true }).fill('1');
  await record.click();
  await expect.poll(() => ledger(op)).toMatchObject({ waiting: 3, passed: 0, scrap: 1 });
  expect(query(`(float) App\\Models\\ScrapEntry::where('work_order_id', ${seed.main})->sum('quantity')`)).toBe(1);

  // A nervous double-click on Record writes one piece.
  await card(op).getByLabel('Good', { exact: true }).fill('1');
  await record.dblclick();
  await expect.poll(() => ledger(op)).toMatchObject({ waiting: 2, passed: 1 });
  expect(query(`(float) App\\Models\\BatchStep::find(${assemble})->passed_qty`)).toBe(1);
});

test('packing counts the piece on the packing step and refuses the next one with nothing waiting', async ({ browser }) => {
  const op = await session(browser, 'e2e-guards-op', seed.line);
  await op.goto('/operator/packaging');
  const scan = op.getByPlaceholder('Scan the PSN or the serial number…');

  await scan.fill(seed.psns[0]);
  await scan.press('Enter');
  await expect.poll(() => query(`App\\Models\\SerialUnit::find(${seed.units[0]})->status`)).toBe('completed');
  expect(query(`(float) App\\Models\\BatchStep::find(${stepId(2)})->passed_qty`)).toBe(1);
  expect(query(`App\\Models\\SerialUnit::find(${seed.units[0]})->carton?->qty`)).toBe(1);

  // Only one widget has passed assembly: a second one cannot be packed beside the count.
  await scan.fill(seed.psns[1]);
  await scan.press('Enter');
  await expect(op.getByText(/was not packed — packing step Pack/)).toBeVisible();
  expect(query(`App\\Models\\SerialUnit::find(${seed.units[1]})->packed_at`)).toBeNull();
});

test('scrapping a packed unit takes it out of its carton count', async ({ browser }) => {
  const op = await session(browser, 'e2e-guards-op', seed.line);
  const res = await send(op, 'POST', `/operator/unit-labels/${seed.units[0]}/scrap`, { reason: 'Cracked housing' });
  expect(res.status).toBe(200);

  const unit = query(`App\\Models\\SerialUnit::with('carton')->find(${seed.units[0]})->only(['status', 'carton_id']) + ['qty' => App\\Models\\SerialUnit::find(${seed.units[0]})->carton?->qty]`);
  expect(unit).toMatchObject({ status: 'scrapped', qty: 0 });
  expect(unit.carton_id).not.toBeNull(); // still linked: the trace says where it was scrapped from

  // No way back: the unit stays scrapped and is not packed again.
  await op.goto('/operator/packaging');
  await op.getByPlaceholder('Scan the PSN or the serial number…').fill(seed.psns[0]);
  await op.getByPlaceholder('Scan the PSN or the serial number…').press('Enter');
  await expect(op.getByText(/is Scrapped and cannot be packed/)).toBeVisible();
});

test('a blocked unit is not packed and only a supervisor releases it', async ({ browser }) => {
  const op = await session(browser, 'e2e-guards-op', seed.line);
  // Another widget through assembly, so the packing step has one waiting.
  await send(op, 'POST', `/operator/batch-step/${stepId(1)}/quantity`, { good_qty: 1 });

  const blocked = await send(op, 'POST', `/operator/unit-labels/${seed.units[1]}/block`, { scrap_reason_id: seed.reason, note: 'Scratch' });
  expect(blocked.status, JSON.stringify(blocked.json)).toBe(200);
  await op.goto('/operator/packaging');
  const scan = op.getByPlaceholder('Scan the PSN or the serial number…');
  await scan.fill(seed.psns[1]);
  await scan.press('Enter');
  await expect(op.getByText(/is Blocked and cannot be packed/)).toBeVisible();
  expect((await send(op, 'POST', `/operator/unit-labels/${seed.units[1]}/unblock`, { note: 'Looks fine' })).status).toBe(403);

  const sup = await session(browser, 'e2e-guards-super', seed.line);
  expect((await send(sup, 'POST', `/operator/unit-labels/${seed.units[1]}/unblock`, {})).status).toBe(422);
  const released = await send(sup, 'POST', `/operator/unit-labels/${seed.units[1]}/unblock`, { note: 'Rechecked, OK' });
  expect(released.status, JSON.stringify(released.json)).toBe(200);

  await scan.fill(seed.psns[1]);
  await scan.press('Enter');
  await expect.poll(() => query(`App\\Models\\SerialUnit::find(${seed.units[1]})->status`)).toBe('completed');
});

test('an operator of another line can do nothing to the order or its units', async ({ browser }) => {
  const other = await session(browser, 'e2e-guards-op-b');
  for (const [url, body] of [
    [`/operator/unit-labels/${seed.units[2]}/scrap`, { reason: 'probe' }],
    [`/operator/unit-labels/${seed.units[2]}/block`, { scrap_reason_id: seed.reason, note: 'probe' }],
  ] as const) {
    const res = await send(other, 'POST', url, body);
    expect(res.status, url).toBe(403);
    expect(res.json?.message).toContain('limited to the line you work on');
  }
  expect(query(`App\\Models\\SerialUnit::find(${seed.units[2]})->status`)).toBe('in_production');

  // Bounced to the line picker ("No lines assigned"), never shown the order.
  await other.goto(`/operator/work-order/${seed.main}`, { waitUntil: 'commit' });
  await expect(other).toHaveURL(/\/operator\/select-line/);
  await expect(other.getByText('No lines assigned')).toBeVisible();
});

test('admin cannot strand the running order', async ({ browser }) => {
  const admin = await session(browser, 'e2e-guards-admin');
  await admin.goto('/admin/dashboard');
  const order = query(`App\\Models\\WorkOrder::find(${seed.main})->only(['order_no', 'line_id', 'product_type_id', 'planned_qty', 'produced_qty', 'status'])`);
  const edit = (changes: object) => send(admin, 'PUT', `/admin/work-orders/${seed.main}`, { ...order, ...changes });

  const below = await edit({ planned_qty: 0.5 });
  expect(below.status).toBe(422);
  expect(below.json.errors.planned_qty[0]).toContain('cannot be below what was already produced');

  const moved = await edit({ line_id: seed.otherLine });
  expect(moved.status).toBe(422);
  expect(moved.json.errors.line_id[0]).toContain('is still open on this line');
  expect(query(`App\\Models\\WorkOrder::find(${seed.main})->only(['line_id'])`)).toEqual({ line_id: seed.line });
});

test('the operator console tabs are in the header for an operator', async ({ browser }) => {
  const op = await session(browser, 'e2e-guards-op', seed.line);
  for (const tab of ['Queue', 'Workstation', 'SN labels', 'Packing']) {
    await expect(op.getByRole('link', { name: tab, exact: true }).or(op.getByRole('button', { name: tab, exact: true })).first()).toBeVisible();
  }
});

// Known navigation gaps (exploration 2026-10-26): live pages with no admin
// sidebar entry. Remove an href from the list when it gets one; drop the
// fixme once the list is empty.
test.fixme('every live admin page has a sidebar entry', () => {
  const nav = readFileSync(path.join(__dirname, '../../../backend/resources/js/layouts/adminNav.js'), 'utf8');
  const missing = [
    '/admin/issue-types', '/admin/companies', '/admin/integrations', '/admin/cost-sources',
    '/admin/quality-control-triggers', '/admin/quality-tasks', '/admin/non-conformance-reports', '/admin/subassemblies',
    '/admin/machine-monitor', '/admin/net-requirements', '/admin/connectivity/counters', '/admin/shift-overview',
    '/admin/pallet-movements', '/logistics/pallets', '/packaging',
  ].filter((href) => !nav.includes(`'${href}'`));
  expect(missing).toEqual([]);
});
