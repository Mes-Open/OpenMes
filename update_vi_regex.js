const fs = require('fs');
let code = fs.readFileSync('tests/e2e/car-production-buildout-vi.spec.ts', 'utf8');

const replacements = [
  [/Raw Material\|Nguyên vật liệu/g, 'Raw Material|Nguyên vật liệu|nguyên liệu thô'],
  [/Batch/g, 'Batch|Lô'], // For pickFormSelect with /Batch/i
  [/\(Released\|Phát hành\)/g, '(Released|Phát hành|Đã phát hành)'],
  [/\(Passed\|Đạt\)/g, '(Passed|Đạt|Đã đậu)'],
  [/\(Shipped\|Đã xuất\)/g, '(Shipped|Đã xuất|Đã vận chuyển)'],
  [/\(Active Work Orders\|Lệnh Sản Xuất Đang Mở\)/g, '(Active Work Orders|Lệnh Sản Xuất Đang Mở|Lệnh làm việc đang hoạt động)'],
  [/\(Active pallet\|Pallet đang mở\)/g, '(Active pallet|Pallet đang mở|Pallet hoạt động)'],
  [/\(Disposition\|Quyết định\)/g, '(Disposition|Quyết định|Bố trí)'],
  [/Operator\|Vận hành/g, 'Operator|Vận hành|Người vận hành'],
  [/\(Create Batch\|Tạo lô\|Thêm\)/g, '(Create Batch|Tạo lô|Tạo hàng loạt|Thêm)'],
  [/\(Assign\|Phân công\)/g, '(Assign|Phân công|Chỉ định)'],
  [/\(Record result\|Ghi nhận kết quả\|Lưu\)/g, '(Record result|Ghi nhận kết quả|Ghi kết quả|Lưu)'],
  [/\(Record disposition\|Ghi nhận\)/g, '(Record disposition|Ghi nhận|Ghi lại cách xử lý)'],
];

replacements.forEach(([regex, replacement]) => {
  code = code.replace(regex, replacement);
});

// For /Batch/i, the literal in the code is /Batch/i. Wait, replace /Batch/g will change that to /Batch|Lô/i.
// Let's refine the replacement for Batch.
code = code.replace(/pickFormSelect\(page, 1, \/Batch\|Lô\/i\)/, 'pickFormSelect(page, 1, /Batch|Lô/i)'); // cleanup if multiple runs happen

fs.writeFileSync('tests/e2e/car-production-buildout-vi.spec.ts', code);
console.log('Regexes updated.');
