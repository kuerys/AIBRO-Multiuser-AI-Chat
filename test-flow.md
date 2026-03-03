# 🧪 端到端流程測試指南

**目標**: 驗證 `Telegram -> Gateway (XEON) -> Node (MSI) -> Telegram` 全流程。

---

## 第一階段：環境就緒檢查

### 1. 在 XEON (Gateway)
- [ ] Gateway 已啟動 (`openclaw gateway status` 顯示運行中)
- [ ] 已生成 Worker Token
- [ ] `openclaw nodes list` 準備檢查 Node 狀態

### 2. 在 MSI (Worker Node)
- [ ] 已修改 `start-node.sh` 中的 Token 和 Gateway IP
- [ ] 執行 `./start-node.sh` 啟動 Node
- [ ] 確認無報錯退出

### 3. 驗證連接
在 **XEON** 執行：
```bash
openclaw nodes list
```
**預期結果**: 應看到 `MSI-Worker` 狀態為 `online`。

---

## 第二階段：基礎通訊測試

### 測試 1：Ping 測試
在 **Telegram** 發送：
```
ping
```
**預期**: Gateway 收到並回覆 "Pong" 或類似確認訊息。

### 測試 2：簡單技能調用
在 **Telegram** 發送：
```
/skill redis-guardian ping
```
**預期流程**:
1. Telegram -> Gateway 接收
2. Gateway -> 路由給 MSI-Node
3. MSI-Node -> 執行 `redis-cli ping`
4. MSI-Node -> 回傳 "PONG"
5. Gateway -> 轉發 "PONG" 到 Telegram

**預期結果**: Telegram 收到 "PONG" 回應。

---

## 第三階段：進階功能測試

### 測試 3：GPU 狀態查詢
在 **Telegram** 發送：
```
/skill aibro-fleet "GPU 狀態"
```
**預期**: MSI-Node 執行 `nvidia-smi` 並回傳 GPU 使用情況。

### 測試 4：Redis 健康檢查
在 **Telegram** 發送：
```
/skill redis-guardian health
```
**預期**: 回傳完整的 Redis 健康報告（記憶體、連接數、延遲等）。

---

## 常見問題排除

| 問題 | 可能原因 | 解決方案 |
|------|----------|----------|
| Node 無法上線 | Token 錯誤 / 網絡不通 | 檢查 Token 是否複製完整，確認防火牆 18789 端口 |
| 技能執行失敗 | Node 上無技能文件 | 將技能文件夾同步到 Node 的 `~/.openclaw/workspace/skills/` |
| 命令被拒絕 | 不在 allowlist 中 | 在 Gateway 的 `openclaw.json` 將命令加入 allowlist |
| 超時無回應 | Node 過載或網絡延遲 | 檢查 Node 日誌，確認帶寬充足 |

---

## 成功指標
- [ ] Node 穩定上線
- [ ] Telegram 可收到 Node 執行的結果
- [ ] 延遲在可接受範圍內 (<5 秒)

**完成以上測試後，AIBRO 艦隊的 WG+Node 架構即正式就緒！** 🦞🚀
