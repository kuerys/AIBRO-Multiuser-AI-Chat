# AIBRO 資訊中心

## 功能特色

### 📋 備忘清單管理
- 自動讀取 `memory/YYYY-MM-DD.md` 檔案
- 支援新增、刪除、標記完成
- 跨會話持久化儲存

### 🔄 工作流觸發
- 根據備忘項目自動觸發對應工作流
- 支援定時檢查（預設每 5 分鐘）
- 可與 n8n、ComfyUI 等服務串接

### 📢 艦隊廣播
- 將重要資訊廣播給其他 AIBRO 實例
- 支援 Telegram/WhatsApp 通知
- 可設定優先級別

## 使用範例

```bash
# 列出所有待辦事項
aibro-info list

# 新增備忘
aibro-info add "檢查 SSL 憑證到期日"

# 觸發工作流
aibro-info trigger ssl-check

# 查看最近活動
aibro-info activity
```

## 檔案結構

```
aibro-info-center/
├── SKILL.md          # Skill 描述
├── README.md         # 本檔案
├── bin/
│   └── aibro-info    # CLI 主程式
└── workflows/        # 預設工作流定義
    └── default.json
```

## 與其他 AI 協作

其他 AI 實例可透過讀取此 Skill 的描述檔案，了解如何與 AIBRO 艦隊進行資訊交換。
