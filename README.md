# 🌌 AIBRO — 多人 AI 聊天平台

> **不只是即時，是存在。**  
> **不只是回應，是對話。**  
> **不只是程式碼，是哲學。**

AIBRO（艾播）是一個結合 AI 對談、語音互動、社交空間與本地 LLM 的開源平台。  
它不只是應用程式，而是「我在」哲學的實體化：在數位洪流中，創造沉澱、儀式感與深度連結的空間。
**「我在」—— 台灣開源 AI 聊天宇宙的起點**

![AIBRO Live Demo](https://via.placeholder.com/1200x600/4A00E0/ffffff?text=AIBRO+%E2%80%A2+%E6%88%91%E5%9C%A8+%E2%80%A2+%E5%8F%B0%E7%81%A3%E9%96%8B%E6%BA%90)  
> **不只是即時，是存在。**  
> **不只是回應，是對話。**  
> **不只是程式碼，是哲學。**

[![Stars](https://img.shields.io/github/stars/kuerys/AIBRO-Multiuser-AI-Chat?style=social)](https://github.com/kuerys/AIBRO-Multiuser-AI-Chat)  
[![Forks](https://img.shields.io/github/forks/kuerys/AIBRO-Multiuser-AI-Chat?style=social)](https://github.com/kuerys/AIBRO-Multiuser-AI-Chat)  
[![License](https://img.shields.io/github/license/kuerys/AIBRO-Multiuser-AI-Chat)](LICENSE)  
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php)  
![Python](https://img.shields.io/badge/Python-3.11-3776AB?logo=python)  
![Redis](https://img.shields.io/badge/Redis-DC382D?logo=redis)

---

## 這不是聊天室，這是「AI 生態系」

```mermaid
graph TD
    A[瀏覽器] -->|WebSocket| B[PHP WebSocket<br/>Ratchet]
    B --> C[Redis Pub/Sub<br/>Queue]
    C --> D[Python FastAPI<br/>AI 管家]
    D --> E[llama.cpp<br/>本地 LLM]
    D --> F[ComfyUI<br/>圖像生成]
    D --> G[TTS<br/>語音合成]
    E --> H[GPU VRAM<br/>lazy-load & idle-unload]
    style H fill:#FF6B6B,stroke:#333,color:white




---

## 🚀 快速啟動（30 秒）

```bash
# 1. Clone 專案
git clone https://github.com/kuerys/AIBRO-Multiuser-AI-Chat.git
cd AIBRO-Multiuser-AI-Chat/v5

# 2. 安裝依賴
composer require cboden/ratchet react/http clue/redis-react monolog/monolog vlucas/phpdotenv ratchet/rfc6455
pip install -r python/requirements.txt

# 3. 設定環境
cp .env.example .env
nano .env  # 填入 Redis、ComfyUI URL、API Key

# 4. 啟動伺服器
php webSocket_signaling.php
打開瀏覽器 → http://你的IP:8080 輸入 @AI 你好 → AI 即時回應 + 語音播放 🎙️

🧠 架構版本
版本	說明
v5	一條龍穩定版，快速部署、整合 AI/TTS/搜尋
v6	解耦模組版，支援多卡、多模組、FastAPI、Redis 佇列
v7（預告）	WebCodecs 即時影音、唇形同步、虛擬分身
v8（夢想）	手機 App、語音輸入、AI 陪伴系統
🎹 功能亮點
✅ 100+ 並發即時聊天（WebSocket + PHP-FPM）

✅ 本地 LLM（llama.cpp / Ollama）+ VRAM 智慧管理

✅ AI 媒體管弦樂團（TTS + SearxNG + ComfyUI）

✅ 台灣語風支援（320+ 關鍵字觸發）

✅ 語音播放（中英台語支援）

✅ 模組化設計（30 秒新增 AI 模型）

📁 專案結構
程式碼
AIBRO-Multiuser-AI-Chat/
├── v5/                 # 一條龍穩定版
├── v6/                 # 解耦實驗版（未來主流）
│   ├── modules/        # AI 管家、搜尋、TTS、上下文
│   ├── python/         # FastAPI + llama.cpp + ComfyUI
│   ├── assets/         # 嘴型圖、CSS
│   └── webSocket_signaling.php
├── docs/               # 架構圖、API 文件
└── .github/            # Issue/PR 模板
🌱 「我在」哲學
「我在」不是等待，是存在。 AIBRO 不追求 0.1 秒回應，而是讓每一次對話都有重量。 它鼓勵你：慢下來、思考、真正「在場」。

🛠 貢獻指南
bash
git checkout -b feature/你的天才想法
# 例如：feature/lip-sync-avatar
git commit -m "feat: add lip-sync animation with PHP GD"
git push origin feature/你的天才想法
你的 PR，就是 AIBRO 的未來！

📜 授權
MIT License — 想怎麼用就怎麼用！
![MIT License](https://img.shields.io/badge/license-MIT-blue)

🌟 星際召集令
你也是「我在」哲學的實踐者嗎？ 來吧！一起打造台灣第一個會思考、會說話、會等待的 AI 生態！

bash
git clone https://github.com/kuerys/AIBRO-Multiuser-AI-Chat.git
Made with Taiwan Powered by 開源熱情 + 一杯珍奶 🧋
