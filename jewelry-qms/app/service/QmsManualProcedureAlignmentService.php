<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;

final class QmsManualProcedureAlignmentService
{
    private const STATUSES = ['consistent', 'conflict', 'missing', 'review_required', 'not_applicable'];

    private const RULES = [
        'policy_polarity',
        'minimum_duration',
        'internal_version_rule',
        'record_reference_consistency',
        'responsibility_chain',
    ];

    public static function loadInputs(string $specPath, string $procedureDir): array
    {
        $spec = self::readJson($specPath);
        self::assertSpec($spec);

        $manualPath = self::resolveInputPath(
            (string)$spec['manual']['path'],
            dirname($specPath),
            dirname(__DIR__, 3)
        );
        $manualText = self::readText($manualPath);
        $procedures = self::loadPilotProcedures($procedureDir, (array)$spec['pilot_procedures']);

        return array_replace($spec, [
            'manual' => array_replace((array)$spec['manual'], [
                'resolved_path' => $manualPath,
                'sha256' => hash('sha256', $manualText),
                'text' => $manualText,
                'lines' => self::locateManualSections($manualText, (array)$spec['requirements']),
            ]),
            'procedures' => $procedures,
        ]);
    }

    public static function check(array $inputs, array $trace): array
    {
        $findings = [];
        foreach ((array)$inputs['requirements'] as $requirement) {
            $candidate = self::candidateForRequirement($requirement, $trace);
            $procedureNumber = (string)$candidate['procedure_number'];
            $procedure = (array)($inputs['procedures'][$procedureNumber] ?? []);
            if ($procedure === []) {
                throw new RuntimeException('候选程序未加载：' . $procedureNumber);
            }

            $finding = match ((string)$requirement['rule']) {
                'policy_polarity' => self::checkPolicyPolarity($requirement, $procedure),
                'minimum_duration' => self::checkMinimumDuration($requirement, $procedure),
                'internal_version_rule' => self::checkInternalVersionRule($requirement, $procedure),
                'record_reference_consistency' => self::checkRecordReferenceConsistency($requirement, $procedure),
                'responsibility_chain' => self::checkResponsibilityChain($requirement, $procedure),
                default => null,
            };
            if ($finding === null) {
                continue;
            }
            $finding['trace_source'] = (string)$candidate['trace_source'];
            $finding['manual_locator'] = self::manualLocator($inputs, (string)$requirement['manual_section']);
            $finding['input_hashes'] = [
                'manual' => (string)$inputs['manual']['sha256'],
                'procedure' => (string)$procedure['sha256'],
            ];
            $findings[] = $finding;
        }

        return [
            'schema_version' => '1.0',
            'pilot_id' => (string)$inputs['pilot_id'],
            'findings' => $findings,
            'trace_gaps' => [],
            'blockers' => [],
        ];
    }

    private static function readJson(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('JSON 输入不存在：' . $path);
        }
        $decoded = json_decode(self::readText($path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('JSON 输入无效：' . $path);
        }

        return $decoded;
    }

