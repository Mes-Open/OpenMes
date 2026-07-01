@echo off
echo ===================================================
echo   GIT UPDATE SCRIPT
echo ===================================================
echo.

git status
echo.

:: Gán giá trị mặc định trước (không dùng ngoặc kép ở lệnh set)
set msg=Update project

:: Hỏi người dùng nhập message
set /p input="Enter commit message (Press Enter for default): "

:: Nếu người dùng có nhập (không rỗng), thì lấy giá trị đó
if not "%input%"=="" set msg=%input%

git add .

:: Sử dụng ngoặc kép ở đây để bao bọc message
git commit -m "%msg%"

:: Tự động lấy tên branch hiện tại
for /f "tokens=*" %%i in ('git branch --show-current') do set current_branch=%%i

git push origin %current_branch%

echo.
echo [DONE] Git sync completed on branch: %current_branch%
pause
