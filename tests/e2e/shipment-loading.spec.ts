import { expect, test, type Page } from '@playwright/test';
import { login, runId, runPhp } from './support/app';

// Scan-to-load verification (#400) in the running app: a supervisor plans a load
// by pasting label numbers, the dock scans it on the loading terminal (green /
// amber / red answers without leaving the page), a second screen follows live,
// an operator is refused a short load and a supervisor confirms it with a reason.
// The scan rules themselves are feature-tested in PHP (ShipmentLoadingTest).

const ID = runId();
const LOAD = `E2E-LOAD-${ID}`;
const GOOD = `PAL-E2E${ID}1`;
const GOOD2 = `PAL-E2E${ID}2`;
const FAILED = `PAL-E2E${ID}3`;
const STRAY = `PAL-E2E${ID}4`;
const OPERATOR = `e2e-op-${ID.toLowerCase()}`;
const OPERATOR_PASS = 'E2e-Loading-1!';

test.beforeAll(() => {
  // Plain Eloquent: factories need Faker, which the production image lacks.
  runPhp(`
use App\\Models\\Pallet; use App\\Models\\User; use App\\Models\\WorkOrder; use Spatie\\Permission\\Models\\Role;
$wo = WorkOrder::firstOrCreate(['order_no' => 'WO-E2E-LOAD-${ID}'], ['planned_qty' => 48, 'status' => 'IN_PROGRESS']);
foreach ([['${GOOD}', 'pass'], ['${GOOD2}', 'pass'], ['${FAILED}', 'fail'], ['${STRAY}', 'pass']] as [$no, $q]) {
    $p = new Pallet; $p->forceFill(['pallet_no' => $no, 'work_order_id' => $wo->id, 'status' => 'closed', 'quality_status' => $q, 'qty' => 12]); $p->saveQuietly();
}
$u = User::firstOrNew(['username' => '${OPERATOR}']);
$u->forceFill(['name' => 'E2E Operator ${ID}', 'email' => '${OPERATOR}@e2e.local', 'password' => bcrypt('${OPERATOR_PASS}'), 'force_password_change' => false])->save();
$u->syncRoles([Role::findOrCreate('Operator', 'web')]);
`);
});

async function scan(page: Page, code: string, result: string): Promise<void> {
  const input = page.locator('#scan-code');
  await expect(input).toBeFocused();
  await input.fill(code);
  await input.press('Enter');
  await expect(page.getByTestId('scan-panel')).toHaveAttribute('data-result', result);
  await expect(page.getByTestId('scan-result')).toContainText(code);
}

test('plan, scan, follow live, refuse and confirm a short load', async ({ page, browser }) => {
  test.setTimeout(180_000);

  // ── Supervisor/admin plans the load by pasting the expected list ──────────
  await login(page);
  await page.goto('/admin/shipments/create');
  await page.fill('#shipment_no', LOAD);
  await page.fill('#carrier', 'E2E Freight');
  await page.fill('#items_text', `${GOOD}\npallet,${GOOD2}\n${FAILED}`);
  await page.getByRole('button', { name: 'Create shipment' }).click();
  await expect(page.getByTestId('shipment-no')).toHaveText(LOAD);
  await expect(page.getByTestId('total-expected')).toContainText('3');
  const shipmentUrl = page.url();

  // ── Operator opens the terminal from the picker ───────────────────────────
  const opContext = await browser.newContext();
  const op = await opContext.newPage();
  await login(op, OPERATOR, OPERATOR_PASS);
  await op.goto('/logistics/loading');
  await op.getByTestId(`pick-${LOAD}`).click();
  await expect(op.getByTestId('shipment-no')).toHaveText(LOAD);

  await scan(op, GOOD, 'loaded');
  await scan(op, GOOD, 'duplicate');
  await scan(op, STRAY, 'unexpected');
  await scan(op, FAILED, 'blocked');
  await scan(op, 'NO-SUCH-LABEL', 'unknown');
  await scan(op, GOOD2, 'loaded');
  await expect(op.getByTestId('total-loaded')).toContainText('2');

  // The supervisor's detail page followed every scan without a reload.
  await expect(page.getByTestId('total-loaded')).toContainText('2');
  await expect(page.getByTestId(`item-${GOOD}`)).toHaveAttribute('data-loaded', '1');
  await expect(page.getByTestId('scan-log')).toContainText('NO-SUCH-LABEL');

  // Undo one scan: the row goes back to missing on both screens.
  await op.getByTestId(`item-${GOOD2}`).getByRole('button', { name: 'Undo' }).click();
  await expect(op.getByTestId('scan-panel')).toHaveAttribute('data-result', 'removed');
  await expect(page.getByTestId(`item-${GOOD2}`)).toHaveAttribute('data-loaded', '0');

  // An operator cannot let a short load go.
  await op.getByTestId('confirm-loading').click();
  await expect(op.getByTestId('scan-panel')).toHaveAttribute('data-result', 'blocked');
  await expect(op.getByTestId('scan-result')).toContainText('Only a supervisor');
  await expect(op.getByTestId('shipment-status')).toContainText(/loading/i);

  // The supervisor confirms it with a reason, from the same terminal.
  await page.goto(shipmentUrl.replace(/\/admin\/shipments\/(\d+)$/, '/logistics/loading/$1'));
  await page.getByTestId('confirm-loading').click();
  await page.fill('#short-reason', 'Failed pallet stays for rework');
  await page.getByTestId('confirm-short').click();
  await expect(page.getByTestId('shipment-status')).toContainText(/confirmed/i);
  await expect(op.getByTestId('shipment-status')).toContainText(/confirmed/i);
  await expect(op.locator('#scan-code')).toBeDisabled();

  const statuses = runPhp(`
echo json_encode(App\\Models\\Pallet::whereIn('pallet_no', ['${GOOD}', '${GOOD2}', '${FAILED}'])->orderBy('pallet_no')->pluck('status', 'pallet_no'));
`);
  expect(JSON.parse(statuses)).toEqual({ [GOOD]: 'shipped', [GOOD2]: 'closed', [FAILED]: 'closed' });

  await opContext.close();
});
