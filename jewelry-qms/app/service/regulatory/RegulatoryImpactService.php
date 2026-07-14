<?php
declare(strict_types=1);

namespace app\service\regulatory;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use app\service\QmsDocumentStructureService;
use think\facade\Db;

final class RegulatoryImpactService
{
    public const RULE_VERSION = 'regulatory-impact-v2';

    private const IMPACT_KEYS = [
        'cma_scope_mark',
        'qms_documents',
        'personnel_authorization',
        'equipment_calibration',
        'lims_rules',
        'training',
    ];

    private const OBSERVATION_TIME_FIELDS = [
        'fetched_at',
        'first_seen_at',
        'last_seen_at',
        'observed_at',
        'retrieved_at',
        'collected_at',
        'generated_at',
    ];

    private const EVIDENCE_BODY_FIELDS = [
        'evidence',
        'raw_text',
        'body_summary',
        'summary',
        'body',
        'text',
        'content',
    ];

    /** @var array<int, array<string, mixed>> */
    private const RULES = [
        [
            'rule_id' => 'REG-CMA-001',
            'version' => '1',
            'keywords' => ['检验检测机构', '资质认定', 'CMA', '能力范围', '标志使用'],
            'document_types' => [],
            'impact_key' => 'cma_scope_mark',
            'explanation' => '检验检测机构监管或资质认定变化可能影响 CMA 范围、标志或声明方式，需人工核对。',
            'conclusion' => 'possible',
            'confidence' => 0.82,
        ],
        [
            'rule_id' => 'REG-CMA-ONE-LIST-DIRECT-001',
            'version' => '1',
            'keywords' => ['证书及标志（CMA）', '一单一库范围'],
            'document_types' => [],
            'impact_key' => 'cma_scope_mark',
            'explanation' => '公告原文直接要求在“一单一库”范围内规范使用资质认定证书及标志（CMA），需人工核对现有范围和标志使用。',
            'conclusion' => 'likely',
            'confidence' => 0.96,
        ],
        [
            'rule_id' => 'REG-QMS-001',
            'version' => '1',
            'keywords' => ['后续处置要求', '整改要求', '变更管理', '质量管理体系', '程序文件'],
            'document_types' => [],
            'impact_key' => 'qms_documents',
            'explanation' => '处置、整改或体系变更要求可能需要修订质量手册、程序或记录，需人工核对。',
            'conclusion' => 'possible',
            'confidence' => 0.84,
        ],
        [
            'rule_id' => 'REG-QMS-ONE-LIST-INFERENCE-001',
            'version' => '1',
            'keywords' => ['一单一库'],
            'document_types' => [],
            'impact_key' => 'qms_documents',
            'explanation' => '确定性业务规则推断，非公告原文直接要求：许可范围和动态管理变化可能需要人工复核体系文件及记录。',
            'conclusion' => 'possible',
            'confidence' => 0.78,
        ],
        [
            'rule_id' => 'REG-QMS-DOCUMENT-TYPE-001',
            'version' => '1',
            'keywords' => ['动态管理机制'],
            'document_types' => ['公示公告'],
            'impact_key' => 'qms_documents',
            'explanation' => '公示公告正文命中动态管理机制，可能需要人工复核相关体系文件和变更记录。',
            'conclusion' => 'possible',
            'confidence' => 0.72,
        ],
        [
            'rule_id' => 'REG-PER-001',
            'version' => '1',
            'keywords' => ['人员能力', '授权签字', '岗位授权', '人员授权'],
            'document_types' => [],
            'impact_key' => 'personnel_authorization',
            'explanation' => '人员能力或授权要求可能影响岗位授权、任命或能力确认，需人工核对。',
            'conclusion' => 'possible',
            'confidence' => 0.78,
        ],
        [
            'rule_id' => 'REG-PER-ONE-LIST-INFERENCE-001',
            'version' => '1',
            'keywords' => ['一单一库'],
            'document_types' => [],
            'impact_key' => 'personnel_authorization',
            'explanation' => '确定性业务规则推断，非公告原文直接要求：能力项目范围变化可能需要人工复核相关岗位任命、授权和能力证据。',
            'conclusion' => 'possible',
            'confidence' => 0.74,
        ],
        [
            'rule_id' => 'REG-EQP-001',
            'version' => '1',
            'keywords' => ['设备校准', '仪器校准', '计量溯源', '期间核查', '设备检定'],
            'document_types' => [],
            'impact_key' => 'equipment_calibration',
            'explanation' => '设备校准、检定或溯源要求可能影响设备台账和校准计划，需人工核对。',
            'conclusion' => 'possible',
            'confidence' => 0.80,
        ],
        [
            'rule_id' => 'REG-LIMS-ONE-LIST-INFERENCE-001',
            'version' => '1',
            'keywords' => ['一单一库'],
            'document_types' => [],
            'impact_key' => 'lims_rules',
            'explanation' => '确定性业务规则推断，非公告原文直接要求：能力项目库变化可能需要人工复核 LIMS 能力目录、字段校验和提示规则。',
            'conclusion' => 'possible',
            'confidence' => 0.76,
        ],
        [
            'rule_id' => 'REG-LIMS-001',
            'version' => '1',
            'keywords' => ['能力验证结果', '检验检测结果', '数据报送', '报告规则', '信息系统'],
            'document_types' => [],
            'impact_key' => 'lims_rules',
            'explanation' => '结果处置、报送或报告规则可能影响 LIMS 校验、状态或提示规则，需人工核对。',
            'conclusion' => 'possible',
            'confidence' => 0.80,
        ],
        [
            'rule_id' => 'REG-TRN-001',
            'version' => '1',
            'keywords' => ['培训', '宣贯', '学习要求', '能力提升'],
            'document_types' => [],
            'impact_key' => 'training',
            'explanation' => '培训、宣贯或学习要求可能影响培训计划和能力确认记录，需人工核对。',
            'conclusion' => 'possible',
            'confidence' => 0.76,
        ],
    ];

