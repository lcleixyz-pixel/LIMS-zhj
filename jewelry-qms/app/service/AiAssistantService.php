<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Db;

class AiAssistantService
{
    public static function ensureSchema(): void
    {
        $migration = dirname(__DIR__, 2) . '/database/migrations/20260531_ai_extraction_logs.sql';
        if (is_file($migration)) {
            Db::execute((string)file_get_contents($migration));
        }
    }

    public static function targetTypes(): array
    {
        return [
            'training' => '培训记录',
            'competency' => '人员能力确认',
            'certificate' => '人员资质证书',
            'reference_material' => '标准物质台账',
            'equipment_check' => '期间核查记录',
        ];
    }

    public static function workspaceSourceRoot(): string
    {
        return dirname(app()->getRootPath(), 2) . DIRECTORY_SEPARATOR . '现用文件';
    }

    public static function listSourceFiles(?string $relativeDir = null): array
    {
        $root = self::workspaceSourceRoot();
        if (!is_dir($root)) {
            return [];
        }

        $base = $relativeDir ? $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeDir) : $root;
        $base = realpath($base) ?: $base;
        $rootReal = realpath($root) ?: $root;
        if (!str_starts_with(str_replace('\\', '/', $base), str_replace('\\', '/', $rootReal))) {
            return [];
        }

        $items = [];
        if (is_dir($base)) {
            foreach (scandir($base) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $base . DIRECTORY_SEPARATOR . $entry;
                $relative = ltrim(str_replace($rootReal, '', $path), DIRECTORY_SEPARATOR);
                if (is_dir($path)) {
                    $items[] = [
                        'type' => 'dir',
                        'name' => $entry,
                        'relative_path' => str_replace('\\', '/', $relative),
                    ];
                    continue;
                }

                $ext = strtolower((string)pathinfo($entry, PATHINFO_EXTENSION));
                if (!in_array($ext, ['doc', 'docx', 'txt'], true)) {
                    continue;
                }
                $items[] = [
                    'type' => 'file',
                    'name' => $entry,
                    'relative_path' => str_replace('\\', '/', $relative),
                    'extension' => $ext,
                    'size' => filesize($path) ?: 0,
                ];
            }
        }

        usort($items, static function (array $left, array $right): int {
            if ($left['type'] !== $right['type']) {
                return $left['type'] === 'dir' ? -1 : 1;
            }

            return strnatcasecmp((string)$left['name'], (string)$right['name']);
        });

