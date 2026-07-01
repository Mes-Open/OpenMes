const fs = require('fs');
const path = require('path');

const filesToPatch = [
    'material-lots/Create.jsx',
    'material-lots/Edit.jsx',
    'material-lots/Index.jsx'
];

const adminPath = 'backend/resources/js/Pages/admin';

filesToPatch.forEach(relPath => {
    const fullPath = path.join(adminPath, relPath);
    if (!fs.existsSync(fullPath)) return;
    
    let content = fs.readFileSync(fullPath, 'utf8');
    
    const depth = relPath.split('/').length;
    const backLevels = '../'.repeat(depth + 1);
    
    if (!content.includes('import { __ }')) {
        content = content.replace(/^(import.*?;)/m, `$1\nimport { __ } from '${backLevels}lib/i18n';`);
    }

    content = content.replace(/<Head title="([^"]+)"\s*\/>/g, '<Head title={__("$1")} />');
    content = content.replace(/(<ResourceForm[^>]*?)title="([^"]+)"/g, '$1title={__("$2")}');
    content = content.replace(/label:\s*'([^']+)'/g, "label: __('$1')");
    content = content.replace(/<h1([^>]*)>([^<\{]+)<\/h1>/g, '<h1$1>{__("$2")}</h1>');
    content = content.replace(/<h2([^>]*)>([^<\{]+)<\/h2>/g, '<h2$1>{__("$2")}</h2>');
    
    // Some specific inner texts
    content = content.replace(/>Dashboard</g, '>{__("Dashboard")}<');
    content = content.replace(/>Back</g, '>{__("Back")}<');
    content = content.replace(/>Material Lots</g, '>{__("Material Lots")}<');

    fs.writeFileSync(fullPath, content);
});

const viPath = 'backend/lang/vi.json';
let vi = JSON.parse(fs.readFileSync(viPath, 'utf8'));
Object.assign(vi, {
    "New Material Lot": "Lô vật liệu mới",
    "Material Lots": "Lô vật liệu"
});
fs.writeFileSync(viPath, JSON.stringify(vi, null, 4));

console.log('Material Lots localized.');
