const fs = require('fs');
const path = require('path');

const fullPath = 'backend/resources/js/Pages/admin/lines/Show.jsx';
let content = fs.readFileSync(fullPath, 'utf8');

if (!content.includes('import { __ }')) {
    content = content.replace(/^(import.*?;)/m, `$1\nimport { __ } from '../../../lib/i18n';`);
}

// Custom replacements for Lines Show
content = content.replace(/>Assigned Product Types</g, '>{__("Assigned Product Types")}<');
content = content.replace(/>Product types that can be produced on this line\.</g, '>{__("Product types that can be produced on this line.")}<');
content = content.replace(/>No product types assigned — all types are allowed\.</g, '>{__("No product types assigned — all types are allowed.")}<');
content = content.replace(/>Change assignment</g, '>{__("Change assignment")}<');
content = content.replace(/>Hide selector</g, '>{__("Hide selector")}<');
content = content.replace(/>No active product types defined yet\.</g, '>{__("No active product types defined yet.")}<');
content = content.replace(/>Leave all unchecked to allow all product types on this line\.</g, '>{__("Leave all unchecked to allow all product types on this line.")}<');
content = content.replace(/>Save Assignment</g, '>{__("Save Assignment")}<');
content = content.replace(/>Workstations</g, '>{__("Workstations")}<');
content = content.replace(/>No workstations configured — line itself acts as a single workstation\.</g, '>{__("No workstations configured — line itself acts as a single workstation.")}<');
content = content.replace(/>\s*workstation\(s\) on this line\.\s*</g, '> {__("workstation(s) on this line.")} <');
content = content.replace(/>Manage</g, '>{__("Manage")}<');
content = content.replace(/>virtual \(line = workstation\)</g, '>{__("virtual (line = workstation)")}<');
content = content.replace(/>Queue</g, '>{__("Queue")}<');
content = content.replace(/>Standard work order list with status, batches, priority and actions\.</g, '>{__("Standard work order list with status, batches, priority and actions.")}<');
content = content.replace(/>Workstation</g, '>{__("Workstation")}<');
content = content.replace(/>Flat production table with quantities, Z1\/Z2 shift inputs and inline entry\.</g, '>{__("Flat production table with quantities, Z1/Z2 shift inputs and inline entry.")}<');
content = content.replace(/>Workstation View</g, '>{__("Workstation View")}<');
content = content.replace(/>Select a view template that defines which columns operators see in the Workstation view for this line\.</g, '>{__("Select a view template that defines which columns operators see in the Workstation view for this line.")}<');
content = content.replace(/>View Template</g, '>{__("View Template")}<');
content = content.replace(/>— Default \(no custom columns\) —</g, '>{__("— Default (no custom columns) —")}<');
content = content.replace(/>Save</g, '>{__("Save")}<');
content = content.replace(/>Workstation View Columns</g, '>{__("Workstation View Columns")}<');
content = content.replace(/>Configure which columns operators see in the Workstation view for this line\.\s*Columns with source/g, '>{__("Configure which columns operators see in the Workstation view for this line. Columns with source")}<');
content = content.replace(/>No custom columns configured\. Default view will be shown\.</g, '>{__("No custom columns configured. Default view will be shown.")}<');
content = content.replace(/>Column Label</g, '>{__("Column Label")}<');
content = content.replace(/>Data Key</g, '>{__("Data Key")}<');
content = content.replace(/>Source</g, '>{__("Source")}<');
content = content.replace(/placeholder="e\.g\. Material"/g, 'placeholder={__("e.g. Material")}');
content = content.replace(/placeholder="e\.g\. material"/g, 'placeholder={__("e.g. material")}');
content = content.replace(/>\+ Add</g, '>{__("+ Add")}<');
content = content.replace(/>Save View Columns</g, '>{__("Save View Columns")}<');

fs.writeFileSync(fullPath, content);
console.log('Lines Show localized.');
