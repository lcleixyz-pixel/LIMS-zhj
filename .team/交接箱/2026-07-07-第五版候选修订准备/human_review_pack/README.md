# LIMS 人工评审与导入前决策包

生成时间：2026-07-07T07:05:47
文件状态：人工评审准备包，不写数据库，不代表受控批准。

## 包内容

- 条款人工评审清单：29 条
- 记录模板人工评审清单：26 条
- 编号附件/表单归属判定：1 条
- apply 前决策闸门：11 条

## 使用边界

1. 本包用于人工评审第五版候选稿和 LIMS 预导入包。
2. 本包不是写库批准，不得替代文件控制程序中的审核、批准和发布。
3. 所有 `human_decision` 初始均为 `pending`；未完成人工评审前不得运行 `--apply --ack-human-reviewed`。
4. `XZTC/CX-05-02-2022` 已按编号附件/表单分流，仍需人工确认归属。

## 文件清单

- `manual_clause_review_checklist.csv`：manual_clause_review
- `record_template_review_checklist.csv`：record_template_review
- `attachment_form_disposition.csv`：attachment_disposition
- `preapply_gate_register.csv`：preapply_gate_register
- `人工评审操作说明.md`：review_guide
- `human_review_manifest.json`：manifest
