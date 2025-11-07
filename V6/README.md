\# 🧩 V6 — 模組化架構 (AIBRO 模擬版)



\### 🎯 目的

模擬「多人聊天室 + AI 併發排程」，驗證 AIBRO 主系統的中控理念。



---



\### 🔬 測試範圍

\- ✅ WebSocket 房間訊號維持  

\- ✅ Redis 任務佇列  

\- ✅ 多行 API 呼叫流程  

\- ✅ Ollama 生成管家  

\- ✅ llama\_cpp 作為 MCP 核心  

\- ✅ 插件整合：TTS 、Search 、ComfyUI  



---



\### ⚙️ 架構重點

\- 拆分 WebSocket 訊號層與 AI 中樞層；

\- 使用 \*\*Python FastAPI + llama\_cpp\*\* 作為 \*\*MCP (主控程序)\*\*；

\- 插件化整合 TTS 、ComfyUI 、搜尋模組；

\- 以 \*\*Redis 佇列\*\* 處理併發與任務排程。



---



\### ✨ 特性

\- 💬 高併發、多房間支援；  

\- 🔌 模型與感官模組可熱插拔；  

\- 🧠 適合研究、測試及企業級整合。





