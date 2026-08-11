import { test, expect, Page } from '@playwright/test';
import * as childProc from 'child_process';
import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';

// E2E for Engineering CAD documents (#179) — the UI completion + operator access.
// The panel/upload/lifecycle rules are covered by PHP feature tests and Vitest;
// this asserts the UI wiring end-to-end: the panel now hosts on the product
// revision / subassembly / process template pages (real upload), and an operator
// can see + download the documents frozen onto their work order.
//
// Self-seeds inside the running container with explicit Eloquent (Faker is
// require-dev and absent from the production image), matching the other specs.

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
const OP = 'e2eop';
const OP_PASS = 'Operator1234!';
const DOC = 'e2e-gearbox-B.pdf';

const BACKEND = `${process.env.OPENMES_NAME_PREFIX || 'openmmes'}-backend`;

const BOOT = `require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();`;

// Idempotent teardown — force-delete the E2E artifacts (all soft-delete models).
const CLEAR = `
App\\Models\\WorkOrder::withTrashed()->where('order_no','E2E-WO-1')->forceDelete();
App\\Models\\EngineeringDocument::withTrashed()->where('original_filename','${DOC}')->forceDelete();
$pt = App\\Models\\ProductType::withTrashed()->where('code','E2E-PT')->first();
if ($pt) {
    App\\Models\\ProductRevision::withTrashed()->where('product_type_id',$pt->id)->forceDelete();
    App\\Models\\TemplateStep::withTrashed()->whereIn('process_template_id',
        App\\Models\\ProcessTemplate::withTrashed()->where('product_type_id',$pt->id)->pluck('id'))->forceDelete();
    App\\Models\\ProcessTemplate::withTrashed()->where('product_type_id',$pt->id)->forceDelete();
    App\\Models\\ProductType::withTrashed()->where('id',$pt->id)->forceDelete();
}
App\\Models\\Subassembly::withTrashed()->where('code','E2E-SUB')->forceDelete();
App\\Models\\Material::withTrashed()->where('code','E2E-MAT')->forceDelete();
App\\Models\\Line::withTrashed()->where('code','E2E-LINE')->forceDelete();
App\\Models\\User::withTrashed()->where('username','${OP}')->forceDelete();
`;

const SEED_PHP = `<?php
${BOOT}
${CLEAR}
$admin = App\\Models\\User::where('username','${ADMIN}')->first();
$pt = App\\Models\\ProductType::create(['code'=>'E2E-PT','name'=>'E2E Gearbox']);
$tpl = App\\Models\\ProcessTemplate::create(['product_type_id'=>$pt->id,'name'=>'E2E Template','version'=>1,'is_active'=>true]);
App\\Models\\TemplateStep::create(['process_template_id'=>$tpl->id,'step_number'=>1,'name'=>'Assemble housing']);
$rev = App\\Models\\ProductRevision::create(['product_type_id'=>$pt->id,'revision_code'=>'E2E-B','lifecycle_status'=>'released','process_template_id'=>$tpl->id,'released_at'=>now(),'released_by_id'=>$admin->id]);
$sub = App\\Models\\Subassembly::create(['code'=>'E2E-SUB','name'=>'E2E Bracket assembly']);
App\\Models\\Material::create(['code'=>'E2E-MAT','name'=>'E2E Steel A36']);
$fpath = "engineering/product_revision/{$rev->id}/".Illuminate\\Support\\Str::uuid()->toString().'.pdf';
Illuminate\\Support\\Facades\\Storage::disk('local')->put($fpath, "%PDF-1.4 E2E");
App\\Models\\EngineeringDocument::create(['entity_type'=>'product_revision','entity_id'=>$rev->id,'original_filename'=>'${DOC}','package_type'=>'pdf','document_type'=>'drawing','mime_type'=>'application/pdf','file_size'=>Illuminate\\Support\\Facades\\Storage::disk('local')->size($fpath),'revision'=>'E2E-B','checksum'=>hash('sha256','e2e'),'storage_path'=>$fpath,'lifecycle_status'=>'released','released_at'=>now(),'released_by_id'=>$admin->id,'uploaded_by_id'=>$admin->id]);
$line = App\\Models\\Line::create(['code'=>'E2E-LINE','name'=>'E2E Line']);
$op = App\\Models\\User::create(['username'=>'${OP}','name'=>'E2E Operator','email'=>'e2eop@local.test','password'=>Illuminate\\Support\\Facades\\Hash::make('${OP_PASS}')]);
$op->assignRole('Operator');
$op->lines()->syncWithoutDetaching([$line->id]);
$wo = app(App\\Services\\WorkOrder\\WorkOrderService::class)->createWorkOrder(['order_no'=>'E2E-WO-1','planned_qty'=>10,'product_type_id'=>$pt->id,'product_revision_id'=>$rev->id,'line_id'=>$line->id]);
echo 'E2ESEED:'.json_encode(['rev'=>$rev->id,'sub'=>$sub->id,'pt'=>$pt->id,'tpl'=>$tpl->id,'wo'=>$wo->id,'frozen'=>count($wo->process_snapshot['engineering_documents'] ?? [])]);
`;

