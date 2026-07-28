<?php
declare(strict_types=1);

namespace app\service;

final class QmsTraceRelationPolicyService
{
    private const TARGETS = [
        'element_id' => ['label' => '要素', 'split_relation_type' => 'supporting'],
        'clause_id' => ['label' => '外部条款', 'split_relation_type' => 'basis'],
        'manual_section_id' => ['label' => '手册章节', 'split_relation_type' => 'implements'],
        'procedure_document_id' => ['label' => '程序文件', 'split_relation_type' => 'supporting'],
        'record_form_template_id' => ['label' => '记录表格', 'split_relation_type' => 'requires_record'],
        'position_id' => ['label' => '岗位', 'split_relation_type' => 'responsible'],
        'business_module_id' => ['label' => '运行模块', 'split_relation_type' => 'renders_to'],
    ];

    private const DEFINITIONS = [
        'basis' => [
            'label' => '主链：外部依据',
            'required_target' => 'clause_id',
            'allowed_targets' => ['clause_id', 'element_id'],
            'optional_targets' => ['element_id'],
        ],
        'implements' => [
            'label' => '主链：落实手册',
            'required_target' => 'manual_section_id',
            'allowed_targets' => ['manual_section_id'],
            'optional_targets' => [],
        ],
        'requires_record' => [
            'label' => '主链：运行记录',
            'required_target' => 'record_form_template_id',
            'allowed_targets' => ['record_form_template_id'],
            'optional_targets' => [],
        ],
        'responsible' => [
            'label' => '职责',
            'required_target' => 'position_id',
            'allowed_targets' => ['position_id'],
            'optional_targets' => [],
        ],
        'renders_to' => [
            'label' => '运行入口',
            'required_target' => 'business_module_id',
            'allowed_targets' => ['business_module_id'],
            'optional_targets' => [],
        ],
        'mentions' => [
            'label' => '仅提及',
            'required_target' => '',
            'allowed_targets' => [
                'element_id',
                'clause_id',
                'manual_section_id',
                'procedure_document_id',
                'record_form_template_id',
                'position_id',
                'business_module_id',
            ],
            'optional_targets' => [],
            'exact_target_count' => 1,
        ],
        'supporting' => [
            'label' => '辅助关系',
            'required_target' => '',
            'allowed_targets' => [
                'element_id',
                'clause_id',
                'manual_section_id',
                'procedure_document_id',
                'record_form_template_id',
                'position_id',
                'business_module_id',
            ],
            'optional_targets' => [],
            'exact_target_count' => 1,
        ],
    ];

    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    public static function validatePayload(array $payload): void
    {
        $relationType = trim((string)($payload['relation_type'] ?? ''));
        $definition = self::DEFINITIONS[$relationType] ?? null;
        if ($definition === null) {
            throw new \RuntimeException('追溯关系类型无效');
        }

        $selectedTargets = self::selectedTargets($payload);
        $requiredTarget = (string)($definition['required_target'] ?? '');
        $relationLabel = (string)$definition['label'];
        if ($requiredTarget !== '' && !in_array($requiredTarget, $selectedTargets, true)) {
            throw new \RuntimeException(
                $relationLabel . '必须选择' . (string)self::TARGETS[$requiredTarget]['label']
            );
        }

        $forbiddenTargets = array_values(array_diff(
            $selectedTargets,
            (array)$definition['allowed_targets']
        ));
        if ($forbiddenTargets !== []) {
            $allowedLabels = array_map(
                static fn(string $field): string => (string)self::TARGETS[$field]['label'],
                (array)$definition['allowed_targets']
            );
            throw new \RuntimeException(
                $relationLabel . '只能选择' . implode('和', $allowedLabels)
                . '，请清除' . self::targetLabels($forbiddenTargets) . '后再保存'
            );
        }

        $exactTargetCount = (int)($definition['exact_target_count'] ?? 0);
        if ($exactTargetCount === 1 && count($selectedTargets) !== 1) {
            throw new \RuntimeException($relationLabel . '一次只能选择一个追溯对象');
        }
    }

    public static function inspectExistingLink(array $link): array
    {
        $selectedTargets = self::selectedTargets($link);
        $splitPreview = [];
        foreach ($selectedTargets as $field) {
            $relationType = (string)self::TARGETS[$field]['split_relation_type'];
            $splitPreview[] = [
                'target_field' => $field,
                'target_kind_label' => (string)self::TARGETS[$field]['label'],
                'target_label' => self::existingTargetLabel($field, $link),
                'relation_type' => $relationType,
                'relation_label' => (string)self::DEFINITIONS[$relationType]['label'],
            ];
        }

        return [
            'is_mixed' => count($selectedTargets) > 1,
            'target_count' => count($selectedTargets),
            'selected_targets' => $selectedTargets,
            'split_preview' => $splitPreview,
        ];
    }

    private static function selectedTargets(array $payload): array
    {
        $selected = [];
        foreach (array_keys(self::TARGETS) as $field) {
            if (trim((string)($payload[$field] ?? '')) !== '') {
                $selected[] = $field;
            }
        }

        return $selected;
    }

    private static function targetLabels(array $fields): string
    {
        return implode('、', array_map(
            static fn(string $field): string => (string)self::TARGETS[$field]['label'],
            $fields
        ));
    }

    private static function existingTargetLabel(string $field, array $link): string
    {
        return match ($field) {
            'element_id' => trim((string)($link['element_name'] ?? '')) ?: '要素待确认',
            'clause_id' => trim(
                (string)($link['source_code'] ?? '')
                . ' '
                . (string)($link['clause_number'] ?? '')
                . ' '
                . (string)($link['clause_title'] ?? '')
            ) ?: '外部条款待确认',
            'manual_section_id' => trim(
                (string)($link['section_number'] ?? '')
                . ' '
                . (string)($link['manual_title'] ?? '')
            ) ?: '手册章节待确认',
            'procedure_document_id' => trim(
                (string)($link['procedure_number'] ?? '')
                . ' '
                . (string)($link['procedure_title'] ?? '')
            ) ?: '程序文件待确认',
            'record_form_template_id' => trim(
                (string)($link['record_number'] ?? '')
                . ' '
                . (string)($link['record_name'] ?? '')
            ) ?: '记录表格待确认',
            'position_id' => trim((string)($link['position_name'] ?? '')) ?: '岗位待确认',
            'business_module_id' => trim(
                (string)($link['module_code'] ?? '')
                . ' '
                . (string)($link['module_name'] ?? '')
            ) ?: '运行模块待确认',
            default => '对象待确认',
        };
    }
}
