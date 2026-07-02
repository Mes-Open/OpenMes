import { test, expect, Page } from '@playwright/test';
import { existsSync, readFileSync } from 'fs';
import { resolve } from 'path';

const envPath = resolve(__dirname, '../../.env');
const envContent = existsSync(envPath) ? readFileSync(envPath, 'utf-8') : '';
const adminPassMatch = envContent.match(/^ADMIN_PASSWORD=(.*)$/m);

const ADMIN = 'admin';
const PASS = process.env.ADMIN_PASSWORD?.trim() || (adminPassMatch ? adminPassMatch[1].trim() : 'MFRz9GZBkM9UEYTfsaHPLG1P');
const TS = Date.now().toString().slice(-6);

const LINE = `Car Assembly Line ${TS}`;
const PRODUCT = `EV Sedan ${TS}`;
const WO = `WO-CAR-${TS}`;
const TRIGGER = `In-line QC ${TS}`;
const EAN = `40${TS}0000`.slice(0, 13).padEnd(13, '0');
const OP_USER = `carop${TS}`;
const OP_PASS = 'Operator123!';
const DEFAULT_USER = 'admin';
const DEFAULT_PASS = 'MFRz9GZBkM9UEYTfsaHPLG1P';

test.describe.configure({ mode: 'serial' });
test.setTimeout(360_000); // 6 mins for full UI flow
let adminCookies: any[] = [];
let opCookies: any[] = [];

async function switchToOperator(page: import('@playwright/test').Page) {
  adminCookies = await page.context().cookies();
  await page.context().clearCookies();
  if (opCookies.length > 0) {
    await page.context().addCookies(opCookies);
  }
}

async function switchToAdmin(page: import('@playwright/test').Page) {
  opCookies = await page.context().cookies();
  await page.context().clearCookies();
  if (adminCookies.length > 0) {
    await page.context().addCookies(adminCookies);
  }
}

test.use({
  video: { mode: 'on', size: { width: 1280, height: 720 } },
  viewport: { width: 1280, height: 720 },
  locale: 'en-US',
  timezoneId: 'Asia/Ho_Chi_Minh',
});

async function login(page: Page, user: string, pass: string) {
  await page.goto('/login');
  await page.fill('input[name="username"]', user);
  await page.fill('input[name="password"]', pass);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type="submit"]')]);
}

async function stepSubtitle(page: Page, text: string) {
  await page.evaluate((msg) => {
    let div = document.getElementById('e2e-subtitle');
    if (!div) {
      div = document.createElement('div');
      div.id = 'e2e-subtitle';
      Object.assign(div.style, {
        position: 'fixed', bottom: '30px', left: '50%', transform: 'translateX(-50%)',
        backgroundColor: 'rgba(0, 0, 0, 0.8)', color: 'white', padding: '12px 24px',
        borderRadius: '8px', fontSize: '20px', fontWeight: 'bold', zIndex: '999999',
        textAlign: 'center', boxShadow: '0 4px 6px rgba(0,0,0,0.3)', pointerEvents: 'none'
      });
      document.body.appendChild(div);
    }
    div.innerText = msg;
  }, text);
  await page.waitForTimeout(2000);
}

async function addClickHighlighter(page: Page) {
  await page.addInitScript(() => {
    document.addEventListener('click', (e) => {
      const circle = document.createElement('div');
      Object.assign(circle.style, {
        position: 'absolute', top: `${e.pageY - 20}px`, left: `${e.pageX - 20}px`,
        width: '40px', height: '40px', borderRadius: '50%', border: '4px solid red',
        pointerEvents: 'none', zIndex: '999999',
        animation: 'e2e-ripple 0.6s linear'
      });
      document.body.appendChild(circle);
      if (!document.getElementById('e2e-ripple-style')) {
        const style = document.createElement('style');
        style.id = 'e2e-ripple-style';
        style.innerHTML = `@keyframes e2e-ripple { 0% { transform: scale(0.5); opacity: 1; } 100% { transform: scale(1.5); opacity: 0; } }`;
        document.head.appendChild(style);
      }
      setTimeout(() => circle.remove(), 600);
    });
  });
}

