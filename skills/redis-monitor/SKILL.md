# Redis 監控與優化技能

## 技能說明
監控 Redis 狀態、效能分析、自動優化建議，並提供 30 秒長態性回應能力。

## 核心功能

### 1. 健康檢查
```bash
# 基礎健康檢查
redis-cli ping
redis-cli INFO server
redis-cli INFO memory
redis-cli INFO stats
```

### 2. 監控指標
- **記憶體使用量**: `used_memory_human`
- **連線數**: `connected_clients`
- **命中率**: `keyspace_hits / (keyspace_hits + keyspace_misses)`
- **QPS**: `instantaneous_ops_per_sec`
- **慢查詢**: `SLOWLOG GET 10`

### 3. 30 秒長態性回應機制
使用 Redis Pub/Sub 實現長態性對話：
- **Channel**: `aibro:longterm:response`
- **TTL**: 30 秒
- **格式**: JSON

## 使用方式

### 監控指令
```bash
# 快速健康檢查
redis-cli ping

# 完整狀態報告
redis-cli INFO

# 慢查詢日誌
redis-cli SLOWLOG GET 10

# 記憶體分析
redis-cli MEMORY STATS

# 即時監控（每秒刷新）
watch -n 1 'redis-cli INFO stats | grep -E "ops_per_sec|keyspace"'
```

### 長態性回應範例
```bash
# 發布長態性回應
redis-cli PUBLISH aibro:longterm:response '{"type": "status", "message": "任務執行中...", "ttl": 30}'

# 訂閱頻道
redis-cli SUBSCRIBE aibro:longterm:response
```

## 自動化腳本

### 健康檢查腳本
```bash
#!/bin/bash
# /home/sysop/.openclaw/workspace/scripts/redis-health.sh

REDIS_HOST="127.0.0.1"
REDIS_PORT="6379"
ALERT_THRESHOLD_MEMORY="80"  # 記憶體使用率超過 80% 告警

# 檢查 Redis 是否可達
if ! redis-cli -h $REDIS_HOST -p $REDIS_PORT ping > /dev/null 2>&1; then
    echo "❌ Redis 無法連接"
    exit 1
fi

# 獲取記憶體使用情況
MEMORY_INFO=$(redis-cli -h $REDIS_HOST -p $REDIS_PORT INFO memory | grep -E "used_memory_human|maxmemory_human")
USED_MEM=$(echo "$MEMORY_INFO" | grep "used_memory_human" | cut -d: -f2 | tr -d '\r')
MAX_MEM=$(echo "$MEMORY_INFO" | grep "maxmemory_human" | cut -d: -f2 | tr -d '\r')

# 獲取連線數
CONNECTED_CLIENTS=$(redis-cli -h $REDIS_HOST -p $REDIS_PORT INFO clients | grep "connected_clients" | cut -d: -f2 | tr -d '\r')

# 獲取命中率
STATS=$(redis-cli -h $REDIS_HOST -p $REDIS_PORT INFO stats)
HITS=$(echo "$STATS" | grep "keyspace_hits" | cut -d: -f2 | tr -d '\r')
MISSES=$(echo "$STATS" | grep "keyspace_misses" | cut -d: -f2 | tr -d '\r')

if [ "$HITS" != "0" ] && [ "$MISSES" != "0" ]; then
    TOTAL=$((HITS + MISSES))
    HIT_RATE=$(echo "scale=2; $HITS * 100 / $TOTAL" | bc)
else
    HIT_RATE="N/A"
fi

# 輸出報告
echo "## 📊 Redis 健康報告"
echo "- 狀態：✅ 正常運行"
echo "- 記憶體使用：$USED_MEM / $MAX_MEM"
echo "- 連線數：$CONNECTED_CLIENTS"
echo "- 命中率：$HIT_RATE%"
echo "- 檢查時間：$(date '+%Y-%m-%d %H:%M:%S')"
```

### 30 秒長態性回應函式庫
```bash
#!/bin/bash
# /home/sysop/.openclaw/workspace/scripts/redis-longresponse.sh

REDIS_CHANNEL="aibro:longterm:response"

# 發布長態性回應
publish_response() {
    local message="$1"
    local ttl="${2:-30}"
    local type="${3:-status}"
    
    local timestamp=$(date -Iseconds)
    local json="{\"type\": \"$type\", \"message\": \"$message\", \"timestamp\": \"$timestamp\", \"ttl\": $ttl}"
    
    redis-cli PUBLISH "$REDIS_CHANNEL" "$json"
    echo "✅ 已發布長態性回應 (TTL: ${ttl}s)"
}

# 發布進度更新
publish_progress() {
    local step="$1"
    local total="$2"
    local message="$3"
    
    local percentage=$(echo "scale=0; $step * 100 / $total" | bc)
    publish_response "[$step/$total] ($percentage%) $message" "30" "progress"
}

# 發布完成通知
publish_complete() {
    local result="$1"
    publish_response "✅ 完成：$result" "30" "complete"
}

# 發布錯誤通知
publish_error() {
    local error="$1"
    publish_response "❌ 錯誤：$error" "30" "error"
}

# 使用範例
# source /home/sysop/.openclaw/workspace/scripts/redis-longresponse.sh
# publish_response "任務開始執行..."
# sleep 5
# publish_progress 1 3 "初始化中"
# sleep 5
# publish_progress 2 3 "處理中"
# sleep 5
# publish_progress 3 3 "完成中"
# sleep 2
# publish_complete "所有任務完成"
```

## 監控儀表板

### Grafana + Prometheus (選配)
如果需要可視化監控，可設置：
```yaml
# prometheus.yml 配置
scrape_configs:
  - job_name: 'redis'
    static_configs:
      - targets: ['localhost:9121']  # Redis Exporter
```

## 警報規則

### 記憶體告警
- **警告**: 記憶體使用 > 80%
- **嚴重**: 記憶體使用 > 95%

### 連線數告警
- **警告**: 連線數 > 100
- **嚴重**: 連線數 > 500

### 命中率告警
- **警告**: 命中率 < 70%
- **嚴重**: 命中率 < 50%

## 效能優化建議

### 1. 記憶體優化
```bash
# 設置最大記憶體限制
redis-cli CONFIG SET maxmemory 2gb

# 設置淘汰策略
redis-cli CONFIG SET maxmemory-policy allkeys-lru
```

### 2. 慢查詢優化
```bash
# 設置慢查詢閾值（秒）
redis-cli CONFIG SET slowlog-log-slower-than 10000

# 查看慢查詢
redis-cli SLOWLOG GET 10

# 清空慢查詢日誌
redis-cli SLOWLOG RESET
```

### 3. 持久化優化
```bash
# 啟用 AOF
redis-cli CONFIG SET appendonly yes

# 設置 AOF fsync 策略
redis-cli CONFIG SET appendfsync everysec
```

## 日誌記錄

所有監控活動記錄到：`/tmp/redis-monitor.log`

```bash
# 記錄健康檢查
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Redis 健康檢查完成" >> /tmp/redis-monitor.log
```

## 整合到 Heartbeat

將 Redis 監控整合到 HEARTBEAT.md 的自動化週期中：
- 每 10 分鐘檢查一次 Redis 健康狀態
- 異常時發送 Telegram 通知
- 記錄效能趨勢到 memory 文件

## 注意事項

1. **安全性**: Redis 預設無密碼，建議設置密碼保護
2. **持久化**: 重要數據建議啟用 RDB + AOF 雙重持久化
3. **備份**: 定期備份 RDB 文件
4. **監控**: 持續監控記憶體使用，避免 OOM
