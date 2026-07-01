const fs = require('fs');
let content = fs.readFileSync('backend/resources/js/Pages/admin/lines/Show.jsx', 'utf8');

const replacements = [
    ['<h2 className="text-xl font-bold text-om-ink">Line Statuses</h2>', '<h2 className="text-xl font-bold text-om-ink">{__(\'Line Statuses\')}</h2>'],
    ['Kanban statuses available for work orders on this line. Global statuses are shown in gray.', '{__(\'Kanban statuses available for work orders on this line. Global statuses are shown in gray.\')}'],
    ['Manage global statuses →', '{__(\'Manage global statuses\')} →'],
    ['No statuses yet. Add one below or ', '{__(\'No statuses yet. Add one below or\')} '],
    ['manage global statuses', '{__(\'manage global statuses\')}'],
    ['(default)', '({__(\'default\')})'],
    ['<span className="text-xs opacity-60">global</span>', '<span className="text-xs opacity-60">{__(\'global\')}</span>'],
    ['title="Delete"', 'title={__(\'Delete\')}'],
    ['<label className="text-xs text-om-muted">Color</label>', '<label className="text-xs text-om-muted">{__(\'Color\')}</label>'],
    ['<label className="text-xs text-om-muted">Status name (line-specific)</label>', '<label className="text-xs text-om-muted">{__(\'Status name (line-specific)\')}</label>'],
    ['placeholder="e.g. Waiting for parts"', 'placeholder={__(\'e.g. Waiting for parts\')}'],
    ['<label className="text-xs text-om-muted">Order</label>', '<label className="text-xs text-om-muted">{__(\'Order\')}</label>'],
    ['Add to this line\n', '{__(\'Add to this line\')}\n'],
    ['<span>{open ? \'Hide selector\' : \'Change assignment\'}</span>', '<span>{open ? __(\'Hide selector\') : __(\'Change assignment\')}</span>'],
    ['Leave all unchecked to allow all product types on this line.', '{__(\'Leave all unchecked to allow all product types on this line.\')}'],
    ['Save Assignment\n', '{__(\'Save Assignment\')}\n'],
    ['No operators assigned yet', '{__(\'No operators assigned yet\')}'],
    ['{WORK_ORDER_STATUS_LABELS[wo.status] ?? wo.status}', '{__(WORK_ORDER_STATUS_LABELS[wo.status] ?? wo.status)}'],
    ['Select a view template that defines which columns operators see in the Workstation view for this line.', '{__(\'Select a view template that defines which columns operators see in the Workstation view for this line.\')}'],
    ['{ value: \'\', label: \'— Default (no custom columns) —\' },', '{ value: \'\', label: __(\'— Default (no custom columns) —\') },'],
    ['Save\n', '{__(\'Save\')}\n'],
    ['Save View Columns\n', '{__(\'Save View Columns\')}\n'],
    ['Back to Production Lines', '{__(\'Back to Production Lines\')}']
];

for (const [search, replace] of replacements) {
    if (content.includes(search)) {
        content = content.replace(search, replace);
    } else {
        console.warn('Could not find:', search);
    }
}

fs.writeFileSync('backend/resources/js/Pages/admin/lines/Show.jsx', content);
console.log('Translated Show.jsx');
