# 批 B/C/D 验证证据 v0.1

- 日期：2026-07-21
- 工作树：`.worktrees/wave1-defect-governance`（`feature/wave1-defect-governance`）
- 隔离栈：`lims-zhj-wave1-smoke-20260720`（8020/3318）
- 8010：全程只读复核

## 批 B · S-5

- `app/middleware/Rbac.php`：删除 `$writeActions` 黑名单；`POST/PUT/DELETE/PATCH` 默认 `canWrite`
- 保留三条例外：`planningresponsibility/approve`、`approval/approve`、`document` 的 `confirmreceipt`/`confirmrecall`
- 只读 POST 白名单：`index/view/exportcsv/download*/print*/alignment`
- smoke：`tests/qms_wave1_s5_rbac_whitelist_smoke.php` **PASS**（含 5 角色 Document 写权限矩阵）

## 批 C · D-3 / D-5

- `config/qms.php`：`docuseal.signing_enabled` 默认 **off**（`DOCUSEAL_SIGNING_ENABLED`）
- D-3：`Document::submitReview` 开关开启时调 `DocuSealService::startSigningForDocument`；失败不阻断提审，写 `document_signing_rounds`
- D-5：`DocuSealWebhook` completed → `storeSignedAsset` → 邮箱反查用户 → `ApprovalService::processApproval(..., $actingUserId)` → `finalizeDocumentIfFullyApproved`（复用 trial_ready/published + obsolete，不直写 status）
- smoke：`tests/qms_wave1_d35_signing_loop_smoke.php` **PASS**；G-3 smoke 不破

## 批 D · 关门禁

| 检查 | 结果 |
|------|------|
| 75 可重复 A1 复跑 | **PASS=75 / FAIL=0**（`75-汇总-A1-20260721c.md`） |
| S-5 / D35 / G-3 专项 | 全部 PASS |
| 8010 CHECKSUM vs `8010-checksums-phase0.txt` | **MATCH**（`8010-checksums-final.txt`） |

### 为过 75 做的环境/断言配套（非 8010）

1. 旧 smoke 对 `$writeActions` 字面量的断言改为 `requiresWritePermission`
2. compose：挂 `../../../参考:/参考`；`knowledge` 可写
3. 重建 `参考/2025年最新版CMA和CNAS质量体系/`（自 archive 硬链）+ GBT `.txt` sidecar（markdown）
4. 扫描 PDF 空抽文本不再冒充正文（`extractPdfText`）；目录标题清 `……页码`
5. wave1 DB：自 8010 **只读** mysqldump 灌入 sources/clauses/elements；插 1 条 equipment

## 待用户拍板（未做）

- 合入 `main`
- 打标签 `v2.2.0`
- 现用镜像重建 / 8010 部署
- 变更台账正式登记（需切执行会话按项目纪律）

## 决策回写

- S-5：POST 默认拒绝 + 只读白名单（已实现）
- D-5：复用 `processApproval`（已实现）
- signing 默认 off（已实现）
- 唯一索引仍用批 A 的 active-only 生成列（未改）
