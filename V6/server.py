from fastapi import FastAPI, Request
from fastapi.responses import StreamingResponse
from pydantic import BaseModel
from llama_cpp import Llama
import uvicorn
import json
import time
import threading
import asyncio
import gc
import os
import psutil

app = FastAPI(title="Gemma Chat API", version="1.0.0")


llm = None
last_used = 0
IDLE_TIMEOUT = 300  #設為 300 秒


def get_llm():
    global llm, last_used
    if llm is None:
        try:
            llm = Llama(
                model_path="/aihome/llama.cpp/models/Gemma-3-TAIDE-12b-Chat-Q6_K.gguf",
                n_ctx=4096,
                n_threads=os.cpu_count(),      # ✅ CPU 自動使用所有核心
                n_gpu_layers=40                # ✅ GPU 手動指定層數（12GB 建議 40~50）
            )
            print("✅ 模型載入完成")
        except Exception as e:
            print(f"❌ 模型載入失敗: {e}")
            raise RuntimeError("模型初始化失敗")
    last_used = time.time()
    return llm


def idle_monitor():
    global llm, last_used
    while True:
        if llm and time.time() - last_used > IDLE_TIMEOUT:
            print("🧹 模型閒置釋放中")
            try:
                # 嘗試取得進程 PID 並終止（選用）
                for proc in psutil.process_iter(['pid', 'name', 'cmdline']):
                    if proc.info['cmdline'] and "llama.cpp" in " ".join(proc.info['cmdline']):
                        print(f"🔪 終止模型進程 PID {proc.info['pid']}")
                        proc.terminate()
                llm = None
                gc.collect()
                print("✅ 模型已釋放")
            except Exception as e:
                print(f"⚠️ 模型釋放失敗：{e}")
        time.sleep(10)

threading.Thread(target=idle_monitor, daemon=True).start()

# 輸入格式驗證
class ChatMessage(BaseModel):
    role: str
    content: str

class ChatRequest(BaseModel):
    model: str
    messages: list[ChatMessage]
    max_tokens: int = 256
    temperature: float = 0.7
    stream: bool = False

# Gemma prompt 格式
def build_prompt(messages):
    parts = []
    for m in messages:
        if m.role == "system":
            parts.append(f"<start_of_turn>system\n{m.content}<end_of_turn>")
        elif m.role == "user":
            parts.append(f"<start_of_turn>user\n{m.content}<end_of_turn>")
        elif m.role == "assistant":
            parts.append(f"<start_of_turn>model\n{m.content}<end_of_turn>")
    parts.append("<start_of_turn>model\n")
    return "\n".join(parts)

# SSE Streaming 回傳
async def stream_response(prompt, request_obj: ChatRequest, request: Request):
    created_ts = int(time.time())
    finish_reason = None
    llm_instance = get_llm()

    for chunk in llm_instance(prompt, max_tokens=request_obj.max_tokens, temperature=request_obj.temperature, stream=True):
        text = chunk["choices"][0]["text"]
        fr = chunk["choices"][0].get("finish_reason")
        if fr:
            finish_reason = fr

        if text:
            data = {
                "id": f"chatcmpl-{created_ts}",
                "object": "chat.completion.chunk",
                "model": request_obj.model,
                "choices": [{
                    "delta": {"content": text},
                    "index": 0,
                    "finish_reason": None
                }]
            }
            yield f"data: {json.dumps(data, ensure_ascii=False)}\n\n"
            await asyncio.sleep(0.01)

    final = {
        "id": f"chatcmpl-{created_ts}",
        "object": "chat.completion.chunk",
        "model": request_obj.model,
        "choices": [{
            "delta": {},
            "index": 0,
            "finish_reason": finish_reason or "stop"
        }]
    }
    yield f"data: {json.dumps(final, ensure_ascii=False)}\n\n"
    yield "data: [DONE]\n\n"

# API 入口
@app.post("/v1/chat/completions")
async def chat(request: Request, body: ChatRequest):
    prompt = build_prompt(body.messages)
    if body.stream:
        return StreamingResponse(
            stream_response(prompt, body, request),
            media_type="text/event-stream",
            headers={
                "Cache-Control": "no-cache",
                "Connection": "keep-alive",
                "X-Accel-Buffering": "no"
            }
        )
    else:
        llm_instance = get_llm()
        output = llm_instance(prompt, max_tokens=body.max_tokens, temperature=body.temperature)
        return {
            "id": f"chatcmpl-{int(time.time())}",
            "object": "chat.completion",
            "model": body.model,
            "choices": [{
                "message": {
                    "role": "assistant",
                    "content": output["choices"][0]["text"].strip()
                },
                "finish_reason": output["choices"][0].get("finish_reason", "stop"),
                "index": 0
            }]
        }

# 本地啟動（請確認檔案名稱）
if __name__ == "__main__":
    uvicorn.run("server:app", host="0.0.0.0", port=8008, timeout_keep_alive=60)
