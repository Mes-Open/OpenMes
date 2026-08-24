// UpdateTranslate — Bước 3 (tiếp): Merge bản dịch từ missing_keys.json vào vi.json
// Chạy SAU KHI agent đã điền bản dịch đầy đủ vào missing_keys.json
// Lệnh: node merge_translations.js
// - Merge các key đã dịch vào backend/lang/vi.json và mobile/lang/vi.json
// - Giữ thứ tự key hiện có, key mới thêm cuối file
// - Verify stillMissing = 0

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

const missingPath = path.join(process.cwd(), 'missing_keys.json');
if (!fs.existsSync(missingPath)) {
  console.error('Thiếu missing_keys.json — chạy translate_missing.js trước.');
  process.exit(1);
}

const data = JSON.parse(fs.readFileSync(missingPath, 'utf8'));

// Lưu bản EN gốc để kiểm tra "trùng tiếng Anh" (chưa dịch)
const enBackend = JSON.parse(fs.readFileSync(path.join(workspace, 'backend/lang/en.json'), 'utf8'));
const enMobile = JSON.parse(fs.readFileSync(path.join(workspace, 'mobile/lang/en.json'), 'utf8'));

function merge(langPath, map, label) {
  const vi = JSON.parse(fs.readFileSync(langPath, 'utf8'));
  let added = 0;
  const skipped = [];
  for (const [k, v] of Object.entries(map)) {
    if (typeof v !== 'string' || v.trim() === '') { skipped.push(k + ' (rỗng)'); continue; }
    if (v === enBackend[k] || v === enMobile[k]) { skipped.push(k + ' (chưa dịch, trùng EN)'); continue; }
    if (k in vi) { skipped.push(k + ' (đã có)'); continue; }
    vi[k] = v;
    added++;
  }
  fs.writeFileSync(langPath, JSON.stringify(vi, null, 4) + '\n', 'utf8');
  console.log(`${label}: đã thêm ${added}, bỏ qua ${skipped.length}: ${skipped.join(', ')}`);
  return added;
}

merge(path.join(workspace, 'backend/lang/vi.json'), data.backend, 'backend');
merge(path.join(workspace, 'mobile/lang/vi.json'), data.mobile, 'mobile');

// Verify
for (const t of [
  { name: 'backend', en: path.join(workspace, 'backend/lang/en.json'), vi: path.join(workspace, 'backend/lang/vi.json') },
  { name: 'mobile', en: path.join(workspace, 'mobile/lang/en.json'), vi: path.join(workspace, 'mobile/lang/vi.json') },
]) {
  const en = JSON.parse(fs.readFileSync(t.en, 'utf8'));
  const vi = JSON.parse(fs.readFileSync(t.vi, 'utf8'));
  const stillMissing = Object.keys(en).filter(k => !(k in vi));
  console.log(`\nVERIFY ${t.name}: en=${Object.keys(en).length} vi=${Object.keys(vi).length} stillMissing=${stillMissing.length}`);
  if (stillMissing.length) console.log('  ' + stillMissing.join('\n  '));
}

// Xóa file tạm
fs.unlinkSync(missingPath);
console.log('\nĐã xóa missing_keys.json (temp).');
