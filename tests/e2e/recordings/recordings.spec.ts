import { test, expect, Browser } from '@playwright/test';
import { execSync } from 'node:child_process';
import { mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { Recorder } from './recorder';

/**
 * Silent screen recordings of the process-template and operator screens, one
 * video per section, each a person working the screen from start to finish —
 * the "definition of done" for an owned area agreed on 20.10.2026. Every
 * section is also a test: a step that stops working fails the run with the
 * section's name, so re-recording doubles as an end-to-end check.
 *
 *   01-process-template   admin builds the product's routing: three steps, a
 *                         "+1 per piece" test step, packing into cartons of 5
 *   02-work-order         admin plans two orders from that routing
 *   03-operator-order     operator signs in, makes a batch, runs Assemble:
 *                         good, scrap with a reason, the rest, step complete
 *   04-workstation        operator at the test bench: starts the step, +1 per
 *                         lamp, reports a problem, finishes on the order page
 *   05-serial-units       units started on their process serial, one blocked
 *   06-switching-screens  queue views, one bench vs all, the SN station,
 *                         theme
 *   07-supervisor         supervisor takes the reported issue through to
 *                         resolved and releases the blocked unit
 *   08-refusals           what the screens refuse, and what they say instead
 *
 * The packing station is not filmed: it is not limited to the operator's line
 * yet, so it would show other lines' orders.
 *
 * Opt-in — it writes data and takes a few minutes:
 *
 *   RECORD=1 npx playwright test tests/e2e/recordings
 *
 * Videos go to recordings/ (git-ignored; REC_OUT to change), as MP4 when
 * ffmpeg is installed. Data comes from seed.php (generic names, unique per
 * run) and is soft-deleted at the end unless REC_KEEP=1. The spec needs the
 * transfer production flow, "Allow overproduction" off and English UI text,
 * and skips when the instance is set up otherwise. Three accounts sign in per
 * run; the login form takes 10 attempts a minute per address.
 */
const CONTAINER = `${process.env.OPENMES_NAME_PREFIX || 'openmmes'}-backend`;
const OUT = process.env.REC_OUT || path.resolve(__dirname, '../../../recordings');
const RUN = (Date.now() / 1000 | 0).toString(36).slice(-4).toUpperCase();

type Seed = {
  run: string; tag: string; password: string; line: number; lineName: string; product: number; productName: string;
  settings: { flow: string; overproduction: boolean; language: string };
};

const SEED = readFileSync(path.join(__dirname, 'seed.php'), 'utf8');

function php(script: string, env: Record<string, string> = {}): string {
  const flags = Object.entries({ RUN, ...env }).map(([k, v]) => `-e ${k}=${v}`).join(' ');
  return execSync(`docker exec -i ${flags} ${CONTAINER} php`, { input: script, encoding: 'utf8' }).trim().split('\n').pop()!;
}

/** One value out of the running app — a read, never a write. */
function query<T = any>(expr: string): T {
  return JSON.parse(php(`<?php require '/var/www/html/vendor/autoload.php'; $app = require '/var/www/html/bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo json_encode(${expr}), "\\n";`));
}

let seed: Seed;
const state: Record<'admin' | 'operator' | 'supervisor', string> = { admin: '', operator: '', supervisor: '' };
const stateDir = mkdtempSync(path.join(tmpdir(), 'openmes-rec-state-'));
const orderNo = (n: number) => `WO-${RUN}-00${n}`;
const orderId = (n: number): number => query(`App\\Models\\WorkOrder::where('order_no', '${orderNo(n)}')->value('id')`);
// Process serials in the instance's PSN shape (ABC-26-0001), unique per run.
const psn = (n: number) => `${RUN.slice(-3)}-26-${String(n).padStart(4, '0')}`;

const titles: Record<string, [string, string]> = {};
const section = (name: string, title: string, subtitle: string) => { titles[name] = [title, subtitle]; return name; };

async function film(browser: Browser, name: string, as: keyof typeof state | null, body: (r: Recorder) => Promise<void>) {
  const r = await Recorder.open(browser, { name, out: OUT, storageState: as ? state[as] || undefined : undefined });
  try {
    const [title, subtitle] = titles[name];
    await r.cover(`OpenMES · ${name.slice(0, 2)}`, title, subtitle);
    await body(r);
  } finally {
    const file = await r.finish();
    test.info().annotations.push({ type: 'video', description: file });
  }
}

/** Sign in on camera and keep the session for the account's later sections. */
async function signIn(r: Recorder, who: keyof typeof state, username: string) {
  await r.goto('/login');
  await r.caption(`Sign in as ${username}`);
  await r.type(r.page.locator('input[name="username"]'), username);
  await r.type(r.page.locator('input[name="password"]'), seed.password, 35);
  await Promise.all([
    r.page.waitForURL((u) => !u.pathname.startsWith('/login'), { waitUntil: 'commit' }),
    r.click(r.page.locator('button[type="submit"]'), 0),
  ]);
  await r.settle(900);
  state[who] = path.join(stateDir, `${who}.json`);
  await r.context.storageState({ path: state[who] });
}

test.describe.configure({ mode: 'serial' });
test.setTimeout(240_000);
test.use({ navigationTimeout: 30_000, actionTimeout: 15_000 });

test.beforeAll(() => {
  test.skip(!process.env.RECORD, 'Recordings are opt-in: RECORD=1 npx playwright test tests/e2e/recordings');
  seed = JSON.parse(php(SEED));
  test.skip(seed.settings.flow !== 'transfer', 'needs the transfer production flow');
  test.skip(seed.settings.overproduction, 'needs "Allow overproduction" off');
  test.skip(seed.settings.language !== 'en', 'reads English UI text');
});

test.afterAll(() => {
  if (seed && !process.env.REC_KEEP) php(SEED, { CLEANUP: '1' });
});

test(section('01-process-template', 'Process template', 'An administrator defines how the product is made'), async ({ browser }) => {
  await film(browser, '01-process-template', null, async (r) => {
    const { page } = r;
    await signIn(r, 'admin', 'rec-admin');

    await r.caption(`${seed.productName} is a new product — it has no routing yet`);
    await r.goto(`/admin/product-types/${seed.product}`);
    await r.point(page.getByRole('heading', { name: 'Process Templates' }));
    await r.caption('Create its process template', 900);
    await r.click(page.getByRole('link', { name: 'Create', exact: true }));
    const create = page.getByRole('dialog', { name: 'Create Process Template' });
    await r.type(create.getByRole('textbox', { name: 'Template Name' }), 'Lamp assembly');
    await r.click(create.getByRole('button', { name: 'Create Template' }));
    await r.settle();
    await expect(page.getByRole('heading', { name: 'Lamp assembly', level: 1 })).toBeVisible();

    const addStep = async (name: string, bench: string, instruction: string, extra?: () => Promise<void>) => {
      await r.click(page.getByRole('button', { name: '+ Add Step' }).first());
      const d = page.getByRole('dialog', { name: 'Add New Step' });
      await r.type(d.getByRole('textbox', { name: 'Step Name' }), name);
      await r.choose(d.getByRole('combobox', { name: 'Workstation (Optional)' }), new RegExp(bench), bench);
      await r.type(d.getByRole('textbox', { name: 'Instructions' }), instruction, 30);
      await r.type(d.getByRole('spinbutton', { name: 'Estimated Duration (minutes)' }), '5');
      if (extra) await extra();
      await r.click(d.getByRole('button', { name: 'Add Step' }));
      await r.settle();
      await expect(page.getByRole('status').filter({ hasText: 'Step added' })).toBeVisible();
    };

    await r.caption('Step 1 — assembled at the assembly bench');
    await addStep('Assemble', 'Assembly bench', 'Fit the arm and shade to the base, route the cable.');

    await r.caption('Step 2 — every lamp is tested one by one, so the bench gets a big +1 per piece');
    await addStep('Function test', 'Test bench', 'Plug in, switch on, check the light.', async () => {
      const d = page.getByRole('dialog', { name: 'Add New Step' });
      await r.click(d.getByRole('radio', { name: /^\+1 per piece/ }), 1200);
    });

    await r.caption('Step 3 — packing: lamps are scanned into cartons of 5');
    await addStep('Pack', 'Packing bench', 'Scan each lamp into its carton; the carton closes at 5.', async () => {
      const d = page.getByRole('dialog', { name: 'Add New Step' });
      await r.choose(d.getByRole('combobox', { name: 'Step kind' }), 'Packing');
      await r.type(d.getByRole('spinbutton', { name: 'Units per carton' }), '5');
      await r.click(d.getByRole('checkbox', { name: 'Print a label for each unit when it is scanned' }));
    });

    await r.caption('The routing graph shows the flow from bench to bench', 2200);
    await r.point(page.getByRole('application').getByText('Function test').first());
    await r.pause(900);

    await r.caption('Assembly gets a required checklist item for the operator');
    await r.click(page.getByRole('button', { name: /1 Assemble/ }).first());
    await r.type(page.getByRole('textbox', { name: 'Add checklist item…' }).first(), 'Cable routed behind the arm');
    await r.click(page.getByRole('checkbox', { name: 'Required' }).first());
    await r.click(page.getByRole('button', { name: 'Add', exact: true }).first());
    await r.settle();
    await expect(page.getByText('Cable routed behind the arm')).toBeVisible();

    await r.caption('Function test is marked "+1 per piece"');
    await r.click(page.getByRole('button', { name: /2 Function test/ }).first(), 1800);
    await r.caption('The routing is active and ready for work orders', 2400);
  });
});

test(section('02-work-order', 'Work order', 'The order takes a snapshot of the routing'), async ({ browser }) => {
  await film(browser, '02-work-order', 'admin', async (r) => {
    const { page } = r;
    // Filtered behind the title card: the list holds every order on the instance.
    await r.goto('/admin/work-orders');
    await page.getByRole('textbox', { name: 'Search…' }).fill(RUN);
    await r.caption('Work orders — filtered to this demo');

    for (const [n, qty] of [[1, 10], [2, 5]] as const) {
      await r.caption(n === 1 ? `Plan ${qty} lamps on ${seed.lineName}` : `And a second, smaller order of ${qty}`);
      await r.click(page.getByRole('button', { name: 'New Work Order' }));
      const d = page.getByRole('dialog', { name: 'New Work Order' });
      await r.type(d.getByRole('textbox', { name: 'Order No', exact: true }), orderNo(n));
      await r.choose(d.getByRole('combobox', { name: 'Line' }), seed.lineName, seed.lineName);
      await r.choose(d.getByRole('combobox', { name: 'Product Type' }), seed.productName, seed.productName);
      await r.type(d.getByRole('spinbutton', { name: 'Planned Qty' }), String(qty));
      await r.click(d.getByRole('button', { name: 'Create' }));
      await r.settle();
      await expect(page.getByRole('status').filter({ hasText: `Work order ${orderNo(n)} created` })).toBeVisible();
    }
    await r.caption('Both orders are waiting for the line', 1800);
    await r.click(page.getByRole('link', { name: orderNo(1) }));
    await r.settle();
    await r.caption('The order carries the routing it was created with', 2600);
    await r.page.mouse.wheel(0, 500);
    await r.pause(1800);
  });
});

test(section('03-operator-order', 'Operator — running an order', 'Batch, first step, good and scrap'), async ({ browser }) => {
  await film(browser, '03-operator-order', null, async (r) => {
    const { page } = r;
    await signIn(r, 'operator', 'rec-operator');
    await r.caption('The operator lands on her line’s queue', 2000);
    await expect(page.getByRole('link', { name: orderNo(1) }).first()).toBeVisible();
    await r.click(page.getByRole('link', { name: orderNo(1) }).first());
    await r.settle();

    await r.caption('No batch yet — create one for the whole order');
    await r.click(page.getByTestId('wo-hero').getByRole('button', { name: 'Create batch' }));
    await r.choose(page.getByRole('combobox', { name: 'Workstation', exact: true }), /Assembly bench/);
    await r.click(page.getByRole('button', { name: 'Create Batch', exact: true }));
    await r.settle();

    const card = page.getByTestId('step-card').first();
    await r.caption('Start the first step: Assemble');
    await r.click(card.getByRole('button', { name: 'Start' }));
    await r.settle();

    await r.caption('6 lamps assembled, 1 housing scratched');
    await r.type(card.getByRole('spinbutton', { name: 'Good' }), '6');
    await r.type(card.getByRole('spinbutton', { name: 'Scrap' }), '1');
    await r.click(card.getByRole('button', { name: /^Record 6 good · 1 scrap/ }));
    await r.settle();
    await expect(card).toContainText(/Waiting\s*3/i);

    await r.caption('The scrapped piece gets its reason');
    await r.choose(page.getByRole('combobox', { name: 'Scrap reason' }).first(), 'Surface defect');
    await r.click(page.getByRole('button', { name: 'Save' }).first());
    await r.settle();

    await r.caption('The last 3 are good');
    await r.click(card.getByRole('button', { name: 'All 3 good' }));
    await r.click(card.getByRole('button', { name: /^Record 3 good/ }));
    await r.settle();

    await r.caption('Nothing is waiting — but the checklist item is still open');
    await r.point(card.getByRole('button', { name: 'Complete step' }));
    await r.pause(1600);
    await r.caption('Tick it, then complete the step');
    await r.click(card.getByRole('checkbox', { name: /Cable routed behind the arm/ }));
    await r.settle();
    await r.click(card.getByRole('button', { name: 'Complete step' }));
    await r.settle(1000);
    await r.caption('9 good lamps moved on to the function test', 2600);
  });
});

test(section('04-workstation', 'Operator — at the workstation', 'One bench, a lamp at a time, and a problem reported'), async ({ browser }) => {
  await film(browser, '04-workstation', 'operator', async (r) => {
    const { page } = r;
    await r.goto(`/operator/queue?line=${seed.line}&workstation=all`);
    await r.caption('Switch to the workstation view');
    await r.click(page.getByRole('link', { name: 'Workstation', exact: true }));
    await r.settle();
    await r.caption('This operator works the test bench');
    await r.click(page.getByRole('button', { name: /^Workstation All workstations/ }));
    await r.click(page.getByRole('option', { name: /Test bench/ }));
    await r.settle();

    const panel = page.getByRole('complementary', { name: 'Execution panel' });
    await r.caption('The order’s steps are on the right — start the function test');
    await r.click(panel.getByRole('button', { name: /^2 Function test/ }));
    await r.click(panel.getByRole('button', { name: 'Start Function test' }));
    await r.settle();

    await r.caption('Each lamp that lights up: +1');
    const plusOne = page.locator('[data-testid^="ws-row-"]').getByRole('button', { name: 'Add one good piece', exact: true });
    for (let i = 0; i < 3; i++) {
      await r.click(plusOne, 300);
      await r.settle(500);
    }

    await r.caption('A lamp flickers — report it from the panel');
    await r.click(panel.getByRole('button', { name: 'Report issue' }));
    const d = page.getByRole('dialog', { name: 'Report issue' });
    await r.click(d.getByRole('radio', { name: 'Quality Issue' }));
    await r.type(d.getByRole('textbox', { name: 'Title' }), 'Lamp flickers at full brightness');
    await r.type(d.getByRole('textbox', { name: 'Details' }), 'Third lamp of batch 1; switch looks loose.', 30);
    await r.click(d.getByRole('button', { name: 'Submit issue' }));
    await r.settle();

    await r.caption('The rest is counted on the order page');
    await r.click(page.locator('[data-testid^="ws-row-"]').getByRole('link', { name: 'Record output' }));
    await r.settle();
    const card = page.getByTestId('step-card').first();
    await r.caption('Big +1 buttons: 4 more good, 1 flickering lamp scrapped');
    for (let i = 0; i < 4; i++) await r.click(card.getByRole('button', { name: 'Add one good piece' }).first(), 450);
    await r.click(card.getByRole('button', { name: /scrap/i }).filter({ hasText: '+1' }).first(), 600);
    await r.settle();
    await r.caption('The scrapped lamp gets its reason');
    await r.choose(page.getByRole('combobox', { name: 'Scrap reason' }).first(), 'Machine malfunction');
    await r.click(page.getByRole('button', { name: 'Save' }).first());
    await r.settle();
    await r.caption('The last lamp passes — and the test step is complete');
    await r.click(card.getByRole('button', { name: 'Add one good piece' }).first());
    await r.settle();
    await r.click(card.getByRole('button', { name: 'Complete step' }));
    await r.settle(1000);
    await expect(page.getByText('Step completed.')).toBeVisible();
    await r.caption('8 tested lamps are waiting at packing', 2400);
  });
});

test(section('05-serial-units', 'Operator — serial units', 'Each lamp gets its process serial; a faulty one is held'), async ({ browser }) => {
  await film(browser, '05-serial-units', 'operator', async (r) => {
    const { page } = r;
    await r.goto(`/operator/queue?line=${seed.line}&workstation=all`);
    await r.caption('Open the SN station');
    await r.click(page.getByRole('link', { name: 'SN labels', exact: true }));
    await r.settle();
    await r.choose(page.getByRole('combobox', { name: 'Work order' }), new RegExp(orderNo(1)));

    for (const n of [1, 2, 3]) {
      await r.caption(n === 1 ? 'Scan the process serial and start the unit' : `Unit ${n}`);
      await r.type(page.getByRole('textbox', { name: 'Process serial (PSN)' }), psn(n), 45);
      await r.click(page.getByRole('button', { name: 'Start unit on PSN' }));
      await r.settle();
      const label = page.getByRole('dialog', { name: 'Unit label' });
      if (await label.isVisible()) {
        if (n === 1) await r.caption('Its label is ready to print', 1600);
        await r.click(label.getByRole('button', { name: 'Close' }).last(), 400);
      }
    }
    await expect(page.getByRole('row').filter({ hasText: psn(3) })).toBeVisible();

    await r.caption(`Unit ${psn(2)} has a scratched shade — block it`);
    await r.click(page.getByRole('row').filter({ hasText: psn(2) }).getByRole('button', { name: 'Block unit' }));
    const d = page.getByRole('dialog').first();
    await r.click(d.getByRole('radio', { name: /Surface defect/ }));
    await r.type(d.getByRole('textbox', { name: 'Note (optional)' }), 'Scratch on the shade');
    await r.click(d.getByRole('button', { name: 'Block unit' }));
    await r.settle();
    await expect(page.getByRole('row').filter({ hasText: psn(2) })).toContainText('Blocked');
    await r.caption('A held unit is not packed until a supervisor releases it', 2600);
  });
});

test(section('06-switching-screens', 'Operator — finding your way', 'The views, one bench or all, and the theme'), async ({ browser }) => {
  await film(browser, '06-switching-screens', 'operator', async (r) => {
    const { page } = r;
    await r.goto(`/operator/queue?line=${seed.line}&workstation=all`);
    await r.caption('The queue as a list…');
    await r.click(page.getByRole('group', { name: 'View' }).getByRole('button', { name: 'Table' }), 1400);
    await r.caption('…as a table…');
    await r.click(page.getByRole('group', { name: 'View' }).getByRole('button', { name: 'Cards' }), 1400);
    await r.caption('…or as cards');
    await r.click(page.getByRole('group', { name: 'View' }).getByRole('button', { name: 'List' }), 900);
    await r.caption('Filter by status');
    await r.click(page.getByRole('group', { name: 'Status filter' }).getByRole('button', { name: 'Pending' }), 1200);
    await r.click(page.getByRole('group', { name: 'Status filter' }).getByRole('button', { name: 'All' }), 900);

    await r.caption('All workstations: every screen of the line is in the menu');
    await r.point(page.getByRole('navigation'));
    await r.pause(1200);
    await r.caption('Pick one bench: the menu keeps only the screens for it');
    await r.click(page.getByRole('button', { name: /^Workstation All workstations/ }));
    await r.click(page.getByRole('option', { name: /Assembly bench/ }));
    await r.settle(1400);
    await r.caption('Back to all workstations');
    await r.click(page.getByRole('button', { name: 'Show all workstations', exact: true }));
    await r.settle(1000);

    await r.caption('The SN station, then back to the queue');
    await r.click(page.getByRole('link', { name: 'SN labels', exact: true }));
    await r.settle(1200);
    await r.click(page.getByRole('link', { name: 'Queue', exact: true }));
    await r.settle(900);

    await r.caption('Dark theme for a dim hall, light for a bright one');
    await r.click(page.getByRole('group', { name: 'Theme' }).getByRole('button', { name: 'Dark' }), 1600);
    await r.click(page.getByRole('group', { name: 'Theme' }).getByRole('button', { name: 'Light' }), 1200);
  });
});

test(section('07-supervisor', 'Supervisor', 'The reported problem is handled; the held unit released'), async ({ browser }) => {
  await film(browser, '07-supervisor', null, async (r) => {
    const { page } = r;
    await signIn(r, 'supervisor', 'rec-supervisor');
    await r.cover('Supervisor', 'Issues', 'What the floor reported');
    await r.goto('/supervisor/issues');
    await page.getByRole('textbox', { name: 'Search…' }).first().fill('Lamp flickers');
    await r.caption('The issue the operator reported, found by its title');
    const row = page.getByRole('row').filter({ hasText: 'Lamp flickers' });
    await expect(row).toBeVisible();
    await r.caption('Acknowledge it — the operator sees someone is on it');
    await r.click(row.getByRole('button', { name: 'Acknowledge' }));
    await r.settle();
    await r.caption('Loose switch replaced — resolve with a note');
    await r.click(page.getByRole('row').filter({ hasText: 'Lamp flickers' }).getByRole('button', { name: 'Resolve' }));
    const prompt = page.getByRole('dialog').last();
    await r.type(prompt.getByRole('textbox').first(), 'Switch re-seated; lamp retested OK.', 35);
    await r.click(prompt.getByRole('button', { name: 'Resolve' }));
    await r.settle();

    await r.caption('The blocked unit: checked and released');
    await r.goto(`/operator/unit-labels/station?line=${seed.line}&workstation=all`);
    const unit = page.getByRole('row').filter({ hasText: psn(2) });
    await r.click(unit.getByRole('button', { name: /Release|Unblock/ }));
    const d = page.getByRole('dialog').last();
    if (await d.isVisible()) {
      const note = d.getByRole('textbox').first();
      if (await note.isVisible()) await r.type(note, 'Shade replaced', 40);
      await r.click(d.getByRole('button', { name: /Release|Unblock/ }).last());
    }
    await r.settle();
    await expect(page.getByRole('row').filter({ hasText: psn(2) })).not.toContainText('Blocked');
    await r.caption('Back in production', 2200);
  });
});

test(section('08-refusals', 'What the screens refuse', 'Wrong input is stopped, with a reason'), async ({ browser }) => {
  await film(browser, '08-refusals', 'operator', async (r) => {
    const { page } = r;
    await r.goto(`/operator/work-order/${orderId(2)}`);
    await r.caption(`${orderNo(2)} needs 5 lamps. Try a batch of 7`);
    await r.click(page.getByTestId('wo-hero').getByRole('button', { name: 'Create batch' }));
    const qty = page.getByRole('spinbutton', { name: 'Quantity' });
    const createBatch = page.getByRole('button', { name: 'Create Batch', exact: true });
    await r.choose(page.getByRole('combobox', { name: 'Workstation', exact: true }), /Assembly bench/);
    await r.type(qty, '7');
    await expect(page.getByRole('alert').filter({ hasText: 'exceeds what the order still needs' })).toBeVisible();
    await expect(createBatch).toBeDisabled();
    await r.point(createBatch);
    await r.pause(2200);
    await r.caption('Half a lamp?');
    await r.type(qty, '2.5');
    await expect(page.getByRole('alert').filter({ hasText: 'Whole pieces only' })).toBeVisible();
    await r.pause(2000);
    await r.caption('5 is what the order needs — accepted');
    await r.type(qty, '5');
    await r.click(page.getByRole('button', { name: 'Create Batch', exact: true }));
    await r.settle();

    const card = page.getByTestId('step-card').first();
    await r.click(card.getByRole('button', { name: 'Start' }));
    await r.settle();
    await r.caption('Record 6 good when only 5 are at the bench?');
    await r.type(card.getByRole('spinbutton', { name: 'Good' }), '6');
    await expect(card.getByText(/More than waiting/)).toBeVisible();
    await r.pause(1800);
    await r.caption('Or 1.5 good?');
    await r.type(card.getByRole('spinbutton', { name: 'Good' }), '1.5');
    await expect(card.getByText('Whole pieces only')).toBeVisible();
    await r.pause(1800);
    await r.type(card.getByRole('spinbutton', { name: 'Good' }), '');
    await r.caption('Completing the step while lamps are still waiting?');
    await expect(card.getByRole('button', { name: 'Complete step' })).toBeDisabled();
    await r.point(card.getByRole('button', { name: 'Complete step' }));
    await r.pause(2200);

    await r.caption('At the SN station: a serial in the wrong format');
    await r.click(page.getByRole('link', { name: 'SN labels', exact: true }));
    await r.settle();
    await r.choose(page.getByRole('combobox', { name: 'Work order' }), new RegExp(orderNo(2)));
    await r.type(page.getByRole('textbox', { name: 'Process serial (PSN)' }), 'lamp 7');
    await r.click(page.getByRole('button', { name: 'Start unit on PSN' }), 400);
    await expect(page.getByRole('alert').filter({ hasText: 'does not match the configured format' })).toBeVisible();
    await r.pause(1800);
    await r.caption('Asking for a number when the product has no serial sequence');
    await r.click(page.getByRole('button', { name: 'Issue number' }).first(), 400);
    await expect(page.getByRole('alert').filter({ hasText: 'no process serial sequence' })).toBeVisible();
    await r.pause(2200);
  });
});
