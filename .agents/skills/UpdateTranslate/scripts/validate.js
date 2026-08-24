// UpdateTranslate — Bước 5: Validate JSON + rà soát thuật ngữ chuẩn
// Chạy: node validate.js
// - Kiểm tra backend/lang/vi.json và mobile/lang/vi.json hợp lệ JSON
// - Rà soát key còn chứa thuật ngữ cũ (sai chuẩn) — KHÔNG PHÂN BIỆT HOA THƯỜNG
// - Kiểm tra ngữ cảnh đặc biệt (VD: "Short" trong MRP = Thiếu, không phải ngắn)

const fs = require('fs');
const path = require('path');

function findWorkspaceRoot(startDir) {
  let dir = startDir;
  for (let i = 0; i < 10; i++) {
    if (fs.existsSync(path.join(dir, '.git'))) return dir;
    const parent = path.dirname(dir);
    if (parent === dir) return null;
    dir = parent;
  }
  return null;
}

const workspace = findWorkspaceRoot(__dirname);
if (!workspace) {
  console.error('Không tìm thấy workspace root (.git).');
  process.exit(1);
}

const files = ['backend/lang/vi.json', 'mobile/lang/vi.json'];
let ok = true;

// Chuẩn từ vựng (xem references/terminology.md) — regex bỏ qua hoa/thường
const badTermPatterns = [
  // Lệnh làm việc / LỆNH LÀM VIỆC / lệnh làm việc / Lệnh công việc / LỆNH CÔNG VIỆC...
  { pattern: /l[êe]nh\s+(l[àa]m vi[ệe]c|c[ôo]ng vi[ệe]c)/i, label: 'Lệnh làm việc / Lệnh công việc (phải là Lệnh sản xuất)' },
  { pattern: /v[ậa]t t[ưu]/i, label: 'Vật tư (phải là Vật liệu)' },
  { pattern: /v[ậa]n h[àa]nh vi[êe]n\s*\(operator\)/i, label: 'Vận hành viên (Operator) (phải là Người vận hành)' },
  { pattern: /s[ẵa]n c[óo]\s*×|s[ẵa]n s[àa]ng\s*×/i, label: 'Công thức OEE sai (phải là Khả dụng × Hiệu suất × Chất lượng)' },
  { pattern: /c[áa]i,\s*kg,\s*m/i, label: 'Đơn vị "cái" (phải là sp, kg, m)' },
];

for (const f of files) {
  const p = path.join(workspace, f);
  try {
    const vi = JSON.parse(fs.readFileSync(p, 'utf8'));
    console.log(`✅ ${f}: JSON hợp lệ (${Object.keys(vi).length} keys)`);

    for (const { pattern, label } of badTermPatterns) {
      const hits = Object.entries(vi).filter(([k, v]) => pattern.test(v));
      if (hits.length) {
        ok = false;
        console.log(`  ❌ ${label} — còn ${hits.length} chỗ:`);
        hits.slice(0, 15).forEach(([k, v]) => console.log(`     [${k}] => ${v}`));
      } else {
        console.log(`  ✅ ${label}: OK`);
      }
    }

    // Kiểm tra key "Short" trong ngữ cảnh MRP: phải là "Thiếu" (thiếu hụt), KHÔNG phải "ngắn" (chiều dài)
    if ('Short' in vi && vi.Short !== 'Thiếu') {
      ok = false;
      console.log(`  ❌ Key "Short" = "${vi.Short}" — trong MRP phải là "Thiếu" (Shortfall/Shortages cùng nhóm)`);
    } else {
      console.log(`  ✅ Key "Short" (MRP context): ${vi.Short || '(missing) — OK nếu en không có key này'}`);
    }

    // Kiểm tra nhóm MRP nhất quán
    for (const groupKey of ['Shortfall', 'Shortages']) {
      if (groupKey in vi && vi[groupKey] && !vi[groupKey].toLowerCase().includes('thiếu')) {
        ok = false;
        console.log(`  ❌ Key "${groupKey}" = "${vi[groupKey]}" — nên dùng "Thiếu hụt" cho đồng bộ MRP`);
      }
    }
  } catch (e) {
    ok = false;
    console.log(`❌ ${f}: LỖI JSON — ${e.message}`);
  }
}

console.log(ok ? '\n✅ TẤT CẢ ĐẠT — sẵn sàng commit.' : '\n❌ Có vấn đề cần sửa trước khi commit.');
process.exit(ok ? 0 : 1);