    private Closure $contextProvider;
    private Closure $clock;
    /** @var array<string, array{0: array<string, mixed>, 1: bool}> */
    private array $contextCache = [];

    public function __construct(?callable $contextProvider = null, ?callable $clock = null)
    {
        $this->contextProvider = Closure::fromCallable(
            $contextProvider ?? static fn (string $companyId, string $asOf): array =>
                self::readOnlyOrganizationContext($companyId, $asOf)
        );
        $this->clock = Closure::fromCallable(
            $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now')
        );
    }

    /**
     * @return array{
     *   impact_analysis: array<string, array{conclusion: string, evidence: array<int, string>, rule_ids: array<int, string>, confidence: float}>,
     *   analysis_rule_version: string,
     *   analysis_confidence: float,
     *   analysis_rationale: string
     * }
     */
    public function analyze(array $item, string $companyId = '', ?string $asOf = null): array
    {
        $input = $this->normalizedInput($item);
        $analysisDate = $this->analysisDate($asOf);
        [$context, $contextAvailable] = $this->organizationContext(trim($companyId), $analysisDate);
        $matches = array_fill_keys(self::IMPACT_KEYS, []);
        foreach ($this->rules() as $rule) {
            $evidence = $this->matchEvidence($rule, $input);
            if ($evidence !== []) {
                $matches[$rule['impact_key']][] = ['rule' => $rule, 'evidence' => $evidence];
            }
        }

        $analysis = [];
        foreach (self::IMPACT_KEYS as $impactKey) {
            $analysis[$impactKey] = $this->assessment(
                $impactKey,
                $matches[$impactKey],
                $context,
                $contextAvailable
            );
        }

        $matchedAssessments = array_values(array_filter(
            $analysis,
            static fn (array $assessment): bool => $assessment['conclusion'] !== 'no_match'
        ));
        $matchedCount = count($matchedAssessments);
        $confidence = $matchedCount === 0
            ? 0.0
            : round(array_sum(array_column($matchedAssessments, 'confidence')) / $matchedCount, 4);

        return [
            'impact_analysis' => $analysis,
            'analysis_rule_version' => self::ruleVersion(),
            'analysis_confidence' => $confidence,
            'analysis_rationale' => sprintf(
                '确定性规则命中 %d/6 类；总体置信度仅按 %d 个已命中影响聚合；%s；所有结论均需人工复核。',
                $matchedCount,
                $matchedCount,
                $contextAvailable ? '机构上下文已只读取得' : '机构上下文数据未取得，需人工确认'
            ),
        ];
    }

