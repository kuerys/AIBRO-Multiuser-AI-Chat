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

## 🤝 A2A Agent-to-Agent 通訊 (內網)

### XEON (Server 端)
- **端點**: `http://192.168.0.100:3001/a2a/message`
- **Agent Card**: `http://192.168.0.100:3001/.well-known/agent.json`
- **Key**: `754f04c3c4ba4e418fdceabb7a15f0e17a79b7aa5dcc7191dc1a84fb5701d94e`
- **狀態**: ✅ 運行中

### P43E (Server 端)
- **IP**: `192.168.0.102`
- **端點**: `http://192.168.0.102:3001/a2a/message`
- **Agent Card**: `http://192.168.0.102:3001/.well-known/agent.json`
- **Key**: `eaf91f071df30d8325e95820be8894a6d5a7b9b3cfaf432be9b2a1d62ebecb04`
- **狀態**: ✅ 運行中

### MSI (Server 端)
- **IP**: `192.168.0.101`
- **端點**: `http://192.168.0.101:3001/a2a/message`
- **Agent Card**: `http://192.168.0.101:3001/.well-known/agent.json`
- **Key**: `a599870742d64c0dbbe1c5759e4c57ff`
- **狀態**: ❌ A2A 服務器未運行

### 發送訊息指令
```bash
# XEON → P43E
curl -X POST http://192.168.0.102:3001/a2a/message \
  -H "Content-Type: application/json" \
  -H "X-Agent-Key: eaf91f071df30d8325e95820be8894a6d5a7b9b3cfaf432be9b2a1d62ebecb04" \
  -d '{"from": "AIBRO-XEON", "intent": "chat", "message": "測試訊息"}'

# P43E → XEON
curl -X POST http://192.168.0.100:3001/a2a/message \
  -H "Content-Type: application/json" \
  -H "X-Agent-Key: 754f04c3c4ba4e418fdceabb7a15f0e17a79b7aa5dcc7191dc1a84fb5701d94e" \
  -d '{"from": "AIBRO-P43E", "intent": "chat", "message": "測試訊息"}'
```

### OpenClaw Webhook 端點 (A2A 喚醒用)
```bash
# 觸發 heartbeat（立即執行）
curl -X POST http://localhost:18789/hooks/wake \
  -H "Authorization: Bearer TOKEN" \
  -d '{"text":"A2A消息","mode":"now"}'

# 運行獨立 agent
curl -X POST http://localhost:18789/hooks/agent \
  -H "Authorization: Bearer TOKEN" \
  -d '{"task":"任務內容"}'
```

---

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
