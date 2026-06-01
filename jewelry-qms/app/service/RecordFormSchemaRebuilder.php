<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;

class RecordFormSchemaRebuilder
{
    private const STRUCTURED_DIR = 'runtime/qms_structured/record_form';
    private const REGISTRY_FILE = 'database/schemas/record_form_schemas.json';

    public static function registryPath(): string
    {
        return self::appRoot() . self::REGISTRY_FILE;
    }

    public static function structuredDir(): string
    {
        return self::appRoot() . self::STRUCTURED_DIR;
    }

    /**
     * Load existing registry or empty array.
     * @return array<string, array> keyed by "doc_number::source_file_stem"
     */
    public static function loadRegistry(): array
    {
        $path = self::registryPath();
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    public static function saveRegistry(array $registry): void
    {
        $dir = dirname(self::registryPath());
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(
            self::registryPath(),
            json_encode($registry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
        );
    }

    /**
     * Parse a structured .md file and extract the "源文件Markdown摘录" section.
     */
    public static function extractSourceMarkdown(string $mdContent): string
    {
        $marker = '### 源文件Markdown摘录';
        $pos = strpos($mdContent, $marker);
        if ($pos === false) {
            return '';
        }
        $after = substr($mdContent, $pos + strlen($marker));
        $nextSection = strpos($after, "\n### ");
        if ($nextSection !== false) {
            $after = substr($after, 0, $nextSection);
        }
        return trim($after);
    }

    /**
     * Parse doc_number from the first heading line of a structured .md file.
     * Expected format: "# XZTC/BG-09-01 合同评审记录表"
     */
    public static function parseDocNumber(string $mdContent): ?string
    {
        if (preg_match('/^#\s+(\S+)\s+/m', $mdContent, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Parse template name from the first heading line.
     */
    public static function parseTemplateName(string $mdContent): ?string
    {
        if (preg_match('/^#\s+\S+\s+(.+)$/m', $mdContent, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Parse the existing heuristic schema section from a structured .md file.
     */
    public static function parseExistingSchema(string $mdContent): array
    {
        $marker = '### 字段schema';
        $pos = strpos($mdContent, $marker);
        if ($pos === false) {
            return [];
        }
        $section = substr($mdContent, $pos + strlen($marker));
        $nextSection = strpos($section, "\n### ");
        if ($nextSection !== false) {
            $section = substr($section, 0, $nextSection);
        }

        $fields = [];
        foreach (explode("\n", $section) as $line) {
            $line = trim($line);
            if (preg_match('/^-\s+`(\w+)`\s+(.+?)（(\w+)，(必填|选填)）/', $line, $m)) {
                $fields[] = [
                    'key' => $m[1],
                    'label' => $m[2],
                    'type' => $m[3],
                    'required' => $m[4] === '必填',
                ];
            }
        }
        return $fields;
    }

    /**
     * Extract formal record numbers visible inside the source table excerpt.
     * @return string[]
     */
    public static function extractSourceRecordNumbers(string $sourceMarkdown): array
    {
        $numbers = [];
        foreach ([
            '/记录编号\s*[：:]\s*(XZTC\/BG-\d{2}-\d{2})/u',
            '/表格编号\s*[：:]\s*(XZTC\/BG-\d{2}-\d{2})/u',
        ] as $pattern) {
            if (preg_match_all($pattern, $sourceMarkdown, $matches)) {
                $numbers = array_merge($numbers, $matches[1]);
            }
        }

        return array_values(array_unique($numbers));
    }

    /**
     * Resolve provisional structured-file numbers against the formal source record identity.
     *
     * @return array{doc_number: string, original_doc_number: string, source_doc_numbers: string[], renumbered?: bool, conflict?: bool, reason?: string}
     */
    public static function analyzeDocNumberIdentity(string $docNumber, string $sourceMarkdown): array
    {
        $sourceDocNumbers = self::extractSourceRecordNumbers($sourceMarkdown);
        $identity = [
            'doc_number' => $docNumber,
            'original_doc_number' => $docNumber,
            'source_doc_numbers' => $sourceDocNumbers,
        ];

        if ($sourceDocNumbers === [] || in_array($docNumber, $sourceDocNumbers, true)) {
            return $identity;
        }

        if (count($sourceDocNumbers) === 1 && self::isProvisionalDocNumber($docNumber)) {
            $identity['doc_number'] = $sourceDocNumbers[0];
            $identity['renumbered'] = true;
            $identity['reason'] = '源摘录记录编号 ' . $sourceDocNumbers[0]
                . ' 归并临时编号 ' . $docNumber;
            return $identity;
        }

        $identity['conflict'] = true;
        $identity['reason'] = '源摘录记录编号 ' . implode('、', $sourceDocNumbers)
            . ' 与解析编号 ' . $docNumber . ' 不一致';
        return $identity;
    }

    /**
     * Parse the associated procedure module from the .md file.
     */
    public static function parseModule(string $mdContent): ?string
    {
        if (preg_match('/关联程序：(.+)$/m', $mdContent, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Call DeepSeek to generate field_schema from source markdown content.
     *
     * @return array field_schema array compatible with RecordFormSchemaService
     */
    public static function generateSchema(
        string $sourceMarkdown,
        string $docNumber,
        string $templateName,
        ?string $module = null,
        array $reconstructionPacket = []
    ): array {
        $config = Config::get('qms.ai', []);
        $apiKey = (string)($config['api_key'] ?? '');
        if ($apiKey === '') {
            throw new \RuntimeException('DeepSeek API 未配置');
        }

        $baseUrl = rtrim((string)($config['base_url'] ?? 'https://api.deepseek.com'), '/');
        $model = (string)($config['model'] ?? 'deepseek-chat');
        $maxTokens = (int)($config['max_tokens'] ?? 4096);

        $systemPrompt = self::buildSystemPrompt();
        $userPrompt = self::buildUserPrompt($sourceMarkdown, $docNumber, $templateName, $module, $reconstructionPacket);

        $payload = json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.05,
            'max_tokens' => $maxTokens,
            'response_format' => ['type' => 'json_object'],
        ], JSON_UNESCAPED_UNICODE);

        $lastInvalidContent = '';
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = self::httpPost($baseUrl . '/chat/completions', (string)$payload, [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ]);

            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('DeepSeek 返回无效 JSON');
            }
            if (isset($decoded['error']['message'])) {
                throw new \RuntimeException('DeepSeek API 错误：' . (string)$decoded['error']['message']);
            }

            $content = (string)($decoded['choices'][0]['message']['content'] ?? '');
            $json = self::decodeAiJson($content);
            if (is_array($json)) {
                return self::normalizeAiSchema($json);
            }

            $lastInvalidContent = $content;
        }

        throw new \RuntimeException('AI 未返回有效 JSON，返回长度：' . strlen($lastInvalidContent));
    }

    /**
     * Process a single .md file: extract source, call AI, return schema.
     *
     * @return array{doc_number: string, name: string, module: ?string, old_schema: array, new_schema: array}
     */
    public static function processFile(string $mdPath): array
    {
        $content = (string)file_get_contents($mdPath);
        $docNumber = self::parseDocNumber($content);
        $name = self::parseTemplateName($content);
        $module = self::parseModule($content);
        $sourceMarkdown = self::extractSourceMarkdown($content);
        $oldSchema = self::parseExistingSchema($content);

        if ($docNumber === null || $name === null) {
            throw new \RuntimeException('无法解析文件元数据：' . basename($mdPath));
        }

        if (trim($sourceMarkdown) === '') {
            return [
                'doc_number' => $docNumber,
                'name' => $name,
                'module' => $module,
                'old_schema' => $oldSchema,
                'new_schema' => $oldSchema,
                'skipped' => true,
                'reason' => '无源文件Markdown摘录',
            ];
        }

        $identity = self::analyzeDocNumberIdentity($docNumber, $sourceMarkdown);
        if (!empty($identity['conflict'])) {
            return [
                'doc_number' => $docNumber,
                'name' => $name,
                'module' => $module,
                'old_schema' => $oldSchema,
                'new_schema' => $oldSchema,
                'skipped' => true,
                'conflict' => true,
                'source_doc_numbers' => $identity['source_doc_numbers'],
                'reason' => $identity['reason'] ?? '源摘录记录编号冲突',
            ];
        }

        $canonicalDocNumber = $identity['doc_number'];
        $reviewGate = RecordFormReconstructionReviewService::assertReadyForRebuild($mdPath);
        $reconstructionPacket = $reviewGate['packet'];
        if (!$reviewGate['ready']) {
            return [
                'doc_number' => $canonicalDocNumber,
                'original_doc_number' => $docNumber,
                'source_doc_numbers' => $identity['source_doc_numbers'],
                'renumbered' => !empty($identity['renumbered']),
                'identity_reason' => $identity['reason'] ?? '',
                'name' => $name,
                'module' => $module,
                'old_schema' => $oldSchema,
                'new_schema' => $oldSchema,
                'reconstruction_packet' => $reconstructionPacket,
                'skipped' => true,
                'reason' => $reviewGate['message'],
            ];
        }

        $newSchema = self::generateSchema($sourceMarkdown, $canonicalDocNumber, $name, $module, $reconstructionPacket);
        $schemaCoverage = RecordFormReconstructionReviewService::schemaCoverage($newSchema, $reconstructionPacket);

        return [
            'doc_number' => $canonicalDocNumber,
            'original_doc_number' => $docNumber,
            'source_doc_numbers' => $identity['source_doc_numbers'],
            'renumbered' => !empty($identity['renumbered']),
            'identity_reason' => $identity['reason'] ?? '',
            'name' => $name,
            'module' => $module,
            'old_schema' => $oldSchema,
            'new_schema' => $newSchema,
            'reconstruction_packet' => $reconstructionPacket,
            'schema_coverage' => $schemaCoverage,
            'skipped' => false,
        ];
    }

    /**
     * List all structured .md files, optionally filtered by module keyword.
     * @return string[] absolute paths
     */
    public static function listFiles(?string $moduleFilter = null): array
    {
        $dir = self::structuredDir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if (!str_ends_with($entry, '.md') || str_starts_with($entry, 'SMOKE_')) {
                continue;
            }
            $path = $dir . '/' . $entry;
            if ($moduleFilter !== null) {
                $content = (string)file_get_contents($path);
                $fileModule = self::parseModule($content);
                if ($fileModule === null || !str_contains($fileModule, $moduleFilter)) {
                    continue;
                }
            }
            $files[] = $path;
        }

        sort($files, SORT_NATURAL);
        return $files;
    }

    private static function buildSystemPrompt(): string
    {
        return implode("\n", [
            '你是 ISO/IEC 17025 实验室质量体系记录表格 Schema 设计专家。',
            '根据提供的记录表格原件文本内容，生成精确的 field_schema JSON。',
            '',
            '## 输出格式',
            '返回严格的 JSON 对象：{"fields": [...]}',
            '每个字段对象包含：',
            '- key: snake_case 英文标识符',
            '- label: 中文字段名称',
            '- type: 下列之一 text|textarea|date|number|select|checkbox|person|department|signature|repeatable_table',
            '- required: boolean',
            '',
            '## type 选择规则',
            '- 日期字段 → date',
            '- 是/否、勾选框 → checkbox',
            '- 下拉选择（有固定选项）→ select，并提供 options 数组',
            '- 签名、签字、会签 → signature',
            '- 人员姓名（非签名区）→ person',
            '- 部门 → department',
            '- 多行文本、描述、内容 → textarea',
            '- 数字、金额、数量 → number',
            '- 明细行/多行记录区（表格体）→ repeatable_table，并提供 columns 数组',
            '- 其余 → text',
            '',
            '## 重要规则',
            '1. 忠实还原原表结构，不要添加原表没有的字段，不要遗漏原表有的字段。',
            '2. 审批/签名链中的每个签名位置应作为独立的 signature 字段。',
            '3. 表头固定字段（编号、日期、部门等）放在 repeatable_table 之前。',
            '4. 如果表格有"评审内容"类的是否勾选项，每项作为独立 checkbox 字段。',
            '5. 如果有多个独立的签名区（如：编制人、审核人、批准人），分别建 signature 字段。',
            '6. key 命名应有语义，与 label 对应（如"评审日期"→ review_date）。',
        ]);
    }

    private static function buildUserPrompt(
        string $sourceMarkdown,
        string $docNumber,
        string $templateName,
        ?string $module,
        array $reconstructionPacket = []
    ): string {
        $parts = [
            '## 记录表格信息',
            '- 编号：' . $docNumber,
            '- 名称：' . $templateName,
        ];
        if ($module) {
            $parts[] = '- 归属程序：' . $module;
        }
        if ($reconstructionPacket !== []) {
            $parts[] = '';
            $parts[] = '## 重构准备审查 reconstruction_packet';
            $parts[] = '- 审查结论：' . (string)($reconstructionPacket['decision'] ?? '');
            $parts[] = '- 字段义务：' . implode('、', (array)($reconstructionPacket['field_obligations'] ?? []));
            $parts[] = '- 责任义务：' . implode('、', (array)($reconstructionPacket['responsibility_obligations'] ?? []));
            $parts[] = '- 频次/触发：' . implode('；', (array)($reconstructionPacket['frequency_obligations'] ?? []));
            $parts[] = '- 保存期限：' . implode('；', (array)($reconstructionPacket['retention_obligations'] ?? []));
            $parts[] = '- 外部台账边界：' . implode('；', (array)($reconstructionPacket['external_register_boundaries'] ?? []));
            $parts[] = '- 缺失义务：' . implode('、', (array)($reconstructionPacket['missing_obligations'] ?? []));
            $parts[] = '请生成字段时同时覆盖原表字段和以上体系义务；不得凭 AI 自行决定体系归属或发布状态。';
        }
        $parts[] = '';
        $parts[] = '## 表格原件内容';
        $parts[] = $sourceMarkdown;
        $parts[] = '';
        $parts[] = '请根据以上表格原件内容，输出精确的 field_schema JSON。';

        return implode("\n", $parts);
    }

    /**
     * Normalize AI-returned schema to the standard format.
     */
    private static function normalizeAiSchema(array $json): array
    {
        $fields = $json['fields'] ?? $json['field_schema'] ?? $json;
        if (!is_array($fields) || (!array_is_list($fields) && isset($fields[0]))) {
            $fields = array_values($fields);
        }

        if (!array_is_list($fields)) {
            $converted = [];
            foreach ($fields as $key => $value) {
                if (is_array($value) && isset($value['label'])) {
                    $value['key'] = $value['key'] ?? $key;
                    $converted[] = $value;
                }
            }
            $fields = $converted;
        }

        $result = [];
        foreach ($fields as $field) {
            if (!is_array($field) || !isset($field['key'], $field['label'])) {
                continue;
            }
            $normalized = [
                'key' => (string)$field['key'],
                'label' => (string)$field['label'],
                'type' => self::mapAiType((string)($field['type'] ?? 'text')),
                'required' => (bool)($field['required'] ?? false),
            ];

            if (!empty($field['options']) && is_array($field['options'])) {
                $normalized['options'] = array_values(array_filter(
                    array_map('strval', $field['options']),
                    static fn(string $v) => $v !== ''
                ));
            }

            if (!empty($field['default'])) {
                $normalized['default'] = $field['default'];
            }

            if (($normalized['type'] === 'repeatable_table') && !empty($field['columns'])) {
                $cols = [];
                foreach ($field['columns'] as $col) {
                    if (!is_array($col) || !isset($col['key'], $col['label'])) {
                        continue;
                    }
                    $colNormalized = [
                        'key' => (string)$col['key'],
                        'label' => (string)$col['label'],
                        'type' => self::mapAiType((string)($col['type'] ?? 'text')),
                        'required' => (bool)($col['required'] ?? false),
                    ];
                    if (!empty($col['options']) && is_array($col['options'])) {
                        $colNormalized['options'] = array_values(array_filter(
                            array_map('strval', $col['options']),
                            static fn(string $v) => $v !== ''
                        ));
                    }
                    $cols[] = $colNormalized;
                }
                $normalized['columns'] = $cols;
            }

            $result[] = $normalized;
        }

        return $result;
    }

    private static function decodeAiJson(string $content): ?array
    {
        $json = json_decode($content, true);
        if (is_array($json)) {
            return $json;
        }

        if (preg_match('/```(?:json)?\s*(.*?)```/is', $content, $matches) === 1) {
            $json = json_decode(trim($matches[1]), true);
            if (is_array($json)) {
                return $json;
            }
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $json = json_decode(substr($content, $start, $end - $start + 1), true);
            if (is_array($json)) {
                return $json;
            }
        }

        return null;
    }

    private static function mapAiType(string $type): string
    {
        $valid = ['text', 'textarea', 'date', 'number', 'select', 'checkbox', 'person', 'department', 'signature', 'repeatable_table'];
        $type = strtolower(trim($type));

        $aliases = [
            'string' => 'text',
            'input' => 'text',
            'multiline' => 'textarea',
            'longtext' => 'textarea',
            'rich_text' => 'textarea',
            'boolean' => 'checkbox',
            'bool' => 'checkbox',
            'yes_no' => 'checkbox',
            'check' => 'checkbox',
            'dropdown' => 'select',
            'enum' => 'select',
            'radio' => 'select',
            'int' => 'number',
            'float' => 'number',
            'decimal' => 'number',
            'integer' => 'number',
            'datetime' => 'date',
            'time' => 'text',
            'sign' => 'signature',
            'name' => 'person',
            'user' => 'person',
            'dept' => 'department',
            'table' => 'repeatable_table',
            'array' => 'repeatable_table',
            'list' => 'repeatable_table',
            'repeatable' => 'repeatable_table',
        ];

        if (in_array($type, $valid, true)) {
            return $type;
        }

        return $aliases[$type] ?? 'text';
    }

    private static function isProvisionalDocNumber(string $docNumber): bool
    {
        return str_starts_with($docNumber, '待定-');
    }

    private static function httpPost(string $url, string $payload, array $headers): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('无法初始化 cURL');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            throw new \RuntimeException('DeepSeek 请求失败：' . $error);
        }
        if ($status >= 400) {
            throw new \RuntimeException('DeepSeek HTTP ' . $status . '：' . $response);
        }

        return (string)$response;
    }

    private static function appRoot(): string
    {
        return dirname(__DIR__, 2) . '/';
    }
}
