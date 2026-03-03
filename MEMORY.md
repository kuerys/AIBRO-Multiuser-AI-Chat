# AIBRO 長期記憶

這是 AI 的長期記憶檔案 — 導師精華濃縮，而非原始對話紀錄。

---

## 📅 重要系統里程碑

### 🌐 憑證與網路

**主站 SSL 憑證到期日**
- **域名**：`star-tw.ddns.net`
- **到期時間**：2026年5月12日 13:34 GMT
- **建立日期**：2026年2月20日（首次檢查）
- **提醒閾值**：
  - ✅ 前 30 天 → Telegram 語音提醒
  - ✅ 前 14 天 → Telegram 語音提醒
  - ✅ 前 7 天 → Telegram 語音提醒
- **檢查指令**：`sudo openssl x509 -enddate -noout -in /etc/letsencrypt/live/star-tw.ddns.net/fullchain.pem`

### 💻 本地 AI 架構 (硬體分流)

**GPU 配置**
- **GPU 0：RTX 3060 (12GB)** — **工具專用卡** (Tools)
  - 負責：Local TTS (語音), Qwen3-VL (視覺), ComfyUI (繪圖)
  - 特性：任務型負載，閒置時應釋放 VRAM
- **GPU 1 & 2：RTX 2080 Ti (22GB x2)** — **大腦專用卡** (Brain)
  - 負責：Ollama (glm-4.7-flash:30b)
  - 特性：常駐高負載，嚴禁執行其他任務以免影響推論

**模型配置 (Ollama)**
- **主模型**：`glm-4.7-flash:30b` (Q8_0) — 邏輯與工具使用 (GPU 1&2)
- **視覺模型**：`qwen3-vl:4b` — 圖片理解 (GPU 0)
- **Context Capacity**：32,768 tokens（穩定）

### 🖥️ 服務與監控

**多媒體服務**
- **TTS 語音服務**：`http://127.0.0.1:5005` (GPU 0)
  - 架構：多進程 (Multiprocessing)，閒置 60秒自動釋放 VRAM
  - 模型：Coqui TTS (Baker - 中文優化)
  - 技巧：使用 `local-tts` 工具並強制回傳 `MEDIA:` 格式以繞過權限限制
- **ComfyUI 守護神**：`http://127.0.0.1:9200` (GPU 0)
- **STT 聽寫服務**：Whisper.cpp (Port 9000)

**監控工具**
- `nvidia-smi` — GPU 狀態查詢
- `heartsbeat` — 定期檢查心願清單（空檔即跳過）

---

## 🤖 AI 能力與限制

**優勢**
- **全能語音互動**：具備聽 (Whisper) 與 說 (Coqui TTS) 的能力
- **多模態理解**：文字 + 圖片 (Qwen3-VL)
- **本地模型托管**：私有化、低延遲、高隱私

**限制**
- 需要 sudo 權限讀取敏感配置（SSL 憑證等）
- 大文件讀取有 32k/50k 限制
- 對外部網路的認知依賴 SearXNG 搜尋結果

---

## 🎯 行動原則

- **Text > Brain**（寫在文件永遠比記在腦子裡可靠）
- **安全第一**（不要隨意外洩主人的私人數據）
- **資源優先**（大腦獨佔 2080Ti，雜事全部丟給 3060）
- **主動語音**（在適當情境下，主動用語音回報狀態或講笑話）
- **操作邊界**：
  - **嚴禁**在 `/home/aihome/` 下使用全局 `pkill -f "python"`（會誤殺 TTS, n8n 等生產服務）。
  - **嚴禁**跨機 Kill 進程（每台機器管自己的進程）。
  - **尊重主人進程**：不主動干預主人運行的生產服務，除非明確要求。
  - **Systemd 配置**：務必確認 `ExecStart` 指向虛擬環境 Python (`venv/bin/python`)。

---

*建立日期：2026年2月20日*
*最後更新：2026年3月1日 (AIBRO 艦隊啟示錄 - 操作邊界與 Systemd 教訓)*