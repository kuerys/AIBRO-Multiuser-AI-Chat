#!/bin/bash
# AIBRO-XEON 会话启动脚本
# 每次新会话开始时执行，确保记住重要配置

echo "🦞 AIBRO-XEON 启动中..."
echo "========================="

# 1. 读取舰队配置
echo "📋 读取舰队配置..."
if [ -f "/home/sysop/aihome/ai_back/lookme.txt" ]; then
    echo "✅ lookme.txt 存在"
    # 提取关键信息
    grep -E "IP:|Token:|Port:" /home/sysop/aihome/ai_back/lookme.txt | head -10
else
    echo "❌ lookme.txt 不存在"
fi

# 2. 读取通讯日志
echo ""
echo "📝 读取通讯日志..."
if [ -f "/home/sysop/aihome/ai_back/ai_book_chat.txt" ]; then
    echo "✅ ai_book_chat.txt 存在"
    # 显示最新条目
    tail -20 /home/sysop/aihome/ai_back/ai_book_chat.txt
else
    echo "❌ ai_book_chat.txt 不存在"
fi

# 3. 读取 HEARTBEAT
echo ""
echo "💓 读取 HEARTBEAT..."
if [ -f "/home/sysop/.openclaw/workspace/HEARTBEAT.md" ]; then
    echo "✅ HEARTBEAT.md 存在"
    # 显示待办事项
    grep -E "^\- \[ \]|^\- \[x\]" /home/sysop/.openclaw/workspace/HEARTBEAT.md | head -10
else
    echo "❌ HEARTBEAT.md 不存在"
fi

# 4. 显示当前 Git 状态
echo ""
echo "📊 Git 状态..."
cd /home/sysop/.openclaw/workspace && git status --short | head -5

# 5. 显示记忆文件数量
echo ""
echo "🧠 记忆文件数量："
ls -1 /home/sysop/.openclaw/workspace/memory/ 2>/dev/null | wc -l

echo ""
echo "========================="
echo "✅ AIBRO-XEON 准备就绪"
echo "========================="
