# Agent Skills dùng chung của nhóm

Repository lưu bốn skill ở **cả** `.agents/skills/` và `.claude/skills/`:

- `senior-backend`
- `senior-frontend`
- `systematic-debugging`
- `verification-before-completion`

Các thư mục là file Git thông thường, không phải symlink hoặc submodule. Clone
đầy đủ repository sẽ có `SKILL.md`, `references/`, `scripts/` và tài liệu phụ trợ
được skill đó tham chiếu. Không cần copy thư mục riêng từ máy người khác.

Mở repository làm workspace trong công cụ AI hỗ trợ Agent Skills. Việc tự phát
hiện skill phụ thuộc công cụ và phiên bản đang dùng; nếu chưa thấy, mở `SKILL.md`
tương ứng và yêu cầu công cụ sử dụng nó. Reload workspace/session sau khi clone.
Hai bản của mỗi skill nên được cập nhật cùng nhau.

Các skill là hướng dẫn cho công cụ phát triển, **không phải dependency của website**.
Ví dụ React/Next.js/Node trong skill frontend/backend là ví dụ tổng quát, không
phải hướng dẫn đổi kiến trúc PHP MVC của FourSmart. Chỉ chạy script khi đã hiểu
đầu vào/đầu ra; một số script `.sh` cần Git Bash, script `.py` cần Python và script
TypeScript cần môi trường tương ứng. Không tự chạy migration, tải package hoặc
ghi đè source chỉ vì một ví dụ trong skill đề nghị làm vậy.

Kiểm tra nhanh sau clone trong PowerShell:

```powershell
Get-ChildItem .agents\skills,.claude\skills -Filter SKILL.md -Recurse
git ls-files .agents/skills .claude/skills
```

Kỳ vọng có tám file `SKILL.md` (bốn skill × hai thư mục). Không đưa cấu hình cá
nhân của công cụ AI, chat log, token hoặc file `.env` vào các thư mục này.
