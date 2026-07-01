const fs = require('fs');
let code = fs.readFileSync('tests/e2e/car-production-buildout-en.spec.ts', 'utf8');

const replacements = [
  // Viewport & Locale
  ['viewport: { width: 1280, height: 720 }', 'viewport: { width: 1440, height: 810 }'],
  ["locale: 'vi-VN'", "locale: 'en-US'"],

  // Constants
  ['Dây chuyền lắp ráp ô tô', 'Car Assembly Line'],
  ['Xe điện Sedan', 'EV Sedan'],
  ['QC Trực tuyến', 'In-line QC'],

  // Subtitles
  ['Đăng nhập bằng tài khoản Quản trị viên (Admin)', 'Login as Administrator'],
  ['Tạo dây chuyền sản xuất:', 'Create Production Line:'],
  ['Tạo loại sản phẩm:', 'Create Product Type:'],
  ['Tạo quy trình và định mức vật tư (BOM)', 'Create Process Template & BOM (Bill of Materials)'],
  ['Tạo trạm sản xuất (Workstation)', 'Create Workstation on the Production Line'],
  ['Khai báo danh mục Nguyên vật liệu', 'Register Raw Materials (6 Components)'],
  ['Nhập kho lô vật tư ban đầu (Thiết lập tồn kho)', 'Create Initial Material Lots (Inventory Setup)'],
  ['Thêm cấu trúc BOM vào quy trình', 'Attach BOM Items to Process Template'],
  ['Tạo Lệnh sản xuất (Work Order) dự kiến 50 xe', 'Create Work Order for 50 EV Sedans'],
  ['Đăng ký dải mã vạch EAN cho Lệnh sản xuất', 'Register EAN Barcode for the Work Order'],
  ['Tạo trigger kiểm tra chất lượng tự động', 'Create In-line Quality Control Trigger'],
  ['MRP: Hệ thống phát hiện thiếu hụt linh kiện cho lệnh SX', 'MRP: System Detects Material Shortages'],
  ['Nhập kho bổ sung vật tư sau khi MRP báo thiếu', 'Receive Additional Materials After MRP Shortage Alert'],
  ['Tạo tài khoản Vận hành viên và Phân quyền', 'Create Operator Account & Assign to Line'],
  ['Vận hành viên đăng nhập vào hệ thống xưởng', 'Operator Logs Into the Shop Floor'],
  ['Operator khai báo trạng thái trạm: Cleaning -> Running', 'Operator Sets Machine State: Cleaning -> Running'],
  ['Operator nhận Lệnh sản xuất và bắt đầu Lô (Batch)', 'Operator Picks Work Order & Starts a Batch'],
  ['Khai báo đóng gói xe lên Pallet và quét mã vạch', 'Pack a Car onto a Pallet & Scan Barcode'],
  ['Nhân viên QC: Thực hiện kiểm tra chất lượng theo Trigger', 'QC Inspector: Perform In-line Quality Control'],
  ['Quản đốc: Xác nhận xuất hàng (Ship Pallet)', 'Supervisor: Ship the Passed Pallet'],
  ['Quản đốc: Ghi nhận sự cố không phù hợp (Non-conformance)', 'Supervisor: Report & Disposition a Non-Conformance'],
  ['Hoàn tất kịch bản sản xuất End-to-End', 'End-to-End Production Scenario Complete ✓'],
  ['Tổng quan bảng điều khiển (Dashboard)', 'Dashboard Overview'],
  ['Tổng quan tiến độ Lệnh Sản Xuất', 'Work Orders Progress Overview'],

  // Inputs
  ['Quy trình lắp ráp xe', 'EV Sedan Assembly Process'],
  ['Lắp ráp hoàn chỉnh & Kiểm tra', 'Final Assembly & Inspection'],
  ['Lắp ráp hoàn chỉnh', 'Final Assembly'],
  ['Trạm lắp ráp', 'Assembly Station'],
  ['Nhân viên vận hành', 'Operator'],
  ['Bánh xe bị trầy xước trong quá trình lắp.', 'Wheel scratched during assembly.'],
  ['Chuyển qua trạm làm lại để thay bánh xe.', 'Moved to rework station to replace the wheel.'],
];

replacements.forEach(([from, to]) => {
  // Escape regex special chars for 'from' string
  const escapedFrom = from.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
  code = code.replace(new RegExp(escapedFrom, 'g'), to);
});

fs.writeFileSync('tests/e2e/car-production-buildout-en.spec.ts', code);
console.log('Translations applied.');
