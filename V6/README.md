# AIBRO – 台灣開源 AI 聊天室（v6 實驗中）

![AIBRO v6]
> **「我在」** – 不只是聊天，是等待、是連結、是存在。

---

## 為什麼你會愛上 AIBRO？

| 特色 | 說明 |
|------|------|
| **多用戶即時聊天** | WebSocket + Ratchet，100+ 並發無壓力 |
| **本地 LLM + VRAM 智慧管理** | `lazy-load` / `idle-unload`，不浪費 GPU |
| **AI 媒體管弦樂團** | TTS + SearxNG + ComfyUI，全部自動協作 |
| **PHP + Python 混合架構** | 前端即時，後端高效 |
| **成本控制神器** | 優先本地 LLM，後備雲端 API |

---

## 快速啟動（30 秒跑起來！）

```bash
git clone https://github.com/kuerys/AIBRO-Multiuser-AI-Chat.git
cd AIBRO-Multiuser-AI-Chat/v6

# 安裝
composer install
pip install -r python/requirements.txt

# 設定
cp .env.example .env
nano .env  # 填 API 金鑰、Redis、ComfyUI URL

# 啟動
php webSocket_signaling.php