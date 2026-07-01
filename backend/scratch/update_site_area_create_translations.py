import json

vi_updates = {
    "New Site": "Tạo Cơ sở mới",
    "Edit Site": "Chỉnh sửa Cơ sở",
    "New Area": "Tạo Khu vực mới",
    "Edit Area": "Chỉnh sửa Khu vực"
}

en_updates = {
    "New Site": "New Site",
    "Edit Site": "Edit Site",
    "New Area": "New Area",
    "Edit Area": "Edit Area"
}

def update_lang_file(file_path, new_translations):
    with open(file_path, "r", encoding="utf-8") as f:
        data = json.load(f)
    
    # Update with new translations
    for k, v in new_translations.items():
        data[k] = v
        
    # Sort keys alphabetically
    sorted_data = {k: data[k] for k in sorted(data.keys())}
    
    with open(file_path, "w", encoding="utf-8") as f:
        json.dump(sorted_data, f, ensure_ascii=False, indent=4)
    print(f"Updated {file_path}")

update_lang_file(r"c:\Users\it7\OpenMes\backend\lang\vi.json", vi_updates)
update_lang_file(r"c:\Users\it7\OpenMes\backend\lang\en.json", en_updates)
