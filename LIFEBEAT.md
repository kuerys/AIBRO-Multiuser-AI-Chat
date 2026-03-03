# AIBRO 生命體徵監控

## ❤️ 雙重守護機制

### 第一層：心跳檢查 (每 10 分鐘)
- **任務**: `aibro-info list`
- **功能**: 掃描備忘清單、觸發工作流
- **日誌**: `/tmp/aibro-heartbeat.log`
- **Cron**: `*/10 * * * *`

### 第二層：守護神 (每 5 分鐘)
- **任務**: `watchdog.sh`
- **功能**: 監控心跳進程、自動恢復異常
- **日誌**: `/tmp/aibro-watchdog.log`
- **Cron**: `*/5 * * * *`

---

## 📊 健康檢查指令

```bash
# 查看心跳日誌
tail -20 /tmp/aibro-heartbeat.log

# 查看守護神日誌
tail -20 /tmp/aibro-watchdog.log

# 檢查 cron 狀態
crontab -l | grep aibro

# 手動觸發心跳
/home/sysop/.openclaw/workspace/skills/aibro-info-center/bin/aibro-info list

# 手動觸發守護神
/home/sysop/.openclaw/workspace/skills/aibro-info-center/bin/watchdog.sh
```

---

## 🚨 自動恢復機制

1. **Cron 遺失** → 守護神自動重建
2. **進程崩潰** → 下次心跳自動重啟
3. **鎖文件過期** → 自動清理 (>15 分鐘)
4. **日誌輪替** → 自動截斷過大檔案

---

## 📈 監控指標

- [x] 心跳週期：10 分鐘
- [x] 守護週期：5 分鐘
- [x] 鎖文件超時：15 分鐘
- [x] 日誌位置：`/tmp/aibro-*.log`

**目標**: 確保工作流永遠活著，即使系統重啟也能自動恢復！
