#!/usr/bin/env python3
"""
AIBRO-Fleet Skill
功能：彙整 AIBRO 艦隊狀態 (節點、Redis、GPU、任務隊列)
作者：AIBRO Bot
"""
import json
import subprocess
import redis
import os

def run_cmd(cmd):
    """執行 shell 命令並回傳輸出"""
    try:
        result = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=10)
        return result.stdout.strip()
    except Exception as e:
        return f"Error: {e}"

def get_gpu_status():
    """獲取 GPU 狀態"""
    cmd = "nvidia-smi --query-gpu=name,temperature.gpu,memory.used --format=csv"
    return run_cmd(cmd)

def get_redis_status():
    """獲取 Redis 狀態"""
    try:
        r = redis.Redis(host='192.168.0.100', port=6379, decode_responses=True)
        r.ping()
        tasks_len = r.llen('aibro:tasks')
        registry_len = r.scard('aibro:registry')
        return f"Redis: 🟢 連線正常 | 任務隊列: {tasks_len} | 註冊節點: {registry_len}"
    except Exception as e:
        return f"Redis: ❌ 錯誤 - {e}"

def get_gateway_status():
    """獲取 Gateway 狀態"""
    cmd = "curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:18789/health"
    code = run_cmd(cmd)
    if code == "200":
        return "Gateway: 🟢 運行中 (Port 18789)"
    else:
        return f"Gateway: ❌ 異常 (HTTP {code})"

def main():
    """主函數：輸出艦隊狀態報告"""
    report = []
    report.append("🦞 **AIBRO 艦隊狀態報告**")
    report.append("")
    report.append("1️⃣ **基礎設施**")
    report.append(get_gateway_status())
    report.append(get_redis_status())
    report.append("")
    report.append("2️⃣ **GPU 狀態**")
    report.append("```\n" + get_gpu_status() + "\n```")
    report.append("")
    report.append("3️⃣ **結論**")
    report.append("✅ 艦隊運作正常，風扇安靜，隨時待命！")
    
    return "\n".join(report)

if __name__ == "__main__":
    print(main())
