<?php
declare(strict_types=1);

namespace app\service;

class AiContextToolService
{
    public static function buildReadPack(string $companyId, string $question, array $pageContext): array
    {
        $page = (array)($pageContext['page'] ?? []);
        $recordSummary = (array)($pageContext['record_summary'] ?? []);
        $question = trim($question);
        $docNumbers = self::targetDocNumbers($question, $recordSummary);
        $year = CopilotReadService::yearFromText($question) ?? self::yearFromSummary($recordSummary);
        $currentInstance = null;
        if ((string)($page['controller'] ?? '') === 'recordforminstance') {
            $currentInstance = CopilotReadService::currentRecordInstance(
                $companyId,
                (string)($page['record_id'] ?? '')
            );
            if ($currentInstance !== null) {
                $docNumbers[] = (string)$currentInstance['doc_number'];
                if ($year === null && isset($currentInstance['year'])) {
                    $year = (int)$currentInstance['year'];
                }
            }
        }

        $docNumbers = self::uniqueStrings(array_merge($docNumbers, self::trainingDocNumbersIfRelevant($question, $recordSummary)));
        $pack = [
            'readonly' => true,
            'summary' => '只读证据包：以下资料由后端受控读取工具生成，仅供 Copilot 回答、解释和起草建议；不会自动保存、发布、删除或改写任何 QMS 数据。',
            'question' => $question,
            'page' => $page,
            'sources' => [],
            'data' => [],
            'warnings' => [],
        ];

        self::putSource($pack, 'page.context', '当前页面上下文', $pageContext);
        if ($currentInstance !== null) {
            self::putSource($pack, 'record_form_instance.current', '当前记录实例', $currentInstance);
        }

        $templates = CopilotReadService::recordTemplates($companyId, $docNumbers, 8);
        if ($templates !== []) {
            self::putSource($pack, 'record_form_template.related', '相关记录模板', $templates);
        }

        if ($year !== null && $docNumbers !== []) {
            $instances = CopilotReadService::recordInstancesByYearDoc($companyId, $year, $docNumbers, 10);
            if ($instances !== []) {
                self::putSource($pack, 'record_form_instance.related_year', $year . ' 年相关记录实例', $instances);
            }
        }

        $employees = CopilotReadService::employees($companyId, 8);
        if ($employees !== []) {
            self::putSource($pack, 'employee.list', '人员台账摘要', $employees);
        }

        $equipment = CopilotReadService::equipment($companyId, 8);
        if ($equipment !== []) {
            self::putSource($pack, 'equipment.list', '设备台账摘要', $equipment);
        }

        $structuredKeyword = self::structuredKeyword($question, $docNumbers);
        $structured = CopilotReadService::structuredDocuments($companyId, $structuredKeyword, 6);
        if ($structured !== []) {
            self::putSource($pack, 'structured_document.search', '结构化文件摘要', $structured);
        }

        $requirements = CopilotReadService::procedureRecordRequirements($companyId, [], 6);
        if ($requirements !== []) {
            self::putSource($pack, 'procedure.record_requirements', '程序文件记录要求摘录', $requirements);
        }

        $profile = CopilotReadService::applicationProfile();
        if ($profile !== []) {
            self::putSource($pack, 'application_profile.summary', '申请书抽取信息摘要', $profile);
        }

        if ($pack['sources'] === []) {
            $pack['warnings'][] = '未命中可用只读来源，回答应只基于当前页面摘要和用户输入。';
        }

        return $pack;
    }

    public static function sourceSummary(array $readPack): array
    {
        return array_map(static fn (array $source): array => [
            'key' => (string)($source['key'] ?? ''),
            'label' => (string)($source['label'] ?? ''),
            'count' => is_array($source['data'] ?? null) ? count($source['data']) : 1,
        ], (array)($readPack['sources'] ?? []));
    }

    private static function putSource(array &$pack, string $key, string $label, mixed $data): void
    {
        $pack['sources'][] = [
            'key' => $key,
            'label' => $label,
            'data' => $data,
        ];
        $pack['data'][$key] = $data;
    }

    private static function targetDocNumbers(string $question, array $recordSummary): array
    {
        $docNumbers = CopilotReadService::docNumbersFromText($question);
        $current = (array)($recordSummary['current_instance'] ?? []);
        if (($current['doc_number'] ?? '') !== '') {
            $docNumbers[] = (string)$current['doc_number'];
        }

        return self::uniqueStrings($docNumbers);
    }

    private static function trainingDocNumbersIfRelevant(string $question, array $recordSummary): array
    {
        $haystack = $question . ' ' . json_encode($recordSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (str_contains($haystack, '培训') || str_contains($haystack, 'BG-01-01') || str_contains($haystack, 'BG-01-02')) {
            return ['XZTC/BG-01-01', 'XZTC/BG-01-02'];
        }

        return [];
    }

    private static function yearFromSummary(array $recordSummary): ?int
    {
        $current = (array)($recordSummary['current_instance'] ?? []);
        if (isset($current['year']) && (int)$current['year'] > 0) {
            return (int)$current['year'];
        }

        $json = json_encode($recordSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

        return CopilotReadService::yearFromText($json);
    }

    private static function structuredKeyword(string $question, array $docNumbers): string
    {
        if ($docNumbers !== []) {
            return (string)$docNumbers[0];
        }
        foreach (['培训', '设备', '记录', '文件', '程序', '申请书'] as $keyword) {
            if (str_contains($question, $keyword)) {
                return $keyword;
            }
        }

        return '';
    }

    private static function uniqueStrings(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $result[$value] = $value;
            }
        }

        return array_values($result);
    }
}