        return $items;
    }

    public static function resolveSourcePath(string $relativePath): string
    {
        $root = realpath(self::workspaceSourceRoot());
        if ($root === false) {
            throw new \InvalidArgumentException('现用文件目录不存在');
        }

        $candidate = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
        $real = realpath($candidate);
        if ($real === false || !is_file($real) || !str_starts_with(str_replace('\\', '/', $real), str_replace('\\', '/', $root))) {
            throw new \InvalidArgumentException('无效的文件路径');
        }

        return $real;
    }

    public static function storeUploadedFile(array $file): string
    {
        $ext = strtolower((string)($file['extension'] ?? pathinfo((string)($file['original_name'] ?? ''), PATHINFO_EXTENSION)));
        if (!in_array($ext, ['doc', 'docx', 'txt'], true)) {
            throw new \InvalidArgumentException('仅支持 doc/docx/txt 文件');
        }

        $dir = runtime_path() . 'ai_uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $target = $dir . DIRECTORY_SEPARATOR . qms_uuid() . '.' . $ext;
        $saved = $file['save_path'] ?? null;
        if ($saved && is_file((string)$saved)) {
            rename((string)$saved, $target);
        } elseif (isset($file['tmp_name']) && is_uploaded_file((string)$file['tmp_name'])) {
            move_uploaded_file((string)$file['tmp_name'], $target);
        } else {
            throw new \InvalidArgumentException('上传文件保存失败');
        }

        return $target;
    }

    public static function buildContext(string $companyId): array
    {
        return [
            'employees' => Db::name('employees')
                ->where('company_id', $companyId)
                ->where('soft_delete', 0)
                ->field('id,employee_number,name')
                ->order('employee_number', 'asc')
                ->select()
                ->toArray(),
            'equipments' => Db::name('equipments')
                ->where('company_id', $companyId)
                ->where('soft_delete', 0)
                ->field('id,equipment_number,name')
                ->order('equipment_number', 'asc')
                ->select()
                ->toArray(),
        ];
    }

    public static function extractRecords(string $filePath, string $targetType, string $companyId, ?string $userId = null): array
    {
        self::ensureSchema();
        if (!isset(self::targetTypes()[$targetType])) {
            throw new \InvalidArgumentException('不支持的记录类型');
        }

        if (!self::isConfigured()) {
            throw new \RuntimeException('DeepSeek API 未配置，请在环境变量中设置 DEEPSEEK_API_KEY');
        }

        $documentText = DocumentParserService::summarizeForAi($filePath);
        if (trim($documentText) === '') {
            throw new \RuntimeException('未能从文档中提取有效文本');
        }

        $context = self::buildContext($companyId);
        $response = self::callDeepSeek($targetType, $documentText, $context);
        $validated = self::validateExtraction($response, $targetType, $context);
        $logId = qms_uuid();
        $now = date('Y-m-d H:i:s');

        Db::name('ai_extraction_logs')->insert([
            'id' => $logId,
            'company_id' => $companyId,
            'source_file' => $filePath,
            'target_type' => $targetType,
            'extracted_json' => json_encode($validated, JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'records_created' => 0,
            'created_by' => $userId,
            'created' => $now,
        ]);

        return [
            'log_id' => $logId,
            'target_type' => $targetType,
            'source_file' => $filePath,
            'data' => $validated,
        ];
    }

    public static function validateExtraction(array $data, string $targetType, array $context): array
    {
        $employeeMap = [];
        foreach ($context['employees'] ?? [] as $employee) {
            $employeeMap[(string)$employee['name']] = (string)$employee['id'];
        }

        $warnings = [];
        $records = [];

        switch ($targetType) {
            case 'training':
                $training = $data['training'] ?? $data['trainings'][0] ?? [];
                $trainingRows = $data['records'] ?? [];
                if ($training === [] && $trainingRows === []) {
                    $warnings[] = '未识别到培训活动或参训人员';
                }
                foreach ($trainingRows as $index => $row) {
                    $name = trim((string)($row['employee_name'] ?? ''));
                    $employeeId = self::matchEmployeeId($name, $employeeMap);
                    if ($employeeId === null && $name !== '') {
                        $warnings[] = '第' . ($index + 1) . '行人员未匹配：' . $name;
                    }
                    $records[] = array_merge($row, [
                        'employee_id' => $employeeId,
                        'employee_name' => $name,
                        'attendance' => in_array((string)($row['attendance'] ?? 'present'), ['present', 'absent', 'excused'], true)
                            ? (string)$row['attendance']
                            : 'present',
                        'evaluation_result' => in_array((string)($row['evaluation_result'] ?? 'pass'), ['pass', 'fail', 'pending'], true)
                            ? (string)$row['evaluation_result']
                            : 'pass',
                    ]);
                }

                return [
                    'training' => [
                        'title' => trim((string)($training['title'] ?? '未命名培训')),
                        'training_date' => self::normalizeDate($training['training_date'] ?? $training['date'] ?? null),
                        'trainer' => trim((string)($training['trainer'] ?? '')),
                        'duration_hours' => self::normalizeNumber($training['duration_hours'] ?? $training['hours'] ?? null),
                        'training_type' => in_array((string)($training['training_type'] ?? 'internal'), ['internal', 'external', 'on_job'], true)
                            ? (string)$training['training_type']
                            : 'internal',
                        'content' => trim((string)($training['content'] ?? '')),
                    ],
                    'records' => $records,
                    'warnings' => $warnings,
                ];

            case 'competency':
                foreach ($data['records'] ?? [] as $index => $row) {
                    $name = trim((string)($row['employee_name'] ?? ''));
                    $employeeId = self::matchEmployeeId($name, $employeeMap);
                    if ($employeeId === null && $name !== '') {
                        $warnings[] = '第' . ($index + 1) . '行人员未匹配：' . $name;
                    }
                    $records[] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $name,
                        'test_item' => trim((string)($row['test_item'] ?? '检测能力')),
                        'method_standard' => trim((string)($row['method_standard'] ?? '')),
                        'assessment_date' => self::normalizeDate($row['assessment_date'] ?? null),
                        'assessor_name' => trim((string)($row['assessor_name'] ?? '')),
                        'result' => in_array((string)($row['result'] ?? 'qualified'), ['pending', 'qualified', 'unqualified', 'supervised'], true)
                            ? (string)$row['result']
                            : 'qualified',
                        'authorization_scope' => trim((string)($row['authorization_scope'] ?? '')),
                        'valid_until' => self::normalizeDate($row['valid_until'] ?? null),
                    ];
                }

                return ['records' => $records, 'warnings' => $warnings];

            case 'certificate':
                foreach ($data['records'] ?? [] as $index => $row) {
                    $name = trim((string)($row['employee_name'] ?? ''));
                    $employeeId = self::matchEmployeeId($name, $employeeMap);
                    if ($employeeId === null && $name !== '') {
                        $warnings[] = '第' . ($index + 1) . '行人员未匹配：' . $name;
                    }
                    $records[] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $name,
                        'certificate_type' => trim((string)($row['certificate_type'] ?? '资质证书')),
                        'certificate_number' => trim((string)($row['certificate_number'] ?? '')),
                        'issuing_authority' => trim((string)($row['issuing_authority'] ?? '')),
                        'issue_date' => self::normalizeDate($row['issue_date'] ?? null),
                        'valid_until' => self::normalizeDate($row['valid_until'] ?? null),
                        'status' => in_array((string)($row['status'] ?? 'active'), ['active', 'expired', 'revoked', 'archived'], true)
                            ? (string)$row['status']
                            : 'active',
                    ];
                }

                return ['records' => $records, 'warnings' => $warnings];

            case 'reference_material':
                foreach ($data['records'] ?? [] as $index => $row) {
                    $code = trim((string)($row['code'] ?? ''));
                    if ($code === '') {
                        $warnings[] = '第' . ($index + 1) . '行缺少标准物质编号';
                    }
                    $records[] = [
                        'code' => $code !== '' ? $code : 'RM-' . ($index + 1),
                        'name' => trim((string)($row['name'] ?? '标准物质')),
                        'lot_number' => trim((string)($row['lot_number'] ?? '')),
                        'manufacturer' => trim((string)($row['manufacturer'] ?? '')),
                        'traceability_certificate_number' => trim((string)($row['traceability_certificate_number'] ?? '')),
                        'valid_until' => self::normalizeDate($row['valid_until'] ?? null),
                        'storage_location' => trim((string)($row['storage_location'] ?? '')),
                        'status' => in_array((string)($row['status'] ?? 'active'), ['active', 'expired', 'depleted', 'discarded'], true)
                            ? (string)$row['status']
                            : 'active',
                    ];
                }

                return ['records' => $records, 'warnings' => $warnings];

            case 'equipment_check':
                foreach ($data['records'] ?? [] as $index => $row) {
                    $equipmentNumber = trim((string)($row['equipment_number'] ?? ''));
                    $equipmentId = self::matchEquipmentId($equipmentNumber, $context['equipments'] ?? []);
                    if ($equipmentId === null && $equipmentNumber !== '') {
                        $warnings[] = '第' . ($index + 1) . '行设备未匹配：' . $equipmentNumber;
                    }
                    $records[] = [
                        'equipment_id' => $equipmentId,
                        'equipment_number' => $equipmentNumber,
                        'equipment_name' => trim((string)($row['equipment_name'] ?? '')),
                        'check_date' => self::normalizeDate($row['check_date'] ?? null),
                        'check_result' => trim((string)($row['check_result'] ?? '合格')),
                        'checker_name' => trim((string)($row['checker_name'] ?? '')),
                        'remarks' => trim((string)($row['remarks'] ?? '')),
                    ];
                }

                return ['records' => $records, 'warnings' => $warnings];

            default:
                return ['records' => [], 'warnings' => ['未知记录类型']];
        }
    }

    public static function confirmAndInsert(string $logId, array $payload, string $confirmedBy): int
    {
        self::ensureSchema();
        $log = Db::name('ai_extraction_logs')->where('id', $logId)->find();
        if (!$log) {
            throw new \InvalidArgumentException('提取记录不存在');
        }
        if ((string)$log['status'] !== 'pending') {
            throw new \RuntimeException('该提取记录已处理');
        }

        $companyId = (string)$log['company_id'];
        $targetType = (string)$log['target_type'];
        $context = self::buildContext($companyId);
        $validated = self::validateExtraction($payload, $targetType, $context);
        $created = 0;

        Db::transaction(function () use ($validated, $targetType, $companyId, $log, &$created): void {
            $created = match ($targetType) {
                'training' => self::insertTrainingRecords($companyId, $validated, (string)$log['source_file']),
                'competency' => self::insertCompetencyRecords($companyId, $validated, (string)$log['source_file']),
                'certificate' => self::insertCertificateRecords($companyId, $validated, (string)$log['source_file']),
                'reference_material' => self::insertReferenceMaterials($companyId, $validated, (string)$log['source_file']),
                'equipment_check' => self::insertEquipmentChecks($companyId, $validated, (string)$log['source_file']),
                default => 0,
            };

            Db::name('ai_extraction_logs')->where('id', (string)$log['id'])->update([
                'extracted_json' => json_encode($validated, JSON_UNESCAPED_UNICODE),
                'status' => 'confirmed',
                'records_created' => $created,
                'confirmed_by' => $confirmedBy,
                'confirmed_at' => date('Y-m-d H:i:s'),
            ]);
        });

        return $created;
    }

    public static function rejectExtraction(string $logId, string $rejectedBy): void
    {
        self::ensureSchema();
        Db::name('ai_extraction_logs')->where('id', $logId)->where('status', 'pending')->update([
            'status' => 'rejected',
            'confirmed_by' => $rejectedBy,
            'confirmed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function history(string $companyId, int $limit = 30): array
    {
        self::ensureSchema();

        return Db::name('ai_extraction_logs')
            ->where('company_id', $companyId)
            ->order('created', 'desc')
            ->limit(max(1, $limit))
            ->select()
            ->toArray();
    }

    public static function getLog(string $logId): ?array
    {
        self::ensureSchema();
        $row = Db::name('ai_extraction_logs')->where('id', $logId)->find();
        if (!$row) {
            return null;
        }

        $row['extracted_json'] = json_decode((string)($row['extracted_json'] ?? '{}'), true) ?: [];

        return $row;
    }

    public static function isConfigured(): bool
    {
        return trim((string)Config::get('qms.ai.api_key', '')) !== '';
    }

    public static function getPromptTemplate(string $targetType): string
    {
        return match ($targetType) {
            'training' => '从培训记录文档中提取培训活动和参训人员，输出 JSON：{"training":{"title":"","training_date":"YYYY-MM-DD","trainer":"","duration_hours":2,"training_type":"internal","content":""},"records":[{"employee_name":"","attendance":"present","evaluation_score":null,"evaluation_result":"pass"}]}',
            'competency' => '从人员能力确认文档中提取记录，输出 JSON：{"records":[{"employee_name":"","test_item":"","method_standard":"","assessment_date":"YYYY-MM-DD","assessor_name":"","result":"qualified","authorization_scope":"","valid_until":"YYYY-MM-DD"}]}',
            'certificate' => '从人员资质证书文档中提取记录，输出 JSON：{"records":[{"employee_name":"","certificate_type":"","certificate_number":"","issuing_authority":"","issue_date":"YYYY-MM-DD","valid_until":"YYYY-MM-DD","status":"active"}]}',
            'reference_material' => '从标准物质相关文档中提取台账，输出 JSON：{"records":[{"code":"","name":"","lot_number":"","manufacturer":"","traceability_certificate_number":"","valid_until":"YYYY-MM-DD","storage_location":"","status":"active"}]}',
            'equipment_check' => '从期间核查文档中提取记录，输出 JSON：{"records":[{"equipment_number":"","equipment_name":"","check_date":"YYYY-MM-DD","check_result":"","checker_name":"","remarks":""}]}',
            default => '输出严格 JSON 对象，字段使用 snake_case。',
        };
    }

    private static function callDeepSeek(string $targetType, string $documentText, array $context): array
    {
        $config = Config::get('qms.ai', []);
        $apiKey = (string)($config['api_key'] ?? '');
        $baseUrl = rtrim((string)($config['base_url'] ?? 'https://api.deepseek.com'), '/');
        $model = (string)($config['model'] ?? 'deepseek-chat');
        $maxTokens = (int)($config['max_tokens'] ?? 4096);
        $temperature = (float)($config['temperature'] ?? 0.1);

        $employeeNames = array_map(static fn (array $row): string => (string)$row['name'], $context['employees'] ?? []);
        $equipmentNumbers = array_map(static fn (array $row): string => (string)$row['equipment_number'] . ' ' . (string)$row['name'], $context['equipments'] ?? []);

        $systemPrompt = implode("\n", [
            '你是实验室质量管理体系文档解析助手，负责把纸质/Word 记录转换为结构化 JSON。',
            '必须只返回 JSON 对象，不要输出 Markdown 或解释文字。',
            '日期统一为 YYYY-MM-DD；无法确定的字段留空字符串或 null。',
            '人员姓名必须尽量匹配已知人员名单；设备编号尽量匹配已知设备清单。',
            self::getPromptTemplate($targetType),
        ]);

        $userPrompt = implode("\n\n", [
            '已知人员名单：' . implode('、', $employeeNames),
            '已知设备清单：' . implode('；', $equipmentNumbers),
            '待解析文档内容：',
            $documentText,
        ]);

        $payload = json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'response_format' => ['type' => 'json_object'],
        ], JSON_UNESCAPED_UNICODE);

        $response = self::httpPost($baseUrl . '/chat/completions', $payload, [
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
        $json = json_decode($content, true);
        if (!is_array($json)) {
            throw new \RuntimeException('AI 未返回有效结构化 JSON');
        }

        return $json;
    }

    private static function httpPost(string $url, string $payload, array $headers): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('DeepSeek 请求失败：' . $error);
        }
        if ($status >= 400) {
            throw new \RuntimeException('DeepSeek HTTP ' . $status . '：' . $response);
        }

        return (string)$response;
    }

    private static function insertTrainingRecords(string $companyId, array $data, string $sourceFile): int
    {
        $training = $data['training'] ?? [];
        $records = $data['records'] ?? [];
        if ($records === []) {
            return 0;
        }

        $title = trim((string)($training['title'] ?? '未命名培训'));
        $trainingDate = self::normalizeDate($training['training_date'] ?? null) ?: date('Y-m-d');
        $existing = Db::name('trainings')
            ->where('company_id', $companyId)
            ->where('title', $title)
            ->where('training_date', $trainingDate)
            ->where('soft_delete', 0)
            ->find();

        $trainingId = (string)($existing['id'] ?? qms_uuid());
        $now = date('Y-m-d H:i:s');
        $payload = [
            'id' => $trainingId,
            'company_id' => $companyId,
            'title' => $title,
            'training_type' => (string)($training['training_type'] ?? 'internal'),
            'trainer' => (string)($training['trainer'] ?? ''),
            'training_date' => $trainingDate,
            'duration_hours' => self::normalizeNumber($training['duration_hours'] ?? null),
            'content' => trim((string)($training['content'] ?? '')) . "\n来源：" . basename($sourceFile),
            'status' => 'completed',
            'publish' => 1,
            'soft_delete' => 0,
            'modified' => $now,
        ];
        if ($existing) {
            Db::name('trainings')->where('id', $trainingId)->update($payload);
        } else {
            $payload['created'] = $now;
            Db::name('trainings')->insert($payload);
        }

        $created = 0;
        foreach ($records as $row) {
            $employeeId = (string)($row['employee_id'] ?? '');
            if ($employeeId === '') {
                continue;
            }
            $exists = Db::name('training_records')
                ->where('training_id', $trainingId)
                ->where('employee_id', $employeeId)
                ->where('soft_delete', 0)
                ->find();
            if ($exists) {
                continue;
            }
            Db::name('training_records')->insert([
                'id' => qms_uuid(),
                'training_id' => $trainingId,
                'employee_id' => $employeeId,
                'attendance' => (string)($row['attendance'] ?? 'present'),
                'evaluation_score' => self::normalizeNumber($row['evaluation_score'] ?? null),
                'evaluation_result' => (string)($row['evaluation_result'] ?? 'pass'),
                'remarks' => 'AI 导入：' . basename($sourceFile),
                'publish' => 1,
                'soft_delete' => 0,
                'created' => $now,
            ]);
            $created++;
        }

        return $created;
    }

    private static function insertCompetencyRecords(string $companyId, array $data, string $sourceFile): int
    {
        $created = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($data['records'] ?? [] as $row) {
            $employeeId = (string)($row['employee_id'] ?? '');
            if ($employeeId === '') {
                continue;
            }
            $testItem = trim((string)($row['test_item'] ?? '检测能力'));
            $assessmentDate = self::normalizeDate($row['assessment_date'] ?? null) ?: date('Y-m-d');
            $exists = Db::name('competency_records')
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->where('test_item', $testItem)
                ->where('assessment_date', $assessmentDate)
                ->where('soft_delete', 0)
                ->find();
            if ($exists) {
                continue;
            }

            Db::name('competency_records')->insert([
                'id' => qms_uuid(),
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'test_item' => $testItem,
                'method_standard' => (string)($row['method_standard'] ?? ''),
                'assessment_date' => $assessmentDate,
                'assessor_id' => self::matchEmployeeId((string)($row['assessor_name'] ?? ''), self::employeeNameMap($companyId)),
                'result' => (string)($row['result'] ?? 'qualified'),
                'authorization_scope' => (string)($row['authorization_scope'] ?? ''),
                'valid_until' => self::normalizeDate($row['valid_until'] ?? null),
                'publish' => 1,
                'soft_delete' => 0,
                'created' => $now,
                'modified' => $now,
            ]);
            $created++;
        }

        return $created;
    }

    private static function insertCertificateRecords(string $companyId, array $data, string $sourceFile): int
    {
        $created = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($data['records'] ?? [] as $row) {
            $employeeId = (string)($row['employee_id'] ?? '');
            if ($employeeId === '') {
                continue;
            }
            $number = trim((string)($row['certificate_number'] ?? ''));
            $type = trim((string)($row['certificate_type'] ?? '资质证书'));
            $query = Db::name('employee_certificates')
                ->where('company_id', $companyId)
                ->where('employee_id', $employeeId)
                ->where('certificate_type', $type)
                ->where('soft_delete', 0);
            if ($number !== '') {
                $query->where('certificate_number', $number);
            }
            if ($query->find()) {
                continue;
            }

            Db::name('employee_certificates')->insert([
                'id' => qms_uuid(),
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'certificate_type' => $type,
                'certificate_number' => $number,
                'issuing_authority' => (string)($row['issuing_authority'] ?? ''),
                'issue_date' => self::normalizeDate($row['issue_date'] ?? null),
                'valid_until' => self::normalizeDate($row['valid_until'] ?? null),
                'status' => (string)($row['status'] ?? 'active'),
                'remarks' => 'AI 导入：' . basename($sourceFile),
                'publish' => 1,
                'soft_delete' => 0,
                'created' => $now,
                'modified' => $now,
            ]);
            $created++;
        }

        return $created;
    }

    private static function insertReferenceMaterials(string $companyId, array $data, string $sourceFile): int
    {
        $created = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($data['records'] ?? [] as $row) {
            $code = trim((string)($row['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $existing = Db::name('reference_materials')->where('code', $code)->where('soft_delete', 0)->find();
            $payload = [
                'company_id' => $companyId,
                'code' => $code,
                'name' => (string)($row['name'] ?? $code),
                'lot_number' => (string)($row['lot_number'] ?? ''),
                'manufacturer' => (string)($row['manufacturer'] ?? ''),
                'traceability_certificate_number' => (string)($row['traceability_certificate_number'] ?? ''),
                'valid_until' => self::normalizeDate($row['valid_until'] ?? null),
                'storage_location' => (string)($row['storage_location'] ?? ''),
                'status' => (string)($row['status'] ?? 'active'),
                'remarks' => 'AI 导入：' . basename($sourceFile),
                'publish' => 1,
                'soft_delete' => 0,
                'modified' => $now,
            ];
            if ($existing) {
                Db::name('reference_materials')->where('id', (string)$existing['id'])->update($payload);
            } else {
                $payload['id'] = qms_uuid();
                $payload['created'] = $now;
                Db::name('reference_materials')->insert($payload);
            }
            $created++;
        }

        return $created;
    }

    private static function insertEquipmentChecks(string $companyId, array $data, string $sourceFile): int
    {
        $created = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($data['records'] ?? [] as $row) {
            $equipmentId = (string)($row['equipment_id'] ?? '');
            if ($equipmentId === '') {
                continue;
            }
            $checkDate = self::normalizeDate($row['check_date'] ?? null) ?: date('Y-m-d');
            $exists = Db::name('equipment_maintenances')
                ->where('equipment_id', $equipmentId)
                ->where('maintenance_date', $checkDate)
                ->whereLike('description', '%期间核查%')
                ->where('soft_delete', 0)
                ->find();
            if ($exists) {
                continue;
            }

            Db::name('equipment_maintenances')->insert([
                'id' => qms_uuid(),
                'equipment_id' => $equipmentId,
                'maintenance_type' => 'verification',
                'maintenance_date' => $checkDate,
                'description' => '期间核查：' . (string)($row['check_result'] ?? '合格') . '；' . (string)($row['remarks'] ?? '') . '；来源：' . basename($sourceFile),
                'performed_by' => (string)($row['checker_name'] ?? ''),
                'publish' => 1,
                'soft_delete' => 0,
                'created' => $now,
            ]);
            $created++;
        }

        return $created;
    }

    private static function employeeNameMap(string $companyId): array
    {
        $map = [];
        foreach (Db::name('employees')->where('company_id', $companyId)->where('soft_delete', 0)->field('id,name')->select()->toArray() as $row) {
            $map[(string)$row['name']] = (string)$row['id'];
        }

        return $map;
    }

    private static function matchEmployeeId(string $name, array $employeeMap): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        if (isset($employeeMap[$name])) {
            return $employeeMap[$name];
        }

        foreach ($employeeMap as $knownName => $id) {
            if ($knownName !== '' && (str_contains($name, $knownName) || str_contains($knownName, $name))) {
                return $id;
            }
        }

        return null;
    }

    private static function matchEquipmentId(string $equipmentNumber, array $equipments): ?string
    {
        $equipmentNumber = trim($equipmentNumber);
        if ($equipmentNumber === '') {
            return null;
        }

        foreach ($equipments as $equipment) {
            if ((string)$equipment['equipment_number'] === $equipmentNumber) {
                return (string)$equipment['id'];
            }
        }

        return null;
    }

    private static function normalizeDate(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        $timestamp = strtotime($value);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private static function normalizeNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? round((float)$value, 2) : null;
    }
}
