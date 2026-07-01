const fs = require('fs');
const path = require('path');

const filesToPatch = [
    'lines/Create.jsx',
    'lines/Edit.jsx',
    'lines/Index.jsx',
    'workstations/Create.jsx',
    'workstations/Edit.jsx',
    'workstations/Index.jsx',
    'materials/Create.jsx',
    'materials/Edit.jsx',
    'materials/Index.jsx',
    'product-types/Create.jsx',
    'product-types/Edit.jsx',
    'product-types/Index.jsx',
    'product-types/Show.jsx',
    'process-templates/Create.jsx',
    'process-templates/Edit.jsx',
    'process-templates/Index.jsx',
    'process-templates/Show.jsx',
    'process-templates/steps/Create.jsx',
    'process-templates/steps/Edit.jsx',
    'work-orders/Create.jsx',
    'work-orders/Edit.jsx',
    'work-orders/Index.jsx'
];

const adminPath = 'backend/resources/js/Pages/admin';

filesToPatch.forEach(relPath => {
    const fullPath = path.join(adminPath, relPath);
    if (!fs.existsSync(fullPath)) return;
    
    let content = fs.readFileSync(fullPath, 'utf8');
    
    // Ensure `import { __ } from '...';` exists. 
    // Usually it's `../../../lib/i18n` or `../../lib/i18n`.
    // Let's count depth by the slashes in relPath.
    const depth = relPath.split('/').length; // e.g. lines/Create.jsx -> 2, steps/Create.jsx -> 3
    // Admin pages are in Pages/admin. lib is in js/lib.
    // Pages/admin/lines/Create.jsx -> ../../../lib/i18n
    // Pages/admin/process-templates/steps/Create.jsx -> ../../../../lib/i18n
    const backLevels = '../'.repeat(depth + 1);
    
    if (!content.includes('import { __ }')) {
        // Insert after first import
        content = content.replace(/^(import.*?;)/m, `$1\nimport { __ } from '${backLevels}lib/i18n';`);
    }

    // Replace <Head title="Something" /> with <Head title={__("Something")} />
    content = content.replace(/<Head title="([^"]+)"\s*\/>/g, '<Head title={__("$1")} />');

    // Replace title="Something" with title={__("Something")} inside ResourceForm
    content = content.replace(/(<ResourceForm[^>]*?)title="([^"]+)"/g, '$1title={__("$2")}');

    // Replace breadcrumbs label: 'Something' with label: __('Something')
    content = content.replace(/label:\s*'([^']+)'/g, "label: __('$1')");

    // Replace <h1 ...>Something</h1> with <h1 ...>{__("Something")}</h1>
    content = content.replace(/<h1([^>]*)>([^<\{]+)<\/h1>/g, '<h1$1>{__("$2")}</h1>');
    
    // Replace <h2 ...>Something</h2> with <h2 ...>{__("Something")}</h2>
    content = content.replace(/<h2([^>]*)>([^<\{]+)<\/h2>/g, '<h2$1>{__("$2")}</h2>');

    // Replace <span>Dashboard</span> with <span>{__("Dashboard")}</span> etc inside nav
    // For specific things like "Dashboard" / "Back" / "Active" / "Inactive"
    content = content.replace(/>Dashboard</g, '>{__("Dashboard")}<');
    content = content.replace(/>Back</g, '>{__("Back")}<');
    content = content.replace(/>Active</g, '>{__("Active")}<');
    content = content.replace(/>Inactive</g, '>{__("Inactive")}<');
    
    // Process Templates show page specifics
    content = content.replace(/>Process Templates</g, '>{__("Process Templates")}<');
    content = content.replace(/>Work Orders</g, '>{__("Work Orders")}<');
    content = content.replace(/>Recent Work Orders</g, '>{__("Recent Work Orders")}<');
    content = content.replace(/>No process templates yet</g, '>{__("No process templates yet")}<');
    content = content.replace(/>No work orders yet</g, '>{__("No work orders yet")}<');
    content = content.replace(/>Process templates define how this product is manufactured\.</g, '>{__("Process templates define how this product is manufactured.")}<');
    
    fs.writeFileSync(fullPath, content);
});

console.log('Admin pages localized.');
