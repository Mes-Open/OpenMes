const fs = require('fs');
const path = require('path');

const filesToPatch = [
    'process-templates/Create.jsx',
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
    content = content.replace(/<h1([^>]*)>([^<\{]+)<\/h1>/g, '<h1$1>{__("$2")}</h1>');
    
    // Custom replacements for Create Process Template
    content = content.replace(/Back to Templates/g, '{__("Back to Templates")}');
    content = content.replace(/<label htmlFor="name" className="form-label">Template Name<\/label>/g, '<label htmlFor="name" className="form-label">{__("Template Name")}</label>');
    content = content.replace(/placeholder="e.g., Standard Assembly Process, Quality Inspection v2"/g, 'placeholder={__("e.g., Standard Assembly Process, Quality Inspection v2")}');
    content = content.replace(/Descriptive name for this manufacturing process/g, '{__("Descriptive name for this manufacturing process")}');
    content = content.replace(/<strong>Note:<\/strong>/g, '<strong>{__("Note:")}</strong>');
    content = content.replace(/Version number will be assigned automatically. After creating the\s*template, you'll be able to add production steps./g, '{__("Version number will be assigned automatically. After creating the template, you\'ll be able to add production steps.")}');
    content = content.replace(/label="Active \(template is ready for use in work orders\)"/g, 'label={__("Active (template is ready for use in work orders)")}');
    content = content.replace(/>\s*Cancel\s*<\/a>/g, '>{__("Cancel")}</a>');
    content = content.replace(/'Creating…' : 'Create Template'/g, '__("Creating…") : __("Create Template")');

    fs.writeFileSync(fullPath, content);
});

console.log('Admin pages localized part 2.');
