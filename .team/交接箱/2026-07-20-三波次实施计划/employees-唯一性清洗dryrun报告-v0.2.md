# employees 唯一性清洗 dry-run 报告 v0.2

- 生成时间：2026-07-20T22:39:01+08:00
- 模式：dry-run
- 写库：否
- 扫描范围：全表（含软删）
- 索引方案：active-only 生成列 UNIQUE
- 员工总数：12（在职 11 / 软删 1）
- 在职空编号：0；在职空邮箱：10
- 全表空编号：0；全表空邮箱：10
- 在职重复编号组：0
- 在职重复邮箱组：0
- 全表重复编号组（含跨软删同号，仅信息）：0
- 全表重复邮箱组（仅信息）：1
- **可上索引（active-only）：no**
- 阻断项：1

## 在职重复编号
（无）

## 在职重复邮箱
（无）

## 在职空邮箱（须归一 NULL）
[
    {
        "id": "9f4f9c77-0551-4c38-a7ac-a5b20d929a6d",
        "name": "俞炳星",
        "employee_number": "E000",
        "soft_delete": 0
    },
    {
        "id": "76b4ebdc-cafd-4ca0-9637-d3194c0fc594",
        "name": "曹红",
        "employee_number": "E003",
        "soft_delete": 0
    },
    {
        "id": "d39db2de-8942-4e7c-99d8-86eabfa4a057",
        "name": "李成辉",
        "employee_number": "E004",
        "soft_delete": 0
    },
    {
        "id": "f589817c-f82c-4ed6-8b0b-66fb81f6210f",
        "name": "刘恒春",
        "employee_number": "E005",
        "soft_delete": 0
    },
    {
        "id": "40eea6cc-9d5b-48f8-8ace-873a21b35197",
        "name": "付丽",
        "employee_number": "E006",
        "soft_delete": 0
    },
    {
        "id": "72812ba5-b58c-4846-9566-abd4dc033c67",
        "name": "米尔布拉·阿卜杜麦麦提",
        "employee_number": "E007",
        "soft_delete": 0
    },
    {
        "id": "c402ff6e-357c-4485-99ea-718916721eba",
        "name": "如则托合提·阿卜杜加帕尔",
        "employee_number": "E008",
        "soft_delete": 0
    },
    {
        "id": "936bdb6a-89d7-49b3-8721-009c038553e2",
        "name": "毛天一",
        "employee_number": "E009",
        "soft_delete": 0
    },
    {
        "id": "9e82e78d-aa6e-40cc-81df-5e13cc915362",
        "name": "王胜林",
        "employee_number": "E010",
        "soft_delete": 0
    },
    {
        "id": "477281ff-4862-4dee-9496-eb52d830f209",
        "name": "史广",
        "employee_number": "E011",
        "soft_delete": 0
    }
]

## 建议动作
- {"kind":"normalize_blank_to_null","fields":["employee_number","email"],"scope":"preferably_active_rows_with_empty_string","note":"空串归一 NULL 步骤保留；上 active-only UNIQUE 前必须执行"}
- {"kind":"blank_email_active","count":10,"action":"normalize_empty_string_to_null"}
