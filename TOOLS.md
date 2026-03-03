# 🖥️ Local Compute Infrastructure (Xeon Server)

## 主體 AI 引擎
- **Ollama** (Port 11434)
  - **Primary Model**: `glm-4.7-flash:30b` (Q8_0) – 高邏輯、工具使用
  - **GPU**: 2080 Ti ×2 (GPU 1 & 2) – 專屬 Brain 卡
  - **Visual Model**: `qwen3‑vl:4b` – 視覺任務
  - **GPU**: RTX 3060 (GPU 0) – 專屬 Tools 卡

## 儲存策略
- **AI Home**: `/aihome` (RAID0 NVMe) – 模型與大型資料
- **Workspace**: `/home/sysop/.openclaw/workspace`

## 多媒體與資料服務
- **SearXNG**: `http://127.0.0.1:8888` – 預設網路搜尋
- **FFmpeg 控制**: Port `8081‑8082` – Live‑mixer、影像/音訊處理
- **音訊處理**
  - STT: `whisper.cpp` (Port 9000)
  - TTS: Coqui TTS (Port 5005, GPU 0) – `local-tts` 指令，強制回傳 `MEDIA:`
- **自動化工作流**: `n8n` (Port 5678)

## 執行風格與最佳實踐
- **GPU 優先排程**
  - Logic / Text → GPU 1 & 2 (2080 Ti)
  - Visual / Audio → GPU 0 (RTX 3060)
- **檔案操作**：優先使用 `trash` 而非 `rm`
- **搜尋**：即時資訊走 `searxng-search`；私人上下文走 `MEMORY.md`

## 系統指令

### GPU 監控
```bash
nvidia-smi --query-gpu=name,index,memory.used,temperature.gpu --format=csv
```

### ComfyUI Guardian (Port 9200)
智能 GPU 排程器，主卡 RTX 3060 空閒 5 分鐘自動釋放 VRAM

喚醒指令：
```bash
curl -s http://127.0.0.1:9200
```

### SSL 憑證檢查
```bash
openssl x509 -enddate -noout -in /etc/letsencrypt/live/star-tw.ddns.net/fullchain.pem
```
