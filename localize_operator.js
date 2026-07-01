const fs = require('fs');

const opFiles = [
    'backend/resources/js/Pages/operator/SelectLine.jsx',
    'backend/resources/js/Pages/operator/Queue.jsx',
    'backend/resources/js/Pages/operator/Workstation.jsx',
    'backend/resources/js/Pages/operator/WorkOrder.jsx'
];

opFiles.forEach(fullPath => {
    if (!fs.existsSync(fullPath)) return;
    let content = fs.readFileSync(fullPath, 'utf8');

    if (!content.includes('import { __ }')) {
        content = content.replace(/^(import.*?;)/m, `$1\nimport { __ } from '../../lib/i18n';`);
    }

    content = content.replace(/<Head title="([^"]+)"\s*\/>/g, '<Head title={__("$1")} />');
    content = content.replace(/<h1([^>]*)>([^<\{]+)<\/h1>/g, '<h1$1>{__("$2")}</h1>');
    content = content.replace(/<h2([^>]*)>([^<\{]+)<\/h2>/g, '<h2$1>{__("$2")}</h2>');
    content = content.replace(/<h3([^>]*)>([^<\{]+)<\/h3>/g, '<h3$1>{__("$2")}</h3>');
    content = content.replace(/label="([^"]+)"/g, (match, p1) => {
        if (p1.match(/^[a-z]+$/)) return match; // skip like label="running" or similar props
        return `label={__("${p1}")}`;
    });
    
    // Generic text replacements
    const textsToWrap = [
        "Choose a production line and optionally a workstation",
        "No lines assigned",
        "You are not assigned to any production lines. Please contact your administrator.",
        "Select Workstation",
        "All Workstations",
        "Select",
        "Active Work Orders",
        "No active work orders at the moment.",
        "Process Details",
        "Steps",
        "Production Steps",
        "Select a workstation to see steps",
        "No steps defined for this work order.",
        "Materials & Serialized Parts",
        "No materials required.",
        "Recent Serials",
        "No serials produced yet.",
        "Create Batch"
    ];

    textsToWrap.forEach(text => {
        const regex = new RegExp(`>\\s*${text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*<`, 'g');
        content = content.replace(regex, `>{__("${text}")}<`);
    });

    fs.writeFileSync(fullPath, content);
});

const viPath = 'backend/lang/vi.json';
let vi = JSON.parse(fs.readFileSync(viPath, 'utf8'));

const opKeys = {
    "Select Production Line": "Chọn dây chuyền sản xuất",
    "Choose a production line and optionally a workstation": "Chọn một dây chuyền sản xuất và tùy chọn một trạm làm việc",
    "No lines assigned": "Không có dây chuyền nào được phân công",
    "You are not assigned to any production lines. Please contact your administrator.": "Bạn chưa được phân công vào bất kỳ dây chuyền sản xuất nào. Vui lòng liên hệ với quản trị viên.",
    "Select Workstation": "Chọn trạm làm việc",
    "All Workstations": "Tất cả các trạm làm việc",
    "Select": "Chọn",
    "Active Work Orders": "Lệnh sản xuất đang mở",
    "No active work orders at the moment.": "Hiện tại không có lệnh sản xuất nào đang mở.",
    "Process Details": "Chi tiết quy trình",
    "Steps": "Các bước",
    "Production Steps": "Các bước sản xuất",
    "Select a workstation to see steps": "Chọn một trạm làm việc để xem các bước",
    "No steps defined for this work order.": "Không có bước nào được định nghĩa cho lệnh sản xuất này.",
    "Materials & Serialized Parts": "Vật liệu & Linh kiện Serial",
    "No materials required.": "Không yêu cầu vật liệu.",
    "Recent Serials": "Serial gần đây",
    "No serials produced yet.": "Chưa có serial nào được sản xuất.",
    "Create Batch": "Tạo lô",
    "Active": "Đang hoạt động"
};

Object.assign(vi, opKeys);
fs.writeFileSync(viPath, JSON.stringify(vi, null, 4));

console.log('Operator pages localized.');
