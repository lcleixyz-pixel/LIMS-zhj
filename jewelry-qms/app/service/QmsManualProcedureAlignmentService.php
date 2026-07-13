<?php
declare(strict_types=1);

namespace app\service;

use RuntimeException;

final class QmsManualProcedureAlignmentService
{
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
}
