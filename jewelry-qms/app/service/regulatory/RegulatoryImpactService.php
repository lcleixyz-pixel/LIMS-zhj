<?php
declare(strict_types=1);

namespace app\service\regulatory;

use Closure;
use RuntimeException;
use Throwable;
use app\service\QmsDocumentStructureService;
use think\facade\Db;

final class RegulatoryImpactService
{
    public const RULE_VERSION = 'regulatory-impact-v1';

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
            'rule_id' => 'REG-CMA-ONE-LIST-001',
            'version' => '1',
            'keywords' => ['一单一库', '资质认定事项清单', '能力项目库'],
            'document_types' => [],
            'impact_key' => 'cma_scope_mark',
            'explanation' => '“一单一库”的事项清单和能力项目库可能影响 CMA 范围及资质声明，需人工核对。',
            'conclusion' => 'likely',
            'confidence' => 0.92,
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
            'rule_id' => 'REG-QMS-ONE-LIST-001',
            'version' => '1',
            'keywords' => ['质量管理体系文件'],
            'document_types' => [],
            'impact_key' => 'qms_documents',
            'explanation' => '“一单一库”要求明确涉及质量管理体系文件，需人工定位受影响手册、程序和记录。',
            'conclusion' => 'likely',
            'confidence' => 0.93,
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
            'rule_id' => 'REG-PER-ONE-LIST-001',
            'version' => '1',
            'keywords' => ['人员授权范围'],
            'document_types' => [],
            'impact_key' => 'personnel_authorization',
            'explanation' => '“一单一库”要求明确涉及人员授权范围，需人工核对任命、授权和能力证据。',
            'conclusion' => 'likely',
            'confidence' => 0.91,
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
            'rule_id' => 'REG-LIMS-ONE-LIST-001',
            'version' => '1',
            'keywords' => ['统一数据规则', '维护检验检测能力信息'],
            'document_types' => [],
            'impact_key' => 'lims_rules',
            'explanation' => '“一单一库”的统一数据规则可能影响 LIMS 能力范围、字段校验和提示规则，需人工核对。',
            'conclusion' => 'likely',
            'confidence' => 0.92,
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

    public function __construct(?callable $contextProvider = null)
    {
        $this->contextProvider = Closure::fromCallable(
            $contextProvider ?? static fn (string $companyId): array => self::readOnlyOrganizationContext($companyId)
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
    public function analyze(array $item, string $companyId = ''): array
    {
        $input = $this->normalizedInput($item);
        [$context, $contextAvailable] = $this->organizationContext(trim($companyId));
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

        $confidence = round(
            array_sum(array_column($analysis, 'confidence')) / count(self::IMPACT_KEYS),
            4
        );
        $matchedCount = count(array_filter(
            $analysis,
            static fn (array $assessment): bool => $assessment['conclusion'] !== 'no_match'
        ));

        return [
            'impact_analysis' => $analysis,
            'analysis_rule_version' => self::RULE_VERSION,
            'analysis_confidence' => $confidence,
            'analysis_rationale' => sprintf(
                '确定性规则命中 %d/6 类；%s；所有结论均需人工复核。',
                $matchedCount,
                $contextAvailable ? '机构上下文已只读取得' : '机构上下文数据未取得，需人工确认'
            ),
        ];
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
            'evidence' => $this->normalizeText(implode(' ', $this->flattenText($item['evidence'] ?? $item['evidence_json'] ?? []))),
        ];
    }

    /** @return array<int, string> */
    private function flattenText(mixed $value): array
    {
        if (is_scalar($value)) {
            return [(string)$value];
        }
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return [];
        }
        $values = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), self::OBSERVATION_TIME_FIELDS, true)) {
                continue;
            }
            array_push($values, ...$this->flattenText($item));
        }

        return $values;
    }

    /** @return array<int, string> */
    private function matchEvidence(array $rule, array $input): array
    {
        $documentTypes = (array)$rule['document_types'];
        if ($documentTypes !== []) {
            $documentTypeMatched = false;
            foreach ($documentTypes as $documentType) {
                if ($input['document_type'] !== '' && mb_stripos($input['document_type'], (string)$documentType) !== false) {
                    $documentTypeMatched = true;
                    break;
                }
            }
            if (!$documentTypeMatched) {
                return [];
            }
        }

        $evidence = [];
        foreach ((array)$rule['keywords'] as $keyword) {
            foreach ($input as $field => $text) {
                if ($text !== '' && mb_stripos($text, (string)$keyword) !== false) {
                    $evidence[] = sprintf(
                        '规则 %s 命中关键词“%s”；输入证据[%s]：%s',
                        $rule['rule_id'],
                        $keyword,
                        $field,
                        $this->evidenceSnippet($text, (string)$keyword)
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
                'confidence' => $contextAvailable ? 0.35 : 0.2,
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
    private function organizationContext(string $companyId): array
    {
        try {
            $context = ($this->contextProvider)($companyId);
            if (!is_array($context)) {
                throw new RuntimeException('机构上下文提供者返回值不可读');
            }

            return [$context, true];
        } catch (Throwable) {
            return [[], false];
        }
    }

    private function contextEvidence(string $impactKey, array $context): string
    {
        return match ($impactKey) {
            'qms_documents' => sprintf(
                '只读机构提示：当前体系结构层共 %d 类，需人工定位受影响文件。',
                count((array)($context['qms_structure_layers'] ?? []))
            ),
            'personnel_authorization', 'training' => sprintf(
                '只读机构提示：在岗人员 %d 项、有效任命/授权 %d 项，未列出个人姓名。',
                (int)($context['active_employee_count'] ?? 0),
                (int)($context['active_personnel_authorization_count'] ?? 0)
            ),
            'equipment_calibration' => sprintf(
                '只读机构提示：在用设备 %d 台、有效设备授权 %d 项，需人工定位受影响对象。',
                (int)($context['active_equipment_count'] ?? 0),
                (int)($context['active_equipment_authorization_count'] ?? 0)
            ),
            default => '只读机构提示：已取得机构级汇总，具体影响范围仍需人工确认。',
        };
    }

    private function evidenceSnippet(string $text, string $keyword): string
    {
        $position = mb_stripos($text, $keyword);
        if ($position === false) {
            return mb_substr($text, 0, 120);
        }
        $start = max(0, $position - 30);

        return mb_substr($text, $start, 120);
    }

    private function normalizeText(string $value): string
    {
        return trim((string)preg_replace('/[\p{Z}\s]+/u', ' ', $value));
    }

    /** @return array<string, mixed> */
    private static function readOnlyOrganizationContext(string $companyId): array
    {
        $layers = array_map(
            static fn (array $layer): string => (string)$layer['key'],
            QmsDocumentStructureService::structureLayerDefinitions()
        );

        return [
            'qms_structure_layers' => $layers,
            'active_employee_count' => Db::name('employees')
                ->where('company_id', $companyId)
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->count(),
            'active_personnel_authorization_count' => Db::name('employee_appointments')
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->count(),
            'active_equipment_count' => Db::name('equipments')
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->count(),
            'active_equipment_authorization_count' => Db::name('equipment_authorizations')
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->where('publish', 1)
                ->where('soft_delete', 0)
                ->count(),
        ];
    }
}
