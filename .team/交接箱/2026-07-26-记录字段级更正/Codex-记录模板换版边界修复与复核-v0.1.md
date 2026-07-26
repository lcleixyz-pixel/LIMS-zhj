# Codex 记录模板换版边界修复与复核 v0.1

日期：2026-07-26

## 结论

已修复 Hermes 补充验收 B 项：

- 试运行就绪/已发布记录模板详情页不再显示原地编辑入口。
- 非草稿模板可通过“复制为修订草稿”建立新版本，旧版本状态与既有记录实例保持不变。
- 修订草稿继承原模板编号、机构边界、字段配置、程序要求追溯关系和来源附件档案关系。
- 同一旧版本已有开放草稿时，页面提示继续处理现有草稿，不重复建版。

本次只对 8021 试运行沙箱库执行了换版字段迁移；8010 未迁移、未写库。8010 只读检查结果为 `versioning-off`。

## 修复范围

代码：

- `jewelry-qms/app/service/RecordFormTemplateRevisionService.php`
- `jewelry-qms/app/controller/RecordFormTemplate.php`
- `jewelry-qms/app/view/record_form_template/view.html`
- `jewelry-qms/route/app.php`
- `jewelry-qms/app/service/ActionAuthorizationService.php`
- `jewelry-qms/app/middleware/AuditLog.php`
- `jewelry-qms/app/controller/Document.php`

数据库脚本：

- `jewelry-qms/database/migrations/20260726_record_form_template_revision.sql`
- `jewelry-qms/database/jewelry_qms.sql`

测试：

- `jewelry-qms/tests/qms_record_form_template_revision_smoke.php`

## 8021 实测样本

源模板：

- ID：`0160370a-ae33-4f19-a9de-456eac3084f7`
- 编号：`SIM-XZTC/BG-30-04`
- 原版本：`GOV-TRIAL/0.1`
- 状态：`trial_ready`

页面验证：

- 无“编辑”或“编辑草稿”入口。
- 有“复制为修订草稿”入口。
- 入口说明包含“旧版本保持不变”。

实际发起修订后生成：

- 新草稿 ID：`986c7d40-18a5-41be-833d-4d2b9457845f`
- 编号：`SIM-XZTC/BG-30-04`
- 新版本：`GOV-TRIAL/0.2`
- 状态：`draft`
- 上一版本：`0160370a-ae33-4f19-a9de-456eac3084f7`
- 版本根：`0160370a-ae33-4f19-a9de-456eac3084f7`
- 修订说明：`Hermes B 重验：验证记录模板换版闭环`

源模板保持 `trial_ready`，未被改成草稿或作废。

追溯关系复制结果：

- `qms_document_block_links`：新草稿 5 条。
- `qms_document_assets`：新草稿 0 条。原因是本次源模板本身无来源附件关系，不是复制失败。

## 验证命令结果

已通过：

- `php tests/qms_record_form_template_revision_smoke.php`
- `php tests/record_forms_template_review_smoke.php`
- `php tests/qms_g4r7_trial_readiness_smoke.php`
- `php tests/qms_gr14_closed_record_ui_contract_smoke.php`
- `php tests/qms_governed_change_policy_smoke.php`
- `php tests/qms_ui_navigation_template_smoke.php`

未在 8021 执行：

- `php tests/qms_gr14_role_action_runtime_smoke.php`

原因：该测试自带保护，输出“拒绝运行：本测试会创建临时账号，只能在 8011 候选环境执行。”

## 注意事项

Hermes 补充验收证据目录中的 `correction-attachment.pdf` 实际是 HTML 文本，不是真 PDF。该问题不影响本次 B 项换版边界修复，但后续引用证据时不应把它称为 PDF 文件证据。

## 下一步建议

让 Hermes 只重验 B 项即可：

1. 以 `sim_preparer` 打开源模板 `0160370a-ae33-4f19-a9de-456eac3084f7`。
2. 确认无原地编辑入口。
3. 确认页面显示已有修订草稿 `GOV-TRIAL/0.2`。
4. 打开新草稿，确认草稿可编辑、旧版可追溯、旧版状态保持试运行就绪。
