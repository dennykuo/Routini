# AI Workflow Guidelines (AI 工作流程指南)

本文件旨在規範 AI Agent 在本專案中的開發行為，以確保程式碼品質與一致性。

同時參照 @.ai/ 下的各式文件：
- `ARCHITECTURE.md` - 完整的架構設計文件
- `CODING_STYLE.md` - 程式碼風格規範
- `TESTING.md` - 測試指南與範例
- `ROADMAP.md` - 開發藍圖

注意：根目錄下的 TODO.md 為開發者的待辦事項，可以進行參考，但不要進行修改。

## 描述及解釋，程式備註

盡量使用繁體中文，但專有名詞則使用英文，或中英對照例如「表單方法偽造 (Form Method Spoofing」)。

## 核心原則 (Core Principles)

1.  **Test-Driven Development (TDD)**
    *   在撰寫任何功能代碼之前，**必須**先撰寫一個會失敗的測試案例。
    *   這確保了我們真正理解需求，並且能夠驗證功能的正確性。

2.  **Verify Before Commit**
    *   在標記任務完成之前，**必須**執行完整的測試套件 (`vendor/bin/pest`)。
    *   嚴禁在未通過測試的情況下提交代碼。

3.  **Code Style**
    *   嚴格遵守 `.ai/CODING_STYLE.md`。

## 開發SOP (Standard Operating Procedure)

1.  **Read Context**: 閱讀 `README.md`、`.ai/ARCHITECTURE.md` 與 `.ai/ROADMAP.md` 確認目標和架構。
2.  **Write Test**: 依據要測試的功能及範圍，在 `tests/Feature` 或 `tests/Unit` 建立測試。
    - 使用 `Request` 和 `Response` 物件進行測試
    - 如涉及安全性功能，在 `tests/Feature/SecurityTest.php` 中新增測試
    - 參考 `.ai/TESTING.md` 了解測試模式和最佳實踐
3.  **Implement**: 撰寫最小可行代碼以通過測試。
    - 遵循介面驅動設計原則
    - 使用依賴注入而非直接實例化
    - 確保型別安全（型別提示和回傳型別）
4.  **Refactor**: 優化代碼結構。
5.  **Verify**: 執行 `vendor/bin/pest` 確認無 Side Effects。
    - 當前測試數：203 tests (398 assertions)
    - 所有測試必須通過
6.  **Document**: 更新相關文件
    - 使用者文件：`/docs/` 目錄（如 ARCHITECTURE.md, SECURITY.md, USAGE_GUIDE.md）
    - AI 參考文件：`.ai/` 目錄
    - 開發過程文件：`/docs/develop/` 目錄
