#!/bin/bash
# Redis 健康檢查腳本

REDIS_HOST="127.0.0.1"
REDIS_PORT="6379"

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

# 獲取 QPS
OPS_PER_SEC=$(echo "$STATS" | grep "instantaneous_ops_per_sec" | cut -d: -f2 | tr -d '\r')

# 輸出報告
echo "## 📊 Redis 健康報告"
echo "- 狀態：✅ 正常運行"
echo "- 記憶體使用：$USED_MEM / ${MAX_MEM:-無限制}"
echo "- 連線數：$CONNECTED_CLIENTS"
echo "- 命中率：${HIT_RATE}%"
echo "- QPS: $OPS_PER_SEC"
echo "- 檢查時間：$(date '+%Y-%m-%d %H:%M:%S')"
