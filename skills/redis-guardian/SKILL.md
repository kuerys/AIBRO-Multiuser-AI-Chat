# Redis Guardian - Redis 監控與優化 Skill

**功能說明：** 監控 Redis 健康狀態、自動優化配置、確保 30 秒內回復的長態性承諾。

**使用方式：**
- 即時狀態：`redis-guardian status`
- 健康檢查：`redis-guardian health`
- 性能優化：`redis-guardian optimize`
- 連接測試：`redis-guardian ping`
- 監控日誌：`redis-guardian logs`

**監控指標：**
- 連接數與並發量
- 記憶體使用率
- 回應延遲（目標：<30 秒）
- 持久化狀態
- 主從同步狀態（如有）

**適用情境：**
- AIBRO 多用戶聊天的即時通訊
- 跨 AI 實例的狀態共享
- 會話緩存與隊列管理
- 高可用集群監控

**注意：** 
- 此 Skill 為**按需執行**，不依賴系統 cron。
- 當主人詢問 Redis 狀態或於 `HEARTBEAT.md` 中提及時才執行。
- 預設連接本地 Redis (`127.0.0.1:6379`)。
