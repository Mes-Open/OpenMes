# Chuẩn thuật ngữ tiếng Việt — Ngành Sản xuất (OpenMes)

> Tài liệu chuẩn bắt buộc dùng khi dịch `en.json` → `vi.json`.
> Mọi bản dịch mới PHẢI theo bảng này. Không tạo biến thể mới cho từ đã có chuẩn.

---

## 1. Thuật ngữ nền tảng (đã chốt — không thay đổi)

| Tiếng Anh | Tiếng Việt CHUẨN | Ghi chú |
|---|---|---|
| Work order | **Lệnh sản xuất** | TUYỆT ĐỐI không dùng "lệnh làm việc" |
| Workstation | **Trạm làm việc** | Ổn định |
| Production line | **Dây chuyền sản xuất** | |
| Area | **Khu vực** | Level giữa Site và Line |
| Site | **Cơ sở** | |
| Factory | **Nhà máy** | |
| Shift | **Ca** | |
| Batch | **Lô** | Lô sản xuất (grouped lots) |
| Lot | **Lô** / **Lô vật liệu** | Lot = lô nguyên vật liệu |
| Quantity | **Số lượng** | |
| Planned quantity | **Số lượng dự kiến** | |
| Downtime | **Thời gian dừng máy** | |
| OEE | **Hiệu suất OEE** | Không dịch OEE |
| Operator | **Người vận hành** | Không dùng "Vận hành viên (Operator)" |
| Supervisor | **Giám sát viên** | |
| Worker | **Công nhân** | |
| Crew | **Tổ làm việc** | |
| Material | **Vật liệu** | KHÔNG dùng "Vật tư" |
| Materials | **Vật liệu** | |
| Component | **Linh kiện / Vật liệu** | |
| Scrap | **Phế liệu** / **Phế phẩm** | Scrap quantity = Số lượng phế liệu; Scrap item = Phế phẩm |
| Rework | **Làm lại / Gia công lại** | tùy ngữ cảnh, ưu tiên "Gia công lại" cho action |
| Changeover | **Chuyển đổi** | |
| Setup | **Thiết lập** | |
| Station | **Trạm** | |
| Machine | **Máy** | |
| Inspection | **Kiểm tra** | |
| Issues | **Sự cố / Vấn đề** | Issues = Sự cố (bảng alerte); Issue type = Loại vấn đề |
| Stock / Inventory | **Tồn kho** | |
| Order | **Lệnh** | Order đơn thuần; Work order = Lệnh sản xuất |
| Schedule | **Lịch trình** | |
| Backlog | **Hàng chờ** | KHÁC "Hàng đợi" (Queue) |
| Queue | **Hàng đợi** | Work Order Queue = Hàng đợi Lệnh sản xuất |
| Availability (OEE) | **Khả dụng** | |
| Performance (OEE) | **Hiệu suất** | |
| Quality (OEE) | **Chất lượng** | |
| pcs | **sp** (viết tắt sản phẩm) | Không dùng "cái" cho đơn vị |
| Nameplate rate | **Tỷ lệ định mức** | |
| Actual output / Expected output | **Sản lượng thực tế / Sản lượng dự kiến** | |
| Speed loss | **Mất tốc độ** | |
| Reduced speed | **Giảm tốc độ** | |
| Effective run time | **Thời gian chạy hiệu quả** | |
| Escalate | **Chuyển cấp** | Escalate to maintenance = Chuyển lên bảo trì |

## 2. Từ viết tắt / thuật ngữ GIỮ NGUYÊN (không dịch)

OEE, BOM, MRP, QA, QC, ANDON, MTTR, EAN, PDF, CSV, ISO, SOP, API, MQTT,
Modbus, OPC UA, ZPL, TOTP, 2FA, PIN, LOT, JSON, HTTP, CORS, RFID.

## 3. Trạng thái & badge IN HOA — giữ IN HOA khi dịch

