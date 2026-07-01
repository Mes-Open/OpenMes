const fs = require('fs');

const viPath = 'backend/lang/vi.json';
let vi = JSON.parse(fs.readFileSync(viPath, 'utf8'));

const newKeys = {
    // Work Orders
    "Order No": "Mã Lệnh Sản Xuất",
    "Customer Order No": "Mã Đơn Hàng (PO)",
    "Line": "Dây chuyền",
    "Product Type": "Loại sản phẩm",
    "Planned Qty": "Sản lượng Kế hoạch",
    "Priority (0–100)": "Mức độ ưu tiên (0-100)",
    "Due Date": "Hạn chót",
    "Description": "Mô tả",
    "Status": "Trạng thái",
    "— None —": "— Không có —",

    // Work Order Statuses
    "Pending": "Chờ duyệt",
    "Accepted": "Đã duyệt",
    "In Progress": "Đang sản xuất",
    "Paused": "Tạm dừng",
    "Blocked": "Đang vướng mắc",
    "Done": "Hoàn thành",
    "Rejected": "Bị từ chối",
    "Cancelled": "Đã hủy",

    // Material Lots
    "Lot Number": "Mã lô (Lot No)",
    "Material": "Vật liệu/Vật tư",
    "Initial Quantity": "Số lượng ban đầu",
    "Cost": "Chi phí nhập",
    "Supplier": "Nhà cung cấp",
    "Expiration Date": "Ngày hết hạn (EXP)",
    "Location": "Vị trí lưu kho",

    // Lines & Workstations
    "Code": "Mã",
    "Name": "Tên",
    "Area": "Khu vực",
    "Type": "Phân loại",
    "Is Line Itself": "Tự động coi Dây chuyền như một Trạm",

    // Materials
    "Material Type": "Loại vật tư",
    "Unit of Measure": "Đơn vị tính (UoM)",
    "Default Scrap %": "% Hao hụt tiêu chuẩn",

    // Product Types
    "Is Active": "Trạng thái hoạt động",
    
    // Process Templates
    "Segment Type": "Loại phân đoạn",
    "Estimated Duration (minutes)": "Thời gian ước tính (phút)",
    "Instruction": "Hướng dẫn thực hiện"
};

Object.assign(vi, newKeys);
fs.writeFileSync(viPath, JSON.stringify(vi, null, 4));

console.log('vi.json updated with manufacturing terminology.');
