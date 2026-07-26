# 仓库侧 Route A 执行记录 v0.1

- 日期：2026-07-23
- 执行侧：Codex 仓库侧
- 范围：BG-01-02《人员培训记录表》、BG-01-06《培训申请表》
- 边界：沙箱/代码层准备；未写现用 8010；未替机构做真实试填或验收签字

## 执行动作

1. 按已签认蓝图更新两张人员培训记录模板的字段 schema。
2. BG-01-02 参加人员改为一对多子行，列为姓名、岗位、签名。
3. BG-01-06 增补申请人/部门/日期、培训类别、预期目标/能力要求、技术负责人审核、实验室主任批准。
4. 打印层增加使用场所驱动的双号呈现：乌市显示 `XZTC/BG-xx-xx`，和田显示 `XZTCH-BG-xx-xx`，同时保留母版编号。
5. 生成乌市/和田各 2 份 HTML 与 PDF 样张。

## 回报物状态

| 回报物 | 当前状态 | 位置 | 说明 |
|---|---|---|---|
| 模板样张 PDF | 仓库侧已生成 | `样张/*.pdf` | 4 份：BG-01-02/01-06 × 乌市/和田 |
| 两场所试填件 | 仓库侧提供样例；真实试填待机构 | `样张/*.html` / `*.pdf` | 当前是代码生成试填样例，不冒充真实业务试填 |
| 更正留痕演示记录 | 待双方执行 | — | 需在沙箱中创建记录、修改一处、验证原值可追溯 |
| 验收签字页 | 待机构 | — | 需业务人员验收签字后回交 |

## 纪律

209字段候选包与蓝图有差异时,一律回报裁决,不得静默采纳

生成器不能自证；本次字段 schema 以已签认 Route A 蓝图为准。外部候选包到位后，只能作为交叉核验输入，不得直接覆盖本蓝图。

## 验证

- `docker compose exec -T app php tests/record_forms_personnel_fidelity_smoke.php`
- `docker compose exec -T app php tests/record_forms_print_smoke.php`
- `docker compose exec -T app php tests/record_forms_schema_smoke.php`
- `docker compose exec -T app php -l app/service/RecordFormPrintService.php`
- `docker compose exec -T app php -l app/service/RecordFormFixtureService.php`
- `docker compose exec -T app php -l app/record_form_print/training_record.php`
- `docker compose exec -T app php -l app/record_form_print/_personnel_record_forms.php`
- `docker compose exec -T app php -l tests/record_forms_personnel_fidelity_smoke.php`
- `node scripts/render-record-pdf.mjs ...` 已为 4 份样张生成 PDF

## 下一棒

1. 机构侧在沙箱按两场所真实业务各试填至少 1 份。
2. 仓库侧配合执行更正留痕演示。
3. 机构回交验收签字页。
4. 四回报物齐备后，由内容引擎登记主路径切换日期并关闭 Route A 试点。
