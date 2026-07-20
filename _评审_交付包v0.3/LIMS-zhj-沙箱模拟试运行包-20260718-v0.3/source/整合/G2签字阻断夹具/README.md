# G2 语义签字阻断 · 夹具与验证

> 对应《接口契约 v1.0 正版》§2.1 + 附录A 缺口 **G2**。
> 2026-07-09 **G2 已补建**:`QmsPreimportPackageService::checkManifestSemanticSignatures` 落地,apply 时语义签字清单不全即阻断(high)。本目录为其负向/正向夹具。

## G2 是什么

契约§2.1 约定 `preimport_manifest.json.semantic_signatures`(按主题的语义签字清单:每条 `{topic,signer,date}`)**不全时 apply 必须阻断**。但补建前平台代码全文无 "semantic" 字样——该字段从未被读,manifest 只强校验 `counts`;apply 阻断全靠其他评审包,**按主题签字阻断未实现**(甲方发包闸为唯一防线,单防线运行)。

## 补建实现

`app/service/QmsPreimportPackageService.php`:
- 新增 `checkManifestSemanticSignatures($manifest, $rows, &$findings, $applyMode)`,在 inspect 主流程 `checkManualBlockRows` 之后调用。
- **必备主题** = 包内 `traceability_matrix` 的 `manual_topic` ∪ `element` 去重值(包级,非写死全集;兼容契约§2.2 列式与构建脚本列式)。

行为矩阵:

| manifest.semantic_signatures | dry-run | apply(--apply-rehearsal) |
|---|---|---|
| 缺失(无此字段) | **跳过**(不破坏既有无此字段的夹具) | **阻断** `semantic_signatures_missing_for_apply`(high) |
| 存在但缺必备主题 | **high** `semantic_signatures_incomplete` → failed | 同(阻断) |
| 存在但条目 signer/date 空 | **high** `semantic_signature_field_missing`(+ incomplete)→ failed | 同(阻断) |
| 齐全(必备主题均有 signer+date) | **passed / 0 发现** | 签字闸**不报**(放行,其余 apply 闸照旧) |

> apply 阻断经 `hasHighFinding→status=failed/blocked`,与既有 applyMode 闸同源。
> "缺失→dry-run 跳过"是为不破坏既有 GREEN 夹具(乙方 fixture/smoke/甲方第9道包均无此字段);这些夹具若走 apply 会被签字闸挡——它们本就是 dry-run 夹具,不用于 apply。

## 夹具(基于乙方 fixture,traceability element=人员 → 必备主题={人员})

| 夹具 | semantic_signatures | 期望 |
|---|---|---|
| `negative_missing_topic/` | `[{topic:设备,...}]`(缺人员) | dry-run failed:`semantic_signatures_incomplete` |
| `negative_field_missing/` | `[{topic:人员,signer:'',date:''}]` | dry-run failed:`semantic_signature_field_missing`+`incomplete` |
| `positive_complete/` | `[{topic:人员,signer:张三,date:2026-07-09}]` | dry-run passed / 0 发现 |

## 复现

```bash
cd "C:/Users/Martyr/OneDrive/桌面/参考/__work_lims/LIMS-zhj-minimal-runnable-20260708/jewelry-qms"
G2="C:/Users/Martyr/OneDrive/桌面/参考/G2签字阻断夹具"

# dry-run(负向应 failed、正向应 passed)
for v in negative_missing_topic negative_field_missing positive_complete; do
  php think qms:preimport-package --package-dir "$G2/$v" --json-out "$G2/${v}_dryrun.json"
done

# apply 阻断(缺签字应报 semantic_signatures_missing_for_apply)
php think qms:preimport-package --apply-rehearsal \
  --package-dir "C:/Users/Martyr/OneDrive/桌面/参考/乙方夹具回归/preimport_golden/fixture" \
  --json-out "$G2/applyrehearsal_absent.json"
```

## 实测结果(2026-07-09)

| 测试 | 结果 |
|---|---|
| ② 乙方正式金样 dry-run(无签字) | 仍 ALL GREEN(缺失→跳过,零破坏)✅ |
| smoke_dbfree dry-run | 仍 ALL GREEN ✅ |
| ① 甲方第9道 dry-run | 仍 passed/findings=0 ✅ |
| negative_missing_topic dry-run | failed / `semantic_signatures_incomplete` ✅ |
| negative_field_missing dry-run | failed / `semantic_signature_field_missing`+`incomplete` ✅ |
| positive_complete dry-run | passed / 0 发现 ✅ |
| 乙方fixture apply-rehearsal(缺签字) | blocked / `semantic_signatures_missing_for_apply` ✅ |
| positive_complete apply-rehearsal(签字齐) | 签字闸不报(仅余无关的 ack/review-dir 闸)✅ |

## 遗留/可选

- 既有 dry-run 夹具(乙方 fixture/smoke/甲方第9道)均**未含** semantic_signatures;若要它们也能过 apply,需补全签字清单(改夹具=改金样,须按契约§五留变更行)。
- 甲方侧 `validate_manifest.py`(DB-free)可镜像此检查(T1 范畴);当前未扩展,保持与平台 dry-run 行为一致即可。