| EN | VI |
|---|---|
| STOPPED | **ĐÃ DỪNG** |
| IDLE | **RẢNH RỖI** |
| FAULT | **LỖI** |
| SETUP | **THIẾT LẬP** |
| CLEANING | **VỆ SINH** |
| RUNNING | **ĐANG CHẠY** |
| ACTIVE | **HOẠT ĐỘNG** |
| BLOCKED | **ĐÃ CHẶN** |
| CANCELLED | **ĐÃ HỦY** |
| COMPLETED | **ĐÃ HOÀN THÀNH** |
| CLOSED | **ĐÓNG CỬA** |
| READY | **SẴN SÀNG** |
| PASSED | **ĐẠT** |
| PLANNED | **DỰ KIẾN** |
| SKIPPED | **ĐÃ BỎ QUA** |
| WAITING | **ĐANG CHỜ** |
| CLASSIFIED | **ĐÃ PHÂN LOẠI** |

## 4. Động từ thao tác chuẩn (bulk actions)

| EN | VI |
|---|---|
| Accept | **Chấp nhận** |
| Reject | **Từ chối** |
| Pause | **Tạm dừng** |
| Resume | **Tiếp tục** |
| Reopen | **Mở lại** |
| Cancel | **Hủy** |
| Delete | **Xóa** |
| Acknowledge | **Xác nhận** |
| Complete | **Hoàn thành** |
| Start | **Bắt đầu** |
| Close | **Đóng** |

## 5. Cảnh báo khi dịch key mới

- **Giữ placeholder** `:count`, `:name`, `:n`, `:m`, `:id`, `{{n}}`, `{{action}}`, `:step`, `:station`... nguyên vị trí, không đổi thứ tự.
- Giữ dấu câu / em-dash (`—`) / mũi tên (`→`) / bullet (`·`) như key EN.
- Câu nghi vấn (có `?`) dịch thành câu hỏi tiếng Việt có `?`.
- Tin cậy vào ngữ cảnh: "Step" trong nhà máy = **Bước công đoạn** (không phải "bước đường").
- Khi gặp khái niệm hoàn toàn mới không có trong bảng: **dừng và hỏi user** để chốt chuẩn trước khi dịch (đừng tự bịa biến thể).

## 6. Các thuật ngữ từng bị dịch SAI (đã sửa — không tái phạm)

| SAI (đừng dùng) | ĐÚNG |
|---|---|
| Lệnh làm việc | Lệnh sản xuất |
| Lệnh công việc | Lệnh sản xuất |
| Vật tư | Vật liệu |
| Vận hành viên (Operator) | Người vận hành |
| Sẵn có × Hoàn hảo × Chất lượng | Khả dụng × Hiệu suất × Chất lượng |
| Sẵn sàng × Hiệu suất × Chất lượng | Khả dụng × Hiệu suất × Chất lượng |
| Hàng đợi (cho Backlog) | Hàng chờ |
| "cái" (cho đơn vị pcs) | sp |
| Short = "ngắn" (trong nhóm MRP) | **Thiếu** (thiếu hụt nguyên vật liệu) |

> [!WARNING]
> **TRA CỨU THEO MỌI BIẾN THỂ HOA/THƯỜNG** — Bug đã gặp với CodeRabbit:
> Khi thay thuật ngữ, phải thay **mọi biến thể**:
> `lệnh làm việc`, `Lệnh làm việc`, `LỆNH LÀM VIỆC`, `Lệnh công việc`, `LỆNH CÔNG VIỆC`...
> Nếu chỉ `split('lệnh làm việc')` (case-sensitive) sẽ **bỏ sót** các biến thể hoa → CodeRabbit phàn nàn.
> Dùng regex `/l[êe]nh\s+(l[àa]m vi[ệe]c|c[ôo]ng vi[ệe]c)/i` hoặc liệt kê đủ biến thể.

## 7. Ngữ cảnh đồng âm / đa nghĩa (cần phân biệt)

| Key tiếng Anh | Ngữ cảnh | Dịch ĐÚNG |
|---|---|---|
| Short | Nhóm MRP / vật liệu (Short, Shortfall, Shortages, Net...) | **Thiếu** — thiếu hụt (KHÔNG dịch "ngắn" — đó là chiều dài) |
| Short | Ngữ cảnh khác (kích thước, thời gian...) | tùy ngữ cảnh: ngắn |
| Queue | Work Order Queue | Hàng đợi |
| Backlog | Planner | Hàng chờ |
| pcs | Đơn vị sản phẩm | sp |
| cái | "một cái", "cái gì" (đại từ) | giữ "cái"