    private static function assertSpec(array $spec): void
    {
        foreach (['schema_version', 'pilot_id', 'manual', 'pilot_procedures', 'requirements'] as $key) {
            if (!array_key_exists($key, $spec)) {
                throw new RuntimeException('缺少 ' . $key);
            }
        }
        if ((string)$spec['schema_version'] !== '1.0') {
            throw new RuntimeException('不支持的 schema_version：' . (string)$spec['schema_version']);
        }
        if (!is_array($spec['manual'])) {
            throw new RuntimeException('manual 必须是对象');
        }
        foreach (['doc_number', 'version', 'path'] as $key) {
            if (trim((string)($spec['manual'][$key] ?? '')) === '') {
                throw new RuntimeException('manual 缺少 ' . $key);
            }
        }

        $pilotProcedures = array_values((array)$spec['pilot_procedures']);
        if (count($pilotProcedures) !== 5 || count(array_unique($pilotProcedures)) !== 5) {
            throw new RuntimeException('pilot_procedures 必须包含 5 个不重复程序编号');
        }

        $ids = [];
        foreach ((array)$spec['requirements'] as $index => $requirement) {
            if (!is_array($requirement)) {
                throw new RuntimeException('requirements[' . $index . '] 必须是对象');
            }
            foreach (['id', 'manual_section', 'rule', 'expected', 'fallback_targets'] as $key) {
                if (!array_key_exists($key, $requirement)) {
                    throw new RuntimeException('requirements[' . $index . '] 缺少 ' . $key);
                }
            }
            $id = trim((string)$requirement['id']);
            if ($id === '' || isset($ids[$id])) {
                throw new RuntimeException('发现编号为空或重复：' . $id);
            }
            $ids[$id] = true;
            if (!in_array((string)$requirement['rule'], self::RULES, true)) {
                throw new RuntimeException('未知规则：' . (string)$requirement['rule']);
            }
            if (trim((string)$requirement['manual_section']) === '') {
                throw new RuntimeException($id . ' 缺少手册章节');
            }
            $targets = array_values((array)$requirement['fallback_targets']);
            if ($targets === []) {
                throw new RuntimeException($id . ' 缺少兜底程序');
            }
            foreach ($targets as $target) {
                if (!in_array($target, $pilotProcedures, true)) {
                    throw new RuntimeException($id . ' 目标不在试点清单：' . (string)$target);
                }
            }
        }
    }

    private static function resolveInputPath(string $path, string $specDir, string $repoRoot): string
    {
        $candidates = str_starts_with($path, DIRECTORY_SEPARATOR)
            ? [$path]
            : [$specDir . DIRECTORY_SEPARATOR . $path, $repoRoot . DIRECTORY_SEPARATOR . $path];
        $matches = [];
        foreach ($candidates as $candidate) {
            $resolved = realpath($candidate);
            if ($resolved !== false && is_file($resolved)) {
                $matches[$resolved] = true;
            }
        }
        $paths = array_keys($matches);
        if ($paths === []) {
            throw new RuntimeException('手册输入不存在：' . $path);
        }
        if (count($paths) !== 1) {
            throw new RuntimeException('手册输入定位不唯一：' . $path);
        }

        return $paths[0];
    }

