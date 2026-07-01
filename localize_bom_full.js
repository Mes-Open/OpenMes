const fs = require('fs');
const path = require('path');

const fullPath = 'backend/resources/js/Pages/admin/process-templates/Bom.jsx';
let content = fs.readFileSync(fullPath, 'utf8');

if (!content.includes('import { __ }')) {
    content = content.replace(/^(import.*?;)/m, `$1\nimport { __ } from '../../../lib/i18n';`);
}

content = content.replace(/<Head title="([^"]+)"\s*\/>/g, '<Head title={__("$1")} />');

content = content.replace(/>Dashboard</g, '>{__("Dashboard")}<');
content = content.replace(/>Back</g, '>{__("Back")}<');
content = content.replace(/>Process Templates</g, '>{__("Process Templates")}<');
content = content.replace(/>Bill of Materials</g, '>{__("Bill of Materials")}<');

content = content.replace(/Edit BOM Item - /g, '{__("Edit BOM Item")} - ');
content = content.replace(/>Add Material to BOM</g, '>{__("Add Material to BOM")}<');
content = content.replace(/Material <span/g, '{__("Material")} <span');
content = content.replace(/Quantity per Unit/g, '{__("Quantity per Unit")}');
content = content.replace(/Step \(optional\)/g, '{__("Step (optional)")}');
content = content.replace(/Scrap %/g, '{__("Scrap %")}');
content = content.replace(/Consumed At/g, '{__("Consumed At")}');
content = content.replace(/>Notes</g, '>{__("Notes")}<');
content = content.replace(/'Saving…' : 'Save Changes'/g, '__("Saving…") : __("Save Changes")');
content = content.replace(/'Adding…' : 'Add to BOM'/g, '__("Adding…") : __("Add to BOM")');
content = content.replace(/Remove this material from BOM\?/g, '{__("Remove this material from BOM?")}');
content = content.replace(/'Material'/g, "__('Material')");
content = content.replace(/'Type'/g, "__('Type')");
content = content.replace(/'Step'/g, "__('Step')");
content = content.replace(/>General</g, '>{__("General")}<');
content = content.replace(/'Qty\/Unit'/g, "__('Qty/Unit')");
content = content.replace(/'Actions'/g, "__('Actions')");
content = content.replace(/>No materials in BOM yet\.</g, '>{__("No materials in BOM yet.")}<');
content = content.replace(/>\+ Add Material</g, '>{__("+ Add Material")}<');
content = content.replace(/label="Active \(template is ready for use in work orders\)"/g, 'label={__("Active (template is ready for use in work orders)")}');

fs.writeFileSync(fullPath, content);

const viPath = 'backend/lang/vi.json';
let vi = JSON.parse(fs.readFileSync(viPath, 'utf8'));
Object.assign(vi, {
    "Bill of Materials": "Định mức vật liệu (BOM)",
    "BOM": "BOM",
    "Edit BOM Item": "Chỉnh sửa vật liệu BOM",
    "Add Material to BOM": "Thêm vật liệu vào BOM",
    "Material": "Vật liệu",
    "Quantity per Unit": "Số lượng mỗi Đơn vị",
    "Step (optional)": "Bước (tùy chọn)",
    "Scrap %": "% Hao hụt",
    "Consumed At": "Tiêu thụ lúc",
    "Notes": "Ghi chú",
    "Saving…": "Đang lưu…",
    "Save Changes": "Lưu thay đổi",
    "Adding…": "Đang thêm…",
    "Add to BOM": "Thêm vào BOM",
    "Remove this material from BOM?": "Xóa vật liệu này khỏi BOM?",
    "Type": "Loại",
    "Step": "Bước",
    "General": "Chung",
    "Qty/Unit": "SL/Đơn vị",
    "Actions": "Hành động",
    "No materials in BOM yet.": "Chưa có vật liệu nào trong BOM.",
    "+ Add Material": "+ Thêm vật liệu"
});
fs.writeFileSync(viPath, JSON.stringify(vi, null, 4));

console.log('BOM fully localized.');
