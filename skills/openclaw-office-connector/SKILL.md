# OpenClaw Office Connector

**功能說明：** 將 AIBRO 艦隊連接到 OpenClaw Office 虛擬儀表板，實現多代理工作流的可視化。

**使用方式：**
- 初始化：`oc-office init`
- 啟動儀表板：`oc-office start`
- 同步代理：`oc-office sync`
- 生成場景：`oc-office generate`
- 查看狀態：`oc-office status`

**整合功能：**
- 自動註冊 AIBRO-XEON 為主要協調器
- 同步 Redis 狀態到儀表板
- 工作流動畫（Agent A → Orchestrator → Agent B）
- 成本追蹤與統計

**適用情境：**
- 監控多用戶聊天流程
- 視覺化任務分發過程
- 追蹤 AI 艦隊活動
- 實時成本監控

**注意：** 需要配置 OpenClaw Gateway 的 WebSocket 連接。
