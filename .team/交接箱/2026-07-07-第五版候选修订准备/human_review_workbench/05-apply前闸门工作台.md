# apply 前闸门工作台

本工作台只用于人工评审准备，不写数据库，不代表审核批准。

| gate_id | gate_name | gate_type | current_evidence | human_decision | required_before_apply |
|---|---|---|---|---|---|
| GATE-01 | 候选修订包门禁 | automated_evidence | 17-候选修订包验证报告.json: passed, findings=0 | pending | yes |
| GATE-02 | LIMS 预导入包结构 dry-run | automated_evidence | 18-LIMS预导入包dry-run验证报告.json: passed, findings=0 | pending | yes |
| GATE-03 | 记录模板模拟试填 | automated_evidence | 20-记录模板试填dry-run验证报告.json: passed, findings=0 | pending | yes |
| GATE-04 | 记录模板全量模拟试填 | automated_evidence | 25-记录模板全量试填dry-run验证报告.json: passed, findings=0 | pending | yes |
| GATE-05 | LIMS 命令层 dry-run | automated_evidence | 21-LIMS预导入命令dry-run报告.json: passed, findings=0 | pending | yes |
| GATE-06 | 未确认 apply 阻断 | automated_evidence | 22-LIMS预导入apply闸门验证报告.json: blocked, findings=1 | pending | yes |
| GATE-07 | 候选手册人工评审 | human_review | pending_human_review | pending | yes |
| GATE-08 | 26 个记录模板字段评审 | human_review | pending_human_review | pending | yes |
| GATE-09 | 05-02 编号附件/表单归属 | human_review | pending_human_review | pending | yes |
| GATE-10 | 外来依据台账人工查新 | human_review | pending_human_review | pending | yes |
| GATE-11 | 用户明确授权 apply | human_review | pending_human_review | pending | yes |
