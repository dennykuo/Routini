# TODO

- 安全性問題
    - 沒有 CSRF 保護：直接接受 POST/PUT/DELETE 請求，容易受到 CSRF 攻擊
    - 沒有 XSS 防護：404 錯誤訊息直接輸出中文，應使用適當的 header
    - 沒有輸入驗證：路由參數未進行消毒處理，可能導致安全漏洞
    - URL 編碼問題：Router::url() 方法中未對參數進行 URL 編碼
-效能優化
  - 路由快取機制
  - 優化路由匹配演算
- 監控與日誌
  - 錯誤日誌
  - 效能監控 
  - 請求記錄
- 沒有路由列表命令（查看所有已註冊的路由）
- API 文件
- 使用文件
- LICENSE 檔案
- CHANGELOG

## Package Name

目前選用 Routini

下面為備選方案

- Laroute
- Routie - 可愛暱稱化
- Gofer -中文意思是跑腿的、雜事辦理員，指在公司裡負責傳遞消息、取送物品等瑣碎工作的員工，多用於美式口語
- Routini - Route + 可愛後綴 (義式小巧版)
- Routini - route + martini 優雅調配
- Byway - 小路,旁路機制
- Ruto - 超簡化
- Roo - 袋鼠聯想
- Ruty - 可愛短音
- Lanie - lane 可愛化
- Pathy - path 暱稱化