const CLEAR_PHP = `<?php
${BOOT}
${CLEAR}
echo 'CLEARED';
`;

function runInContainer(php: string): string {
  const tmp = path.join(os.tmpdir(), `pw-eng-${Date.now()}-${Math.random().toString(36).slice(2)}.php`);
  fs.writeFileSync(tmp, php);
  try {
    childProc.execSync(`docker cp ${tmp} ${BACKEND}:/tmp/eng-seed.php`, { stdio: 'ignore' });
    return childProc.execSync(`docker exec ${BACKEND} php /tmp/eng-seed.php`, { encoding: 'utf8' });
  } finally {
    try { fs.unlinkSync(tmp); } catch { /* ignore */ }
  }
}

let ids: { rev: number; sub: number; pt: number; tpl: number; wo: number; frozen: number };

test.beforeAll(() => {
  const out = runInContainer(SEED_PHP);
  const m = out.match(/E2ESEED:(\{.*\})/);
  if (!m) throw new Error(`seed failed: ${out}`);
  ids = JSON.parse(m[1]);
  expect(ids.frozen).toBeGreaterThan(0); // the released doc froze onto the work order
});

test.afterAll(() => {
  runInContainer(CLEAR_PHP);
});

async function login(page: Page, user: string, pass: string) {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="username"]', user);
  await page.fill('input[name="password"]', pass);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
}

test('admin: engineering panel on the product-revision page, with a real upload', async ({ page }) => {
  await login(page, ADMIN, PASS);
  await page.goto(`/admin/product-revisions/${ids.rev}`);

  await expect(page.getByRole('heading', { name: 'Engineering documents' })).toBeVisible();
  await expect(page.getByText(DOC)).toBeVisible();

  const tmp = path.join(os.tmpdir(), 'e2e-bracket.step');
  fs.writeFileSync(tmp, 'ISO-10303-21;\nHEADER; E2E STEP; ENDSEC;\n');
  await page.setInputFiles('input[type="file"]', tmp);
  await page.fill('input[placeholder="e.g. A"]', 'C');
  await page.getByRole('button', { name: 'Upload' }).click();

  await expect(page.getByText('e2e-bracket.step')).toBeVisible({ timeout: 15_000 });
});

test('admin: engineering panel on subassembly and process-template pages', async ({ page }) => {
  await login(page, ADMIN, PASS);

  await page.goto(`/admin/subassemblies/${ids.sub}`);
  await expect(page.getByRole('heading', { name: 'Engineering documents' })).toBeVisible();

  await page.goto(`/admin/product-types/${ids.pt}/process-templates/${ids.tpl}`);
  await expect(page.getByRole('heading', { name: 'Engineering documents' })).toBeVisible();
});

test('operator: sees and can download the documents frozen onto the work order', async ({ page }) => {
  await login(page, OP, OP_PASS);

  await page.goto('/operator/select-line');
  await page.locator('form', { has: page.getByText('E2E Line') })
    .getByRole('button', { name: 'Select' }).click();
  await page.waitForLoadState('networkidle');

  await page.goto(`/operator/work-order/${ids.wo}`);
  await expect(page.getByRole('heading', { name: 'Engineering documents' })).toBeVisible();
  await expect(page.getByText(DOC)).toBeVisible();

  const download = page.getByRole('link', { name: 'Download' }).first();
  await expect(download).toHaveAttribute('href', /\/api\/v1\/engineering-documents\/\d+\/download$/);
});