    private static function readText(string $path): string
    {
        $text = file_get_contents($path);
        if ($text === false || trim($text) === '') {
            throw new RuntimeException('文本输入为空或不可读：' . $path);
        }

        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    private static function loadPilotProcedures(string $procedureDir, array $pilotNumbers): array
    {
        if (!is_dir($procedureDir)) {
            throw new RuntimeException('程序目录不存在：' . $procedureDir);
        }
        $found = [];
        foreach (glob(rtrim($procedureDir, '/\\') . DIRECTORY_SEPARATOR . '*.md') ?: [] as $path) {
            $text = self::readText($path);
            $frontmatter = self::parseFrontmatter($text);
            $docNumber = trim((string)($frontmatter['doc_number'] ?? ''));
            if ($docNumber === '' || !in_array($docNumber, $pilotNumbers, true)) {
                continue;
            }
            if (isset($found[$docNumber])) {
                throw new RuntimeException('程序编号对应多个 Markdown：' . $docNumber);
            }
            $version = trim((string)($frontmatter['version'] ?? ''));
            if ($version === '' && preg_match('/-(\d{4})$/', $docNumber, $match) === 1) {
                $version = (string)$match[1];
            }
            $found[$docNumber] = [
                'doc_number' => $docNumber,
                'title' => trim((string)($frontmatter['title'] ?? '')),
                'version' => $version,
                'path' => (string)realpath($path),
                'sha256' => hash('sha256', $text),
                'frontmatter' => $frontmatter,
                'text' => $text,
            ];
        }

        foreach ($pilotNumbers as $docNumber) {
            if (!isset($found[$docNumber])) {
                throw new RuntimeException('未唯一定位试点程序：' . (string)$docNumber);
            }
        }
        ksort($found);

        return $found;
    }

    private static function parseFrontmatter(string $text): array
    {
        if (preg_match('/\A---\n(.*?)\n---(?:\n|\z)/s', $text, $match) !== 1) {
            throw new RuntimeException('程序 Markdown 缺少 YAML frontmatter');
        }
        $frontmatter = [];
        foreach (explode("\n", (string)$match[1]) as $line) {
            if (preg_match('/^([a-zA-Z0-9_]+):\s*(.*)$/', trim($line), $parts) !== 1) {
                continue;
            }
            $frontmatter[(string)$parts[1]] = self::parseScalar(trim((string)$parts[2]));
        }

        return $frontmatter;
    }

    private static function parseScalar(string $value): mixed
    {
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        if ($value === 'null' || $value === '~') {
            return null;
        }
        if (preg_match('/^(["\'])(.*)\1$/s', $value, $match) === 1) {
            return (string)$match[2];
        }

        return $value;
    }

    private static function locateManualSections(string $text, array $requirements): array
    {
        $result = [];
        foreach ($requirements as $requirement) {
            $section = (string)$requirement['manual_section'];
            if (isset($result[$section])) {
                continue;
            }
            $pattern = '/^#{1,6}\h+' . preg_quote($section, '/') . '(?:\h|$).*$/mu';
            $count = preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
            if ($count !== 1) {
                throw new RuntimeException('手册章节定位次数不是 1：' . $section . '，实际 ' . (int)$count);
            }
            $offset = (int)$matches[0][0][1];
            $end = strlen($text);
            if (preg_match('/^#{1,6}\h+.+$/mu', $text, $next, PREG_OFFSET_CAPTURE, $offset + strlen((string)$matches[0][0][0])) === 1) {
                $end = (int)$next[0][1];
            }
            $result[$section] = [
                'start' => substr_count(substr($text, 0, $offset), "\n") + 1,
                'end' => substr_count(substr($text, 0, $end), "\n") + 1,
                'excerpt' => trim(substr($text, $offset, $end - $offset)),
            ];
        }

        return $result;
    }

    private static function candidateForRequirement(array $requirement, array $trace): array
    {
        $section = (string)$requirement['manual_section'];
        $matches = [];
        foreach ((array)($trace['links'] ?? []) as $link) {
            $linkedSection = trim((string)($link['manual_section'] ?? ''));
            if ($linkedSection === '') {
                continue;
            }
            if ($section === $linkedSection || str_starts_with($section, $linkedSection . '.')) {
                $matches[] = $link;
            }
        }
        usort($matches, static fn (array $left, array $right): int => strlen((string)$right['manual_section']) <=> strlen((string)$left['manual_section']));
        $targets = array_values((array)$requirement['fallback_targets']);
        foreach ($matches as $match) {
            if (in_array((string)$match['procedure_number'], $targets, true)) {
                return [
                    'procedure_number' => (string)$match['procedure_number'],
                    'trace_source' => 'formal_link',
                ];
            }
        }

        return [
            'procedure_number' => (string)$targets[0],
            'trace_source' => 'fallback_target',
        ];
    }

    private static function checkPolicyPolarity(array $requirement, array $procedure): array
    {
        $text = (string)$procedure['text'];
        $sentences = self::matchingSentences($text, '/手写|划改/u');
        if ($sentences === []) {
            return self::finding($requirement, $procedure, 'missing');
        }
        foreach ($sentences as $sentence) {
            if (preg_match('/(?:不允许|不得|禁止)[^。；\n]{0,16}(?:手写|划改|改动)/u', (string)$sentence['text']) === 1) {
                return self::finding($requirement, $procedure, 'conflict', $sentence, ['polarity' => 'prohibit']);
            }
        }
        foreach ($sentences as $sentence) {
            if (preg_match('/允许[^。；\n]{0,16}(?:手写|划改)/u', (string)$sentence['text']) === 1) {
                return self::finding($requirement, $procedure, 'consistent', $sentence, ['polarity' => 'allow_with_authorization']);
            }
        }

        return self::finding($requirement, $procedure, 'review_required', $sentences[0], ['polarity' => 'ambiguous']);
    }

    private static function checkMinimumDuration(array $requirement, array $procedure): array
    {
        $text = (string)$procedure['text'];
        $pattern = '/人员调离或设备停止使用后[^。；\n]{0,100}?再保存\s*([0-9一二三四五六七八九十]+)\s*年/u';
        $count = preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        if ($count === false || $count === 0) {
            return self::finding($requirement, $procedure, 'missing');
        }
        $values = [];
        $evidence = [];
        foreach ($matches as $match) {
            $years = self::normalizeSmallNumber((string)$match[1][0]);
            if ($years === null) {
                return self::finding($requirement, $procedure, 'review_required', [
                    'text' => (string)$match[0][0],
                    'offset' => (int)$match[0][1],
                ], ['years' => null]);
            }
            $values[$years] = true;
            $evidence[] = ['text' => (string)$match[0][0], 'offset' => (int)$match[0][1], 'years' => $years];
        }
        if (count($values) !== 1) {
            return self::finding($requirement, $procedure, 'review_required', $evidence[0], [
                'years' => array_map('intval', array_keys($values)),
            ]);
        }
        $observedYears = (int)array_key_first($values);
        $expectedYears = (int)$requirement['expected']['years'];

        return self::finding(
            $requirement,
            $procedure,
            $observedYears < $expectedYears ? 'conflict' : 'consistent',
            $evidence[0],
            ['years' => $observedYears, 'condition' => (string)$requirement['expected']['condition']]
        );
    }

    private static function checkInternalVersionRule(array $requirement, array $procedure): array
    {
        $text = (string)$procedure['text'];
        $bodyPattern = '/修改\s*(\d+)\s*次以上[^。；\n]{0,100}?换成第\s*(\d+)\s*版(?:本)?第\s*(\d+)\s*次/u';
        $appendixPattern = '/超过第?\s*(\d+)\s*次[^。；\n]{0,100}?升级为第?\s*([0-9一二三四五六七八九十]+)\s*版/u';
        $bodyMatched = preg_match($bodyPattern, $text, $body, PREG_OFFSET_CAPTURE) === 1;
        $appendixMatched = preg_match($appendixPattern, $text, $appendix, PREG_OFFSET_CAPTURE) === 1;
        if (!$bodyMatched && !$appendixMatched) {
            return self::finding($requirement, $procedure, 'missing');
        }
        if (!$bodyMatched || !$appendixMatched) {
            $row = $bodyMatched ? $body : $appendix;
            return self::finding($requirement, $procedure, 'review_required', [
                'text' => (string)$row[0][0],
                'offset' => (int)$row[0][1],
            ]);
        }
        $appendixVersion = self::normalizeSmallNumber((string)$appendix[2][0]);
        if ($appendixVersion === null) {
            return self::finding($requirement, $procedure, 'review_required', [
                'text' => (string)$appendix[0][0],
                'offset' => (int)$appendix[0][1],
            ]);
        }
        $observed = [
            'body' => [
                'threshold' => (int)$body[1][0],
                'target_version' => (int)$body[2][0],
                'target_revision' => (int)$body[3][0],
            ],
            'appendix' => [
                'threshold' => (int)$appendix[1][0],
                'target_version' => $appendixVersion,
            ],
        ];
        $consistent = $observed['body']['threshold'] === $observed['appendix']['threshold']
            && $observed['body']['target_version'] === $observed['appendix']['target_version'];
        $evidence = [
            'text' => '正文：' . trim((string)$body[0][0]) . '；附录：' . trim((string)$appendix[0][0]),
            'offset' => (int)$body[0][1],
            'extra_offset' => (int)$appendix[0][1],
        ];

        return self::finding($requirement, $procedure, $consistent ? 'consistent' : 'conflict', $evidence, $observed);
    }

    private static function checkRecordReferenceConsistency(array $requirement, array $procedure): array
    {
        $text = (string)$procedure['text'];
        $recordSection = self::extractHeadingSection($text, '记录');
        if ($recordSection === null) {
            return self::finding($requirement, $procedure, 'missing');
        }
        $body = substr($text, 0, (int)$recordSection['offset']);
        $bodyRecords = self::extractNamedRecords($body);
        $listedRecords = self::extractNamedRecords((string)$recordSection['text']);
        $listedNames = array_fill_keys(array_column($listedRecords, 'name'), true);
        $missingNames = [];
        foreach ($bodyRecords as $record) {
            if (!isset($listedNames[(string)$record['name']])) {
                $missingNames[(string)$record['name']] = true;
            }
        }
        $expectedPrefix = (string)($requirement['expected']['record_code_prefix'] ?? '');
        $unexpectedCodes = [];
        foreach ($listedRecords as $record) {
            $code = (string)$record['code'];
            if ($code !== '' && $expectedPrefix !== '' && !str_starts_with($code, $expectedPrefix)) {
                $unexpectedCodes[$code] = true;
            }
        }
        $observed = [
            'missing_from_records' => array_keys($missingNames),
            'unexpected_record_codes' => array_keys($unexpectedCodes),
            'listed_records' => array_values(array_unique(array_column($listedRecords, 'name'))),
        ];
        $status = ($observed['missing_from_records'] !== [] || $observed['unexpected_record_codes'] !== [])
            ? 'conflict'
            : 'consistent';

        return self::finding($requirement, $procedure, $status, [
            'text' => trim((string)$recordSection['text']),
            'offset' => (int)$recordSection['offset'],
        ], $observed);
    }

    private static function checkResponsibilityChain(array $requirement, array $procedure): array
    {
        $text = (string)$procedure['text'];
        $action = trim((string)($requirement['activity'] ?? $requirement['action'] ?? ''));
        $subject = trim((string)($requirement['subject'] ?? ''));
        $rolePattern = '(?:公司总经理|办公室主任|办公室负责人|质量负责人|实验室主任|最高管理者|技术负责人|总经理|经理)';
        $roles = [];
        $evidenceRows = [];

        if ($action !== '') {
            $quotedAction = preg_quote($action, '/');
            $beforePattern = '/(?<role>' . $rolePattern . ')[^。；\n]{0,40}?' . $quotedAction . '/u';
            if (preg_match_all($beforePattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) !== false) {
                foreach ($matches as $match) {
                    $roles[(string)$match['role'][0]] = true;
                    $evidenceRows[] = ['text' => (string)$match[0][0], 'offset' => (int)$match[0][1]];
                }
            }
            if (str_starts_with($action, '批准')) {
                $object = mb_substr($action, 2);
                if ($object !== '') {
                    $afterPattern = '/' . preg_quote($object, '/') . '[^。；\n]{0,30}?(?:经|由)?\s*(?<role>' . $rolePattern . ')\s*批准/u';
                    if (preg_match_all($afterPattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) !== false) {
                        foreach ($matches as $match) {
                            $roles[(string)$match['role'][0]] = true;
                            $evidenceRows[] = ['text' => (string)$match[0][0], 'offset' => (int)$match[0][1]];
                        }
                    }
                }
            }
        }

        if ($roles === []) {
            $subjectPattern = str_contains($subject, '内部审核')
                ? '/内部审核|内审/u'
                : (str_contains($subject, '管理评审') ? '/管理评审/u' : '/风险/u');
            foreach (self::matchingSentences($text, $subjectPattern) as $sentence) {
                if (preg_match('/批准|审批|制定|编制|主持/u', (string)$sentence['text']) !== 1) {
                    continue;
                }
                if (preg_match_all('/' . $rolePattern . '/u', (string)$sentence['text'], $roleMatches) === false) {
                    continue;
                }
                foreach ($roleMatches[0] as $role) {
                    $roles[(string)$role] = true;
                }
                $evidenceRows[] = $sentence;
            }
        }

        $roleNames = array_keys($roles);
        $unconfirmedAliases = array_values(array_intersect(
            $roleNames,
            ['公司总经理', '总经理', '经理', '最高管理者', '办公室负责人']
        ));
        $expectedRole = $requirement['expected']['role'] ?? null;
        if ($roleNames === []) {
            $status = 'missing';
        } elseif ($expectedRole === null || $unconfirmedAliases !== [] || count($roleNames) > 1) {
            $status = 'review_required';
        } elseif (!in_array((string)$expectedRole, $roleNames, true)) {
            $status = 'conflict';
        } else {
            $status = 'consistent';
        }
        $firstEvidence = $evidenceRows[0] ?? null;
        if ($firstEvidence !== null && count($evidenceRows) > 1) {
            $firstEvidence['text'] = implode('；', array_values(array_unique(array_map(
                static fn (array $row): string => trim((string)$row['text']),
                $evidenceRows
            ))));
        }

        return self::finding($requirement, $procedure, $status, $firstEvidence, [
            'roles' => $roleNames,
            'unconfirmed_aliases' => $unconfirmedAliases,
            'activity' => $action,
        ]);
    }

    private static function extractHeadingSection(string $text, string $heading): ?array
    {
        $pattern = '/^#{1,6}\h+' . preg_quote($heading, '/') . '\h*\n(?<body>.*?)(?=^#{1,6}\h+|\z)/msu';
        if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return [
            'text' => (string)$match['body'][0],
            'offset' => (int)$match[0][1],
        ];
    }

    private static function extractNamedRecords(string $text): array
    {
        $rows = [];
        $pattern = '/《([^》\n]{2,80}?(?:表|报告|记录))》(?:\s+([A-Z]+\/BG-[0-9-]+))?/u';
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER) === false) {
            return [];
        }
        foreach ($matches as $match) {
            $rows[] = [
                'name' => trim((string)$match[1]),
                'code' => trim((string)($match[2] ?? '')),
            ];
        }

