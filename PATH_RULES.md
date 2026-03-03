# 🗺️ AIBRO 路徑與功能區劃規則

> **最高指導原則**: 嚴守路徑權限，不越界訪問，保持環境整潔。

## 📂 核心路徑對照表

| 路徑 | 代號 | 功能描述 | 操作準則 |
| :--- | :--- | :--- | :--- |
| **`/home/sysop/aihome`** | `SHARED` | **AI 群組共享空間** | ✅ **讀寫自由**: AI 之間交換文件、臨時數據、共享上下文。<br>⚠️ **注意**: 定期清理過期文件，避免堆積。 |
| **`/home/sysop/ai_run`** | `RUNTIME` | **Python 腳本執行區** | ✅ **腳本部署**: 所有 `.py` 腳本請放置於此。<br>✅ **執行環境**: 在此目錄下運行 `python script.py`。<br>❌ **禁止**: 不要在這裡存放非執行文件。 |
| **`/home/sysop/www`** | `SHOWCASE` | **AIBRO Web 展示區 (V5)** | ✅ **靜態資源**: 放置 V5 簡化版的 HTML/CSS/JS。<br>✅ **Web 服務**: 對應 Nginx/Apache 的 root 目錄。<br>🎯 **目標**: 讓主人能透過瀏覽器看到 AIBRO 的狀態。 |
| **`/aihome`** | `MODELS` | **AI 模型倉庫** | ✅ **模型存儲**: 存放 Ollama, Whisper, Coqui 等大型模型。<br>⚠️ **只讀優先**: 除非更新模型，否則盡量不更動。<br>💾 **空間大戶**: 注意磁碟空間使用。 |
| **`/home/aihome`** | `LEGACY` | **舊版遺址 (Legacy)** | 🏛️ **歷史檔案**: 主人早期的 Python 作品。<br>⚠️ **區別注意**: 與 `/home/sysop/aihome` (共享區) **完全不同**，請勿混淆！<br>📜 **只讀參考**: 除非重構舊項目，否則以讀取為主。 |
| **`/home/sysop/.openclaw/workspace`** | `BRAIN` | **AIBRO 大腦與記憶** | 🧠 **核心工作區**: 存放 Skills, Memory, Config, HEARTBEAT.md。<br>🔒 **高優先級**: 嚴禁刪除此處關鍵配置。 |

---

## 🚦 使用場景範例

### 1. 部署新技能腳本
```bash
# ❌ 錯誤：放在共享區或舊址
# /home/sysop/aihome/my_skill.py  (容易混亂)
# /home/aihome/my_skill.py        (容易遺忘)

# ✅ 正確：放在執行區
cp my_skill.py /home/sysop/ai_run/
cd /home/sysop/ai_run
python my_skill.py
```

### 2. 更新 Web 展示頁面
```bash
# ✅ 正確：更新 V5 展示區
cp index.html /home/sysop/www/
```

### 3. AI 之間交換文件
```bash
# ✅ 正確：使用共享空間
cp data.json /home/sysop/aihome/ai_group_data.json
```

---

## ⚠️ 常見錯誤與避免

| 錯誤行為 | 後果 | 正確做法 |
| :--- | :--- | :--- |
| 混淆 `/home/aihome` 與 `/home/sysop/aihome` | 讀錯舊數據或污染共享區 | 記住：**有 `sysop` 的才是共享區** |
| 在 `/home/sysop/www` 跑 Python | 權限錯誤或污染 Web 目錄 | Web 目錄只放靜態資源，腳本去 `ai_run` |
| 模型下載到工作區 | 耗盡系統盤空間 | 模型一律走 `/aihome` |

---

**更新時間**: 2026-03-02  
**版本**: v1.0 (AIBRO 艦隊領土法)
