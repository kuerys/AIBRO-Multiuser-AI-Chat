# 🧠 Gateway 配置檢查清單 (XEON)

**目標**: 確保 Gateway 能正確接收 Telegram 消息，並能將任務分發給 Worker Node。

## 1. 檢查 `openclaw.json` 或 `gateway.json`
請確認以下配置項正確無誤：

```json
{
  "gateway": {
    "bind": "0.0.0.0",       // ✅ 必須：允許區網 Node 連接 (不要只綁定 localhost)
    "port": 18789            // ✅ 預設端口
  },
  "tools": {
    "agentToAgent": {
      "enabled": true        // ✅ 必須：允許 Gateway 與 Node 間通訊
    }
  },
  "exec": {
    "security": "allowlist", // ✅ 建議：白名單模式，僅允許特定命令
    "allowlist": [           // ✅ 選填：若用白名單，需在此列舉命令
      "nvidia-smi",
      "redis-cli",
      "git",
      "bash"
    ]
  }
}
```

## 2. 生成 Node 配對 Token
在 XEON 上執行以下命令，生成一個專給 MSI/P43E 用的 Token：

```bash
# 生成一個具有 exec, file, redis 權限的 Worker Token
openclaw tokens create --role worker --scope "exec,file,redis" --name "MSI-Worker"
```
> **注意**: 請將輸出的 Token 複製下來，稍後啟動 Node 時需要用到！

## 3. 啟動 Gateway
```bash
openclaw gateway
```

## 4. 驗證 Gateway 狀態
```bash
openclaw gateway status
# 應顯示：Listening on 0.0.0.0:18789
```

## 5. 等待 Node 連接
啟動 Node 後，在 Gateway 執行：
```bash
openclaw nodes list
# 應看到 MSI-Worker (或您設定的名稱) 上線
```

---
**完成上述步驟後，Gateway 就準備好接收任務並分發給 Node 了！**
