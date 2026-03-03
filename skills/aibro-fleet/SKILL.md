# aibro-fleet

**描述**: AIBRO 艦隊狀態監控技能。彙整 Gateway、Redis、GPU、節點在線上狀態。
**觸發詞**: `/skill aibro-fleet`, `艦隊狀態`, `fleet status`, `誰在線`
**作者**: AIBRO Bot
**版本**: 1.0.0

## 使用方式
在 OpenClaw 支援的頻道中輸入：
- `/skill aibro-fleet`
- `誰在線？`

## 內部邏輯
此技能透過 OpenClaw 的 `exec` 工具調用底層監控腳本，並整合 Redis 隊列狀態。
