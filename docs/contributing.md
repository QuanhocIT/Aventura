# 🤝 Hướng Dẫn Đóng Góp (Contributing)

> Cảm ơn bạn đã muốn đóng góp cho Aventura! Vui lòng đọc kỹ trước khi tạo Pull Request.

---

## 🌿 Quy Tắc Tên Branch

```
feature/ten-tinh-nang      ← Tính năng mới
fix/mo-ta-loi              ← Sửa bug
refactor/pham-vi           ← Tái cấu trúc code
docs/noi-dung-tai-lieu     ← Chỉ thay đổi tài liệu
chore/cong-viec-khac       ← Build, CI, cấu hình...
```

**Ví dụ:**
```
feature/feedback-sentiment-analysis
fix/order-lock-bypass-validation
docs/update-api-reference
```

---

## 💬 Quy Tắc Commit Message

Dùng chuẩn **Conventional Commits**:

```
<type>(<scope>): <mô tả ngắn gọn>

[body - mô tả chi tiết nếu cần]
[footer - breaking change, issue reference]
```

### Các `type` được phép:

| Type | Khi nào dùng |
|---|---|
| `feat` | Thêm tính năng mới |
| `fix` | Sửa bug |
| `refactor` | Tái cấu trúc không ảnh hưởng logic |
| `style` | Chỉnh CSS/UI, không đổi logic |
| `docs` | Cập nhật tài liệu |
| `test` | Thêm/sửa test |
| `chore` | Build, CI, cấu hình... |
| `perf` | Cải thiện hiệu năng |

### Ví dụ commit tốt:

```
feat(feedback): add sentiment analysis via Python service

- Integrate Python FastAPI at port 8002
- Add SentimentAnalysisJob dispatched after feedback save
- Update FeedbackResource to include sentiment field

Closes #142
```

```
fix(order): prevent lock bypass with invalid manager code

Previously, empty string was accepted as valid bypass code.
Now validated against settings table.

Fixes #158
```

---

## 🔍 Checklist Trước Khi Tạo PR

- [ ] Code đã pass toàn bộ test: `php artisan test`
- [ ] Không có lỗi TypeScript: `npm run types:check`  
- [ ] Không có lỗi ESLint: `npm run lint:check`
- [ ] Đã viết test cho tính năng mới (nếu có)
- [ ] Đã cập nhật tài liệu liên quan trong `docs/`
- [ ] Commit message theo đúng Conventional Commits

---

## 🔗 Xem Thêm

- [Quay lại mục lục](README.md)
