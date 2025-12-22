# 📚 Routini 文件中心

歡迎來到 Routini 的文件中心！這裡包含了所有使用和開發 Routini 所需的文件。

---

## 🚀 快速開始

如果你是第一次使用 Routini，建議按以下順序閱讀：

1. **[主 README](../README.md)** - 快速開始和基本使用
2. **[使用指南 (USAGE_GUIDE.md)](./USAGE_GUIDE.md)** - 詳細的功能說明
3. **[架構設計 (ARCHITECTURE.md)](./ARCHITECTURE.md)** - 深入了解內部設計

---

## 📖 使用者文件

### 核心文件

#### [USAGE_GUIDE.md](./USAGE_GUIDE.md)
**詳細使用指南** - 19KB, 873 行

完整的使用說明，包含：
- 基本路由註冊
- Request/Response 物件系統
- 中介軟體系統詳解
- 路由群組和參數
- 進階功能和完整範例
- 向後相容性說明

**適合**: 想要深入了解所有功能的使用者

---

#### [ARCHITECTURE.md](./ARCHITECTURE.md)
**架構設計文件** - 27KB

Routini 的完整架構文件，包含：
- 設計原則（SOLID）
- 核心組件詳解
- 架構層次和資料流向
- 設計模式（Facade, Strategy, Pipeline 等）
- 介面契約說明
- 安全性設計
- 擴展性指南

**適合**: 開發者、貢獻者、想深入了解內部設計的使用者

---

### 參考文件

#### [SECURITY.md](./SECURITY.md)
**安全性指南** - 15KB

Routini 的安全性文件，包含：
- 已識別的安全風險
- 已實作的防護措施（S1, S2, S3）
- 使用範例和最佳實踐
- 安全性測試覆蓋
- 回報安全漏洞的流程

**適合**: 關注安全性的使用者和開發者

---

#### [LARAVEL_COMPARISON.md](./LARAVEL_COMPARISON.md)
**與 Laravel 路由系統的比較** - 23KB

深度比較 Routini 與 Laravel 路由系統，包含：
- 設計理念差異
- 功能對照表（30+ 項）
- 使用範例對照
- 優勢與劣勢分析
- 遷移指南

**適合**: 熟悉 Laravel 的開發者、正在評估路由方案的使用者

---

#### [REMAINING_IMPROVEMENTS.md](./REMAINING_IMPROVEMENTS.md)
**待改善項目清單** - 16KB

專案的改善規劃和進度追蹤，包含：
- 已完成的改善項目
- 可選的改善方向
- 未來擴展建議
- 各項目的優先級和預估時間

**適合**: 貢獻者、想了解專案未來方向的使用者

---

#### [SERVER_CONFIGURATION.md](./SERVER_CONFIGURATION.md)
**伺服器配置指南** - 1KB

伺服器環境配置說明：
- Nginx 配置範例
- Apache 配置範例（待補充）

**適合**: 部署 Routini 應用的使用者

---

## 🔧 開發文件

開發過程中產生的分析、建議和進度追蹤文件位於 `/docs/develop/` 目錄。

#### [develop/](./develop/)

包含以下文件：
- **ARCHITECTURE_SUGGESTIONS.md** - 架構改善建議分析（57KB）
- **ARCHITECTURE_PROGRESS.md** - 架構改進進度追蹤（13KB）
- **CLAUDE_ANALYSIS_REPORT.md** - 初始分析報告（16KB）
- **CURRENT_STATUS_ASSESSMENT.md** - 狀態評估（10KB）

詳細說明請參考 [develop/README.md](./develop/README.md)

**適合**: 開發者、貢獻者、AI 助手

---

## 📋 文件地圖

### 使用情境導向

#### 我想開始使用 Routini
1. [主 README](../README.md) - 安裝和快速開始
2. [USAGE_GUIDE.md](./USAGE_GUIDE.md) - 學習所有功能

#### 我想了解 Routini 的設計
1. [ARCHITECTURE.md](./ARCHITECTURE.md) - 架構設計
2. [develop/ARCHITECTURE_SUGGESTIONS.md](./develop/ARCHITECTURE_SUGGESTIONS.md) - 設計決策過程

#### 我想貢獻程式碼
1. [ARCHITECTURE.md](./ARCHITECTURE.md) - 了解架構
2. [REMAINING_IMPROVEMENTS.md](./REMAINING_IMPROVEMENTS.md) - 找到可以貢獻的項目
3. [develop/](./develop/) - 了解專案演進

#### 我想從 Laravel 遷移到 Routini
1. [LARAVEL_COMPARISON.md](./LARAVEL_COMPARISON.md) - 了解差異
2. [USAGE_GUIDE.md](./USAGE_GUIDE.md) - 學習 Routini 的用法

#### 我關注安全性
1. [SECURITY.md](./SECURITY.md) - 安全性指南
2. [ARCHITECTURE.md](./ARCHITECTURE.md) - 安全性設計章節

---

## 🔍 文件統計

| 文件類型 | 文件數 | 總大小 |
|:---|:---:|:---:|
| 使用者文件 | 6 | ~97KB |
| 開發文件 | 4 | ~96KB |
| 說明文件 | 2 | ~5KB |
| **總計** | **12** | **~198KB** |

---

## 📝 文件維護

### 文件更新原則

- **使用者文件** (`/docs/*.md`): 隨功能更新而更新
- **開發文件** (`/docs/develop/*.md`): 記錄開發過程，通常不更新
- **主 README** (`/README.md`): 保持簡潔，指向詳細文件

### 新增文件指南

- **使用者導向文件**: 放在 `/docs/` 根目錄
- **開發過程文件**: 放在 `/docs/develop/`
- **臨時分析文件**: 放在 `/docs/develop/`

---

## 💡 反饋與建議

如果你發現文件有誤、不清楚或需要補充的地方，歡迎：

1. 提交 Issue: [GitHub Issues](https://github.com/dennykuo/routini/issues)
2. 提交 Pull Request 改善文件
3. 聯繫維護者

---

**最後更新**: 2025-12-22
**維護者**: Routini Team
