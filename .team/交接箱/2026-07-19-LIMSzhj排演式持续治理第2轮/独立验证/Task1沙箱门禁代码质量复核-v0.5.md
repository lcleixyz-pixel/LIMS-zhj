# Task1 沙箱门禁代码质量复核 v0.5

## 1. 结论

**APPROVED。**

本次 SQL guard 小修关闭了 8013 与隔离栈之间的真实环境差异：145 个现用 `record_form_templates` 属于受控内容基线，不应被“所有模板身份必须 SIM”这一旧规则误判；当前实现只对 SIM ID 或当前排演 `run_id` 标记的模板执行 SIM 身份检查，因此现用内容模板可保留，演练模板仍保持 fail-closed。

该结论只针对此次 guard 修复及 8013 受控 SIM 装载，不扩大 v0.4 的授权范围。

## 2. 根因关闭判断

修复后的限定集合是：

```sql
id LIKE 'SIM-%' OR trial_batch = @rehearsal_run_id
```

其中 `@rehearsal_run_id` 从数据库内 `qms_rehearsal_environment_marker` 读取，而不是只信任进程环境。marker 缺失或 `run_id` 为空会计入 policy mismatch，并由临时表 CHECK 约束拒绝。

对限定集合内的模板，仍同时要求：

- `id` 以 `SIM-` 开头；
- `doc_number` 以 `SIM-` 开头；
- `canonical_doc_number` 以 `SIM-` 开头；
- `name` 明确以 `SIM-` 或 `SIM ` 开头。

因此：

1. 现用非 SIM 内容模板且不属于当前排演批次时，不再误报；
2. 当前排演批次中，即使模板 ID、编号或名称伪装为非 SIM，仍会拒绝；
3. `/*x*/SIM-*` 这类注释前缀不是允许的开头，仍会拒绝；
4. marker 丢失时不会因比较结果为 NULL 而放行。

## 3. 新鲜运行证据

在现有 8013 主排演栈运行快速测试，未重建数据库容器，未装载业务夹具，未读取密封目录。

| 检查 | 结果 |
|---|---|
| 现用内容模板基线 + 新 guard | PASS |
| REAL 供应商/联系人探针 | PASS：guard 拒绝，事务回滚 |
| `/*x*/SIM-*` 模板编号探针 | PASS：guard 拒绝，事务回滚 |
| 当前 `run_id` 下的非 SIM 模板探针 | PASS：CHECK 拒绝，持久化计数为 0 |
| 临时清空 marker.run_id | PASS：CHECK 拒绝，事务回滚后 marker 保持 `SIM-GOV-R2-20260719` |
| 两个回归脚本 `bash -n` | PASS |
| `git diff --check` | PASS |

关键输出：

```text
[PASS] SQL post-guard allows controlled content templates while retaining SIM operational enforcement
[PASS] SQL post-guard rejected REAL supplier/contact payload and transaction rolled back
[PASS] SQL post-guard rejected a comment-prefixed template identity and transaction rolled back
[PASS] current-run non-SIM template rejected by guard and rolled back (status=1, persisted=0)
[PASS] blank marker run_id fails closed and transaction rolled back
```

## 4. 未决项

无 P1/P2 阻断项。

建议后续把“当前 run 下非 SIM 模板拒绝”和“marker 缺失拒绝”两条独立探针固化进仓库回归脚本；当前已完成真实事务内验证，因此不阻断此次 8013 继续执行。
