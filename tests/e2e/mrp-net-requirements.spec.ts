import { test, expect } from '@playwright/test';
import { expectPage, login, runId, runPhp } from './support/app';

// MRP net requirements & shortage report (#90). Seeds one planned work order
// whose BOM needs Driver ×2 (50 in stock) and PCB ×1 (500 in stock) for 100
// units: the driver is short by 150, the PCB is covered.

const ID = runId();
const DRV = `DRV-${ID}`;
const PCB = `PCB-${ID}`;
const WO = `WO-MRP-${ID}`;

test.beforeAll(() => {
  runPhp(`
use App\\Models\\{BomItem, Line, Material, MaterialType, ProcessTemplate, ProductType, WorkOrder};
$type = MaterialType::firstOrCreate(['code' => 'E2E-RAW'], ['name' => 'E2E raw material']);
$drv = Material::create(['code' => '${DRV}', 'name' => 'LED driver ${ID}', 'material_type_id' => $type->id, 'unit_of_measure' => 'pcs', 'stock_quantity' => 50]);
$pcb = Material::create(['code' => '${PCB}', 'name' => 'PCB ${ID}', 'material_type_id' => $type->id, 'unit_of_measure' => 'pcs', 'stock_quantity' => 500]);
$pt = ProductType::create(['code' => 'LAMP-${ID}', 'name' => 'Lamp ${ID}', 'unit_of_measure' => 'pcs', 'is_active' => true]);
$tpl = ProcessTemplate::create(['product_type_id' => $pt->id, 'name' => 'Lamp ${ID} v1', 'is_active' => true, 'version' => 1]);
BomItem::create(['process_template_id' => $tpl->id, 'material_id' => $drv->id, 'quantity_per_unit' => 2, 'scrap_percentage' => 0, 'consumed_at' => 'start']);
BomItem::create(['process_template_id' => $tpl->id, 'material_id' => $pcb->id, 'quantity_per_unit' => 1, 'scrap_percentage' => 0, 'consumed_at' => 'start']);
$line = Line::create(['code' => 'MRP-${ID}', 'name' => 'MRP line ${ID}', 'is_active' => true]);
WorkOrder::create(['order_no' => '${WO}', 'product_type_id' => $pt->id, 'line_id' => $line->id, 'planned_qty' => 100, 'status' => WorkOrder::STATUS_PENDING, 'due_date' => today()->addDays(5)]);`);
});

test('MRP net requirements report shows the shortage', async ({ page }) => {
  await login(page);
  await page.setViewportSize({ width: 1500, height: 1100 });

  await page.goto('/admin/net-requirements');
  await expectPage(page, 'Net requirements');
  await expect(page.getByText('Components short')).toBeVisible();

  // Earlier runs leave their own shortages behind and the table pages, so
  // narrow the shortage list to this run's component first.
  await page.getByPlaceholder('Search…').first().fill(DRV);
  const driver = page.getByRole('row').filter({ hasText: DRV }).first();
  await expect(driver).toBeVisible({ timeout: 15_000 });
  await expect(driver).toContainText('150'); // shortfall: 2 × 100 − 50
  await expect(driver).toContainText(WO); // the work order that drives it

  // The PCB is fully covered by stock, so the shortage list leaves it out.
  await page.getByPlaceholder('Search…').first().fill(PCB);
  await expect(page.getByRole('row').filter({ hasText: PCB })).toHaveCount(0);
});
