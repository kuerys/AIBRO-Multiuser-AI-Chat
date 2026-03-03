#!/usr/bin/env bash
# AIBRO 工作流守護神 (Watchdog)
# 確保心跳進程永遠活著

set -e

WORKSPACE="/home/sysop/.openclaw/workspace"
HEARTBEAT_SCRIPT="$WORKSPACE/skills/aibro-info-center/bin/aibro-info"
LOG_FILE="/tmp/aibro-watchdog.log"
CRON_PATTERN="*/10 * * * *"
LOCK_FILE="/tmp/aibro-heartbeat.lock"

# 顏色定義
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log() {
    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

check_cron() {
    if crontab -l 2>/dev/null | grep -q "aibro-info"; then
        return 0
    else
        return 1
    fi
}

restore_cron() {
    log "${YELLOW}偵測到心跳任務遺失，嘗試恢復...${NC}"
    
    (crontab -l 2>/dev/null | grep -v "aibro-info"; \
     echo "$CRON_PATTERN $HEARTBEAT_SCRIPT list >> /tmp/aibro-heartbeat.log 2>&1") | crontab -
    
    if check_cron; then
        log "${GREEN}✓ 心跳任務已恢復${NC}"
    else
        log "${RED}✗ 恢復失敗，請手動檢查${NC}"
    fi
}

check_process() {
    # 檢查是否有 aibro-info 正在執行
    if pgrep -f "aibro-info" > /dev/null; then
        return 0
    else
        return 1
    fi
}

main() {
    log "${GREEN}=== AIBRO 工作流守護神啟動 ===${NC}"
    
    # 1. 檢查 cron 是否運行
    if ! check_cron; then
        log "${YELLOW}警告：未發現心跳定時任務${NC}"
        restore_cron
    else
        log "${GREEN}✓ 心跳定時任務正常運行${NC}"
    fi
    
    # 2. 檢查鎖文件（防止多重執行）
    if [ -f "$LOCK_FILE" ]; then
        # 檢查鎖文件是否過期（超過 15 分鐘）
        lock_age=$(($(date +%s) - $(stat -c %Y "$LOCK_FILE" 2>/dev/null || echo 0)))
        if [ $lock_age -gt 900 ]; then
            rm -f "$LOCK_FILE"
            log "${YELLOW}清理過期的鎖文件${NC}"
        fi
    fi
    
    # 3. 記錄守護神狀態
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Watchdog OK" >> /tmp/aibro-watchdog-heartbeat.log
    
    log "${GREEN}守護神監控完成，下次檢查於 10 分鐘後${NC}"
}

# 執行主程式
main