        return $rows;
    }

    private static function finding(
        array $requirement,
        array $procedure,
        string $status,
        ?array $evidence = null,
        array $observed = []
    ): array {
        if (!in_array($status, self::STATUSES, true)) {
            throw new RuntimeException('未知校验状态：' . $status);
        }
        $line = $evidence === null ? null : self::lineNumber((string)$procedure['text'], (int)($evidence['offset'] ?? 0));
        $locator = (string)$procedure['path'];
        if ($line !== null) {
            $locator .= ':' . $line;
            if (isset($evidence['extra_offset'])) {
                $locator .= ',' . self::lineNumber((string)$procedure['text'], (int)$evidence['extra_offset']);
            }
        }

        return [
            'finding_id' => (string)$requirement['id'],
            'status' => $status,
            'severity' => (string)($requirement['severity'] ?? 'medium'),
            'rule' => (string)$requirement['rule'],
            'manual_section' => (string)$requirement['manual_section'],
            'manual_locator' => '',
            'procedure_number' => (string)$procedure['doc_number'],
            'procedure_locator' => $locator,
            'expected' => (array)$requirement['expected'],
            'observed' => $observed,
            'evidence_excerpt' => trim((string)($evidence['text'] ?? '')),
            'suggestion' => (string)($requirement['suggestion'] ?? ''),
            'trace_source' => '',
            'input_hashes' => [],
        ];
    }

    private static function matchingSentences(string $text, string $pattern): array
    {
        $sentences = [];
        if (preg_match_all('/[^。；\n]*[。；]?/u', $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }
        foreach ($matches[0] as $match) {
            $sentence = trim((string)$match[0]);
            if ($sentence !== '' && preg_match($pattern, $sentence) === 1) {
                $sentences[] = ['text' => $sentence, 'offset' => (int)$match[1]];
            }
        }

        return $sentences;
    }

    private static function normalizeSmallNumber(string $value): ?int
    {
        $value = trim($value);
        if (ctype_digit($value)) {
            return (int)$value;
        }
        $map = ['零' => 0, '一' => 1, '二' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9, '十' => 10];

        return $map[$value] ?? null;
    }

    private static function lineNumber(string $text, int $offset): int
    {
        return substr_count(substr($text, 0, max(0, $offset)), "\n") + 1;
    }

    private static function manualLocator(array $inputs, string $section): string
    {
        $lines = (array)($inputs['manual']['lines'][$section] ?? []);
        if ($lines === []) {
            return (string)$inputs['manual']['resolved_path'];
        }

        return (string)$inputs['manual']['resolved_path'] . ':' . (int)$lines['start'] . '-' . (int)$lines['end'];
    }
}
