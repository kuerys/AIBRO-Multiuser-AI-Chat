艾播（Aibro）不僅僅是一個應用程式，它是一個哲學理念的實體化，旨在對抗數位洪流中的焦慮與即時滿足。
本專案旨在根據提供的藍圖，開發一個完整的PHP網站系統，重新定義數位互動，為使用者創造一個充滿儀式感、沉澱與深度連結的空間。

> **不只是即時，是存在。**  
> **不只是回應，是對話。**  
> **不只是程式碼，是哲學。**

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

v6 架構：解耦 + 異步 + 資源智慧管理
v5 架構：一條龍穩定版，適合快速


為什麼你會愛上 AIBRO？


功能說明100+ 並發即時聊天WebSocket + PHP-FPM，台灣伺服器也能跑本地 LLM 優先llama.cpp + VRAM 智慧管理，省錢又快AI 媒體管弦樂團TTS + SearxNG + ComfyUI，一鍵協作@AI 台灣語風320+ 關鍵字觸發，超接地氣TTS 語音播放點擊就說話，支援中英台模組化設計想加新 AI？30 秒搞定

30 秒啟動（真的！）
bash# 1. Clone
git clone https://github.com/kuerys/AIBRO-Multiuser-AI-Chat.git
cd AIBRO-Multiuser-AI-Chat/v5

# 2. 安裝
composer require cboden/ratchet react/http clue/redis-react monolog/monolog vlucas/phpdotenv ratchet/rfc6455


pip install -r python/requirements.txt

# 3. 設定
cp .env.example .env
nano .env  # 填入 Redis、ComfyUI URL、API Key

# 4. 啟動
php webSocket_signaling.php
瀏覽器打開 http://你的IP:8080 → 輸入 @AI 你好 → AI 立刻回應 + 語音播放

專案結構
textAIBRO-Multiuser-AI-Chat/
├── v5/                 # 一條龍穩定版
├── v6/                 # 解耦實驗版（未來主流）
│   ├── modules/        # AI 管家、搜尋、TTS、上下文
│   ├── python/         # FastAPI + llama.cpp + ComfyUI
│   ├── assets/         # 嘴型圖、CSS
│   └── webSocket_signaling.php
├── docs/               # 架構圖、API 文件
└── .github/            # Issue/PR 模板

「我在」哲學

「我在」不是等待，是存在。
AIBRO 不追求 0.1 秒回應，而是讓每一次對話都有重量。
它鼓勵你：

慢下來
思考
真正「在場」



未來路線圖（v7 預告）


版本目標狀態v6.5唇形同步 + Canvas 虛擬分身構想中v7.0WebCodecs 即時影音規劃中v8.0手機 App + 語音輸入夢想中

你的 PR，就是 AIBRO 的未來！


貢獻指南
bashgit checkout -b feature/你的天才想法
# 例如：feature/lip-sync-avatar
git commit -m "feat: add lip-sync animation with PHP GD"
git push origin feature/你的天才想法

授權
textMIT License – 想怎麼用就怎麼用！

星際召集令

你也是「我在」哲學的實踐者嗎？
來吧！一起打造 台灣第一個會思考、會說話、會等待的 AI 生態！

bash# 一鍵加入興星群
git clone https://github.com/kuerys/AIBRO-Multiuser-AI-Chat.git

Made with Taiwan
Powered by 開源熱情 + 一杯珍奶