const createBtn = (page: Page) => page.getByRole('button', { name: /(Create|Save)/i }).first();

async function pickFormSelect(page: Page, index: number, optionName: string | RegExp) {
  await page.locator('form button[aria-haspopup="listbox"]').nth(index).click();
  await page.getByRole('option', { name: optionName }).first().click();
}
async function pickDropdown(button, optionName: string | RegExp) {
  await button.click();
  await button.page().getByRole('option', { name: optionName }).first().click();
}

test.beforeAll(() => {
  // DB reset + lot tracking is now handled externally by reset-test_en.cmd
});

test('build an EV sedan production configuration from zero and run it', async ({ page, browser }) => {
  await addClickHighlighter(page);
  
  await stepSubtitle(page, 'Login as Administrator');
  await login(page, ADMIN, PASS);

  await stepSubtitle(page, 'Create Production Line: ' + LINE);
  await test.step('create assembly line', async () => {
    await page.goto('/admin/lines/create');
    await page.fill('input[name="code"]', `CAR-${TS}`);
    await page.fill('input[name="name"]', LINE);
    await createBtn(page).click();
    await page.waitForURL(/\/admin\/lines$/);
  });

  await stepSubtitle(page, 'Create Product Type: ' + PRODUCT);
  await test.step('create product type', async () => {
    await page.goto('/admin/product-types/create');
    await page.fill('input[name="code"]', `SED-${TS}`);
    await page.fill('input[name="name"]', PRODUCT);
    await page.fill('input[name="unit_of_measure"]', 'pcs');
    await createBtn(page).click();
    await page.waitForURL(/\/admin\/product-types$/);
  });

  await stepSubtitle(page, 'Create Process Template & BOM (Bill of Materials)');
  await test.step('create active process template with a step', async () => {
    await page.goto('/admin/product-types');
    await page.locator('.bg-om-card', { hasText: PRODUCT }).getByRole('button', { name: /(View Details|Xem chi tiết)/i }).click();
    await page.locator('a:has-text("Create"), a:has-text("Tạo"), a:has-text("Thêm")').first().click();
    await page.fill('input#name', `EV Sedan Assembly Process ${TS}`);
    await page.getByRole('button', { name: /(Create Template|Tạo|Lưu)/i }).first().click();
    await page.waitForURL(/\/admin\/product-types\/\d+\/process-templates\/\d+/);
    
    await page.getByRole('button', { name: /(Add Step|Thêm bước)/i }).first().click();
    await page.locator('input[placeholder*="Attach component"], input[placeholder*="Gắn linh kiện"]').fill('Final Assembly & Inspection');
    await page.locator('form').getByRole('button', { name: /(Add Step|Thêm bước)/i }).click();
    await expect(page.getByText('Final Assembly')).toBeVisible({ timeout: 15_000 });
  });

  await stepSubtitle(page, 'Create Workstation on the Production Line');
  await test.step('create workstation', async () => {
    await page.goto('/admin/lines');
    await page.locator('tr', { hasText: LINE }).getByRole('link', { name: /(Cấu hình|Configure)/i }).click();
    await page.locator('.bg-om-card', { hasText: /(Workstations|Trạm sản xuất)/i }).getByRole('link', { name: /(Manage|Quản lý)/i }).click();
    await page.getByRole('link', { name: /(Add Workstation|Thêm trạm|Create Workstation|Tạo trạm)/i }).first().click();

    await page.getByPlaceholder(/WS-A01/i).fill(`WB-${TS}`);
    await page.getByPlaceholder(/Assembly Station 1/i).fill(`Assembly Station ${TS}`);
    await createBtn(page).click();
    await page.waitForURL(/\/admin\/lines\/\d+\/workstations$/);
  });

  const bomDefs = [
    { code: `BATTERY-${TS}`, name: `BATTERY ${TS}`, qtyPerUnit: 1, stock: 2 },
    { code: `CHASSIS-${TS}`, name: `CHASSIS ${TS}`, qtyPerUnit: 1, stock: 3 },
    { code: `WHEEL-${TS}`,   name: `WHEEL ${TS}`,   qtyPerUnit: 4, stock: 10 },
    { code: `MOTOR-${TS}`,   name: `MOTOR ${TS}`,   qtyPerUnit: 1, stock: 5 },
    { code: `SEAT-${TS}`,    name: `SEAT ${TS}`,    qtyPerUnit: 5, stock: 200 },
    { code: `ECU-${TS}`,     name: `ECU ${TS}`,     qtyPerUnit: 1, stock: 40 },
  ];

  await stepSubtitle(page, 'Register Raw Materials (6 Components)');
  await test.step('create materials', async () => {
    for (const mat of bomDefs) {
      await page.goto('/admin/materials/create');
      await page.fill('input[name="code"]', mat.code);
      await page.fill('input[name="name"]', mat.name);
      await pickFormSelect(page, 0, /Raw Material|Nguyên vật liệu/i);
      await pickFormSelect(page, 1, /Batch/i);
      await page.fill('input[name="unit_of_measure"]', 'pcs');
      await createBtn(page).click();
      await page.waitForURL(/\/admin\/materials$/);
    }
  });

  await stepSubtitle(page, 'Create Initial Material Lots (Inventory Setup)');
  await test.step('create material lots for stock', async () => {
    for (const mat of bomDefs) {
      await page.goto('/admin/material-lots/create');
      await pickFormSelect(page, 0, new RegExp(mat.name));
      await page.fill('input[name="lot_number"]', `LOT-${mat.code}`);
      await page.fill('input[name="quantity_received"]', String(mat.stock));
      await pickFormSelect(page, 2, /(Released|Phát hành)/i);
      await page.getByText('Select date').first().click();
      await page.getByRole('button', { name: new Date().toISOString().split('T')[0], exact: true }).click();
      await createBtn(page).click();
      await page.waitForURL(/\/admin\/material-lots$/);
    }
  });

  await stepSubtitle(page, 'Attach BOM Items to Process Template');
  await test.step('add BOM items', async () => {
    await page.goto('/admin/product-types');
    await page.locator('.bg-om-card').filter({ hasText: PRODUCT }).getByRole('button', { name: /View Details|Xem chi tiết/i }).click();
    await page.locator('a:has-text("View All"), a:has-text("Xem tất cả")').first().click();
    await page.locator('.bg-om-card').filter({ hasText: `EV Sedan Assembly Process ${TS}` }).getByRole('button', { name: /View Steps/i }).click();
    await page.locator('a:has-text("BOM")').first().click();
    
    for (const mat of bomDefs) {
      await page.getByRole('button', { name: /(Add Material|Thêm vật tư|Thêm)/i }).first().click();
      await pickFormSelect(page, 0, new RegExp(mat.name));
      await page.fill('input[type="number"]', String(mat.qtyPerUnit));
      await page.locator('form').getByRole('button', { name: /(Save|Create|Tạo|Lưu|Add to BOM)/i }).first().click();
      await page.waitForTimeout(500); // small delay to let dialog close
    }
  });

  await stepSubtitle(page, 'Create Work Order for 50 EV Sedans');
  await test.step('create planned work order', async () => {
    await page.goto('/admin/work-orders/create');
    await page.fill('input[name="order_no"]', WO);
    await pickFormSelect(page, 0, new RegExp(LINE));
    await pickFormSelect(page, 1, new RegExp(PRODUCT));
    await page.fill('input[name="planned_qty"]', '50');
    const dateStr = new Date().toISOString().split('T')[0];
    await page.getByText('Select date').first().click();
    await page.getByRole('button', { name: dateStr }).first().click();
    await createBtn(page).click();
    await page.waitForURL(/\/admin\/work-orders$/);
  });

  await stepSubtitle(page, 'Register EAN Barcode for the Work Order');
  await test.step('register EAN', async () => {
    await page.goto('/packaging/eans');
    await pickDropdown(page.locator('form button[aria-haspopup="listbox"]').first(), new RegExp(WO));
    await page.locator('input[placeholder*="5901234123457"]').fill(EAN);
    await page.getByRole('button', { name: /(Dodaj EAN|Add EAN|Thêm EAN)/i }).click();
    await expect(page.getByText(EAN)).toBeVisible({ timeout: 15_000 });
  });

  await stepSubtitle(page, 'Create In-line Quality Control Trigger');
  await test.step('create in-line QC trigger', async () => {
    await page.goto('/admin/quality-control-triggers/create');
    await page.fill('input[name="name"]', TRIGGER);
    // Trigger type defaults to "In production" which is fine for this test
    await createBtn(page).click();
    await page.waitForURL(/\/admin\/quality-control-triggers$/);
  });

  await stepSubtitle(page, 'MRP: System Detects Material Shortages');
  await test.step('MRP net requirements shows part shortages', async () => {
    await page.goto('/admin/net-requirements');
    await page.waitForTimeout(3000); // Overview pause
    // WHEEL: 4/car × 50 = 200 required, stock 10 → short by 190.
    const wheelRow = page.locator('tr', { hasText: `WHEEL-${TS}` }).first();
    await expect(wheelRow).toBeVisible({ timeout: 15_000 });
    await expect(wheelRow).toContainText(WO);
  });

  await stepSubtitle(page, 'Receive Additional Materials After MRP Shortage Alert');
  await test.step('receive missing materials', async () => {
    for (const mat of bomDefs) {
      const required = mat.qtyPerUnit * 50;
      if (mat.stock < required) {
        await page.goto('/admin/material-lots/create');
        await pickFormSelect(page, 0, new RegExp(mat.name));
        await page.fill('input[name="lot_number"]', `LOT2-${mat.code}`);
        await page.fill('input[name="quantity_received"]', String(required - mat.stock));
        await pickFormSelect(page, 2, /(Released|Phát hành)/i);
        await page.getByText('Select date').first().click();
        await page.getByRole('button', { name: new Date().toISOString().split('T')[0], exact: true }).first().click();
        await createBtn(page).click();
        await page.waitForURL(/\/admin\/material-lots$/);
      }
    }
  });

  await stepSubtitle(page, 'Create Operator Account & Assign to Line');
  await test.step('create operator + assign to line', async () => {
    await page.goto('/admin/users/create');
    await page.locator('input[type="text"]').nth(0).fill(`Car Operator ${TS}`);
    await page.locator('input[type="text"]').nth(1).fill(OP_USER);
    await page.locator('input[type="email"]').fill(`${OP_USER}@example.test`);
    await page.locator('input[type="password"]').nth(0).fill(OP_PASS);
    await page.locator('input[type="password"]').nth(1).fill(OP_PASS);
    await pickFormSelect(page, 0, /Operator|Vận hành/i);
    await page.locator('form').getByRole('button', { name: /(Create|Save|Lưu|Tạo)/i }).first().click();
    await page.waitForURL(/\/admin\/users(\?.*)?$/);

    await page.goto('/admin/lines');
    await page.locator('tr', { hasText: LINE }).getByRole('link', { name: /(Configure|Cấu hình)/i }).first().click();
    const opForm = page.locator('form').filter({ has: page.getByRole('button', { name: /(Assign|Phân công)/i, exact: true }) });
    const opValue = await opForm.locator('select > option', { hasText: new RegExp(OP_USER) }).getAttribute('value');
    await opForm.locator('select').selectOption(opValue);
    await opForm.getByRole('button', { name: /(Assign|Phân công)/i, exact: true }).click();
    await expect(page.getByText(`Car Operator ${TS}`).first()).toBeVisible({ timeout: 15_000 });
  });

  await stepSubtitle(page, 'Operator Logs Into the Shop Floor');
  await switchToOperator(page);
  await login(page, OP_USER, OP_PASS);

  await stepSubtitle(page, 'Operator Sets Machine State: Cleaning -> Running');
  await test.step('operator sets machine state cleaning then running', async () => {
    await page.goto('/operator/select-line');
    await page.locator('form').getByRole('button', { name: /(Select|Chọn)/i }).first().click();
    await page.waitForURL(/\/operator\/(queue|workstation)/);
    await page.goto('/operator/workstation'); // Ensure we are on workstation for state change
    const select = page.locator(`select`);
    await expect(select).toBeVisible({ timeout: 15_000 });
    await select.selectOption('CLEANING');
    await page.waitForTimeout(1000);
    await select.selectOption('RUNNING');
    await page.waitForTimeout(1000);
  });

  await stepSubtitle(page, 'Operator Picks Work Order & Starts a Batch');
  await test.step('operator starts a batch step', async () => {
    await page.goto('/operator/queue');
    await page.getByText(WO, { exact: false }).first().click();
    await page.waitForURL(/\/operator\/work-order\/\d+/);
    await page.getByRole('button', { name: /(Create Batch|Tạo lô|Thêm)/i }).first().click();
    
    // Đặt số lượng lô (batch) là 1 để Lệnh Sản Xuất không bị hoàn thành 100%
    // Nếu hoàn thành 100%, Work Order sẽ chuyển sang trạng thái DONE và không cho phép báo cáo sự cố (Report Issue) nữa.
    const batchDialog = page.locator('.fixed.inset-0.z-50');
    await batchDialog.locator('input[type="number"]').first().fill('1');
    await batchDialog.getByRole('button', { name: /(Create Batch|Tạo lô|Lưu)/i }).click();
    
    // Ensure the batch is visible
    const batchCard = page.locator('.bg-om-card').filter({ hasText: /Batch #1/i }).first();
    await expect(batchCard).toBeVisible({ timeout: 15_000 });
    
    // Expand the batch accordion if not already expanded
    const startBtn = batchCard.getByRole('button', { name: /^(Start|Bắt đầu)$/i }).first();
    let isStartVisible = false;
    for (let i = 0; i < 3; i++) {
      await page.waitForTimeout(1000); // Give React a moment to render defaults or process the click
      if (await startBtn.isVisible()) {
        isStartVisible = true;
        break;
      }
      // Click the header to toggle
      await batchCard.locator('button').first().click({ force: true });
    }
    
    if (!isStartVisible) {
      await expect(startBtn).toBeVisible({ timeout: 5000 });
    }
    
    await startBtn.click({ force: true });

    const confirmBtn = page.getByRole('button', { name: /Confirm picks/i }).first();
    if (await confirmBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      if (await confirmBtn.isDisabled()) {
        const selects = await page.locator('select').all();
        for (const select of selects) {
          // Select the first actual lot option (index 1, since index 0 is "+ Add lot...")
          await select.selectOption({ index: 1 });
          await page.waitForTimeout(200);
        }
      }

      await expect(confirmBtn).toBeEnabled({ timeout: 2000 });
      await confirmBtn.click();
    }

    const completeBtn = batchCard.getByRole('button', { name: /Complete/i }).first();
    await expect(completeBtn).toBeVisible({ timeout: 15_000 });
    // Click Complete to finish the step, waiting for the API response before navigating away
    console.log('Clicking Complete button and waiting for response...');
    await Promise.all([
      page.waitForResponse(res => res.url().includes('complete') && res.ok()),
      completeBtn.click({ force: true })
    ]);
    console.log('Complete button click finished.');
  });

  let palletNo = '';
  await stepSubtitle(page, 'Pack a Car onto a Pallet & Scan Barcode');
  await test.step('package a car onto a pallet', async () => {
    console.log('Going to packaging station...');
    await page.goto('/packaging/station');
    const woDropdown = page.locator('label', { hasText: /Create pallet for order|Tạo pallet cho lệnh/i }).locator('..').locator('button[aria-haspopup="listbox"]');
    await pickDropdown(woDropdown, new RegExp(WO));
    console.log('Clicking create pallet...');
    await page.getByRole('button', { name: /(Create pallet|Tạo pallet)/i }).click();
    console.log('Waiting for active pallet text...');
    await expect(page.getByText(/(Active pallet|Pallet đang mở)/i)).toBeVisible({ timeout: 15_000 });
    palletNo = (await page.locator('text=/PAL-\\d{6}/').first().innerText()).trim();
    console.log('Clicking on page to focus...');
    await page.getByRole('heading', { name: /(Pack(ing|aging) Station|Trạm đóng gói)/i }).click();
    
    // Type EAN quickly to avoid the 500ms buffer clear
    await page.keyboard.type(EAN, { delay: 10 });
    await page.keyboard.press('Enter');
    
    // Check if error flash appears
    const errorFlash = page.locator('.bg-om-blocked-bg');
    if (await errorFlash.isVisible({ timeout: 2000 }).catch(() => false)) {
       const errText = await errorFlash.innerText();
       console.log('Packaging scan error:', errText);
    }
    
    console.log('Waiting for scanned EAN to appear...');
    await expect(page.getByText(new RegExp(EAN))).toBeVisible({ timeout: 15_000 });
    console.log('Clicking close pallet...');
    await page.getByRole('button', { name: /(Close pallet|Đóng pallet)/i }).click();
    console.log('Waiting for pallet to close...');
    await expect(page.getByText(/(Active pallet|Pallet đang mở)/i)).toBeHidden({ timeout: 15_000 });
    console.log('Pallet step done.');
  });

  await stepSubtitle(page, 'QC Inspector: Perform In-line Quality Control');
  await switchToAdmin(page);
  await test.step('perform quality control linked to the pallet', async () => {
    await page.goto('/admin/quality-tasks');
    const row = page.locator('tr', { hasText: WO }).first();
    await expect(row).toBeVisible({ timeout: 45_000 });
    await page.waitForTimeout(1500);
    
    // Click the "Perform" action button in the row's action menu
    const dialog = page.getByRole('dialog');
    await expect(async () => {
      // Try clicking via action menu button (kebab menu)
      const actionBtn = row.locator('button[aria-haspopup="menu"]').first();
      if (await actionBtn.isVisible()) {
        await actionBtn.click();
        const performItem = page.getByRole('menuitem', { name: /(Perform|Thực hiện)/i });
        if (await performItem.isVisible({ timeout: 2_000 }).catch(() => false)) {
          await performItem.click();
        }
      }
      // Fallback: direct button click
      if (!await dialog.isVisible().catch(() => false)) {
        await row.getByRole('button', { name: /(Perform|Thực hiện)/i }).first().click();
      }
      await expect(dialog).toBeVisible({ timeout: 3_000 });
    }).toPass({ timeout: 30_000 });

    // If pallet dropdown is visible, select our pallet
    const palletDropdown = dialog.locator('button[aria-haspopup="listbox"]').last();
    if (await palletDropdown.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await pickDropdown(palletDropdown, palletNo);
    }

    // Click "Record result" - uses Inertia router.post, not XHR
    console.log('Clicking Record result...');
    await page.getByRole('button', { name: /(Record result|Ghi nhận kết quả|Lưu)/i }).click();
    console.log('Waiting for dialog to close...');
    await expect(dialog).toBeHidden({ timeout: 15_000 });
    console.log('QC step done.');
  });

  await stepSubtitle(page, 'Supervisor: Ship the Passed Pallet');
  await test.step('ship the passed pallet', async () => {
    await page.goto('/admin/pallets');
    const palletRow = page.locator('tr', { hasText: palletNo });
    await expect(palletRow).toBeVisible({ timeout: 15_000 });
    await expect(palletRow).toContainText(/(Passed|Đạt)/i);
    await palletRow.locator('[data-action="Edit"]').click();
    await page.waitForURL(/\/admin\/pallets\/\d+\/edit/);
    await page.locator('form button[aria-haspopup="listbox"]').last().click();
    await page.getByRole('option', { name: /(Shipped|Đã xuất)/i }).click();
    await page.locator('form').getByRole('button', { name: /(Save|Create|Lưu|Tạo)/i }).first().click();
    await page.waitForURL(/\/admin\/pallets$/);
    await expect(page.locator('tr', { hasText: palletNo })).toContainText(/(Shipped|Đã xuất)/i);
  });

  await stepSubtitle(page, 'Supervisor: Report & Disposition a Non-Conformance');
  await test.step('set disposition on a non-conformance (#11)', async () => {
    
    // Switch to Operator session to report the issue
    await switchToOperator(page);
    await page.goto('/operator/queue');
    await page.getByText(WO, { exact: false }).first().click();
    await page.waitForURL(/\/operator\/work-order\/\d+/);

    // Click Report Issue
    await page.getByRole('button', { name: /(Report Issue|Báo cáo sự cố|\+ Report|\+ Báo cáo)/i }).first().click();
    const issueDialog = page.locator('.fixed.inset-0.z-50');
    await expect(issueDialog).toBeVisible({ timeout: 5_000 });
    
    // Fill Issue Type via dropdown (Select type...)
    await pickDropdown(issueDialog.locator('button[aria-haspopup="listbox"]').first(), /.+/);
    
    await issueDialog.locator('input[placeholder="Brief summary of the issue"]').fill(`Car NCR ${TS}`);
    await issueDialog.locator('textarea[placeholder*="Additional details"]').fill('Wheel scratched during assembly.');
    await issueDialog.getByRole('button', { name: /(Report Issue|Báo cáo)/i }).click();
    await expect(issueDialog).toBeHidden({ timeout: 15_000 });

    // Now switch to Admin view to set disposition
    await switchToAdmin(page);
    await page.goto('/admin/issues');
    
    // Open Disposition Modal
    const issueRow = page.locator('tr', { hasText: `Car NCR ${TS}` }).first();
    await expect(issueRow).toBeVisible({ timeout: 15_000 });
    
    // Click Disposition button directly in the row (ResourceTable renders actions as buttons)
    await issueRow.getByRole('button', { name: /(Disposition|Quyết định)/i }).first().click();
    
    const resolveDialog = page.getByRole('dialog');
    await expect(resolveDialog).toBeVisible({ timeout: 5_000 });
    
    // Pick the "Rework" disposition (Assuming option index or matching text)
    await pickDropdown(resolveDialog.locator('button[aria-haspopup="listbox"]').first(), /(Rework|Làm lại)/i);
    
    // Fill Root Cause (the first textarea)
    await resolveDialog.locator('textarea').first().fill('Moved to rework station to replace the wheel.');
    
    // Submit
    await resolveDialog.getByRole('button', { name: /(Record disposition|Ghi nhận)/i }).click();
    await expect(resolveDialog).toBeHidden({ timeout: 15_000 });
    
    await expect(issueRow).toContainText(/(Rework|Làm lại)/i);
  });

  await stepSubtitle(page, 'End-to-End Production Scenario Complete ✓');
  await page.screenshot({ path: 'test-results/car-production-buildout.png', fullPage: true });

  // Show overview screens in the clip as requested
  await stepSubtitle(page, 'Dashboard Overview');
  await page.goto('/admin/dashboard');
  await expect(page.getByText(/(Recent Work Orders|Lệnh Sản Xuất Đang Mở)/i)).toBeVisible({ timeout: 15_000 });
  await page.waitForTimeout(3000);

  await stepSubtitle(page, 'Work Orders Progress Overview');
  await page.goto('/admin/work-orders');
  await expect(page.locator('table')).toBeVisible({ timeout: 15_000 });
  await page.waitForTimeout(3000);

  });
