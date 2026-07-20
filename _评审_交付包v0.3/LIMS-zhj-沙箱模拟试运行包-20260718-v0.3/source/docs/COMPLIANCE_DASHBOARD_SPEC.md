# 审核准备驾驶舱（Compliance Readiness Dashboard）开发规格 v2

> 基于 Codex 审查意见修订。主要变更：追溯模型改用现有 qms_* 表、字段/状态对齐真实 schema、
> 控制器命名适配 RBAC、状态语义细化、Phase 1 简化为固定规则服务。

## 概述

在现有 Dashboard（数量统计+待办+图表）基础上，新增独立的"审核准备驾驶舱"页面，
为管理层回答核心问题：**现在能不能迎审？差什么？谁负责补？**

设计参照：Qualio Compliance Intelligence、WiseLIMS、TLM Compliance Monitor。

### 核心设计原则

1. **只读聚合层** — Phase 1 只做"读"，不建业务数据的"写"入口
2. **固定规则优先** — 规则硬编码在 Service 中，同步写入 compliance_checks 作为可读配置记录
3. **贴合真实 schema** — 所有表名、字段名、状态值严格与 `jewelry_qms.sql` 一致
4. **区分"无数据"与"不适用"** — 状态增加 `insufficient_data`，不把缺失证据误当跳过

### 与现有页面的关系

| 页面 | 定位 | 操作 |
|------|------|------|
| `/dashboard/index` | 日常运营看板 | 保留不动 |
| `/planning/index` | 体系策划中心 | 保留不动 |
| `/compliance/index` | **审核准备驾驶舱** | **新增** |

导航菜单增加"审核准备"入口，权限：`admin`、`quality_manager`、`auditor`。

---

## 一、数据库设计

### 1.1 compliance_checks — 合规判定规则配置表（只读参考）

Phase 1 中此表仅用于记录规则定义（由种子写入），不提供后台编辑 UI。
实际判定逻辑写在 `ComplianceCheckService` 硬编码方法中。

