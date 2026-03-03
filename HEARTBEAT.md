# AIBRO 艦隊任務清單

> **注意**：此檔案供 AI 於對話中「按需讀取」，非自動執行腳本。
> 當主人詢問狀態、提及關鍵詞或進行例行檢查時，請讀取此檔案並回報。

## 🔒 主站 SSL 憑證到期提醒
**到期日期**：2026 年 5 月 12 日 13:34 GMT
- 檢查頻率：每 24 小時（於對話中提醒）
- 30/14/7 天前 → 發送 Telegram 語音提醒

## 🤖 AIBRO 多用戶聊天倉庫
**GitHub**: https://github.com/kuerys/AIBRO-Multiuser-AI-Chat
- 定期提交本地變更到 GitHub
- 追蹤多用戶聊天功能開發進度

## 🏗️ AIBRO 資訊中心
**位置**: `/home/sysop/.openclaw/workspace/skills/aibro-info-center/`
- 持續完善 CLI 功能
- 整合更多自動化工作流

## 📡 Redis 即時監控 (AIBRO-XEON 負責)
- **負責人**: AIBRO-XEON
- **狀態**: 監控中
- **承諾**: 30 秒內回復長態性保證
- **工具**: `redis-guardian` CLI
- **功能**:
  - 即時健康檢查（按需執行）
  - 性能優化建議
  - 回應時間監控（目標 <30ms）

## 🖥️ OpenClaw Office 儀表板（參考用）
**GitHub**: https://github.com/wickedapp/openclaw-office
- **狀態**: 僅供參考，尚未安裝
- **功能**: 多代理工作流可視化（未來擴充）

## 📋 例行檢查清單（按需執行）
- [ ] 檢查 SSL 憑證（每日）
- [ ] Redis 健康檢查（當詢問狀態時）
- [ ] GitHub 同步檢查（當有變更時）
- [ ] V5 完善度檢查（當討論版本時）

---
## Commands（供 AI 參考）
```bash
# SSL 檢查
sudo openssl x509 -enddate -noout -in /etc/letsencrypt/live/star-tw.ddns.net/fullchain.pem

# GitHub 同步
cd /home/sysop/.openclaw/workspace && git status

# Redis 監控
/home/sysop/.openclaw/workspace/skills/redis-guardian/bin/redis-guardian health

# AIBRO 資訊中心
/home/sysop/.openclaw/workspace/skills/aibro-info-center/bin/aibro-info list
```
