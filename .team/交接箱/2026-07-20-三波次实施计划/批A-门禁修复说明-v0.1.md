# 批 A · 门禁修复说明 v0.1

| 项 | 结果 |
|----|------|
| A1 runner 口径 | PASS=exit0∧哨兵；Exception]/Fatal→FAIL。复判全量 **PASS=70 / FAIL=59**（≤82） |
| A2 #86 | 全表 UNIQUE → active-only 生成列 UNIQUE；`qms_responsibility_draft_smoke` 双跑 PASS；fixture 改为直接插软删历史行 |
| A3 dry-run v0.2 | 全表含软删；8010 `ready_for_unique_index=no`（在职空邮箱 10，须 ''→NULL） |
| A4 环境 | 挂载 docs/import-preview、现用文件、knowledge；5 个原 Fatal 脚本复验 PASS |

## 关键产物
- `全量smoke-129-汇总-A1口径-20260720.md`
- `A2-responsibility_draft双跑-20260720.txt`
- `employees-唯一性清洗dryrun报告-v0.2.md`
- `75可重复-分流台账-v0.2.md`
- `A4-材料挂载复验-20260720.txt`