```sql
CREATE TABLE IF NOT EXISTS `compliance_checks` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `clause_number` varchar(20) DEFAULT NULL COMMENT '17025条款号如6.4.6',
  `element_key` varchar(50) DEFAULT NULL COMMENT '关联 qms_elements.key',
  `dimension` enum('personnel','equipment','material','method','environment','document','record','management') NOT NULL,
  `check_code` varchar(100) NOT NULL COMMENT '唯一规则编码',
  `check_name` varchar(200) NOT NULL COMMENT '中文名称',
  `check_description` text COMMENT '判定逻辑自然语言描述',
  `severity` enum('critical','major','minor') NOT NULL DEFAULT 'major',
  `weight` decimal(5,2) NOT NULL DEFAULT 1.00 COMMENT '评分权重',
  `suggestion_template` text COMMENT '不满足时建议',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int NOT NULL DEFAULT 0,
  `soft_delete` tinyint(1) DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_check_code` (`company_id`, `check_code`),
  KEY `idx_dimension` (`dimension`),
  KEY `idx_element_key` (`element_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合规判定规则配置（只读参考）';
```

### 1.2 compliance_snapshots — 评估快照表

```sql
CREATE TABLE IF NOT EXISTS `compliance_snapshots` (
  `id` varchar(36) NOT NULL,
  `company_id` varchar(36) NOT NULL,
  `snapshot_time` datetime NOT NULL,
  `trigger_type` enum('scheduled','manual') NOT NULL DEFAULT 'manual',
  `total_score` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '总评分 0-100',
  `dimension_scores` json NOT NULL COMMENT '{"personnel":85.0,"equipment":72.0,...}',
  `summary` json NOT NULL COMMENT '{"total":N,"pass":N,"fail":N,"warning":N,"insufficient_data":N,"not_applicable":N}',
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_time` (`company_id`, `snapshot_time` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合规评估快照';
```

### 1.3 compliance_check_results — 逐项判定结果

```sql
CREATE TABLE IF NOT EXISTS `compliance_check_results` (
  `id` varchar(36) NOT NULL,
  `snapshot_id` varchar(36) NOT NULL,
  `check_id` varchar(36) NOT NULL,
  `check_code` varchar(100) NOT NULL COMMENT '冗余存储便于查询',
  `dimension` varchar(30) NOT NULL,
  `status` enum('pass','fail','warning','insufficient_data','not_applicable') NOT NULL,
  `score` decimal(5,4) DEFAULT NULL COMMENT '通过比例 0.0000-1.0000，not_applicable/insufficient_data 为 NULL',
  `total_checked` int NOT NULL DEFAULT 0 COMMENT '检查范围内记录总数',
  `fail_count` int NOT NULL DEFAULT 0,
  `warning_count` int NOT NULL DEFAULT 0,
  `fail_items` json DEFAULT NULL COMMENT '[{"id":"","name":"","code":"","reason":""}]',
  `checked_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_snapshot` (`snapshot_id`),
  KEY `idx_check_code` (`check_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合规判定逐项结果';
```

### 1.4 compliance_gap_actions — 缺口行动跟踪（Phase 2，本次不建表）

Phase 2 建立。Phase 1 中缺口仅展示，不做行动分派和关闭跟踪。

### 1.5 Migration 文件

`database/migrations/20260530_compliance_dashboard.sql` — 使用 `CREATE TABLE IF NOT EXISTS`，幂等。

---

## 二、状态语义定义

| 状态 | 含义 | 是否参与评分 | 典型场景 |
|------|------|-------------|----------|
| `pass` | 全部通过 | 是，得满分 | 所有设备校准在有效期内 |
| `warning` | 无不合规但有即将到期 | 是，得满分（但 UI 标黄提醒） | 设备30天内到期但未过期 |
| `fail` | 存在不合规记录 | 是，按通过比例计分 | 2台设备校准过期 |
| `insufficient_data` | 系统中无数据，无法判定（可能是审核缺口） | **否，不参与评分计算，但 UI 红色标记** | 标准物质台账为空、无运行记录 |
| `not_applicable` | 检查项不适用于本实验室 | 否，不参与评分 | 实验室无抽样业务，7.3 不适用 |

**关键区别**：`insufficient_data` 在 UI 上与 `fail` 同等醒目（红色/橙色），提示"缺少证据数据"；
`not_applicable` 灰色，不在缺口列表中出现。

---

## 三、预置判定规则清单

所有规则对齐真实 schema：

| # | check_code | dimension | severity | clause | 判定逻辑 | 涉及表和字段 |
|---|-----------|-----------|----------|--------|----------|-------------|
| 1 | `equip_calibration_valid` | equipment | critical | 6.4.6 | active + calibration_required=1 的设备，next_calibration_date >= TODAY | `equipments.next_calibration_date` |
| 2 | `equip_has_authorization` | equipment | major | 6.4.2 | 每台 active 设备至少有1条 status='active' 的 equipment_authorizations | `equipments` ← `equipment_authorizations` |
| 3 | `personnel_competency_valid` | personnel | critical | 6.2.5 | competency_records 中 result='qualified' 且 (valid_until IS NULL OR valid_until >= TODAY) | `competency_records.valid_until`, `.result` |
| 4 | `personnel_certificate_valid` | personnel | major | 6.2.2 | employee_certificates status='active' 且 (valid_until IS NULL OR valid_until >= TODAY) | `employee_certificates.valid_until`, `.status` |
| 5 | `personnel_has_training` | personnel | major | 6.2.3 | 每位 publish=1 的 employee 至少有1条 training_records | `employees` ← `training_records` |
| 6 | `refmat_not_expired` | material | critical | 6.5 | reference_materials status='active' 且 valid_until >= TODAY | `reference_materials.valid_until`, `.status` |
| 7 | `doc_review_not_overdue` | document | major | 8.3 | published 文件的 review_date 不超12个月 | `documents.review_date`, `.status` |
| 8 | `doc_has_traceability` | document | minor | 8.2 | published 文件至少有1条 qms_element_documents 关联 | `documents` ← `qms_element_documents` |
| 9 | `procedure_has_record_template` | record | major | 8.4 | level=2或3 的 published 文件至少关联1个 record_form_template（通过 qms_document_block_links.record_form_template_id） | `documents` ← `qms_document_block_links` |
| 10 | `procedure_has_record_instance` | record | major | 7.5 | 有关联模板的程序文件，模板下至少有1条 status='locked' 的 record_form_instances | `record_form_templates` ← `record_form_instances(status='locked')` |
| 11 | `capa_not_overdue` | management | major | 8.7 | 无 status<>'closed' 且 due_date < TODAY 的 CAPA | `capas.status`, `.due_date` |
| 12 | `findings_not_stale` | management | minor | 8.8 | 无 status<>'closed' 且 created < TODAY-90天 的 audit_findings | `audit_findings.status`, `.created` |
| 13 | `management_review_current` | management | major | 8.9 | 12个月内至少有1次 status='completed' 的 management_reviews | `management_reviews.status`, `.review_date` |
| 14 | `internal_audit_current` | management | major | 8.8 | 12个月内至少有1次 status='completed' 的 audit_plans | `audit_plans.status`, `.created` |
| 15 | `clause_has_element` | document | minor | 4-8 | qms_clauses 中至少80%有对应 qms_element_clause_links | `qms_clauses` ← `qms_element_clause_links` |
| 16 | `element_has_document` | document | major | 8.2 | qms_elements 中每个要素至少有1条 qms_element_documents | `qms_elements` ← `qms_element_documents` |

> Phase 1 暂无 `method`、`environment` 维度自动规则，因为现有 schema 尚未形成方法验证和环境监控的结构化业务表。驾驶舱仍保留这两个维度卡片，但显示 `—` 和"暂无检查项"，且不参与总分分母。

---

## 四、Service 层设计

### 4.1 ComplianceCheckService

文件：`app/service/ComplianceCheckService.php`

```php
<?php
declare(strict_types=1);

namespace app\service;

use app\model\ComplianceCheck;
use app\model\ComplianceCheckResult;
use app\model\ComplianceSnapshot;
use think\facade\Db;
use think\helper\Str;

class ComplianceCheckService
{
    // ======================== 公共接口 ========================

    /**
     * 执行全量评估，产出新快照。
     * 返回: ['snapshot_id', 'total_score', 'dimension_scores', 'summary', 'gaps']
     */
    public static function runFullAssessment(string $companyId, string $triggerType = 'manual', ?string $triggeredBy = null): array;

    /**
     * 获取最新快照的评分卡（不触发新评估）。
     * 无快照时返回 null。
     */
    public static function getLatestScorecard(string $companyId): ?array;

    /**
     * 获取指定维度的缺口详情（基于最新快照）。
     */
    public static function getGapsByDimension(string $companyId, string $dimension): array;

    /**
     * 获取全部缺口，按 severity 排序：critical > major > minor, insufficient_data 同级 major。
     */
    public static function getAllGaps(string $companyId): array;

    /**
     * 评分趋势（最近N次快照）。
     */
    public static function scoreTrend(string $companyId, int $count = 10): array;

    /**
     * 幂等写入预置规则到 compliance_checks。
     */
    public static function seedDefaultChecks(string $companyId): int;

    // ======================== 内部逻辑 ========================

    /**
     * 规则注册表：硬编码全部判定逻辑。
     * 返回 [check_code => callable] 映射。
     */
    private static function checkRegistry(): array
    {
        return [
            'equip_calibration_valid'     => [self::class, 'checkEquipCalibration'],
            'equip_has_authorization'     => [self::class, 'checkEquipAuthorization'],
            'personnel_competency_valid'  => [self::class, 'checkPersonnelCompetency'],
            'personnel_certificate_valid' => [self::class, 'checkPersonnelCertificate'],
            'personnel_has_training'      => [self::class, 'checkPersonnelTraining'],
            'refmat_not_expired'          => [self::class, 'checkRefMatExpiry'],
            'doc_review_not_overdue'      => [self::class, 'checkDocReview'],
            'doc_has_traceability'        => [self::class, 'checkDocTraceability'],
            'procedure_has_record_template' => [self::class, 'checkProcedureRecordTemplate'],
            'procedure_has_record_instance' => [self::class, 'checkProcedureRecordInstance'],
            'capa_not_overdue'            => [self::class, 'checkCapaOverdue'],
            'findings_not_stale'          => [self::class, 'checkFindingsStale'],
            'management_review_current'   => [self::class, 'checkManagementReview'],
            'internal_audit_current'      => [self::class, 'checkInternalAudit'],
            'clause_has_element'          => [self::class, 'checkClauseElement'],
            'element_has_document'        => [self::class, 'checkElementDocument'],
        ];
    }
}
```

### 4.2 评估执行流程

```php
public static function runFullAssessment(string $companyId, string $triggerType = 'manual', ?string $triggeredBy = null): array
{
    $checks = ComplianceCheck::where('company_id', $companyId)
        ->where('is_active', 1)->where('soft_delete', 0)
        ->order('sort_order')->select();

    $registry = self::checkRegistry();
    $snapshotId = Str::uuid();
    $now = date('Y-m-d H:i:s');
    $results = [];

    foreach ($checks as $check) {
        $handler = $registry[$check->check_code] ?? null;
        if (!$handler || !is_callable($handler)) {
            $result = self::buildResult('not_applicable', 0, 0, 0, []);
        } else {
            $result = call_user_func($handler, $companyId);
        }

        $results[] = [
            'check' => $check,
            'result' => $result,
        ];

        // 写入 compliance_check_results
        ComplianceCheckResult::create([
            'id' => Str::uuid(),
            'snapshot_id' => $snapshotId,
            'check_id' => $check->id,
            'check_code' => $check->check_code,
            'dimension' => $check->dimension,
            'status' => $result['status'],
            'score' => $result['score'],
            'total_checked' => $result['total_checked'],
            'fail_count' => $result['fail_count'],
            'warning_count' => $result['warning_count'],
            'fail_items' => $result['fail_items'] ? json_encode($result['fail_items'], JSON_UNESCAPED_UNICODE) : null,
            'checked_at' => $now,
        ]);
    }

    $dimensionScores = self::calcDimensionScores($results);
    $totalScore = self::calcTotalScore($dimensionScores);
    // calcSummary: 按 status 统计各状态数量。
    // 返回 ['total'=>N, 'pass'=>N, 'fail'=>N, 'warning'=>N, 'insufficient_data'=>N, 'not_applicable'=>N]
    $summary = self::calcSummary($results);

    ComplianceSnapshot::create([
        'id' => $snapshotId,
        'company_id' => $companyId,
        'snapshot_time' => $now,
        'trigger_type' => $triggerType,
        'total_score' => $totalScore,
        'dimension_scores' => json_encode($dimensionScores, JSON_UNESCAPED_UNICODE),
        'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
        'created_by' => $triggeredBy,
    ]);

    return [
        'snapshot_id' => $snapshotId,
        'total_score' => $totalScore,
        'dimension_scores' => $dimensionScores,
        'summary' => $summary,
    ];
}
```

### 4.3 评分算法（修正 skip 矛盾）

```php
private static function calcDimensionScores(array $results): array
{
    $dimensions = [];
    foreach ($results as $item) {
        $check = $item['check'];
        $result = $item['result'];
        $dim = $check->dimension;

        if (!isset($dimensions[$dim])) {
            $dimensions[$dim] = ['total_weight' => 0.0, 'earned' => 0.0];
        }

        // not_applicable 和 insufficient_data 不参与评分计算
        if (in_array($result['status'], ['not_applicable', 'insufficient_data'], true)) {
            continue;
        }

        $weight = (float) $check->weight;
        $dimensions[$dim]['total_weight'] += $weight;

        // score 是 0.0-1.0 的通过比例
        // pass/warning 得 1.0 * weight（warning 仅 UI 提醒，不扣分）
        // fail 得 score * weight
        $scoreRatio = ($result['status'] === 'warning') ? 1.0 : ($result['score'] ?? 0.0);
        $dimensions[$dim]['earned'] += $scoreRatio * $weight;
    }

    $scores = [];
    foreach ($dimensions as $dim => $data) {
        $scores[$dim] = $data['total_weight'] > 0
            ? round($data['earned'] / $data['total_weight'] * 100, 1)
            : null; // 该维度无有效规则
    }
    return $scores;
}

private static function calcTotalScore(array $dimensionScores): float
{
    // 过滤掉 null 值（无有效规则的维度）
    $valid = array_filter($dimensionScores, fn($v) => $v !== null);
    if (empty($valid)) return 0.0;
    return round(array_sum($valid) / count($valid), 1);
}
```

### 4.4 检查器实现示例

#### equip_calibration_valid — 设备校准有效性

```php
private static function checkEquipCalibration(string $companyId): array
{
    $query = Db::name('equipments')
        ->where('company_id', $companyId)
        ->where('soft_delete', 0)
        ->where('status', 'active')
        ->where('calibration_required', 1);

    $total = (clone $query)->count();
    if ($total === 0) {
        return self::buildResult('insufficient_data', 0, 0, 0, [],
            '系统中无需校准设备记录，请确认设备台账是否已录入');
    }

    $today = date('Y-m-d');
    $warningDate = date('Y-m-d', strtotime('+30 days'));

    // 过期设备
    $failItems = (clone $query)->where(function ($q) use ($today) {
        $q->whereNull('next_calibration_date')
          ->whereOr('next_calibration_date', '<', $today);
    })->field('id, name, equipment_number, next_calibration_date')->select()->toArray();

    $failCount = count($failItems);

    // 预警设备（未过期但30天内到期）
    $warningCount = (clone $query)
        ->where('next_calibration_date', '>=', $today)
        ->where('next_calibration_date', '<=', $warningDate)
        ->count();

    $passCount = $total - $failCount - $warningCount;
    $status = $failCount > 0 ? 'fail' : ($warningCount > 0 ? 'warning' : 'pass');

    $formattedItems = array_map(function ($item) use ($today) {
        $days = $item['next_calibration_date']
            ? (int)((strtotime($today) - strtotime($item['next_calibration_date'])) / 86400)
            : null;
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'code' => $item['equipment_number'],
            'reason' => $days !== null ? "校准过期{$days}天" : '未设置校准日期',
        ];
    }, $failItems);

    return self::buildResult($status, $total, $failCount, $warningCount, $formattedItems);
}
```

#### doc_has_traceability — 文件追溯关联（使用真实 qms_element_documents）

```php
private static function checkDocTraceability(string $companyId): array
{
    // published 文件至少有1条 qms_element_documents 关联
    $totalDocs = Db::name('documents')
        ->where('company_id', $companyId)
        ->where('soft_delete', 0)
        ->where('status', 'published')
        ->count();

    if ($totalDocs === 0) {
        return self::buildResult('insufficient_data', 0, 0, 0, [],
            '系统中无已发布文件');
    }

    // 有追溯关联的文件数
    $linkedDocs = Db::name('documents')
        ->alias('d')
        ->join('qms_element_documents ed', 'ed.document_id = d.id AND ed.soft_delete = 0')
        ->where('d.company_id', $companyId)
        ->where('d.soft_delete', 0)
        ->where('d.status', 'published')
        ->group('d.id')
        ->count();

    $failCount = $totalDocs - $linkedDocs;

    if ($failCount === 0) {
        return self::buildResult('pass', $totalDocs, 0, 0, []);
    }

    // 获取缺失追溯的文件
    $failItems = Db::name('documents')
        ->alias('d')
        ->leftJoin('qms_element_documents ed', 'ed.document_id = d.id AND ed.soft_delete = 0')
        ->where('d.company_id', $companyId)
        ->where('d.soft_delete', 0)
        ->where('d.status', 'published')
        ->whereNull('ed.id')
        ->field('d.id, d.title as name, d.doc_number as code')
        ->limit(20)
        ->select()->toArray();

    $formattedItems = array_map(fn($item) => [
        'id' => $item['id'],
        'name' => $item['name'],
        'code' => $item['code'],
        'reason' => '未建立要素追溯关联',
    ], $failItems);

    return self::buildResult('fail', $totalDocs, $failCount, 0, $formattedItems);
}
```

#### procedure_has_record_instance — 使用 qms_document_block_links + locked 状态

```php
private static function checkProcedureRecordInstance(string $companyId): array
{
    // level=2或3 的 published 程序文件，通过 qms_document_block_links 关联 record_form_template
    // 检查每个关联模板下是否有 status='locked' 的实例

    $procedures = Db::name('documents')
        ->alias('d')
        ->join('qms_structured_documents sd', 'sd.document_id = d.id AND sd.soft_delete = 0')
        ->join('qms_document_blocks b', 'b.structured_document_id = sd.id AND b.soft_delete = 0')
        ->join('qms_document_block_links bl', "bl.block_id = b.id AND bl.soft_delete = 0 AND bl.record_form_template_id IS NOT NULL")
        ->where('d.company_id', $companyId)
        ->where('d.soft_delete', 0)
        ->where('d.status', 'published')
        ->whereIn('d.level', [2, 3])
        ->group('bl.record_form_template_id')
        ->column('bl.record_form_template_id');

    if (empty($procedures)) {
        return self::buildResult('insufficient_data', 0, 0, 0, [],
            '无程序文件关联记录表格模板，请先建立文件结构化追溯');
    }

    $total = count($procedures);
    $hasInstance = 0;
    $failItems = [];

    foreach ($procedures as $templateId) {
        $instanceCount = Db::name('record_form_instances')
            ->where('template_id', $templateId)
            ->where('status', 'locked')  // locked = 已完成锁定的运行记录
            ->count();

        if ($instanceCount > 0) {
            $hasInstance++;
        } else {
            $tpl = Db::name('record_form_templates')
                ->where('id', $templateId)
                ->field('id, name, doc_number')
                ->find();
            if ($tpl) {
                $failItems[] = [
                    'id' => $tpl['id'],
                    'name' => $tpl['name'],
                    'code' => $tpl['doc_number'],
                    'reason' => '无已锁定的运行记录实例',
                ];
            }
        }
    }

    $failCount = $total - $hasInstance;
    $status = $failCount > 0 ? 'fail' : 'pass';

    return self::buildResult($status, $total, $failCount, 0, $failItems);
}
```

#### 辅助方法

```php
private static function buildResult(
    string $status,
    int $totalChecked,
    int $failCount,
    int $warningCount,
    array $failItems,
    ?string $insufficientReason = null
): array {
    $score = null;
    if (!in_array($status, ['not_applicable', 'insufficient_data'], true)) {
        $score = $totalChecked > 0 ? round(($totalChecked - $failCount) / $totalChecked, 4) : 1.0;
    }

    if ($status === 'insufficient_data' && $insufficientReason) {
        $failItems = [['id' => '', 'name' => '', 'code' => '', 'reason' => $insufficientReason]];
    }

    return [
        'status' => $status,
        'score' => $score,
        'total_checked' => $totalChecked,
        'fail_count' => $failCount,
        'warning_count' => $warningCount,
        'fail_items' => $failItems,
    ];
}
```

---

## 五、Controller 设计

### 5.1 控制器命名 — 适配 RBAC

RBAC 用 `strtolower($request->controller())` 判断权限。
控制器命名为 `Compliance`（不是 `ComplianceDashboard`），使权限名为 `compliance`。

文件：`app/controller/Compliance.php`

```php
<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\ComplianceCheckService;
use think\facade\Config;
use think\facade\Session;
use think\facade\View;

class Compliance extends BaseController
{
    /**
     * 驾驶舱主页
     * GET /compliance/index
     */
    public function index()
    {
        $companyId = Config::get('qms.company_id');
        $scorecard = ComplianceCheckService::getLatestScorecard($companyId);
        $gaps = ComplianceCheckService::getAllGaps($companyId);
        $trend = ComplianceCheckService::scoreTrend($companyId, 10);

        View::assign('scorecard', $scorecard);
        View::assign('gaps', $gaps);
        View::assign('trendJson', json_encode($trend, JSON_UNESCAPED_UNICODE));
        View::assign('dimensions', self::dimensionLabels());
        View::assign('hasSnapshot', $scorecard !== null);

        return View::fetch('compliance/index');
    }

    /**
     * 手动触发重新评估
     * POST /compliance/refresh
     */
    public function refresh()
    {
        $companyId = Config::get('qms.company_id');
        $userId = Session::get('user.id');

        $result = ComplianceCheckService::runFullAssessment($companyId, 'manual', $userId);

        Session::flash('success', sprintf(
            '评估完成：总评分 %.1f，%d项通过，%d项不合规，%d项数据不足。',
            $result['total_score'],
            $result['summary']['pass'] ?? 0,
            $result['summary']['fail'] ?? 0,
            $result['summary']['insufficient_data'] ?? 0
        ));

        return redirect('/compliance/index');
    }

    /**
     * 维度详情页
     * GET /compliance/dimension?dim=equipment
     */
    public function dimension()
    {
        $companyId = Config::get('qms.company_id');
        $dimension = $this->request->get('dim', 'equipment');
        $allowed = array_keys(self::dimensionLabels());
        if (!in_array($dimension, $allowed, true)) {
            $dimension = 'equipment';
        }

        $gaps = ComplianceCheckService::getGapsByDimension($companyId, $dimension);

        View::assign('dimension', $dimension);
        View::assign('dimensionLabel', self::dimensionLabels()[$dimension]);
        View::assign('gaps', $gaps);

        return View::fetch('compliance/dimension');
    }

    /**
     * 种子预置规则
     * POST /compliance/seed
     */
    public function seed()
    {
        $companyId = Config::get('qms.company_id');
        $count = ComplianceCheckService::seedDefaultChecks($companyId);
        Session::flash('success', "已初始化 {$count} 条判定规则。");
        return redirect('/compliance/index');
    }

    private static function dimensionLabels(): array
    {
        return [
            'personnel'   => '人员 (6.2)',
            'equipment'   => '设备 (6.4)',
            'material'    => '标准物质 (6.5)',
            'method'      => '方法 (7.2)',
            'environment' => '环境 (6.3)',
            'document'    => '文件 (8.2-8.3)',
            'record'      => '记录 (7.5/8.4)',
            'management'  => '管理体系 (8.5-8.9)',
        ];
    }
}
```

### 5.2 路由注册

`route/app.php` 认证路由组内追加：

```php
Route::get('compliance/index', 'Compliance/index');
Route::post('compliance/refresh', 'Compliance/refresh');
Route::get('compliance/dimension', 'Compliance/dimension');
Route::post('compliance/seed', 'Compliance/seed');
```

### 5.3 权限配置

`config/qms.php` → `permissions`：

```php
'quality_manager' => [
    // ...现有权限
    'compliance',
],
'auditor' => [
    // ...现有权限
    'compliance',
],
```

RBAC 匹配逻辑：`strtolower('Compliance')` = `'compliance'` ✓

Rbac 中间件 `$writeActions` 追加 `'refresh'`, `'seed'`，确保只有 quality_manager+ 可执行。

---

## 六、视图设计

### 6.1 compliance/index.html — 驾驶舱主页

#### 信息层次

| 区域 | 目标用户 | 核心问题 |
|------|----------|----------|
| 总评分 + 摘要 | 管理层/技术负责人 | 一眼看到"行不行" |
| 维度评分卡 | 质量负责人 | 哪个维度最弱 |
| 高风险缺口表 | 执行人员 | 今天要处理什么 |
| 评分趋势图 | 管理评审输入 | 体系在改善还是退化 |

#### 页面结构

```html
<!-- 顶部：标题 + 操作 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>审核准备驾驶舱</h4>
    <div>
        {if $hasSnapshot}
        <span class="text-muted me-3">最后评估: {$scorecard.snapshot_time}</span>
        {/if}
        <form method="post" action="/compliance/refresh" class="d-inline">
            {:token()}
            <button class="btn btn-primary btn-sm">重新评估</button>
        </form>
    </div>
</div>

{if !$hasSnapshot}
<!-- 首次使用引导 -->
<div class="alert alert-info">
    尚未执行合规评估。请先确保基础数据已录入，然后点击"重新评估"。
    <form method="post" action="/compliance/seed" class="d-inline ms-3">
        {:token()}
        <button class="btn btn-sm btn-outline-primary">初始化判定规则</button>
    </form>
</div>
{else}

<!-- 总评分区 -->
<!-- 维度卡片区（8个卡片） -->
<!-- 高风险缺口表 -->
<!-- 评分趋势图（ECharts） -->

{/if}
```

#### UI 设计要点

**总评分**：大字号数字 + 颜色指示（≥90 绿 / 70-89 黄 / <70 红），旁边展示统计摘要（pass/fail/warning/insufficient_data 各多少项）。

**维度卡片**：Bootstrap col-md-3 card 组合，每张卡片显示：
- 维度中文名 + 条款号
- 评分（百分制或 null 显示"—"）
- 状态指示色
- 点击跳转 `/compliance/dimension?dim=xxx`

**缺口表**：
- 表格形式，列：严重度徽章 | 条款 | 检查项名称 | 维度 | 不合规数 | 首条原因 | 操作
- 操作列：链接到对应模块列表页（如设备台账、人员证书等）
- `insufficient_data` 行用独立样式标注"数据不足"
- 默认只展示 fail + insufficient_data，warning 折叠展示

**趋势图**：ECharts 折线图，复用现有 CDN 引入方式。

### 6.2 compliance/dimension.html — 维度详情

展示该维度全部检查项（含 pass），便于审计时逐项核查。

每条规则展示：状态图标 | 检查项名称 | 得分 | 不合规详情（可展开）| 建议。

---

## 七、Console Command

### 7.1 app/command/ComplianceAssess.php

```php
// 命令: php think compliance:assess
// 参数: 无
// 功能: 对默认 company_id 执行全量评估
// 输出格式:
//   合规评估完成
//   总评分: 78.5/100
//   维度评分: 人员 85.0 | 设备 65.0 | 标准物质 100.0 | ...
//   缺口: 5项不合规, 2项数据不足
//   详情请访问 /compliance/index
```

注册到 `config/console.php` commands 数组。

Cron：`0 2 * * * cd /path/to/jewelry-qms && php think compliance:assess >> runtime/log/compliance.log 2>&1`

---

## 八、与现有追溯模型的关系

### 真实追溯链路（非虚构的 planning_traceability）

```
qms_clauses (17025条款)
    ↓ qms_element_clause_links
qms_elements (体系要素)
    ↓ qms_element_documents
documents (体系文件)
    ↓ qms_structured_documents → qms_document_blocks → qms_document_block_links
        ↓ record_form_template_id
record_form_templates (记录模板)
    ↓ template_id
record_form_instances (运行记录)
```

驾驶舱利用这条链路判定：
- `clause_has_element`：条款 → 要素是否建立
- `element_has_document`：要素 → 文件是否关联
- `doc_has_traceability`：文件 → 要素是否反向关联
- `procedure_has_record_template`：文件块 → 记录模板是否存在
- `procedure_has_record_instance`：记录模板 → 运行实例是否存在

---

## 九、安全约束

1. **表名/字段名白名单**：虽然 Phase 1 不使用 JSON config 动态查询，但 Phase 2 如启用动态规则，必须限制可查询表为白名单：

```php
private static array $allowedTables = [
    'equipments', 'equipment_authorizations', 'calibrations',
    'employees', 'competency_records', 'employee_certificates', 'training_records',
    'reference_materials', 'documents', 'record_form_templates', 'record_form_instances',
    'capas', 'audit_findings', 'audit_plans', 'management_reviews',
    'qms_elements', 'qms_clauses', 'qms_element_clause_links', 'qms_element_documents',
    'qms_structured_documents', 'qms_document_blocks', 'qms_document_block_links',
];
```

2. **强制范围约束**：所有查询必须限制在当前 `company_id` 范围内；有 `soft_delete` 字段的表必须附加 `soft_delete=0`，没有 `company_id` 或 `soft_delete` 字段的子表必须通过父表关联回公司范围。例如 `record_form_instances` 本身无 `company_id`/`soft_delete` 字段，需通过 `template_id` 关联回有 `company_id` 的 `record_form_templates` 后，再用 `status='locked'` 判定运行证据。
3. **只读保证**：Service 中只有 SELECT 和 INSERT（写快照/结果），不对业务表做 UPDATE/DELETE。

---

## 十、实施步骤与验收标准

### Phase 1: 数据模型 + Service + Command（可独立 PR）

**产出物**：
1. `database/migrations/20260530_compliance_dashboard.sql`（3 张表）
2. `app/Model/ComplianceCheck.php`
3. `app/Model/ComplianceSnapshot.php`
4. `app/Model/ComplianceCheckResult.php`
5. `app/service/ComplianceCheckService.php`（全部 16 个检查器 + 评估框架）
6. `app/command/ComplianceAssess.php`
7. `config/console.php` 追加

**验收标准**：
- [ ] `php think compliance:assess` 成功执行，输出评分
- [ ] 连续执行 3 次产出 3 个不同 snapshot（快照式，不覆盖）
- [ ] 无设备数据时，equip_calibration_valid 返回 `insufficient_data` 而非 `pass`/`skip`
- [ ] 有 1 台设备校准过期时，得分正确计算为 (total-1)/total
- [ ] insufficient_data 不影响维度评分计算（分母不包含它）
- [ ] warning 状态（30天内到期）不扣分但标记
- [ ] Migration 重复执行不报错
- [ ] seedDefaultChecks 幂等（重复调用不增加行）

### Phase 2: Controller + View + 集成（可独立 PR）

**产出物**：
1. `app/controller/Compliance.php`
2. `app/view/compliance/index.html`
3. `app/view/compliance/dimension.html`
4. `route/app.php` 追加
5. `config/qms.php` 权限追加
6. 导航菜单追加

**验收标准**：
- [ ] 访问 `/compliance/index` 正常（无快照时显示引导）
- [ ] 点击"初始化判定规则"写入 16 条规则
- [ ] 点击"重新评估"产出评分并刷新展示
- [ ] 维度卡片点击进入维度详情
- [ ] 缺口列表按 severity 排序正确
- [ ] insufficient_data 条目有明显"数据不足"标记
- [ ] staff 角色访问 `/compliance/index` 被 RBAC 拦截
- [ ] auditor 角色可访问并可执行 refresh/seed；refresh/seed 只写评估快照和规则参考记录，不修改正式业务数据
- [ ] ECharts 趋势图正确渲染

---

## 十一、路线图定位

- **启动时机**：Phase 1B（通知去重）完成后可启动，与 1A/1C/1E 无阻塞依赖
- **并行关系**：可与 1A（服务端校验）、1C（多场所）、1E（字段审计）并行开发
- **1D 影响**：CSRF（1D）不阻塞驾驶舱只读页面；POST refresh/seed 已在 FormTokenCheck 中间件组内，1D 完成前可用现有 token 机制
- **Phase 2 衔接**：驾驶舱上线后，Phase 2 业务深化的优先级可依据驾驶舱暴露的最低分维度决定

---

## 十二、后续扩展预留

### AI Agent（时机未定，不在 Phase 1/2 范围内）

- Service 层预留 `ComplianceAdvisorInterface` 定义（不实现）
- 视图中 **不展示** AI 占位区（Codex 建议：等有真实建议再出现）
- 数据结构上，`fail_items` 的 JSON 格式和 `suggestion_template` 字段为 AI 生成建议提供数据基础

### 缺口行动闭环（Phase 2+）

- 驾驶舱缺口条目增加"创建行动"按钮
- 写入 `compliance_gap_actions`
- 行动完成后重新评估验证

### 管理评审自动输入（Phase 2+）

- ManagementReview 新建时可拉取最近评分快照
- 各维度趋势自动灌入 inputs

---

## 十三、文件清单

| 文件路径 | 操作 | 说明 |
|----------|------|------|
| `database/migrations/20260530_compliance_dashboard.sql` | 新增 | 3 张表 DDL |
| `app/Model/ComplianceCheck.php` | 新增 | |
| `app/Model/ComplianceSnapshot.php` | 新增 | |
| `app/Model/ComplianceCheckResult.php` | 新增 | |
| `app/service/ComplianceCheckService.php` | 新增 | 核心 |
| `app/controller/Compliance.php` | 新增 | |
| `app/command/ComplianceAssess.php` | 新增 | |
| `app/view/compliance/index.html` | 新增 | |
| `app/view/compliance/dimension.html` | 新增 | |
| `config/console.php` | 追加 | 注册命令 |
| `config/qms.php` | 追加 | 权限 + 状态标签 |
| `route/app.php` | 追加 | 4 条路由 |
| 导航模板 | 追加 | 菜单入口 |
