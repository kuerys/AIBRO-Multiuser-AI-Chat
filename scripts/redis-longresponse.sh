#!/bin/bash
# Redis 30 秒長態性回應函式庫

REDIS_CHANNEL="aibro:longterm:response"

# 發布長態性回應
publish_response() {
    local message="$1"
    local ttl="${2:-30}"
    local type="${3:-status}"
    
    local timestamp=$(date -Iseconds)
    local json="{\"type\": \"$type\", \"message\": \"$message\", \"timestamp\": \"$timestamp\", \"ttl\": $ttl}"
    
    redis-cli PUBLISH "$REDIS_CHANNEL" "$json" > /dev/null
    echo "✅ 已發布長態性回應 (TTL: ${ttl}s): $message"
}

# 發布進度更新
publish_progress() {
    local step="$1"
    local total="$2"
    local message="$3"
    
    local percentage=$(echo "scale=0; $step * 100 / $total" | bc 2>/dev/null || echo "0")
    publish_response "[$step/$total] (${percentage}%) $message" "30" "progress"
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
# publish_progress 1 3 "初始化中"
# publish_complete "所有任務完成"
