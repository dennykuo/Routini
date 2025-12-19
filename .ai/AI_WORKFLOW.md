# AI Workflow Guidelines (AI 工作流程指南)

本文件旨在規範 AI Agent 在本專案中的開發行為，以確保程式碼品質與一致性。

同時參照 @.ai/ 下的各式文件

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

1.  **Read Context**: 閱讀 `README.md` 與 `ROADMAP.md` 確認目標。
2.  **Write Test**: 依據要測試的功能及範圍，在 `tests/Feature` 或 `tests/Unit` 建立測試。
3.  **Implement**: 撰寫最小可行代碼以通過測試。
4.  **Refactor**: 優化代碼結構。
5.  **Verify**: 執行 `vendor/bin/pest` 確認無 Side Effects。
6.  **Document**: 更新相關文件 (@index.php, @README.md, @.ai/ 等相關相關內容)。
