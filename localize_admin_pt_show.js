const fs = require('fs');
const path = require('path');

const fullPath = 'backend/resources/js/Pages/admin/process-templates/Show.jsx';
let content = fs.readFileSync(fullPath, 'utf8');

// Replace standard stuff if missed
if (!content.includes('import { __ }')) {
    content = content.replace(/^(import.*?;)/m, `$1\nimport { __ } from '../../../lib/i18n';`);
}
content = content.replace(/<Head title="([^"]+)"\s*\/>/g, '<Head title={__("$1")} />');

// Custom replacements for Process Template Show
content = content.replace(/Back to Templates/g, '{__("Back to Templates")}');
content = content.replace(/>Edit Template</g, '>{__("Edit Template")}<');
content = content.replace(/>\+ Add Step</g, '>{__("+ Add Step")}<');
content = content.replace(/>Production Steps \(first to last\)</g, '>{__("Production Steps (first to last)")}<');
content = content.replace(/>No production steps yet</g, '>{__("No production steps yet")}<');
content = content.replace(/>Add steps to define the manufacturing process for this product\.</g, '>{__("Add steps to define the manufacturing process for this product.")}<');
content = content.replace(/>\+ Add First Step</g, '>{__("+ Add First Step")}<');
content = content.replace(/>General Reference Photos \(0\/20\)</g, '>{__("General Reference Photos (0/20)")}<');
content = content.replace(/>Photo \(JPEG\/PNG\/WebP, max 10 MB\)</g, '>{__("Photo (JPEG/PNG/WebP, max 10 MB)")}<');
content = content.replace(/>Caption</g, '>{__("Caption")}<');
content = content.replace(/>Upload Photo</g, '>{__("Upload Photo")}<');
content = content.replace(/>Are you sure you want to delete this step\?</g, '>{__("Are you sure you want to delete this step?")}<');
content = content.replace(/>Are you sure you want to delete this photo\?</g, '>{__("Are you sure you want to delete this photo?")}<');

fs.writeFileSync(fullPath, content);

const ctrlPath = 'backend/app/Http/Controllers/Web/Admin/ProcessTemplateManagementController.php';
let ctrl = fs.readFileSync(ctrlPath, 'utf8');
ctrl = ctrl.replace(/'Process template created successfully\. Now add production steps\.'/g, "__('Process template created successfully. Now add production steps.')");
fs.writeFileSync(ctrlPath, ctrl);

console.log('Admin pages localized part 3.');
