# 🦞 AIBRO 艦隊實戰指南 v2.0 (2026.03.02 更新版)

> **版本說明**: 基於 OpenClaw 2026.2.27 版本，整合 ACP 協議與 Node Host 架構。

---

## 📊 環境測試結果摘要

### ✅ 已確認就緒
- **Gateway**: 運行於 `127.0.0.1:18789` (本地) / `0.0.0.0:18789` (需配置)
- **ACP 協議**: 可用 (`openclaw acp client`)
- **Node Host**: 支援，但需安裝
- **設備管理**: `openclaw devices` 可管理配對與 Token

### ⚠️ 需修正項目
1. **Node Host 未安裝**: 需執行 `openclaw node install`
2. **Gateway 綁定**: 目前僅限 `127.0.0.1`，區網訪問需改為 `0.0.0.0`
3. **Systemd 配置**: PATH 缺少部分目錄，但不影響基本功能
4. **Ollama 連接**: 暫時無法連接模型 (需檢查 GPU 服務)

---

## 🚀 實戰步驟 (修正版)

### 第一階段：準備 Gateway (XEON)

#### 1. 啟動 Gateway (前台測試)
```bash
# 測試模式：前台運行，方便觀察日誌
openclaw gateway --port 18789
```

#### 2. 生成 Node 配對 Token
```bash
# 生成一個 Worker 角色的 Token
# 注意：OpenClaw 使用 device 配對機制，而非靜態 Token
openclaw devices rotate --role worker
```
> **重要**: OpenClaw 使用 **設備配對** 而非靜態 Token。Node 啟動時會生成配對請求，需在 Gateway 上批准。

#### 3. 配置 Gateway 允許遠程連接
編輯 `~/.openclaw/openclaw.json`:
```json
{
  "gateway": {
    "bind": "0.0.0.0",
    "port": 18789
  }
}
```

---

### 第二階段：安裝並啟動 Node Host (MSI/P43E)

#### 1. 安裝 Node Host 服務
```bash
# 在 MSI 或 P43E 上執行
openclaw node install
```

#### 2. 啟動 Node Host
```bash
# 前台運行 (測試用)
openclaw node run --host 127.0.0.1 --port 18789

# 或後台服務 (生產用)
openclaw node start
```

#### 3. 配對 Node 到 Gateway
Node 啟動後，會在 Gateway 產生一筆 **待配對請求**。在 **Gateway (XEON)** 上執行：
```bash
# 查看待配對設備
openclaw devices list

# 批准配對 (使用設備 ID)
openclaw devices approve --device <設備 ID>
```

---

### 第三階段：驗證端到端流程

#### 1. 檢查 Node 狀態
在 **Gateway** 執行：
```bash
openclaw nodes list
# 應看到已配對的 Node
```

#### 2. 測試 ACP 連接
```bash
# 啟動 ACP 互動式客戶端
openclaw acp client

# 或在 Telegram 測試
# 發送：/skill redis-guardian health
```

---

## 🔧 關鍵差異說明 (與舊版對比)

| 項目 | 舊版認知 | **新版實際 (2026.2.27)** |
| :--- | :--- | :--- |
| **Token 機制** | 靜態 Token 字符串 | **設備配對制** (需批准) |
| **Node 啟動** | `openclaw node --token xxx` | `openclaw node install` + `approve` |
| **角色權限** | `--scope` 參數 | 在 `devices approve` 時授予 |
| **配置位置** | `openclaw.json` | `~/.openclaw/openclaw.json` |
| **ACP 腳本** | 需手寫 Bridge | 內建 `openclaw acp client` |

---

## 📝 推薦配置 (`openclaw.json`)

```json
{
  "gateway": {
    "bind": "0.0.0.0",
    "port": 18789,
    "remote": {
      "url": "ws://192.168.0.100:18789"
    }
  },
  "memory": {
    "provider": "file",
    "storage": {
      "short_term": "session",
      "long_term": "file"
    }
  },
  "exec": {
    "security": "allowlist",
    "allowlist": [
      "nvidia-smi",
      "redis-cli",
      "git",
      "bash",
      "openclaw"
    ]
  }
}
```

---

## 🎯 下一步行動建議

### 選項 A：快速驗證 (推薦)
1. 在 XEON 啟動 Gateway (前台)
2. 在 MSI 安裝並啟動 Node Host
3. 在 XEON 批准配對
4. Telegram 測試 `/skill redis-guardian ping`

### 選項 B：完整部署
1. 修正 Gateway 配置為 `0.0.0.0`
2. 安裝 Node Host 為系統服務
3. 配置防火牆規則
4. 部署 `aibro-fleet` 技能到 Node

### 選項 C：觀望 Memory 升級
1. 等待 OpenClaw 官方 Memory 插件成熟
2. 先維持現有文件存儲模式
3. 定期檢查 `openclaw memory --help` 新功能

---

## 📞 故障排除

| 問題 | 解決方案 |
| :--- | :--- |
| Node 無法連接 Gateway | 確認 `bind: 0.0.0.0` 且防火牆開放 18789 |
| 配對請求未出現 | 檢查 Node 日誌 `openclaw node status` |
| 技能執行失敗 | 確認命令在 `allowlist` 中 |
| ACP 連接超時 | 確認 Gateway 與 Node 版本一致 |

---

**報告生成時間**: 2026-03-02 16:30 GMT+8  
**測試環境**: OpenClaw 2026.2.27, Linux 6.17.0, Node v24.14.0  
**狀態**: 環境已就緒，等待主人回來執行實戰！🦞🚀
