# AIBRO 資訊中心 (AIBRO Info Center)

**功能說明：** 提供 AIBRO 艦隊內部資訊交換、備忘清單讀取、工作流觸發的統一介面。

**使用方式：**
- 讀取備忘清單：`aibro-info list`
- 新增備忘項目：`aibro-info add "項目內容"`
- 刪除備忘項目：`aibro-info remove <編號>`
- 觸發工作流：`aibro-info trigger <工作流名稱>`
- 查看最近活動：`aibro-info activity`

**適用情境：**
- 多 AI 協作時的資訊同步
- 跨會話任務追蹤
- 自動化工作流觸發
- 艦隊狀態廣播

**注意：** 
- 此 Skill 為**按需執行**，不依賴系統 cron。
- 當主人詢問、查看 `HEARTBEAT.md` 或於對話中觸發時才執行。
- 此 Skill 會讀取 `/home/sysop/.openclaw/workspace/memory/` 目錄下的備忘檔案。
- **OpenClaw First**: 所有操作限制在 OpenClaw Workspace 內，不越權操作主機系統。