    public static function ruleVersion(): string
    {
        $definition = json_encode(self::RULES, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return self::RULE_VERSION . '-' . substr(hash('sha256', $definition), 0, 12);
    }

    /** @return array<int, array<string, mixed>> */
    private function rules(): array
    {
        $rules = self::RULES;
        usort($rules, static fn (array $left, array $right): int => strcmp($left['rule_id'], $right['rule_id']));
        foreach ($rules as $rule) {
            foreach (['rule_id', 'version', 'keywords', 'document_types', 'impact_key', 'explanation', 'conclusion', 'confidence'] as $field) {
                if (!array_key_exists($field, $rule)) {
                    throw new RuntimeException('法规影响规则缺少字段：' . $field);
                }
            }
            if (!in_array($rule['impact_key'], self::IMPACT_KEYS, true)) {
                throw new RuntimeException('法规影响规则使用未知影响分类');
            }
            if (!in_array($rule['conclusion'], ['likely', 'possible'], true)) {
                throw new RuntimeException('法规影响规则结论无效');
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function normalizedInput(array $item): array
    {
        return [
            'title' => $this->normalizeText((string)($item['title'] ?? '')),
            'announcement_number' => $this->normalizeText((string)($item['announcement_number'] ?? '')),
            'document_type' => $this->normalizeText((string)($item['document_type'] ?? '')),
            'summary' => $this->normalizeText((string)($item['summary'] ?? $item['evidence_summary'] ?? '')),
            'evidence' => $this->evidenceBodyText($item['evidence'] ?? $item['evidence_json'] ?? []),
        ];
    }

    private function evidenceBodyText(mixed $value): string
    {
        if (is_scalar($value)) {
            return $this->normalizeText((string)$value);
        }
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return '';
        }
        if (array_is_list($value)) {
            $body = [];
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $body[] = (string)$item;
                    continue;
                }
                $text = $this->evidenceBodyText($item);
                if ($text !== '') {
                    $body[] = $text;
                }
            }

            return $this->normalizeText(implode(' ', $body));
        }

        $body = [];
        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string)$key);
            if (in_array($normalizedKey, self::OBSERVATION_TIME_FIELDS, true)
                || !in_array($normalizedKey, self::EVIDENCE_BODY_FIELDS, true)
            ) {
                continue;
            }
            $text = $this->evidenceBodyText($item);
            if ($text !== '') {
                $body[] = $text;
            }
        }

        return $this->normalizeText(implode(' ', $body));
    }

    /** @return array<int, string> */
    private function matchEvidence(array $rule, array $input): array
    {
        $documentTypes = (array)$rule['document_types'];
        if ($documentTypes !== []) {
            $normalizedDocumentTypes = array_map(
                fn (mixed $documentType): string => $this->normalizeText((string)$documentType),
                $documentTypes
            );
            if ($input['document_type'] === '' || !in_array($input['document_type'], $normalizedDocumentTypes, true)) {
                return [];
            }
        }

        $evidence = [];
        foreach ((array)$rule['keywords'] as $keyword) {
            foreach ($input as $field => $text) {
                $position = $text !== '' ? $this->keywordPosition($text, (string)$keyword) : false;
                if ($position !== false) {
                    $evidence[] = sprintf(
                        '规则 %s 命中关键词“%s”；输入证据[%s]：%s',
                        $rule['rule_id'],
                        $keyword,
                        $field,
                        $this->evidenceSnippet($text, $position)
                    );
                }
            }
        }
        $evidence = array_values(array_unique($evidence));
        sort($evidence, SORT_STRING);

        return $evidence;
    }

    /**
     * @param array<int, array{rule: array<string, mixed>, evidence: array<int, string>}> $matches
     * @return array{conclusion: string, evidence: array<int, string>, rule_ids: array<int, string>, confidence: float}
     */
    private function assessment(
        string $impactKey,
        array $matches,
        array $context,
        bool $contextAvailable
    ): array {
        if ($matches === []) {
            return [
                'conclusion' => 'no_match',
                'evidence' => [
                    '现有确定性规则未命中，需人工复核。',
                    $contextAvailable
                        ? $this->contextEvidence($impactKey, $context)
                        : '机构上下文数据未取得，需人工确认。',
                ],
                'rule_ids' => [],
                'confidence' => 0.0,
            ];
        }

        $evidence = [];
        $ruleIds = [];
        $conclusion = 'possible';
        $confidence = 0.0;
        foreach ($matches as $match) {
            $rule = $match['rule'];
            $ruleIds[] = (string)$rule['rule_id'];
            array_push($evidence, ...$match['evidence']);
            $evidence[] = (string)$rule['explanation'];
            if ($rule['conclusion'] === 'likely') {
                $conclusion = 'likely';
            }
            $confidence = max($confidence, (float)$rule['confidence']);
        }
        $evidence[] = $contextAvailable
            ? $this->contextEvidence($impactKey, $context)
            : '机构上下文数据未取得，需人工确认。';
        $ruleIds = array_values(array_unique($ruleIds));
        sort($ruleIds, SORT_STRING);
        $evidence = array_values(array_unique($evidence));
        sort($evidence, SORT_STRING);

        return [
            'conclusion' => $conclusion,
            'evidence' => $evidence,
            'rule_ids' => $ruleIds,
            'confidence' => round($contextAvailable ? $confidence : $confidence * 0.75, 4),
        ];
    }

    /** @return array{0: array<string, mixed>, 1: bool} */
    private function organizationContext(string $companyId, string $asOf): array
    {
        $cacheKey = $companyId . "\0" . $asOf;
        if (isset($this->contextCache[$cacheKey])) {
            return $this->contextCache[$cacheKey];
        }

        $context = ($this->contextProvider)($companyId, $asOf);
        if (!is_array($context)) {
            throw new RuntimeException('机构上下文提供者返回值不可读');
        }
        if (($context['available'] ?? true) === false) {
            if (trim((string)($context['reason'] ?? '')) === '') {
                throw new RuntimeException('机构上下文不可用时必须提供内部原因');
            }

            return $this->contextCache[$cacheKey] = [[], false];
        }
        if (!is_array($context['qms_structure_layers'] ?? null)) {
            throw new RuntimeException('机构上下文结构层信息无效');
        }
        foreach ([
            'active_employee_count',
            'active_personnel_authorization_count',
            'active_equipment_count',
            'active_equipment_authorization_count',
        ] as $countField) {
            $value = $context[$countField] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new RuntimeException('机构上下文计数无效：' . $countField);
            }
        }
        $context['as_of'] = $asOf;

        return $this->contextCache[$cacheKey] = [$context, true];
    }

    private function analysisDate(?string $asOf): string
    {
        if ($asOf === null || trim($asOf) === '') {
            $now = ($this->clock)();
            if (!$now instanceof DateTimeInterface) {
                throw new RuntimeException('法规影响分析时钟必须返回日期时间对象');
            }

            return $now->format('Y-m-d');
        }
        $asOf = trim($asOf);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $asOf);
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $asOf) {
            throw new InvalidArgumentException('as_of 必须为有效的 YYYY-MM-DD 日期');
        }

        return $asOf;
    }

    private function contextEvidence(string $impactKey, array $context): string
    {
        return match ($impactKey) {
            'qms_documents' => sprintf(
                '只读机构提示：当前体系结构层共 %d 类，需人工定位受影响文件。',
                count((array)($context['qms_structure_layers'] ?? []))
            ),
            'personnel_authorization', 'training' => sprintf(
                '只读机构提示：截至 %s，在岗人员 %d 项、符合日期/状态/关联人员条件的任命或授权 %d 项，未列出个人姓名。',
                (string)($context['as_of'] ?? ''),
                (int)($context['active_employee_count'] ?? 0),
                (int)($context['active_personnel_authorization_count'] ?? 0)
            ),
            'equipment_calibration' => sprintf(
                '只读机构提示：截至 %s，在用设备 %d 台、符合日期/状态/关联人员及设备条件的设备授权 %d 项，需人工定位受影响对象。',
                (string)($context['as_of'] ?? ''),
                (int)($context['active_equipment_count'] ?? 0),
                (int)($context['active_equipment_authorization_count'] ?? 0)
            ),
            default => '只读机构提示：已取得机构级汇总，具体影响范围仍需人工确认。',
        };
    }

    private function keywordPosition(string $text, string $keyword): int|false
    {
        if (preg_match('/^[A-Za-z0-9]+$/D', $keyword) === 1) {
            $pattern = '/(?<![A-Za-z0-9_])' . preg_quote($keyword, '/') . '(?![A-Za-z0-9_])/i';
            if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) !== 1) {
                return false;
            }

            return mb_strlen(substr($text, 0, (int)$match[0][1]), 'UTF-8');
        }

        return mb_stripos($text, $keyword, 0, 'UTF-8');
    }

    private function evidenceSnippet(string $text, int $position): string
    {
        $start = max(0, $position - 30);

        return mb_substr($text, $start, 120);
    }

    private function normalizeText(string $value): string
    {
        return trim((string)preg_replace('/[\p{Z}\s]+/u', ' ', $value));
    }

    /** @return array<string, mixed> */
    public static function readOnlyOrganizationContext(string $companyId, string $asOf): array
    {
        $layers = array_map(
            static fn (array $layer): string => (string)$layer['key'],
            QmsDocumentStructureService::structureLayerDefinitions()
        );

        $appointmentRows = Db::query(
            "SELECT COUNT(*) AS total
             FROM employee_appointments appointment
             INNER JOIN employees employee
                ON employee.id = appointment.employee_id
               AND employee.company_id = appointment.company_id
             WHERE appointment.company_id = ?
               AND appointment.status = 'active'
               AND appointment.publish = 1
               AND appointment.soft_delete = 0
               AND appointment.appointed_at IS NOT NULL
               AND appointment.appointed_at <= ?
               AND (appointment.valid_until IS NULL OR appointment.valid_until >= ?)
               AND employee.publish = 1
               AND employee.soft_delete = 0",
            [$companyId, $asOf, $asOf]
        );
        $equipmentAuthorizationRows = Db::query(
            "SELECT COUNT(*) AS total
             FROM equipment_authorizations authorization
             INNER JOIN equipments equipment
                ON equipment.id = authorization.equipment_id
               AND equipment.company_id = authorization.company_id
             INNER JOIN employees employee
                ON employee.id = authorization.employee_id
               AND employee.company_id = authorization.company_id
             WHERE authorization.company_id = ?
               AND authorization.status = 'active'
               AND authorization.publish = 1
               AND authorization.soft_delete = 0
               AND authorization.authorized_date <= ?
               AND (authorization.valid_until IS NULL OR authorization.valid_until >= ?)
               AND equipment.status = 'active'
               AND equipment.publish = 1
               AND equipment.soft_delete = 0
               AND employee.publish = 1
               AND employee.soft_delete = 0",
            [$companyId, $asOf, $asOf]
        );

        return [
            'available' => true,
            'as_of' => $asOf,
            'qms_structure_layers' => $layers,
            'active_employee_count' => Db::name('employees')
                ->where('company_id', $companyId)
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->count(),
            'active_personnel_authorization_count' => (int)($appointmentRows[0]['total'] ?? 0),
            'active_equipment_count' => Db::name('equipments')
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->count(),
            'active_equipment_authorization_count' => (int)($equipmentAuthorizationRows[0]['total'] ?? 0),
        ];
    }
}
