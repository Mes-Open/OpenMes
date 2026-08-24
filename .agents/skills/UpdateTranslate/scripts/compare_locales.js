// UpdateTranslate — Bước 2: So sánh en.json vs vi.json (backend + mobile)
// Chạy: node compare_locales.js
// In ra các key có trong en.json nhưng THIẾU trong vi.json (cần dịch),
// và các key THỪA trong vi.json không có trong en.json (cần xem xét xóa).

const fs = require('fs');
const path = require('path');
const os = require('os');

// Tự động tìm workspace root: folder chứa .git — lấy từ nơi đặt script này
// Script nằm tại: <workspace>/.agents/skills/UpdateTranslate/scripts/compare_locales.js
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

const scriptDir = __dirname;
const workspace = findWorkspaceRoot(scriptDir);
if (!workspace) {
  console.error('Không tìm thấy workspace root (.git). Chạy script từ trong repo OpenMes.');
  process.exit(1);
}

const targets = [
  { name: 'backend', en: path.join(workspace, 'backend/lang/en.json'), vi: path.join(workspace, 'backend/lang/vi.json') },
  { name: 'mobile', en: path.join(workspace, 'mobile/lang/en.json'), vi: path.join(workspace, 'mobile/lang/vi.json') },
];

// Hỗ trợ ghi output ra file nếu dài: node compare_locales.js [output.txt]
const outFile = process.argv[2];
let out = '';

function log(s = '') {
  console.log(s);
  out += s + '\n';
}

for (const t of targets) {
  log(`\n===== ${t.name} =====`);
  const en = JSON.parse(fs.readFileSync(t.en, 'utf8'));
  const vi = JSON.parse(fs.readFileSync(t.vi, 'utf8'));
  const enKeys = Object.keys(en);
  const viKeys = Object.keys(vi);
  const missing = enKeys.filter(k => !(k in vi));
  const extra = viKeys.filter(k => !(k in en));

  log(`en keys: ${enKeys.length} | vi keys: ${viKeys.length}`);
  log(`MISSING in vi.json (${missing.length}):`);
  for (const k of missing) log(`  [${k}] => ${JSON.stringify(en[k])}`);
  log(`EXTRA in vi.json not in en.json (${extra.length}):`);
  for (const k of extra) log(`  [${k}] => ${JSON.stringify(vi[k])}`);
}

if (outFile) {
  fs.writeFileSync(path.join(process.cwd(), outFile), out, 'utf8');
  console.log(`\nĐã ghi output đầy đủ vào: ${path.resolve(outFile)}`);
}
