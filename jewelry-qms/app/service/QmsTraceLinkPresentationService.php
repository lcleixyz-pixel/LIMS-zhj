<?php
declare(strict_types=1);

namespace app\service;

final class QmsTraceLinkPresentationService
{
    private const GROUPS = [
        'primary' => [
            'title' => '主链证据',
            'description' => '查看外部依据、手册落实和运行记录是否形成完整证明链。',
        ],
        'responsibility' => [
            'title' => '职责',
            'description' => '查看由哪个岗位承担本内容块的职责。',
        ],
        'execution' => [
            'title' => '运行入口',
            'description' => '查看本要求在系统哪个模块执行或留痕。',
        ],
        'supporting' => [
            'title' => '辅助关系',
            'description' => '查看辅助证明、正文提及及仍待确认的历史关系。',
        ],
    ];

    private const RELATION_LABELS = [
        'basis' => '主链：外部依据',
        'implements' => '主链：落实手册',
        'requires_record' => '主链：运行记录',
        'responsible' => '职责',
        'renders_to' => '运行入口',
        'supporting' => '辅助关系',
        'mentions' => '仅提及',
    ];

    private const STATE_LABELS = [
        'suspected_mismatch' => [
            'label' => '疑似错挂',
            'class' => 'badge-status-obsolete',
        ],
        'pending_review' => [
            'label' => '等待复核',
            'class' => 'badge-status-pending',
        ],
        'confirmed_primary' => [
            'label' => '已确认主链',
            'class' => 'badge-status-effective',
        ],
        'supporting' => [
            'label' => '辅助关系',
            'class' => 'badge-status-draft',
        ],
    ];

    public static function build(array $links): array
    {
        $priority = [];
        $grouped = [
            'primary' => [],
            'responsibility' => [],
            'execution' => [],
            'supporting' => [],
        ];

        foreach ($links as $rawLink) {
            if (!is_array($rawLink)) {
                continue;
            }
            $link = self::decorate($rawLink);
            if ((bool)($link['relation_policy']['is_mixed'] ?? false)) {
                $priority[] = $link;
                continue;
            }
            $grouped[self::groupKey(
                (string)($link['relation_type'] ?? '')
            )][] = $link;
        }

        $groups = [];
        foreach (self::GROUPS as $key => $definition) {
            if ($grouped[$key] === []) {
                continue;
            }
            $groups[] = [
                'key' => $key,
                'title' => $definition['title'],
                'description' => $definition['description'],
                'count' => count($grouped[$key]),
                'links' => $grouped[$key],
            ];
        }

        return [
            'priority' => $priority,
            'groups' => $groups,
            'total' => count($links),
        ];
    }

    private static function decorate(array $link): array
    {
        $relationType = trim((string)($link['relation_type'] ?? ''));
        $state = trim((string)($link['governance_state'] ?? ''));
        $stateDefinition = self::STATE_LABELS[$state] ?? [
            'label' => '状态待确认',
            'class' => 'badge-status-draft',
        ];

        $link['relation_label'] = self::RELATION_LABELS[$relationType]
            ?? '待确认关系';
        $link['state_label'] = $stateDefinition['label'];
        $link['state_class'] = $stateDefinition['class'];
        $link['targets'] = self::targets($link);

        return $link;
    }

    private static function groupKey(string $relationType): string
    {
        return match ($relationType) {
            'basis', 'implements', 'requires_record' => 'primary',
            'responsible' => 'responsibility',
            'renders_to' => 'execution',
            default => 'supporting',
        };
    }

    private static function targets(array $link): array
    {
        $targets = [];
        foreach ([
            [
                'id' => 'element_id',
                'label' => '要素',
                'values' => ['element_name'],
            ],
            [
                'id' => 'clause_id',
                'label' => '外部条款',
                'values' => [
                    'source_code',
                    'clause_number',
                    'clause_title',
                ],
            ],
            [
                'id' => 'manual_section_id',
                'label' => '手册章节',
                'values' => ['section_number', 'manual_title'],
            ],
            [
                'id' => 'procedure_document_id',
                'label' => '程序文件',
                'values' => ['procedure_number', 'procedure_title'],
            ],
            [
                'id' => 'record_form_template_id',
                'label' => '记录表格',
                'values' => ['record_number', 'record_name'],
            ],
            [
                'id' => 'position_id',
                'label' => '岗位',
                'values' => ['position_name'],
            ],
            [
                'id' => 'business_module_id',
                'label' => '运行模块',
                'values' => ['module_code', 'module_name'],
            ],
        ] as $definition) {
            if (trim((string)($link[$definition['id']] ?? '')) === '') {
                continue;
            }
            $parts = [];
            foreach ($definition['values'] as $field) {
                $value = trim((string)($link[$field] ?? ''));
                if ($value !== '') {
                    $parts[] = $value;
                }
            }
            $targets[] = [
                'label' => $definition['label'],
                'value' => $parts !== []
                    ? implode(' ', $parts)
                    : '对象信息待补充',
            ];
        }

        return $targets;
    }
}
