// UpdateTranslate — Bước 3: Tạo template các key thiếu cần dịch
// Chạy: node translate_missing.js
// Sinh ra file missing_keys.json chứa các key thiếu (giá trị = tiếng Anh gốc,
// để agent điền bản dịch tiếng Việt vào). Sau đó chạy merge_translations.js để merge.
//
// Quy trình dịch 3 bước:
//   1) node translate_missing.js          -> sinh missing_keys.json
//   2) Agent ĐIỀN bản dịch tiếng Việt vào missing_keys.json (giá trị mỗi key)
//   3) node merge_translations.js         -> merge vào vi.json + verify
//   4) node validate.js                   -> validate JSON
const fs = require('fs');
const path = require('path');
const os = require('os');

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

const backendMissing = getMissing(path.join(workspace, 'backend/lang/en.json'), path.join(workspace, 'backend/lang/vi.json'));
const mobileMissing = getMissing(path.join(workspace, 'mobile/lang/en.json'), path.join(workspace, 'mobile/lang/vi.json'));

const out = {
  note: 'Điền bản dịch tiếng Việt vào value của từng key. KHÔNG sửa key. Giữ placeholder (:count, :name, {{n}}...) và dấu câu. Dùng chuẩn thuật ngữ trong references/terminology.md',
  backend: backendMissing,
  mobile: mobileMissing,
};

const outPath = path.join(process.cwd(), 'missing_keys.json');
fs.writeFileSync(outPath, JSON.stringify(out, null, 2), 'utf8');
console.log(`Đã sinh: ${outPath}`);
console.log(`  backend missing: ${Object.keys(backendMissing).length}`);
console.log(`  mobile missing:  ${Object.keys(mobileMissing).length}`);

function getMissing(enPath, viPath) {
  const en = JSON.parse(fs.readFileSync(enPath, 'utf8'));
  const vi = JSON.parse(fs.readFileSync(viPath, 'utf8'));
  const missing = {};
  for (const k of Object.keys(en)) {
    if (!(k in vi)) missing[k] = en[k];
  }
  return missing;
}
