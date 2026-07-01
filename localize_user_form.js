const fs = require('fs');

const formPath = 'backend/resources/js/Pages/admin/users/UserForm.jsx';
let content = fs.readFileSync(formPath, 'utf8');

if (!content.includes('import { __ }')) {
    content = content.replace(/^(import.*?;)/m, `$1\nimport { __ } from '../../../lib/i18n';`);
}

content = content.replace(/label="Name"/g, 'label={__("Name")}');
content = content.replace(/label="Username"/g, 'label={__("Username")}');
content = content.replace(/label="Email"/g, 'label={__("Email")}');
content = content.replace(/label="Password"/g, 'label={__("Password")}');
content = content.replace(/label="Confirm Password"/g, 'label={__("Confirm Password")}');
content = content.replace(/label=\{isEdit \? 'Password \(blank = keep\)' : 'Password'\}/g, 'label={isEdit ? __("Password (blank = keep)") : __("Password")}');
content = content.replace(/label="Require password change at next login"/g, 'label={__("Require password change at next login")}');
content = content.replace(/label="Role"/g, 'label={__("Role")}');
content = content.replace(/label="Workstation"/g, 'label={__("Workstation")}');
content = content.replace(/>Account Type</g, '>{__("Account Type")}<');
content = content.replace(/'Personal user'/g, "__('Personal user')");
content = content.replace(/'Workstation'/g, "__('Workstation')");
content = content.replace(/>Worker Profile \(optional\)</g, '>{__("Worker Profile (optional)")}<');
content = content.replace(/>Hide worker profile</g, '>{__("Hide worker profile")}<');
content = content.replace(/>Add worker profile</g, '>{__("Add worker profile")}<');
content = content.replace(/placeholder="— Select role —"/g, 'placeholder={__("— Select role —")}');
content = content.replace(/placeholder="— Select workstation —"/g, 'placeholder={__("— Select workstation —")}');

fs.writeFileSync(formPath, content);

const viPath = 'backend/lang/vi.json';
let vi = JSON.parse(fs.readFileSync(viPath, 'utf8'));
Object.assign(vi, {
    "New Account": "Tài khoản mới",
    "Name": "Họ và tên",
    "Username": "Tên đăng nhập",
    "Email": "Email",
    "Password": "Mật khẩu",
    "Confirm Password": "Xác nhận mật khẩu",
    "Password (blank = keep)": "Mật khẩu (để trống nếu không đổi)",
    "Require password change at next login": "Yêu cầu đổi mật khẩu ở lần đăng nhập tiếp theo",
    "Role": "Vai trò",
    "Workstation": "Trạm làm việc",
    "Account Type": "Loại tài khoản",
    "Personal user": "Người dùng cá nhân",
    "— Select role —": "— Chọn vai trò —",
    "— Select workstation —": "— Chọn trạm làm việc —"
});
fs.writeFileSync(viPath, JSON.stringify(vi, null, 4));

console.log('UserForm localized.');
