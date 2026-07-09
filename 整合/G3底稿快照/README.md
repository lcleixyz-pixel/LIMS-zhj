# G3 底稿快照接收目录 · 验证

> 对应《接口契约 v1.0 正版》§二 + 附录A 缺口 **G3(2026-07-09 已补建)**。
> 平台新增可选 `draft_snapshot/` 包内子目录,接收甲方工作版 md(含 [K-xx] 回链)。

## G3 是什么

契约§二原包格式无"底稿快照"位置:发布件禁止残留 `[K-xx]`(契约§二),但工作版(含句级出处回链 [K-xx])是举证/归档的源,无处可放。G3 补建可选 `draft_snapshot/` 子目录接收它,平台识别+清点、不写库、不强制。

## 实现

`app/service/QmsPreimportPackageService.php`:
- 新增 `inspectDraftSnapshot($packageDir)`:扫描 `<packageDir>/draft_snapshot/`,返回 `{present, files, count}`。
- 在 inspect 主流程调用,结果入报告顶层 `draft_snapshot` 字段。
- **不产生 finding**(可选;缺席 present=false,无发现)。

## 行为

| 包 | draft_snapshot 报告 | 现有夹具影响 |
|---|---|---|
| 含 `draft_snapshot/`(工作版 md,可含 [K-xx]) | `{present:true, files:[…], count:N}` | — |
| 不含该目录 | `{present:false, files:[], count:0}` | 无发现,②/smoke/① 仍 GREEN |

## 实测(2026-07-09)

- `fixture_with_snapshot/`(draft_snapshot/ 含 2 个工作版 md)→ 报告 `draft_snapshot:{present:true, files:[程序-工作版.md, 质量手册-工作版.md], count:2}`,status=passed/findings=0 ✅
- 乙方 fixture(无该目录)→ 仍 ALL GREEN ✅

## 约定

- `draft_snapshot/` 内放工作版 md(含 [K-xx] 回链),供举证/归档;发布件仍禁 [K-xx],此目录为工作版例外。
- 平台仅识别+清点,不解析回链入库(块级追溯仍由 `manual_blocks_preimport.csv` → `qms_document_blocks`/`qms_document_block_links` 承载)。
