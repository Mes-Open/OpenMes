import json
import os

vi_path = r"c:\Users\it7\OpenMes\backend\lang\vi.json"
en_path = r"c:\Users\it7\OpenMes\backend\lang\en.json"

# Load vi.json
with open(vi_path, 'r', encoding='utf-8') as f:
    vi_data = json.load(f)

# Load en.json
with open(en_path, 'r', encoding='utf-8') as f:
    en_data = json.load(f)

# 1. Update keys/values for Division / Phân khu / Phân chia
# Define exact key updates or generic replacements for values
updates_vi = {
    "Division": "Bộ phận",
    "Divisions": "Bộ phận",
    "Add Division": "Thêm Bộ phận",
    "Create Division": "Tạo Bộ phận",
    "Create a division within a factory": "Tạo một bộ phận trong nhà máy",
    "DIVISIONS": "BỘ PHẬN",
    "Delete division \":name\"?": "Xóa bộ phận \":name\"?",
    "Delete this division?": "Xóa bộ phận này?",
    "New Division": "Tạo Bộ phận mới",
    "+ New Division": "+ Bộ phận mới",
    "No divisions yet": "Chưa có bộ phận nào",
    "No divisions yet.": "Chưa có bộ phận nào.",
    "— No division —": "— Không bộ phận —",
    "Edit Division": "Chỉnh sửa Bộ phận",
    "Edit Division: :name": "Chỉnh sửa Bộ phận: :name",
    
    # Subassembly
    "New Subassembly": "Tạo Cụm lắp ráp phụ mới",
    "Edit Subassembly": "Chỉnh sửa Cụm lắp ráp phụ",
    "Edit Subassembly: :name": "Chỉnh sửa Cụm lắp ráp phụ: :name",
    
    # Workstation / Workstation type
    "New Workstation Type": "Tạo Loại trạm làm việc mới",
    "Edit Workstation Type": "Chỉnh sửa Loại trạm làm việc",
    "Edit Workstation Type: :name": "Chỉnh sửa Loại trạm làm việc: :name",
    "Create Workstation": "Tạo Trạm làm việc",
    "Edit Workstation: :name": "Chỉnh sửa Trạm làm việc: :name",
    "(currently at: :station)": "(hiện tại ở: :station)",
}

# Apply explicit updates first
for k, v in updates_vi.items():
    vi_data[k] = v

# Process all values to replace "máy trạm" with "trạm làm việc", and "Phân khu" with "Bộ phận"
for k, v in vi_data.items():
    if isinstance(v, str):
        # Replacements for workstation
        v = v.replace("máy trạm", "trạm làm việc")
        v = v.replace("Máy trạm", "Trạm làm việc")
        v = v.replace("MÁY TRẠM", "TRẠM LÀM VIỆC")
        v = v.replace("Máy Trạm", "Trạm Làm Việc")
        
        # Replacements for division
        v = v.replace("phân khu", "bộ phận")
        v = v.replace("Phân khu", "Bộ phận")
        v = v.replace("PHÂN KHU", "BỘ PHẬN")
        vi_data[k] = v

# Apply updates again to ensure we don't accidentally override them
for k, v in updates_vi.items():
    vi_data[k] = v

# Ensure en.json has all the keys from updates_vi
for k in updates_vi.keys():
    if k not in en_data:
        en_data[k] = k

# Save vi.json sorted by keys if it was sorted (optional, but let's keep formatting)
with open(vi_path, 'w', encoding='utf-8') as f:
    json.dump(vi_data, f, ensure_ascii=False, indent=4)

# Save en.json
with open(en_path, 'w', encoding='utf-8') as f:
    json.dump(en_data, f, ensure_ascii=False, indent=4)

print("Translations successfully updated!")
